<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\BundleEditCmdiForm.
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

use Symfony\Component\HttpFoundation\Request;

class BundleEnterCmdiForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'flat_deposit_bundle_edit_cmdi_form';
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


    // ctools_add_js('ajax-responder');
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
    $available_profiles = \FormBuilder::getAvailableTemplates('flat_bundle');

    // Add option to import a external file
    $available_profiles['Import'] = 'I want to upload a CMDI metadata file';

    $form['#prefix'] = "<div id='flat_collection_add_form_wrapper'>";
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

    // load template if selected
    \CmdiTemplateManager::load($form_state);

    // adding modal component to form
    $form['flat_modal'] = \CmdiTemplateManager::modal();

    // adding save cmdi template feature
    $saved = \CmdiTemplateManager::save($form_state);

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


  public function selectProfileNameAjaxCallback(array &$form, FormStateInterface $form_state, Request $request)
  {
    return $form['template_container'];
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state)
  {
    // @FIXME
    // Most CTools APIs have been moved into core.
    //
    // @see https://www.drupal.org/node/2164623
    // ctools_include('ajax');

    ctools_add_js('ajax-responder');

    $nid = $form_state['build_info']['args'][0]->get([]);
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    \Drupal::messenger()->addMessage(t('Metadata for bundle %title has been saved', [
      '%title' => $node->title
    ]));

    $commands[] = ctools_ajax_command_redirect('node/' . $nid);
    print ajax_render($commands);
    exit;
  }
}
