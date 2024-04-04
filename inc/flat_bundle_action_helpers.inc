<?php

use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;


function get_nids($form_state)
{

    foreach ($form_state['values']['table_container'] as $index => $entry) {
        if ($entry['checked']) {
            $nids[$index] = $form_state['values']['data'][$index]['nid'];
        }
    }

    return $nids;
}


function parent_is_known($nid)
{

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    if ($node !== false and $node->getType() == 'flat_bundle') {

        $parent_nid = (int)$node->get('flat_parent_nid_bundle')->value > 0 ? true : false;
    } else {

        $parent_nid = false;
    }

    return $parent_nid;
}

function has_cmdi($nid)
{


    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $has_cmdi = !$node->get('flat_cmdi_file') ? FALSE : TRUE;

    return $has_cmdi;
}



function is_valid_xml($nid, $has_cmdi)
{

    if ($has_cmdi) {

        $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

        if ($node->hasField('flat_cmdi_file') && !$node->get('flat_cmdi_file')->isEmpty()) {
            // Get the file ID (fid).
            $fid = $node->get('flat_cmdi_file')->target_id;

            // Load the file object.
            $file = File::load($fid);

            // Get the file URI.
            $uri = $file->getFileUri();

            $xml = simplexml_load_file($uri);

            $is_valid = !$xml ? false : true;
        } else {
            $is_valid = false;
        }
    } else {

        $is_valid = false;
    }

    return $is_valid;
}


function has_correct_filename($nid)
{

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    // @FIXME entity_metadata_wrapper fix for drupal 9
    // $wrapper = entity_metadata_wrapper('node', $node);
    $exploded_uri = explode('/', $node->flat_cmdi_file->value['uri']);
    $file_name = end($exploded_uri);

    $correct = $file_name == 'record.cmdi' ? TRUE : false;

    return $correct;
}

function bundle_file_exists($nid)
{
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $location = $node->hasField('flat_location') ? $node->get('flat_location')->value : NULL;

    $exists = FALSE;

    if ($location) {
        $exists = file_exists($location);
    }

    return $exists;
}

function bundle_new_files($nid)
{

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $location = $node->hasField('flat_location') ? $node->get('flat_location')->value : NULL;

    $files = [];

    if ($location) {
        if (is_dir($location)) {
            $files = \Drupal::service('file_system')->scanDirectory($location, '/.*/', array('min_depth' => 0));
        }
    }

    $nFiles = count($files);

    if ($nFiles == 0) {

        return FALSE;
    } else {

        return TRUE;
    }
}

function bundle_deleted_files($nid)
{

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    // @FIXME entity_metadata_wrapper fix for drupal 9
    // $wrapper = entity_metadata_wrapper('node', $node);

    $deletedFiles = $node->hasField('flat_deleted_resources') ? $node->get('flat_deleted_resources')->value : NULL;

    if (!isset($deletedFiles) or ($deletedFiles == '')) {

        return FALSE;
    } else {

        return TRUE;
    }
}

