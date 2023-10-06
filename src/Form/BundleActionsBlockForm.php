<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\BlockFormController
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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

        $form['actions']['describe_bundle'] = array(
            '#type' => 'submit',
            '#value' => t('Fill in metadata for bundle'),
            '#description' => t('Enter metadata for this bundle (required)'),
            '#validate' => array('flat_bundle_action_form_describe_validate'),
            '#access' => FALSE,
        );

        $form['actions']['edit_metadata'] = array(
            '#type' => 'submit',
            '#value' => t('Edit metadata for bundle'),
            '#description' => t('Edit the metadata for this bundle'),
            '#access' => TRUE,
        );

        $form['actions']['upload_data'] = array(
            '#type' => 'submit',
            '#value' => t('Upload data'),
            '#access' => FALSE,
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
        $phrases = $form_state->getValue('phrases');
        // The value cannot be empty.
        if (is_null($phrases)) $form_state->setErrorByName('phrases', t('This field cannot be empty.'));
        // The value must be numeric.
        if (!is_numeric($phrases)) {
            $form_state->setErrorByName('phrases', t('Please use a number.'));
        } else {
            // A numeric value must still be an integer.
            if (floor($phrases) != $phrases) $form_state->setErrorByName('phrases', t('No decimals, please.'));
            // A numeric value cannot be zero or negative.
            if ($phrases < 1) $form_state->setErrorByName('phrases', t('Please use a number greater than zero.'));
        }
    }

    /**
     * {@inheritdoc}
     *
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $form_state->setRedirect(
            'loremipsum.generate',
            array(
                'paragraphs' => $form_state->getValue('paragraphs'),
                'phrases' => $form_state->getValue('phrases'),
            )
        );
    }
}
