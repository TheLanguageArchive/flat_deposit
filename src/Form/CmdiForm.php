<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\CmdiForm.
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class CmdiForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flat_deposit_cmdi_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $user = \Drupal::currentUser();
    $form['owner'] = [
      '#title' => t('Owner of the collection'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $user->name,
    ];
    $form['basic'] = [
      '#type' => 'fieldset',
      '#tree' => FALSE,
    ];

    $form['basic']['Title'][0] = [
      '#title' => 'Bundle title',
      '#type' => 'textfield',
      '#required' => FALSE,
    ];

    $form['basic']['Data Type'] = [
      '#type' => 'container',
      '#prefix' => '<div id="data-type">',
      '#suffix' => '</div>',
      #'#attributes' => array('id' => array('data-type')),
    ];
    $form['basic']['Data Type'][0] = [
      '#title' => 'Data Type',
      '#options' => [
        'Audio recordings' => 'Audio recordings',
        'Behavioural data' => 'Behavioural data',
        'Breathing measurements' => 'Breathing measurements',
        'Cell biology data' => 'Cell biology data',
        'Computational modeling data' => 'Computational modeling data',
        'Demographic data' => 'Demographic data',
        'Dictionary' => 'Dictionary',
        'DNA sequences' => 'DNA sequences',
        'DTI data' => 'DTI data',
        'EEG data' => 'EEG data',
        'Eye tracking data' => 'Eye tracking data',
        'FCPP (forced choice pointing) data' => 'FCPP (forced choice pointing) data',
        'item notes' => 'item notes',
        'fMRI data' => 'fMRI data',
        'Genome data' => 'Genome data',
        'Geolocation data' => 'Geolocation data',
        'Grammatical description' => 'Grammatical description',
        'Grammaticality judgement data' => 'Grammaticality judgement data',
        'GSR data' => 'GSR data',
        'Histology data' => 'Histology data',
        'Images' => 'Images',
        'IQ test data' => 'IQ test data',
        'Kinematic data' => 'Kinematic data',
        'Kinship data' => 'Kinship data',
        'LENA recording data' => 'LENA recording data',
        'Lexicographic data' => 'Lexicographic data',
        'Linguistic annotations' => 'Linguistic annotations',
        'MEG data' => 'MEG data',
        'Microscopic images' => 'Microscopic images',
        'Molecular data' => 'Molecular data',
        'Neuropsychological test data' => 'Neuropsychological test data',
        'Phenotype data' => 'Phenotype data',
        'Phonetic analysis' => 'Phonetic analysis',
        'Photographs' => 'Photographs',
        'Phylogenetic data' => 'Phylogenetic data',
        'Proteomic data' => 'Proteomic data',
        'Questionnaire data' => 'Questionnaire data',
        'Reaction time data' => 'Reaction time data',
        'Resting state fMRI data' => 'Resting state fMRI data',
        'sMRI data' => 'sMRI data',
        'SNP genotype data' => 'SNP genotype data',
        'Statistical data' => 'Statistical data',
        'Stimuli' => 'Stimuli',
        'tACS data' => 'tACS data',
        'tDCS data' => 'tDCS data',
        'TMS data' => 'TMS data',
        'Transcriptions' => 'Transcriptions',
        'Transcriptome data' => 'Transcriptome data',
        'Video recordings' => 'Video recordings',
        'Word list' => 'Word list',
      ],
      '#type' => 'select',
      '#required' => FALSE,
      '#description' => 'Kind of data that is acquired',
      '#attributes' => [
        'class' => [
          'data-type-0'
          ]
        ],
    ];

    $form['basic']['Data Type']['add'] = [
      '#weight' => 999,
      '#name' => 'button-data-type-add',
      '#type' => 'button',
      '#value' => 'Add Data Type',
      '#ajax' => [
        'callback' => 'data_type_buttons_ajax',
        'wrapper' => 'data-type',
        'method' => 'replace',
        'event' => 'click',
        'prevent' => 'submit click mousedown',
      ],
      '#limit_validation_errors' => [],
    ];
    if (!$form_state->getTriggeringElement() AND $form_state->getTriggeringElement() == 'button-data-type-add') {
      $copy = $form['basic']['Data Type'][0];
      $form['basic']['Data Type'][1] = $copy;

    }
    $form['basic']['Data Type']['remove'] = [
      '#weight' => 999,
      '#name' => 'button-data-type-remove',
      '#type' => 'button',
      '#value' => 'Remove Data Type',
      '#access' => TRUE,
      '#ajax' => [
        'callback' => 'data_type_buttons_ajax',
        'wrapper' => 'data-type',
        'method' => 'replace',
        'effect' => 'fade',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['Submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#validate' => [
        'flat_deposit_cmdi_form_final_validate'
        ],
    ];
    /*
*/
    return $form;

  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    /*

        $node = menu_get_object();
        $wrapper = entity_metadata_wrapper('node', $node);

        $new_file = file_save((object)array(
            'filename' => 'record.cmdi',
            'uri' => $form_state['values']['recordCmdi'],
            'status' => FILE_STATUS_PERMANENT,
            'filemime' => file_get_mimetype($form_state['values']['recordCmdi']),
            'display' => '1',
            'description' =>'',
        ));



        // for some unknown reason flat_location and flat_original_path are messed up by attaching the newly created cmdi file, so we need to restore it
        $flat_location_original = $wrapper->flat_location->value();
        $flat_original_path_original = $wrapper->flat_original_path->value();

        $wrapper->flat_cmdi_file->file->set($new_file);
        $wrapper->save();

        $node = menu_get_object();
        $wrapper = entity_metadata_wrapper('node', $node);
        $wrapper->flat_location->set($flat_location_original);
        $wrapper->flat_original_path->set($flat_original_path_original);
        $wrapper->save();
        $form_state['redirect'] = 'node/' .$node->nid;
        \Drupal::messenger()->addMessage(t('Metadata for bundle %title has been saved', array('%title' => $node->title)));
    */

    \Drupal::messenger()->addMessage(t('Metadata for bundle has been saved'));
  }
}