<?php

function flat_collection_add_current_collection_form($form, &$form_state, $current_collection){

    // @FIXME
// drupal_set_title() has been removed. There are now a few ways to set the title
// dynamically, depending on the situation.
//
//
// @see https://www.drupal.org/node/2067859
// drupal_set_title(t('Add Current Collection Form'));


    $form['field'] = array(
        '#type' => 'fieldset',


    );
    $form['field']['text'] = array(
        '#type' => 'markup',

        '#markup' => t('Add current collections to \'My Collections\'?'),
        '#prefix' => '<div>',
        '#suffix' => '</br></div>',


    );
    $form['field']['Submit'] = array(
        '#type' => 'submit',
        '#value' => t('Submit'),
    );

    $form['data'] = array(
        '#type' => 'value',
        '#value' => array('fedoraObject' => $current_collection),
    );


    return $form;
}



function flat_collection_add_current_collection_form_validate($form, &$form_state){


    $user = \Drupal::currentUser();

    // User should not have the same collection in 'My collections'

    $obj = $form_state['values']['data']['fedoraObject'];
    $fid = $obj->id;

    module_load_include('inc', 'flat_deposit', 'inc/class.FlatCollection');
    $collection_nodes = FlatCollection::getUserCollectionNodes($user->uid, $fid);
    if (!empty($collection_nodes)){
        $form_state->setErrorByName('submit','Current collection is already active');
        return $form;


    }

    // Check whether current object is really a collection object
    $models = $obj->models;
    if (!in_array('islandora:collectionCModel', $models)){

        $form_state->setErrorByName('submit','Current node is not a collection object');
        return $form;

    }



}


/**
* Creates new flat_collection node and redirects to parent node.
 *
 */
function flat_collection_add_current_collection_form_submit($form, &$form_state){

    $obj = $form_state['values']['data']['fedoraObject'];
    $label = $obj->label;

    $user = \Drupal::currentUser();
    $uid = $user->uid;


    $fid = $obj->id;


    module_load_include('inc','flat_deposit', 'inc/flat_collection.add_collection');
    create_collection_node($label, $uid, $fid);

    \Drupal::messenger()->addMessage('Collection has been added to your active collections');
    $form_state['redirect'] = 'islandora/object/' . $fid;

}


