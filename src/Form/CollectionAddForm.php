<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\CollectionAddForm.
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\RedirectCommand;

use Symfony\Component\HttpFoundation\Request;

class CollectionAddForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'flat_collection_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $fedoraObject = NULL)
  {

    $triggeringElement = $form_state->getTriggeringElement();

    // @FIXME
    // drupal_set_title() has been removed. There are now a few ways to set the title
    // dynamically, depending on the situation.
    //
    //
    // @see https://www.drupal.org/node/2067859
    // drupal_set_title(t('Add Collection'));


    // Set selected profile as this is updated on every AJAX request
    if ($form_state->hasValue(['select_profile_name'])) {
      $form_state->set(['selected'], $form_state->getValue(['select_profile_name']));
    }

    // unset saved 'ajax_select' value if the ajax_select-button is unselected, the saved value of this button is empty and no button has been clicked
    if (!$form_state->hasValue(['select_profile_name']) && !empty($form_state->get(['selected'])) && null === $triggeringElement) {
      $form_state->set(['selected'], '');
    }

    // get all available form template files
    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/FormBuilder/class.FormBuilder');
    $available_profiles = \FormBuilder::getAvailableTemplates('flat_collection');

    // Add option to import a external file
    $available_profiles['Import'] = 'I want to upload a CMDI metadata file';

    $form['#prefix'] = "<div id='flat_cmdi_form_wrapper'>";
    $form['#suffix'] = "</div>";

    $user = \Drupal::currentUser();

    $form['owner'] = [
      '#title' => t('Owner of the collection'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $user->getAccountName(),
    ];
    // Field for entering namespace value should only be shown to admin and manager users
    if (count(array_intersect([
      'manager',
      'administrator',
    ], $user->getRoles())) > 0) {
      $show_namespace_field = TRUE;
    } else {
      $show_namespace_field = FALSE;
    }
    $form['namespace_toggle'] = [
      '#title' => t('Enter namespace value'),
      '#type' => 'checkbox',
      '#required' => FALSE,
      '#access' => $show_namespace_field,
    ];

    $form['namespace'] = [
      '#title' => t('Namespace for the collection (leave blank to use parent namespace)'),
      '#type' => 'textfield',
      '#required' => FALSE,
      '#access' => $show_namespace_field,
      '#states' => [
        // Only show this field when the 'toggle_me' checkbox is enabled.
        'visible' => [
          ':input[name="namespace_toggle"]' => [
            'checked' => TRUE
          ]
        ]
      ],
    ];

    if (!\Drupal::currentUser()->hasPermission('admin collection')) {
      $form['owner']['#disabled'] = TRUE;
    }

    $form['trigger']['select_profile_name'] = [
      '#title' => t('Which metadata profile do you want to use?'),
      '#type' => 'select',
      '#empty_option' => '-- Select --',
      '#required' => TRUE,
      '#options' => $available_profiles,
      '#ajax' => [
        'callback' => [$this, 'selectProfileNameAjaxCallback'],
        'wrapper' => 'template-form',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];
    if ($form_state->has(['selected'])) {
      $form['trigger']['select_profile_name']['#default_value'] = $form_state->get(['selected']);
    }

    $form['cmdi_file'] = [
      '#type' => 'file',
      '#title' => t('Choose a file'),
      '#states' => [
        'visible' => [
          ':input[name="select_profile_name"]' => [
            'value' => 'Import'
          ]
        ],
        'required' => [
          ':input[name="select_profile_name"]' => [
            'value' => 'Import'
          ]
        ],
      ],
    ];

    $form['select_policy'] = [
      '#title' => t('Which access policy do you want to apply?'),
      '#type' => 'select',
      '#description' => t('Select which access policy should be applied as the default for bundles within this collection. "Public" materials can be accessed by anyone without having to log in. "Authenticated Users" means any user with a valid account for the archive. "Academic Users" are users that logs in with an academic account or whose academic status has been verified. "Private" means that the materials are only accessible to the depositor. Access policies can be refined later.'),
      '#required' => TRUE,
      '#options' => [
        'public' => t('public'),
        'authenticated' => t('authenticated users'),
        'academic' => t('academic users'),
        'private' => t('private'),
      ],
      '#default_value' => 'public',
    ];

    $form['visibility'] = [
      '#title' => t('Visibility'),
      '#type' => 'select',
      '#description' => t('Hidden collections are not visible to anyone but the depositor and the archive managers. This is to be used only in cases in which the name or other metadata fields reveal too much information about work in progresss. The visibility will be applied to this collection and will be the default value for any sub-collections or bundles within this collection. Only materials with a private access policy can be hidden.'),
      '#required' => TRUE,
      '#options' => [
        'show' => 'visible',
        'hide' => 'hidden',
      ],
      '#default_value' => 'visible',
      '#states' => [
        'visible' => [
          ':input[name="select_policy"]' => [
            'value' => 'private'
          ]
        ]
      ],
    ];

    /*
    $form['inherit_from_collection'] = array(
        '#title' => t('Inherit from collection'),
        '#type' => 'checkbox',
        '#required' => FALSE,
        '#default_value' => TRUE,
        '#states' => array(
            'invisible' => array(
                array(
                    array(':input[name="select_profile_name"]' => array('value' => '')),
                    'or',
                    array(':input[name="select_profile_name"]' => array('value' => 'Import')),
                ),
            ),
        ),
        '#ajax' => array(
            'callback' => 'select_profile_name_ajax_callback',
            'wrapper' => 'template-form',
            'method' => 'replace',
            'effect' => 'fade',
        ),
    );

    */

    $form['template_container'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'id' => [
          'template-form'
        ]
      ],
    ];

    // attach hidden data
    $form['data'] = [
      '#type' => 'value',
      '#value' => [
        'fid' => 'entity:node/10:en', //$fedoraObject->id
      ],
    ];

    $form['Submit'] = [
      '#type' => 'submit',
      '#name' => 'submit',
      '#value' => t('Submit'),
      '#ajax' => [
        'callback' => [$this, 'submitAjaxHandler'],
        'wrapper' => 'flat_collection_add_form_wrapper',
        'effect' => 'fade',
      ],
    ];

    //********************************************************************
    // Generate profile specific form render array and attach to container
    //********************************************************************

    // load preset if selected
    \CmdiPresetManager::load($form_state);

    // adding modal component to form
    $form['flat_modal'] = \CmdiPresetManager::modal();

    // adding save cmdi preset feature
    $saved = \CmdiPresetManager::save($form_state);

    // \Drupal::logger('flat_deposit')->notice('<pre><code>' . var_export($form_state->get(['selected']), true) . '</code></pre>');
    // var_dump($form_state->get(['selected']));
    if ($form_state->has(['selected']) && $form_state->get(['selected']) != 'Import' && $form_state->get(['selected']) != '') {

      $inheritedData = NULL;
      #$inherit = $form_state['values']['inherit_from_collection'];

      /**
       * @FIXME CMD datastream load need to be fixed when we introduce fedora into the flat_deposit upgrade
       */
      // Load inherited cmdi metadata from node
      // $cmdiDs = islandora_datastream_load('CMD', $fedoraObject);
      $cmdiDs = null;

      if ($cmdiDs) {

        module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/class.CmdiHandler');
        $inheritedData = simplexml_load_string($cmdiDs->content, 'CmdiHandler');
      }

      // Load form builder app
      $templateName = $form_state->get(['selected']);
      $formBuilder = new \FormBuilder(NULL);

      // count button presses per field
      $formBuilder->aggregatePressedButtons($form_state);

      // get the node in nested array from which we can start iterating all button presses per field
      if ($form_state->has(['pressedButtons', 'template_container', 'elements'])) {
        $pressedButtonsRoot = $form_state->get(['pressedButtons', 'template_container', 'elements']);
      } else {
        $pressedButtonsRoot = NULL;
      }

      // Generate the form elements
      $formBuilder->setForm($templateName, $inheritedData, $pressedButtonsRoot);

      // Attach form elements to base form
      $elements = $formBuilder->getForm();

      $form['template_container']['elements'] = $elements;

      // check if everything worked as expected
      if (!is_array($form['template_container']['elements'])) {
        \Drupal::messenger()->addWarning(t('Unable to generate CMDI form based on selected profile'));
      }
    }

    return $form;
  }

  /**
   * Instead of using validateForm, which is called every time either ajax or form requests
   * are processed, we hook up this custom validation handler to the submit button so that this
   * handler will only be called if user presses the submit button.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {

    $triggeringElement = $form_state->getTriggeringElement();
    if (null === $triggeringElement || !isset($triggeringElement['#name']) || $triggeringElement['#name'] !== 'submit') {
      return $form;
    }

    // Set selected profile as this is updated on every AJAX request
    if ($form_state->hasValue(['select_profile_name'])) {
      $form_state->set(['selected'], $form_state->getValue(['select_profile_name']));
    }

    // unset saved 'ajax_select' value if the ajax_select-button is unselected, the saved value of this button is empty and no button has been clicked
    if (!$form_state->hasValue(['select_profile_name']) && !empty($form_state->get(['selected'])) && null === $triggeringElement) {
      $form_state->set(['selected'], '');
    }

    module_load_include('inc', 'flat_deposit', 'inc/class.FlatTuque');
    // Form Validation setup
    $owner = $form_state->getValue(['owner']);
    $namespace = $form_state->getValue(['namespace']);
    $profile = $form_state->getValue(['select_profile_name']);
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/flat_deposit.settings.yml and config/schema/flat_deposit.schema.yml.
    $allowed_collection_profiles = \Drupal::config('flat_deposit.settings')->get('flat_deposit_cmdi_profiles')['collection_profile_ids'];
    $allowed_collection_profile_ids = explode(',', $allowed_collection_profiles);
    $export_dir = 'metadata://' . str_replace('@', '_at_', $owner) . "/.collection/";
    $cmdiFile = $export_dir . '/' . $profile . '_' . uniqid() . '.cmdi';
    $form_state->setValue(['cmdiFile'], $cmdiFile);

    // @TODO get fid from node once it's added as a property
    //$fid = $form_state->getValue(['data', 'fid']);
    $fid = 'lat:12345678';
    // stop validation if errors have previously occurred
    if ($form_state->getErrors()) {
      return $form;
    }

    //****************************//
    // Perform general validation //
    //****************************//

    // Validate that selected profile is not empty
    if ($form_state->getValue(['select_profile_name']) === '-- Select --') {
      $form_state->setErrorByName('select_profile_name', t('Please choose an option from the list'));
      return $form;
    }

    // Validate if owner exists.
    if (!user_load_by_name($owner)) {

      $form_state->setErrorByName('owner', t('Specified owner is unknown'));
      return $form;
    };

    // Validate that output directory for new cmdi exists or can be created
    if (!file_exists($export_dir)) {
      \Drupal::service("file_system")->mkdir($export_dir, NULL, TRUE);
    }

    if (!file_exists($export_dir)) {
      $form_state->setError('error', t('Cannot create a directory to temporarily store CMDI files'));
      return $form;
    }

    //*******************************************//
    // Perform validation specific chosen option //
    //*******************************************//

    $selected = $form_state->getValue(['select_profile_name']);

    switch (true) {
        // For all not imported cases
      case $selected != 'Import':
        //*******************//
        // Title Validations //
        //*******************//
        $title = $form_state->getValue(['template_container', 'elements', 'title_field', 0]);

        // 1. Validate that no other collection at same collection with very similar name exists
        $values = \FlatTuque::getChildrenLabels($fid);

        if ($values === false) {
          $form_state->setErrorByName('title', t('Unable to validate that collection name is unique at this location'));
          return $form;
        }

        if (in_array(strtoupper($title), array_unique(array_map('strtoupper', $values)))) {
          $form_state->setErrorByName('title', t('Another collection or bundle with same name exists at this location. Please use a different name'));
          return $form;
        }

        // 2. Validate that output directory for new cmdi exists or can be created
        if (!file_exists($export_dir)) {
          \Drupal::service("file_system")->mkdir($export_dir, NULL, TRUE);
        }

        if (!file_exists($export_dir)) {
          $form_state->setErrorByName('error', t('Cannot create directory to temporarily store cmdi files'));
          return $form;
        }


        //*******************//
        // Generate Cmdi file//
        //*******************//
        module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiCreator/class.CmdiCreator');
        module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiPreset/class.CmdiValueExtractor');

        $templateName = $form_state->get(['selected']);
        $owner = $form_state->getValue(['owner']);
        $form_data = \CmdiValueExtractor::extract($form_state);

        $creator = new \CmdiCreator();

        try {
          $creator->setCmdi($templateName, $form_data, $owner);
          $cmdi = $creator->getCmdi();
        } catch (\CmdiCreatorException $e) {
          $form_state->setErrorByName('error', $e->getMessage());
          return $form;
        }

        $exported = $cmdi->asXML($cmdiFile);

        if (!$exported) {
          $form_state->setErrorByName('error', t('Unable to save CMDI file on the server'));
          return $form;
        }

        break;

      case 'Import':
        module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/class.CmdiHandler');
        module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/FormBuilder/class.FormBuilder');

        $file = file_save_upload('cmdi_file', array(
          // Validate file extensions
          'file_validate_extensions' => array('cmdi'),
        ));
        if (!$file) {
          // No file specified or has incorrect extension
          $form_state->setErrorByName('cmdi_file', t('No file was specified or it has an incorrect file extension (should be .cmdi)'));
          return $form;
        }
        $cmdi = \CmdiHandler::simplexml_load_cmdi_file(\Drupal::service("file_system")->realpath($file->uri));
        // Valid xml?
        if (!$cmdi) {
          $form_state->setErrorByName('cmdi_file', t('Your uploaded CMDI file is not a valid XML file'));
          return $form;
        }
        // Check whether CMDI file has allowed CMDI collection profile
        $type = $cmdi->getCmdiProfileType();
        if ($type !== 'collection') {
          $form_state->setErrorByName('cmdi_file', t('Your uploaded CMDI file has a profile that is not accepted as a Collection profile. See the deposit manual for more information about accepted CMDI profiles.'));
          return $form;
        }

        // Check that no other collection/bundle exists at this level with same or very similar name
        $profile_name = $cmdi->getNameById();
        $profile_filename = \FormBuilder::FORM_TEMPLATES_PATH . $profile_name . ".xml";
        $template_xml = simplexml_load_file($profile_filename);
        $template_name = (string)$template_xml->xpath('/profileToDrupal/header/template_name')[0];
        $title_field = (string)$template_xml->xpath('/profileToDrupal/items/item[@id="title_field"]/@name')[0];
        $values = \FlatTuque::getChildrenLabels($fid);
        $title = (string)$cmdi->xpath("/cmd:CMD/cmd:Components/cmd:$template_name/cmd:$title_field")[0];
        if (!$title) {
          //let's try without namespace
          $title = (string)$cmdi->xpath("/CMD/Components/$template_name/$title_field")[0];
        }

        if (!$title) {
          $form_state->setErrorByName('cmdi_file', t('Unable to read the collection name from your uploaded CMDI file'));
          return $form;
        }

        if ($values === false) {
          $form_state->setErrorByName('cmdi_file', t('Unable to validate that collection name is unique at this location'));
          return $form;
        }

        if (in_array(strtoupper($title), array_unique(array_map('strtoupper', $values)))) {
          $form_state->setErrorByName('cmdi_file', t('Another collection or bundle with same name exists at this location. Please use a different name'));
          return $form;
        }

        // Remove MdSelfLink (new collections cannot have an existing MdSelfLink)
        $cmdi->removeMdSelfLink();

        // Remove Resources (new collections should not already link to resources)
        $cmdi->stripResourceProxyAndResources();

        $exported = $cmdi->asXML($cmdiFile);

        if (!$exported) {
          $form_state->setErrorByName('error', t('Unable to save CMDI file on the server'));
          return $form;
        }

        break;

      default:
        break;
    }

    //***************//
    // Validate cmdi //
    //***************//
    /*
    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/class.DOMValidator');
    $validator = new DomValidator;
    try{
        $validated = $validator->validateFeeds($cmdiFile, $templateName . '.xsd');
        echo "Feed successfully validated";

    } catch (Exception $e){

        $form_state->setErrorByName($validator->displayErrors());
        return $form;

    }
    */
    /*
    */
    //********************************//
    // Do ingest using the Doorkeeper //
    //********************************//
    $sipType = 'Collection';
    $fid = $form_state->getValue(['data', 'fid']);
    /**
     * @FIXME reminder to set test back to false after upgrade
     */
    $test = true;

    module_load_include('php', 'flat_deposit', 'Helpers/IngestService/IngestClient');
    try {
      $ingest_client = new \IngestClient($sipType, $owner, $cmdiFile, $fid, $test, $namespace);
    } catch (\IngestServiceException $exception) {
      $form_state->setErrorByName('debug', $exception->getMessage());
      return $form;
    }

    $options = [];
    $options['policy'] = $form_state->getValue(['select_policy']);
    $options['content_type'] = 'flat_collection';
    $options['visibility'] = $form_state->getValue(['visibility']);
    $fid = $ingest_client->requestSipIngest($options);

    /**
     * @FIXME implement fetching object from islandora after sip works
     */
    $fObject = ['label' => 'testCollection', 'owner' => 'admin', 'fid' => 'lat:12345678'];
    // $fObject = islandora_object_load($fid);

    if (!$fObject) {
      $form_state->setErrorByName('error', t('Check of FID for new collection item did not reveal valid data. Error message:' . $fid));
      return $form;
    }

    $form_state->setValue(['data', 'fid'], $fObject['fid']);
    $form_state->setValue(['data', 'label'], $fObject['label']);
    $form_state->setValue(['data', 'owner'], $fObject['owner']);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $owner = user_load_by_name($form_state->getValue(['data', 'owner']));
    $uid = $owner->uid;
    $label = $form_state->getValue(['data', 'label']);
    $fid = $form_state->getValue(['data', 'fid']);
    //$target = 'islandora/object/' . $form_state->getValue(['data', 'fid']);
    //create_collection_node($label, $uid, $fid);

    \Drupal::messenger()->addMessage(t('New collection item has been created'));

    $response = new AjaxResponse();
    //$response->addCommand(new RedirectCommand($target));

    return $response;
  }

  public function selectProfileNameAjaxCallback(array &$form, FormStateInterface $form_state, Request $request)
  {
    return $form['template_container'];
  }

  public function submitAjaxHandler(array &$form, FormStateInterface $form_state, Request $request)
  {

    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiPreset/class.CmdiValueSyncer');
    \CmdiValueSyncer::sync($form, $form_state);

    return $form;
  }
}