function bundle_max_files_exceeded($nid)
{

    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/class.CmdiHandler');

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $location = $node->hasField('flat_location') ? $node->get('flat_location')->value : NULL;

    $new_files = [];

    if ($location) {
        if (file_exists($location)) {

            $new_files = array_diff(preg_grep('/^([^.])/', scandir($location)), array('..', '.'));
        }
    }

    $new_files_count = count($new_files);

    $existing_files = [];

    /*
    @TODO: this needs to be implemented once we know how to fetch the CMDI file from Fedora 6
    $fedora_id = $node->flat_fid->value;

    $cmdi_file = $node->flat_cmdi_file->value;
    $cmdi_file_uri = $cmdi_file['uri'];

    if (file_exists(\Drupal::service("file_system")->realpath($cmdi_file_uri))) {
        $cmd = @CmdiHandler::simplexml_load_cmdi_file(\Drupal::service("file_system")->realpath($cmdi_file_uri));
    }

    if ($fedora_id && $cmd) {

        foreach ($cmd->children('cmd', true)->Resources->ResourceProxyList->ResourceProxy as $resource) {

            $resource_type = (string)$resource->children('cmd', true)->ResourceType;
            if ($resource_type == 'LandingPage' || $resource_type == 'searchpage') {
                continue;
            } else {

                $resource_id = (string)$resource->attributes()->id;
                $resourceref_attributes = $resource->children('cmd', true)->ResourceRef->attributes('lat', TRUE);
                $fid = $resourceref_attributes['flatURI'];

                $fObj = islandora_object_load($fid);
                $label = $fObj->label;
                $existing_files[] = $label;
            }
        }
    }
    */

    $existing_files_count = count($existing_files);

    $replaced_files = array_intersect($new_files, $existing_files);
    $replaced_files_count = count($replaced_files);

    $totalFiles = $new_files_count + $existing_files_count - $replaced_files_count;

    $max = \Drupal::config('flat_deposit.settings')->get('flat_deposit_ingest_service')['max_ingest_files'];

    if ($totalFiles > $max) {

        return TRUE;
    } else {

        return FALSE;
    }
}

function bundle_max_file_size_exceeded($nid)
{

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $location = $node->hasField('flat_location') ? $node->get('flat_location')->value : NULL;

    $max_size = \Drupal::config('flat_deposit.settings')->get('flat_deposit_ingest_service')['max_file_size'];
    $max_size_bytes = ($max_size * 1073741824);

    $files = [];

    if ($location) {

        if (is_dir($location)) {
            $files = \Drupal::service('file_system')->scanDirectory($location, '/.*/', array('min_depth' => 0));
        }
    }

    $max_file_size_exceeded = [];
    foreach ($files as $file) {
        $path = $file->uri;
        $name = basename($path);
        $size = filesize($path);
        if ($size > $max_size_bytes) {
            $max_file_size_exceeded[] = $name;
        }
    }
    if (!empty($max_file_size_exceeded)) {

        return $max_file_size_exceeded;
    } else {

        return FALSE;
    }
}

function bundle_invalid_file_names($nid)
{

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $location = $node->hasField('flat_location') ? $node->get('flat_location')->value : NULL;

    $filename_pattern = '/^[\da-zA-Z][\da-zA-Z\._\-]+$/';

    $files = [];

    if ($location) {

        if (is_dir($location)) {
            $files = \Drupal::service('file_system')->scanDirectory($location, '/.*/', array('min_depth' => 0));
        }
    }

    $invalid_file_names = array();
    foreach ($files as $file) {
        $path = $file->uri;
        $name = basename($path);
        if (preg_match($filename_pattern, $name) == FALSE) {
            $invalid_file_names[] = $name;
        }
    }
    if (!empty($invalid_file_names)) {

        return $invalid_file_names;
    } else {

        return FALSE;
    }
}

function bundle_invalid_file_extensions($nid)
{

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $location = $node->hasField('flat_location') ? $node->get('flat_location')->value : NULL;

    $allowed_extensions = explode(', ', \Drupal::config('flat_deposit.settings')->get('flat_deposit_ingest_service')['allowed_extensions']);

    $files = [];

    if ($location) {

        if (is_dir($location)) {
            $files = \Drupal::service('file_system')->scanDirectory($location, '/.*/', array('min_depth' => 0));
        }
    }

    $invalid_file_extensions = [];
    foreach ($files as $file) {
        $path = $file->uri;
        $name = basename($path);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (!in_array($extension, $allowed_extensions)) {
            $invalid_file_extensions[] = $name;
        }
    }
    if (!empty($invalid_file_extensions)) {

        return $invalid_file_extensions;
    } else {

        return FALSE;
    }
}

function bundle_has_subfolders($nid)
{

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $location = $node->hasField('flat_location') ? $node->get('flat_location')->value : NULL;

    if ($location) {

        if (is_dir($location)) {
            $content = \Drupal::service('file_system')->scanDirectory($location, '/.*/', ['recurse' => FALSE]);
            if (!empty($content)) {
                foreach ($content as $item) {
                    if (is_dir($item->uri)) {
                        return TRUE;
                    }
                }
            }
        }
    }
}


