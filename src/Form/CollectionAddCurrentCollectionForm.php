<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\CollectionAddCurrentCollectionForm.
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class CollectionAddCurrentCollectionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flat_collection_add_current_collection_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $current_collection = NULL) {

    // @FIXME
// drupal_set_title() has been removed. There are now a few ways to set the title
// dynamically, depending on the situation.
//
//
// @see https://www.drupal.org/node/2067859
// drupal_set_title(t('Add Current Collection Form'));


    $form['field'] = ['#type' => 'fieldset'];
    $form['field']['text'] = [
      '#type' => 'markup',
      '#markup' => t('Add current collections to \'My Collections\'?'),
      '#prefix' => '<div>',
      '#suffix' => '</br></div>',
    ];
    $form['field']['Submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];

    $form['data'] = [
      '#type' => 'value',
      '#value' => [
        'fedoraObject' => $current_collection
        ],
    ];


    return $form;
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {


    $user = \Drupal::currentUser();

    // User should not have the same collection in 'My collections'

    $obj = $form_state->getValue(['data', 'fedoraObject']);
    $fid = $obj->id;

    module_load_include('inc', 'flat_deposit', 'inc/class.FlatCollection');
    $collection_nodes = FlatCollection::getUserCollectionNodes($user->id(), $fid);
    if (!empty($collection_nodes)) {
      $form_state->setErrorByName('submit', 'Current collection is already active');
      return $form;


    }

    // Check whether current object is really a collection object
    $models = $obj->models;
    if (!in_array('islandora:collectionCModel', $models)) {

      $form_state->setErrorByName('submit', 'Current node is not a collection object');
      return $form;

    }



  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {

    $obj = $form_state->getValue(['data', 'fedoraObject']);
    $label = $obj->label;

    $user = \Drupal::currentUser();
    $uid = $user->id();


    $fid = $obj->id;


    module_load_include('inc', 'flat_deposit', 'inc/flat_collection.add_collection');
    create_collection_node($label, $uid, $fid);

    \Drupal::messenger()->addMessage('Collection has been added to your active collections');
    $form_state->set(['redirect'], 'islandora/object/' . $fid);

  }

}
