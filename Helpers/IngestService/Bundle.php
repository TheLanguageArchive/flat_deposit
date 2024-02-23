<?php

//include_once drupal_get_path('module', 'flat_deposit') . '/Helpers/IngestService/SIP.php';
module_load_include('php', 'flat_deposit', 'Helpers/IngestService/SIP');

class Bundle extends SIP
{
    // the node containing most of the important information
    protected $node;

    // the drupal entity_metadata_wrapper of this node
    protected $wrapper;

    /**
     * Set up function for bundle ingest; also validates provided input.
     *
     * @param array $info  array of configuration parameters necessary for successful processing of a bundle
     *
     * The array requires following parameters:
     *
     * 'loggedin_user': the user ID of the user doing the ingest
     * 'nid' : the node id of the bundle to ingest
     *
     * @return bool
     *
     * @throws IngestServiceException
     */
    public function init($info)
    {
        $this->logging('Starting init');

        if (!$info['nid']) {
            throw new \IngestServiceException("Node id is not specified");
        }

        $this->node = \Drupal::entityTypeManager()->getStorage('node')->load($info['nid']);
        // $this->wrapper = entity_metadata_wrapper('node',$this->node);

        $info['cmdi_handling'] = $this->node->get('flat_cmdi_option')->value;
        $info['policy'] = $this->node->get('flat_policies')->value;
        $info['visibility'] = $this->node->get('flat_metadata_visibility')->value;

        $required = array(
            'loggedin_user',
            'nid',
            'policy',
            'cmdi_handling',
            'visibility'
        );

        $diff = array_diff($required, array_keys($info));

        if ($diff) {
            throw new \IngestServiceException('Not all required variables are defined. Following variables are missing: ' . implode(', ', $diff));
        };

        $this->info = $info;

        // set status of bundle
        $status = $this->test ? 'validating' : 'processing';

        $this->node->set('flat_bundle_status', $status);
        $this->node->save();

        $this->logging('Finishing init');
        return TRUE;
    }

    /**
     * Bundle permissions are handled by drupal. In case of missing permissions the whole ingest form including
     * submission button is absent. Accordingly has permission been granted in case user click on submit.
     *
     * 
     * @TODO probably needs to be improved rather than passing on the user
     * 
     * @return bool
     *\
     * @throws IngestServiceException
     */
    public function authenticateUser()
    {
        $this->logging('Starting authentication');

        $uid_bundle = $this->node->getOwnerId();
        $id_loggedin_user = $this->info['loggedin_user'];

        // only bundle owner, editors and admins might validate the bundle
        if ($this->test) {

            if ($id_loggedin_user === $uid_bundle || $user->hasPermission('use deposit module')) {

                $this->logging('Finishing authentication');
                return TRUE;
            } else {
                throw new \IngestServiceException('User has not enough privileges to perform requested action');
            }
        } else {

            // only certified users and corpmanager might ingest the bundle

            if (($id_loggedin_user === $uid_bundle && $user->hasPermission('certified user')) || $user->hasPermission('use deposit module')) {

                $this->logging('Finishing authentication');
                return TRUE;
            } else {
                throw new \IngestServiceException('User has not enough privileges to perform requested action');
            }
        }
    }

    /**
     * Either freeze data (validate) or do nothing (ingest)
     *
     * @return bool
     *
     * @throws IngestServiceException
     */
    function prepareSipData()
    {
        $this->logging('Starting prepareSipData');

        // Validated bundles do not need to be prepared
        if (!$this->test) {

            $this->logging('Finishing prepareSipData');
            return TRUE;
        }

        module_load_include('inc', 'flat_deposit', 'inc/class.FlatBundle');

        $move = \FlatBundle::moveBundleData($this->node, 'data', 'freeze');

        $this->node->set('flat_location', $move);
        $this->node->save();

        if (!$move) {
            throw new \IngestServiceException('Unable to move bundle data to freeze');
        }

        if (!is_null($this->node->get('flat_cmdi_file')->value)) {

            $move = \FlatBundle::moveBundleData($this->node, 'metadata', 'freeze');

            if (!$move) {
                throw new \IngestServiceException('Unable to move bundle metadata to freeze');
            }

            // update local variables
            $this->node = \Drupal::entityTypeManager()->getStorage('node')->load($this->node->id());

            $cmdi = $node->get('flat_cmdi_file')->target_id;
            if ($cmdi) {
                $cmdi_file = File::load($cmdi);
                $cmdi_file_uri = $cmdi_file->getFileUri();
            }

            $this->cmdiRecord = $cmdi_file_uri ? $cmdi_file_uri : NULL;
        };

        $this->logging('Finishing prepareSipData; Data has been moved');
        return TRUE;
    }

    function validateResources()
    {

        $this->logging('Starting validateResources');
        $location = $this->node->get('flat_location')->value;

        $files = [];

        if (is_dir($location)) {
            $files = \Drupal::service('file_system')->scanDirectory($location, '/.*/', array('min_depth' => 0));
        }

        $deletedFiles = $this->node->get('flat_deleted_resources') ? $this->node->get('flat_deleted_resources')->value : NULL;

        if (!isset($deletedFiles) || ($deletedFiles == '')) {

            if (empty($files)) {
                throw new \IngestServiceException('There are no (accessible) files in the chosen folder.');
            }
        }

        $pattern = '/^[\da-zA-Z][\da-zA-Z\._\-]+\.[\da-zA-Z]{1,9}$/';
        $violators = [];

        foreach ($files as $uri => $file_array) {

            $fileName = $file_array->filename;

            if (preg_match($pattern, $fileName) == false) {
                $violators[] = $fileName;
            }
        }

        if (!empty($violators)) {

            $message = 'Bundle contains files with names violating our file naming policy. ' .
                'Allowed are names starting with an alphanumeric characters (a-z,A-Z,0-9) followed by more alphanumeric characters ' .
                'or these special characters (.-_). The name of the file needs to have an extension marked by a dot (".") ' .
                'followed by 1 to 9 characters. ';

            $message .= 'Following file(s) have triggered this message: ';
            $message .= implode(', ', $violators);

            throw new \IngestServiceException($message);
        }

        $this->logging('Finishing validateResources');
        return TRUE;
    }

