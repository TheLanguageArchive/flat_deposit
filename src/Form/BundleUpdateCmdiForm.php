<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\BundleUpdateCmdiForm.
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class BundleUpdateCmdiForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flat_bundle_update_cmdi_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $fedora_object = NULL) {
    // @FIXME
// drupal_set_title() has been removed. There are now a few ways to set the title
// dynamically, depending on the situation.
//
//
// @see https://www.drupal.org/node/2067859
// drupal_set_title(t('Update Bundle Metadata'));


    ctools_add_js('ajax-responder');

    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/class.CmdiHandler');
    $ds = islandora_datastream_load("CMD", $fedora_object->id);
    $inheritedData = CmdiHandler::simplexml_load_cmdi_string($ds->content);
    if ($inheritedData) {
      $profile = $inheritedData->getNameById();
    }
    else {
      $profile = NULL;
    }

    $form['#prefix'] = "<div id='flat_bundle_update_form_wrapper'>";
    $form['#suffix'] = "</div>";

    $user = \Drupal::currentUser();

    $form['file'] = [
      '#type' => 'file',
      '#name' => 'files[cmdi]',
      '#title' => t('(Optional) Upload an updated CMDI file. Note: if a file is selected here, any changes in the form below will be ignored.'),
      '#description' => t('Update your metadata by uploading an updated CMDI file. The uploaded file needs to have the same CMDI profile as the archived file. Allowed file extensions: cmd, cmdi, xml'),
    ];

    $form['owner'] = [
      '#title' => t('Owner of this bundle'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $user->name,
      '#disabled' => TRUE,
    ];

    if (!\Drupal::currentUser()->hasPermission('admin bundle')) {
      $form['owner']['#disabled'] = TRUE;
    }

    $form['trigger']['select_profile_name'] = [
      '#type' => 'value',
      '#value' => $profile,
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
        'fid' => $fedora_object->id
        ],
    ];

    if ($inheritedData) {
      $form['data']['#value']['handle'] = (string) $inheritedData->children('cmd', TRUE)->Header->MdSelfLink;
    }

    $form['Submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#ajax' => [
        'callback' => 'flat_bundle_update_form_ajax_handler',
        'wrapper' => 'flat_bundle_update_form_wrapper',
        'effect' => 'fade',
      ],
      '#validate' => ['flat_bundle_update_form_final_validate'],
    ];



    //********************************************************************
    // Generate profile specific form render array and attach to container
    //********************************************************************
    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/FormBuilder/class.FormBuilder');

    // load template if selected
    CmdiTemplateManager::load($form_state);

    // adding modal component to form
    $form['flat_modal'] = CmdiTemplateManager::modal();

    // adding save cmdi template feature
    $saved = CmdiTemplateManager::save($form_state);

    $availableFormTemplates = FormBuilder::getAvailableTemplates('flat_bundle');

    if (array_key_exists($profile, $availableFormTemplates)) {
      // Load form builder app
      $formBuilder = new FormBuilder($form_state->getBuildInfo());

      // count button presses per field
      $formBuilder->aggregatePressedButtons($form_state);

      // get the node in nested array from which we can start iterating all button presses per field
      if (!$form_state->get([
        'pressedButtons',
        'template_container',
        'elements',
      ])) {
        $pressedButtonsRoot = $form_state->get([
          'pressedButtons',
          'template_container',
          'elements',
        ]);
      }
      else {
        $pressedButtonsRoot = NULL;
      }

      // Generate the form elements
      $formBuilder->setForm($profile, $inheritedData, $pressedButtonsRoot, $inheritAll = TRUE);

      // Attach form elements to base form
      $elements = $formBuilder->getForm();

      $form['template_container']['elements'] = $elements;

      // check if everything worked as expected
      if (!is_array($form['template_container']['elements'])) {
        \Drupal::messenger()->addWarning('Unable to generate cmdi form based on profile');
      }
    }
    else {
      \Drupal::messenger()->addMessage('Online editing of the metadata of this bundle is not supported');
      $form['Submit']['#disabled'] = TRUE;
    }
    return $form;
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $fid = $form_state->getValue(['data', 'fid']);
    $target = 'islandora/object/' . $fid;

    \Drupal::messenger()->addMessage('Bundle metadata has been updated');

    // @FIXME
    // Most CTools APIs have been moved into core.
    //
    // @see https://www.drupal.org/node/2164623
    // ctools_include('ajax');

    ctools_add_js('ajax-responder');
    $commands[] = ctools_ajax_command_redirect($target);
    print ajax_render($commands);
    exit;
  }
}