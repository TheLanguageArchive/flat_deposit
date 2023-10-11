<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\BlockFormController
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Bundle Actions block form
 */
class BundleActionsBlockForm extends FormBase
{
    /** 
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'bundle_actions_block_form';
    }

    /**
     * {@inheritdoc}
     * Bundle Actions block.
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['actions']['bundle_image'] = array(
            '#type' => 'image_button',
            '#title' => 'test',
            '#value' => t('Bundle image'),
            '#disabled' => TRUE,
            '#prefix' => '<div><br></div>',
            '#src' => '/test/image.png',

        );

        $form['actions']['container'] = array(
            '#type' => 'container',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['enter_metadata'] = array(
            '#type' => 'submit',
            '#value' => t('Fill in metadata for bundle'),
            '#description' => t('Enter metadata for this bundle (required)'),
            //'#validate' => array('flat_bundle_action_form_enter_metadata_validate'),
            '#access' => FALSE,
        );

        $form['actions']['edit_metadata'] = array(
            '#type' => 'submit',
            '#value' => t('Edit metadata for bundle'),
            '#description' => t('Edit the metadata for this bundle'),
            '#access' => TRUE,
        );

        $form['actions']['markup_1'] = array(
            '#markup' => '<div><br></div>'
        );

        $form['actions']['validate_bundle'] = array(
            '#type' => 'submit',
            '#value' => t('Validate bundle'),
            '#validate' => array('flat_bundle_action_form_validate_validate'),
            '#description' => t('Validate the bundle. Valid bundles cannot be altered, unless they are re-opened again.'),
            '#disabled' => TRUE,
        );

        $form['actions']['reopen_bundle'] = array(
            '#type' => 'submit',
            '#value' => t('Re-open bundle'),
            '#validate' => array('flat_bundle_action_form_reopen_validate'),
            '#description' => t('Re-open the bundle to allow modifications of its metadata or included files'),
            '#disabled' => TRUE,
        );

        $form['actions']['archive_bundle'] = array(
            '#type' => 'submit',
            '#value' => t('Archive bundle'),
            '#description' => t('Submit the bundle to be stored in the archive.'),
            '#disabled' => TRUE,
        );

        $form['actions']['edit_bundle'] = array(
            '#type' => 'submit',
            '#value' => t('Edit bundle properties'),
            '#prefix' => '<div><br/></div>',
        );

        $form['actions']['delete_bundle'] = array(
            '#type' => 'submit',
            '#value' => t('Delete bundle'),
            '#suffix' => '<div><br/></div>',
        );

        $form['actions']['note'] = array(
            '#prefix' => '<div id="form-actions-note">',
            '#markup' => t('Note: Deleting a bundle will only delete it from your active bundles. In case you are modifying an existing bundle in the archive, clicking "Delete bundle" will leave the original in the archive untouched.'),
            '#suffix' => '</div><div><br/></div>',
        );

        $node = \Drupal::routeMatch()->getParameter('node');

        if ($node instanceof \Drupal\node\NodeInterface) {
            $nid = $node->id();
        }

        $form['values']['node'] = array(
            '#type' => 'value',
            '#value' => $node
        );

        $form['values']['origin_url'] = array(
            '#type' => 'value',
            '#value' => 'node/' . $nid
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {

    }

    /**
     * {@inheritdoc}
     *
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {

        \Drupal::messenger()->addMessage("Default submit handler executed");

        $node = \Drupal::routeMatch()->getParameter('node');
        if ($node instanceof \Drupal\node\NodeInterface) {
          $nid = $node->id();
        }

        //ksm($form_state);
    
        $action_element = $form_state->getTriggeringElement();
        //$action = $action_element->value;
        $action = 'Fill in metadata for bundle';
    
        switch ($action) {    
            case 'Fill in metadata for bundle':
                $form_state->setRedirect('flat_deposit.enter_metadata', ['node' => $nid]);
                break;
    
            case 'Edit metadata for bundle':
                $url = array('node/' . $nid . '/edit_cmdi');
                $form_state['redirect'] = $url;
                break;
    
    
            case 'Validate bundle':
            case 'Archive bundle':
    
                $debug = isset($form_state['values']['serial']) ? $form_state['values']['serial'] : false;
    
                send_request($node->nid, $action, $debug);
    
                $processed = ($node->flat_bundle_status->value == 'valid') ? 'archived' : 'validated';
    
                $user = \Drupal::currentUser();
                $form_state['redirect'] = 'dashboard';
                \Drupal::messenger()->addMessage("Bundle is being $processed");
    
                break;
    
    
            case 'Edit bundle properties':
                $form_state->setRedirect('entity.node.edit_form', ['node' => $nid]);
                break;
    
    
            case 'Delete bundle':
                $form_state->setRedirect('entity.node.delete_form', ['node' => $nid]);
                break;
    
            case 'Re-open bundle':
    
                $node->flat_bundle_status->value = 'open';
                $node->save();
                \Drupal::messenger()->addMessage('Bundle is open and can be modified again');
    
                break;
    
    
        }

    }
}
