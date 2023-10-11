<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\BundleEditCmdiForm.
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use \Drupal\file\Entity\File;

class BundleEditCmdiForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'flat_deposit_bundle_edit_cmdi_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state)
  {

    // @FIXME
    // drupal_set_title() has been removed. There are now a few ways to set the title
    // dynamically, depending on the situation.
    //
    //
    // @see https://www.drupal.org/node/2067859
    // drupal_set_title(t('Edit Bundle Metadata'));


    //$nid = $form_state['build_info']['args'][0]->get([]);

    $node = \Drupal::routeMatch()->getParameter('node');

    if (!empty($node)) {

      //ctools_add_js('ajax-responder');

      module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/class.CmdiHandler');

      // exit form generation if no parent collection has been selected
      if ($form_state->get([
        'parent_nid'
      ])) {
        $parent_nid = $form_state->get(['parent_nid']);
      } else {
        $parent_nid_entity = $node->get('flat_parent_nid_bundle');
        $parent_nid = $parent_nid_entity->value;
        $form_state->set(['parent_nid'], $parent_nid);
      }

      if ($parent_nid === '0') {
        \Drupal::messenger()->addError('Cannot generate or edit form because collection is not specified');
        return $form;
      }

      //$existing_cmdi = $node->get('flat_cmdi_file')['uri'];
      $existing_cmdi = $node->get('flat_cmdi_file')->target_id;
      if($existing_cmdi) {
      $existing_cmdi_file = File::load($existing_cmdi);
      $existing_cmdi_url = $existing_cmdi_file->createFileUrl();
      $inheritedData = \CmdiHandler::simplexml_load_cmdi_file(\Drupal::service('file_system')->realpath($existing_cmdi_url));
      if ($inheritedData) {
        $profile = $inheritedData->getNameById();
      } else {
        $profile = NULL;
      }
    }
    else {
      $profile = NULL;
    }

      $form['#prefix'] = "<div id='flat_bundle_edit_cmdi_form_wrapper'>";
      $form['#suffix'] = "</div>";

      $user = \Drupal::currentUser();
      $form['owner'] = [
        '#title' => t('Owner of the collection'),
        '#type' => 'hidden',
        '#required' => TRUE,
        '#default_value' => $user->getAccountName(),
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

      $form['trigger']['select_profile_name'] = [
        '#type' => 'value',
        '#value' => $profile,
      ];

      if ($inheritedData) {
        $form['data']['#value']['handle'] = (string) $inheritedData->children('cmd', TRUE)->Header->MdSelfLink;
      }

      $form['Submit'] = [
        '#type' => 'submit',
        '#value' => t('Submit'),
        '#ajax' => [
          'callback' => 'flat_bundle_edit_cmdi_form_ajax_handler',
          'wrapper' => 'flat_bundle_edit_cmdi_form_wrapper',
          'effect' => 'fade',
        ],
        '#validate' => ['flat_deposit_bundle_edit_cmdi_form_final_validate'],
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
        module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/FormBuilder/class.FormBuilder');
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
        } else {
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
      } else {
        \Drupal::messenger()->addMessage('Online editing of the metadata of this bundle is not supported');
        $form['Submit']['#disabled'] = TRUE;
      }
      return $form;
    } else {
      \Drupal::messenger()->addWarning('Unable to load metadata editing form');
    }
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
