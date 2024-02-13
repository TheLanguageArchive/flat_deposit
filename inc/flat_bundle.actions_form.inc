<?php


function flat_bundle_action_form($form, &$form_state)
{
    $form_state->loadInclude('flat_deposit', 'inc', 'inc/flat_bundle_action_helpers');

    $form['actions']['bundle_image'] = array(
        '#type' => 'image_button',
        '#title' => 'test',
        '#value' => t('Bundle image'),
        '#disabled' => TRUE,
        '#prefix' => '<div><br></div>',

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

    $node = \Drupal::routeMatch()->getParameter();

    $form['values']['node'] = array(
        '#type' => 'value',
        '#value' => $node
    );

    $form['values']['origin_url'] = array(
        '#type' => 'value',
        '#value' => 'node/' . $node->nid
    );

    if (user_role_load_by_name('developer')) {
        $form['options']['serial'] = array(
            '#type' => 'checkbox',
            '#title' => t('Serial processing (debug mode)'),
            '#prefix' => '</p>',
            '#default_value' => 0,

        );
    }

    return $form;
}



function flat_deposit_form_flat_bundle_action_form_alter(&$form, \Drupal\Core\Form\FormStateInterface &$form_state, $form_id)
{

    $node = \Drupal::routeMatch()->getParameter();
    // @FIXME entity_metadata_wrapper fix for drupal 9
    // $wrapper = entity_metadata_wrapper('node', $node);

    // Specify which handlers to become active and which bundle_image to display
    $form['actions']['edit_bundle']['#disabled'] = TRUE;
    $status = $node->flat_bundle_status->value;

    # deactivate link to file browser for local bundles
    $source = $node->flat_source->value;
    $enable_file_browser = in_array($source, array('local', 'cloud')) ? FALSE : TRUE;

    switch ($status) {
        case 'open': {

                $form['actions']['upload_data']['#access'] = $enable_file_browser;
                $form['actions']['bundle_image']['#disabled'] = !$enable_file_browser;
                if (empty($node->flat_cmdi_file)) {
                    $form['actions']['validate_bundle']['#disabled'] = TRUE;
                } else {
                    $form['actions']['validate_bundle']['#disabled'] = FALSE;
                    $form['actions']['validate_bundle']['#attributes']['class'][] = 'btn-success';
                }
                $form['actions']['edit_bundle']['#disabled'] = FALSE;
                $name = 'open_bundle';
                break;
            }

        case 'validating': {
                $name = 'process_bundle';
                $form['actions']['reopen_bundle']['#disabled'] = FALSE;
                $form['actions']['edit_metadata']['#disabled'] = TRUE;
                break;
            }

        case 'failed': {
                $form['actions']['upload_data']['#access'] = $enable_file_browser;
                $form['actions']['bundle_image']['#disabled'] = !$enable_file_browser;
                $form['actions']['validate_bundle']['#disabled'] = FALSE;
                $form['actions']['edit_bundle']['#disabled'] = FALSE;
                $form['actions']['edit_metadata']['#disabled'] = FALSE;
                $name = 'closed_bundle_failed';
                break;
            }

        case 'valid': {
                $form['actions']['archive_bundle']['#disabled'] = FALSE;
                $form['actions']['reopen_bundle']['#disabled'] = FALSE;
                $form['actions']['archive_bundle']['#attributes']['class'][] = 'btn-success';
                $form['actions']['edit_metadata']['#disabled'] = TRUE;
                $name = 'closed_bundle';
                break;
            }
        case 'processing': {
                $name = 'process_bundle';

                break;
            }
    }

    $form['actions']['bundle_image']['#src'] = drupal_get_path('module', 'flat_deposit') . '/assets/images/' . $name . '.png';

    // Activate metadata creation button only if 1) cmdi option is 'new' 2) status is either open or failed and 3) no cmdi has been created
    // If metadata creation button is active (.e.g. if metadata file has been removed), validate button should be disabled.
    if ($node->flat_cmdi_option->value == 'new' and ($status == 'open' or $status == 'failed') and (empty($node->flat_cmdi_file))) {

        $form['actions']['describe_bundle']['#access'] = TRUE;
        $form['actions']['describe_bundle']['#attributes']['class'][] = 'btn-success';
        $form['actions']['edit_metadata']['#access'] = FALSE;
        $form['actions']['validate_bundle']['#disabled'] = TRUE;
    }
}


/**
 * Implements hook_form_validate.
 *
 * Validation of opening the bundle data browser
 *
 */
function flat_bundle_action_form_describe_validate($form, &$form_state)
{

    $node = $form_state['values']['node'];
    // @FIXME entity_metadata_wrapper fix for drupal 9
    // $wrapper = entity_metadata_wrapper('node', $node);

    $parent_nid = $node->flat_parent_nid->value;
    if ($parent_nid === '0') {
        $form_state->setErrorByName('describe_bundle', "You need to assign this bundle to a parent collection first before editing the metadata. Go to \"Edit Bundle Properties\" to select a parent collection.");
        return $form;
    }
}

/**
 * Implements hook_form_validate.
 *
 * Validation of opening the bundle data browser
 *
 */
function flat_bundle_action_form_reopen_validate($form, &$form_state)
{

    $node = $form_state['values']['node'];
    $error = move_node_data($node->nid);
    if (!empty($error)) {

        $form_state->setErrorByName('reopen', t('Unable to move bundle !what to unfreeze', array('!what' => $node->nid)));
    }
}






function flat_bundle_action_form_validate_validate($form, &$form_state)
{

    $node = $form_state['values']['node'];
    $nid = $node->nid;
    // @FIXME entity_metadata_wrapper fix for drupal 9
    // $wrapper = entity_metadata_wrapper('node', $node);
    $path = $node->flat_location->value;

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

    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/flat_deposit.settings.yml and config/schema/flat_deposit.schema.yml.
    $max_number_files = \Drupal::config('flat_deposit.settings')->get('flat_deposit_ingest_service')['max_ingest_files'];
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/flat_deposit.settings.yml and config/schema/flat_deposit.schema.yml.
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
        $errors[] = t('No new files added and no exising files selected for removal.');
    }

    if ($max_files_exceeded) {
        $errors[] = t('The bundle contains too many files, the maximum is !limit.', array('!limit' => $max_number_files));
    }

    if ($max_file_size_exceeded) {
        $max_file_size_exceeded_list = implode(", ", $max_file_size_exceeded);
        $errors[] = t('The selected folder contains files that are larger than the maximum allowed file size of !max_size GB: !max_file_size_exceeded_list.', ['!max_size' => $max_size, '!max_file_size_exceeded_list' => $max_file_size_exceeded_list]);
    }

    if ($invalid_file_names) {
        $invalid_filenames_list = implode(", ", $invalid_file_names);
        $errors[] = t('The selected folder contains files that have disallowed characters in their name: !invalid_filenames_list.', ['!invalid_filenames_list' => $invalid_filenames_list]);
    }

    if ($invalid_file_extensions) {
        $invalid_file_extensions_list = implode(", ", $invalid_file_extensions);
        $errors[] = t('The selected folder contains files that have a disallowed file extension: !invalid_file_extensions_list. See the deposit manual for allowed file types and extensions.', ['!invalid_file_extensions_list' => $invalid_file_extensions_list]);
    }

    if (isset($has_flat_uri) && $has_flat_uri) {
        $errors[] = t('Your uploaded CMDI file contains references to files that are already in the archive. To use this CMDI file for a different set of files, use the "upload CMDI file as template" option, see deposit manual.');
    }

    if (isset($files_mismatch) && $files_mismatch) {
        $files_mismatch_list = implode(", ", $files_mismatch);
        $errors[] = t('There is a mismatch between the files listed in your uploaded CMDI file and the files you provided in the selected folder. Missing file(s): !files_mismatch_list. In case you wish to use this CMDI file for a different set of files, use the "upload CMDI file as template" option, see deposit manual.', ['!files_mismatch_list' => $files_mismatch_list]);
    }

    if (!empty($errors)) {
        $form_state->setErrorByName('validate_bundle', implode('<br>', $errors));
        return $form;
    }
}


function flat_bundle_action_form_submit($form, &$form_state)
{

    $node = $form_state['values']['node'];
    // @FIXME entity_metadata_wrapper fix for drupal 9
    // $wrapper = entity_metadata_wrapper('node', $node);

    $action = $form_state['clicked_button']['#value'];

    switch ($action) {
        case 'Bundle image':
        case 'Upload data':
            $user = \Drupal::currentUser();
            $form_state['redirect'] = array('user/' . $user->uid . '/imce');
            break;


        case 'Fill in metadata for bundle':
            $url = array('node/' . $node->nid . '/cmdi');
            $form_state['redirect'] = $url;
            break;

        case 'Edit metadata for bundle':
            $url = array('node/' . $node->nid . '/edit_cmdi');
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
            $url = array('node/' . $node->nid . '/edit');
            $form_state['redirect'] = $url;
            break;


        case 'Delete bundle':
            $url = array('node/' . $node->nid . '/delete');
            $form_state['redirect'] = $url;

            break;

        case 'Re-open bundle':

            $node->flat_bundle_status->value = 'open';
            $node->save();
            \Drupal::messenger()->addMessage('Bundle is open and can be modified again');

            break;
    }
}