    function addResourcesToCmdi()
    {

        $this->logging('Starting addResourcesToCmdi');

        module_load_include('inc', 'flat_deposit', '/Helpers/CMDI/class.CmdiHandler');

        $file_name = $this->cmdiTarget;

        $cmdi = \CmdiHandler::simplexml_load_cmdi_file($file_name);

        if (!$cmdi || !$cmdi->canBeValidated()) {
            throw new \IngestServiceException('Unable to load record.cmdi file');
        }

        $directory = $this->node->get('flat_location')->value;

        try {

            $fid = $this->node->hasField('flat_fid') ? $this->node->get('flat_fid')->value : NULL;
            $flat_type = $this->node->hasField('flat_type') ? $this->node->get('flat_type')->value : NULL;
            $md_type = $this->node->hasField('flat_cmdi_option') ? $this->node->get('flat_cmdi_option')->value : NULL;

            if ($flat_type == 'update') {
                $md_type = 'existing';
            }

            switch ($md_type) {
                case 'new':
                    $cmdi->cleanMdSelfLink();
                    break;
                case 'import':
                case 'template':
                case 'existing':
                    if ($flat_type !== 'update') {
                        $cmdi->removeMdSelfLink();
                    } else {
                        $cmdi->cleanMdSelfLink();
                    }
                    break;
            }

            $cmdi->addResources($md_type, $directory, $fid);
        } catch (\CmdiHandlerException $exception) {
            throw new \IngestServiceException($exception->getMessage());
        }

        $check = $cmdi->asXML($file_name);

        if ($check !== TRUE) {
            throw new \IngestServiceException($check);
        }

        $this->logging('Finishing addResourcesToCmdi');
        return TRUE;
    }

    function finish()
    {
        $this->logging('Starting finish');
        $this->removeFrozenZipDir();
        $this->removeSipZip();
        #$this->removeSwordBag();
        /*


                */

        //$this->createBlogEntry(TRUE);

        if ($this->test) {

            $this->node->set('flat_bundle_status', 'valid');
            $this->node->save();
        } else {

            // TODO remove comment when working

            $file_system = \Drupal::service("file_system");
            $dir = $file_system->realpath($this->node->get('flat_location')->value);

            if ($dir && is_readable($dir) && count(scandir($dir)) == 2) {
                $file_system->unlink($dir);
            };

            node_delete_multiple(array($this->info['nid']));
        }


        $this->logging('Stop finish');
        return TRUE;
    }

    /**
     *
     * @param bool $succeeded outcome of the processing procedure.
     *
     * @param null|string $additonal_message possible error messages generated during processing
     */
    protected function  createBlogEntry($succeeded, $additonal_message = NULL)
    {

        global $base_url;

        $this->logging('Starting createBlogEntry');

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

        if (!$this->test && $succeeded) {
            $url_link = 'islandora/object/' . $this->fid;
        } else {
            $url_link = 'node/' . (string)$this->node->id();
        }

        $outcome = $succeeded ? 'succeeded' : 'failed';
        $action = $this->test ? 'Validation' : 'Archiving';

        $bundle = $this->node->getTitle();
        $collection = $this->node->get('flat_parent_title')->value;

        $summary = sprintf("<p>%s of %s %s</p>", $action, $bundle, $outcome);
        // @FIXME
        // l() expects a Url object, created from a route name or external URI.
        // $body = sprintf("<p>%s %s</p><p>%s of %s belonging to %s %s. Check bundle ". l(t('here'), $url_link, array('html' => TRUE, 'external' => FALSE, 'absolute' => TRUE, 'base_url' => $base_url)) . '</p>', $bundle, $collection, $action, $bundle, $collection, $outcome);


        if ($additonal_message) {
            $body .= '</p>Exception message:</p>' . $additonal_message;
        };

        $new_node = new \stdClass();
        $new_node->type = 'blog';
        $new_node->title = sprintf("Result of processing bundle %s", $bundle);
        $new_node->uid = $this->node->id();
        $new_node->status = 1;
        $new_node->sticky = 0;
        $new_node->promote = 0;
        $new_node->format = 3;
        $new_node->revision = 0;
        $new_node->body[0]['format'] = 'full_html';
        $new_node->body[0]['summary'] = $summary;
        $new_node->body[0]['value'] = $body;
        $new_node->save();

        $this->logging('Finishing createBlogEntry; Blog entry created');
    }

    function customRollback($message)
    {

        $this->logging('Starting customRollback');

        // bundles need to unfreeze (if frozen) during rollback
        module_load_include('inc', 'flat_deposit', 'inc/class.FlatBundle');

        $move = \FlatBundle::moveBundleData($this->node, 'data', 'unfreeze');
        $move = \FlatBundle::moveBundleData($this->node, 'metadata', 'unfreeze');

        // create blog entry
        //$this->createBlogEntry(FALSE, $message);

        //set status of bundle
        $this->node->set('flat_bundle_status', 'failed');
        $this->node->save();

        $this->logging('Finishing customRollback');
        return;
    }
}