/**
 *
 * Function to check whether any resourceproxy has a flatURI attribute, which means it is referring to a file that is already archived
 *
 * @TODO implement once we know how to get the CMDI file from Fedora 6
 *
 */
function has_flat_uri($nid)
{
    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/class.CmdiHandler');

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $cmdi = $node->hasField('flat_cmdi_file') ? $node->get('flat_cmdi_file')->target_id : NULL;
    if ($cmdi) {
        $cmdi_file = File::load($cmdi);
        $cmdi_file_uri = $cmdi_file->getFileUri();

        if (file_exists(\Drupal::service("file_system")->realpath($cmdi_file_uri))) {
            $cmd = @CmdiHandler::simplexml_load_cmdi_file(\Drupal::service("file_system")->realpath($cmdi_file_uri));
        } else {
            return FALSE;
        }

        if ($cmd) {

            foreach ($cmd->children('cmd', true)->Resources->ResourceProxyList->ResourceProxy as $resource) {
                $resourceref_attributes = $resource->children('cmd', true)->ResourceRef->attributes('lat', TRUE);
                $fid = $resourceref_attributes['flatURI'];
                if ($fid) {
                    return TRUE;
                }
            }
        }
    }

    return FALSE;
}

/**
 *
 * Function to check whether there is a matching file in the selected bundle directory for each of the resourceproxies in the CMDI
 *
 *  * @TODO implement once we know how to get the CMDI file from Fedora 6
 *
 */
function bundle_files_mismatch($nid)
{

    module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/class.CmdiHandler');

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $location = $node->hasField('flat_location') ? $node->get('flat_location')->value : NULL;

    $cmdi = $node->get('flat_cmdi_file')->target_id;
    if ($cmdi) {
        $cmdi_file = File::load($cmdi);
        $cmdi_file_uri = $cmdi_file->getFileUri();

        if (file_exists(\Drupal::service("file_system")->realpath($cmdi_file_uri))) {
            $cmd = @CmdiHandler::simplexml_load_cmdi_file(\Drupal::service("file_system")->realpath($cmdi_file_uri));
        } else {
            $cmd = FALSE;
        }
    }

    $new_files = [];

    if (file_exists($location)) {

        $new_files = array_diff(preg_grep('/^([^.])/', scandir($location)), array('..', '.'));
    }

    $existing_files = [];

    if ($cmd) {

        foreach ($cmd->children('cmd', true)->Resources->ResourceProxyList->ResourceProxy as $resource) {

            $resource_type = (string)$resource->children('cmd', true)->ResourceType;
            if ($resource_type == 'LandingPage' || $resource_type == 'searchpage') {
                continue;
            } else {

                $resourceref_attributes = $resource->children('cmd', true)->ResourceRef->attributes('lat', TRUE);
                $localURI = $resourceref_attributes['localURI'];
                if ($localURI) {
                    $label = basename($localURI);
                    $existing_files[] = $label;
                } else {
                    $fid = $resourceref_attributes['flatURI'];
                    if ($fid) {
                        // @TODO reimplement below
                        $fObj = islandora_object_load($fid);
                        $label = $fObj->label;
                        $existing_files[] = $label;
                    }
                }
            }
        }
    }

    $files_mismatch = array_diff($existing_files, $new_files);

    if (!empty($files_mismatch)) {

        return $files_mismatch;
    } else {

        return FALSE;
    }
}



function user_has_permissions($nid)
{

    $user_id = \Drupal::currentUser()->id();

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    $bundle_id = $node->uid;

    if ($user_id === $bundle_id or \Drupal::currentUser()->hasPermission('Administer FLAT Deposit')) {
        $has = true;
    } else {

        $has = false;
    }

    return $has;
}

