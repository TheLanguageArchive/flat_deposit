<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\BlockFormController
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

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
        $node = \Drupal::routeMatch()->getParameter('node');
        if ($node instanceof \Drupal\node\NodeInterface) {
            if ($node->bundle() === 'flat_bundle') {

                $form['actions']['bundle_image'] = array(
                    '#type' => 'image_button',
                    '#value' => t('Bundle image'),
                    '#disabled' => TRUE,
                    '#prefix' => '<div><br></div>',
                    '#src' => '',

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
                    '#validate' => [[$this, 'flat_bundle_action_form_validate_validate']],
                    '#description' => t('Validate the bundle. Valid bundles cannot be altered, unless they are re-opened again.'),
                    '#disabled' => TRUE,
                );

                $form['actions']['reopen_bundle'] = array(
                    '#type' => 'submit',
                    '#value' => t('Re-open bundle'),
                    //'#validate' => array('flat_bundle_action_form_reopen_validate'),
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
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
    }

    public function flat_bundle_action_form_validate_validate(array &$form, FormStateInterface &$form_state)
    {

        $form_state->loadInclude('flat_deposit', 'inc', 'inc/flat_bundle_action_helpers');

        $node = \Drupal::routeMatch()->getParameter('node');
        $nid = $node->id();
        $path = $node->get('flat_location')->value;
        $parent_is_known = parent_is_known($nid);
        $has_cmdi = has_cmdi($nid);
        $valid_xml = is_valid_xml($nid, $has_cmdi);
        //$good_name = has_correct_filename($nid); filename here not relevant, only in SIP that is offered to doorkeeper
        $file_exists = bundle_file_exists($nid);
        $user_has_permissions = user_has_permissions($nid);
        $max_files_exceeded = bundle_max_files_exceeded($nid);
        $max_file_size_exceeded = bundle_max_file_size_exceeded($nid);
        $invalid_file_names = bundle_invalid_file_names($nid);
        $invalid_file_extensions = bundle_invalid_file_extensions($nid);
        $has_new_or_deleted_files = (bundle_new_files($nid) or bundle_deleted_files($nid));

        $max_number_files = \Drupal::config('flat_deposit.settings')->get('flat_deposit_ingest_service')['max_ingest_files'];
        $max_size = \Drupal::config('flat_deposit.settings')->get('flat_deposit_ingest_service')['max_file_size'];

        // validate that a collection has been selected
        if ($parent_is_known === false) {
            $form_state->setErrorByName('error', "The bundle has not been assigned to a collection");
            return $form;
        }

        // In case no cmdi file exists
        if ($has_cmdi === false) {
            $form_state->setErrorByName('error', "No metadata file has been specified");
            return $form;
        }

        // Quick and dirty Check cmdi valid xml
        if ($valid_xml === false) {
            $form_state->setErrorByName('validate_bundle', t("The CMDI metadata file is not a valid xml file"));
            return $form;
        }

        /* In case of wrong naming
        if ($good_name === false){
            $form_state->setErrorByName('validate_bundle', t("Metadata file has wrong file name (record.cmdi expected)"));
            return $form;
        }
        */

        // Check existence external location
        if ($file_exists === false) {

            $form_state->setErrorByName('validate_bundle', t('Location does not exist (:path) ', array(':path' => $path)));
            return $form;
        }

        // Check user permissions
        if ($user_has_permissions === false) {
            $form_state->setErrorByName('validate_bundle', t('You do not have the permission to perform this action. Please contact the archive manager.'));
            return $form;
        }

        // for imported (uploaded) CMDI file, check that the resourceproxy resourcerefs match with the provided files
        $md_type = isset($node->flat_cmdi_option) ? $node->flat_cmdi_option->value : NULL;
        $flat_type = isset($node->flat_type) ? $node->flat_type->value : NULL;
        if ($flat_type == 'update') {
            $md_type = 'existing';
        }
        if ($md_type == 'import') {
            $has_flat_uri = has_flat_uri($nid);
            if (!$has_flat_uri) {
                $files_mismatch = bundle_files_mismatch($nid);
            } else {
                $files_mismatch = FALSE;
            }
        }

        $errors = [];

        if (!$has_new_or_deleted_files) {
            $errors[] = t('No new files added and/or no exising files selected for removal.');
        }

        if ($max_files_exceeded) {
            $errors[] = t('The bundle contains too many files, the maximum is @limit.', array('@limit' => $max_number_files));
        }

        if ($max_file_size_exceeded) {
            $max_file_size_exceeded_list = implode(", ", $max_file_size_exceeded);
            $errors[] = t('The selected folder contains files that are larger than the maximum allowed file size of @max_size GB: @max_file_size_exceeded_list.', ['@max_size' => $max_size, '@max_file_size_exceeded_list' => $max_file_size_exceeded_list]);
        }

        if ($invalid_file_names) {
            $invalid_filenames_list = implode(", ", $invalid_file_names);
            $errors[] = t('The selected folder contains files that have disallowed characters in their name: @invalid_filenames_list.', ['@invalid_filenames_list' => $invalid_filenames_list]);
        }

        if ($invalid_file_extensions) {
            $invalid_file_extensions_list = implode(", ", $invalid_file_extensions);
            $errors[] = t('The selected folder contains files that have a disallowed file extension: @invalid_file_extensions_list. See the deposit manual for allowed file types and extensions.', ['@invalid_file_extensions_list' => $invalid_file_extensions_list]);
        }

        if (isset($has_flat_uri) && $has_flat_uri) {
            $errors[] = t('Your uploaded CMDI file contains references to files that are already in the archive. To use this CMDI file for a different set of files, use the "upload CMDI file as template" option, see deposit manual.');
        }

        if (isset($files_mismatch) && $files_mismatch) {
            $files_mismatch_list = implode(", ", $files_mismatch);
            $errors[] = t('There is a mismatch between the files listed in your uploaded CMDI file and the files you provided in the selected folder. Missing file(s): !files_mismatch_list. In case you wish to use this CMDI file for a different set of files, use the "upload CMDI file as template" option, see deposit manual.', ['!files_mismatch_list' => $files_mismatch_list]);
        }

        if (!empty($errors)) {
            if (count($errors) > 1) {
                $errors = [
                    '#theme' => 'item_list',
                    '#type' => 'ul',
                    '#items' => $errors,
                ];
            } else {
                $errors = $errors[0];
            }
            $form_state->setErrorByName('validate_bundle', $errors);
            return $form;
        }
    }

    /**
     * {@inheritdoc}
     *
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $node = \Drupal::routeMatch()->getParameter('node');
        if ($node instanceof \Drupal\node\NodeInterface) {
            $nid = $node->id();
        }

        $action_element = $form_state->getTriggeringElement();
        $action = $action_element['#value'];

        switch ($action) {
            case 'Fill in metadata for bundle':
                $form_state->setRedirect('flat_deposit.enter_metadata', ['node' => $nid]);
                break;

            case 'Edit metadata for bundle':
                $form_state->setRedirect('flat_deposit.edit_metadata', ['node' => $nid]);
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
                $node->set('flat_bundle_status', 'open');
                $node->save();
                \Drupal::messenger()->addMessage('Bundle is open and can be modified again');

                break;
        }
    }
}