function check_completeness($type, $nid, $index, $form_state)
{
    $complete = false;

    if ($form_state['values']['table_container'][$index]['checked']) {

        $meta = $form_state['values']['data'][$index]['metadata'];
        $correct_meta = ($meta == 'available') ? true : false;

        $status = $form_state['values']['data'][$index]['status'];

        switch ($type) {
            case  'validate':
                $correct_status = ($status == 'open' or $status == 'failed') ? true : false;
                break;
            case  'archive':
                $correct_status = ($status == 'valid') ? true : false;
                break;
            case  'reopen':
                $correct_status = ($status == 'valid') ? true : false;
                break;
        }

        $complete = ($correct_status and $correct_meta) ? true : false;
    }

    return $complete;
}



function check_permissions($nid)
{

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    // Check user permissions
    $user = \Drupal::currentUser();
    $user_id = $user->uid;

    $bundle_id = $node->id();

    if (($user_id === $bundle_id and \Drupal::currentUser()->hasPermission('certified user')) or \Drupal::currentUser()->hasPermission('ingest bundles')) {

        $permissions = true;
    } else {

        $permissions = false;
    }

    return $permissions;
}


function send_request($nid, $action, $debug = FALSE)
{

    $user = \Drupal::currentUser();

    // background service http request settings
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/flat_deposit.settings.yml and config/schema/flat_deposit.schema.yml.
    $host = \Drupal::config('flat_deposit.settings')->get('flat_deposit_ingest_service')['host_name'];
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/flat_deposit.settings.yml and config/schema/flat_deposit.schema.yml.
    $scheme = \Drupal::config('flat_deposit.settings')->get('flat_deposit_ingest_service')['host_scheme'];
    $base = $GLOBALS['base_path'];

    // create array containing data needed by the ingest service
    $data_to_post = array();
    $data_to_post['drupal_path'] = \Drupal::root();
    $data_to_post['nid'] = $nid;
    $data_to_post['loggedin_user'] = $user->id();
    $data_to_post['test'] = $action;

    $options = array(
        'method' => 'POST',
        'data' => $data_to_post,
        'blocking' => FALSE,
        'headers' => array(
            // Set the Host header to self.
            'Host' => $host,
        ),
    );

    //$url = $scheme . '://' . $host . $base . 'api/flat_deposit/ingest'; #?user='.$user->name. '&pass='.$user->pass
    $url = 'http://' . $host . $base . 'api/flat_deposit/ingest'; #?user='.$user->name. '&pass='.$user->pass

    $options = [
        'method' => 'POST',
        'uri' => $url,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($data_to_post),
    ];

    if ($debug) {

        $ch = curl_init();
        curl_setopt_array($ch, array(

            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIE => "XDEBUG_SESSION=PHPSTORM",
            CURLOPT_POST => sizeof($data_to_post),
            CURLOPT_POSTFIELDS => $data_to_post
        ));

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpcode  != 200) {
            \Drupal::messenger()->addMessage("Unable to send sequential http request using curl to Ingest service");
        }
        curl_close($ch);
    } else {

        $client = \Drupal::httpClient();

        try {
            $response = $client->request($options['method'], $options['uri'], $options);
            // Handle the response
            if ($response->getStatusCode() === 200) {
                $body = $response->getBody()->getContents();
                // Do something with the response body
            } else {
                $body = $response->getBody()->getContents();
                \Drupal::messenger()->addMessage($body);
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Drupal::messenger()->addMessage($e);
            \Drupal::messenger()->addMessage("Unable to send request to Ingest service");
        }
    }
}


function move_node_data($nid)
{

    $error = [];

    module_load_include('inc', 'flat_deposit', 'inc/class.FlatBundle');
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    // @FIXME entity_metadata_wrapper fix for drupal 9
    // $wrapper = entity_metadata_wrapper('node', $node);

    $move = FlatBundle::moveBundleData($node, 'data', 'unfreeze');

    if (!$move) {
        $error['nid'][] = 'data';
    }

    if (isset($node->flat_cmdi_file)) {
        $move = FlatBundle::moveBundleData($node, 'metadata', 'unfreeze');

        if (!$move) {
            $error['nid'][] = 'metadata';
        }
    };

    return $error;
}
