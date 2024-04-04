<?php

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

/**
 *
 */

class FlatBundleException extends Exception
{
}

class FlatBundle
{

    /**
     * Checks that combination of collection name and bundle is unique for a certain user.
     *
     * @param $user_id integer drupal id of user for which nodes are queried
     * @param $collection String title of the parent
     * @param $bundle_name String title of the node
     *
     * @return bool TRUE if combination of collection and bundle name is unique
     */
    public static function checkUniquenessNaming($user_id, $collection, $bundle_name)
    {
        $query = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('type', 'flat_bundle')
            //->condition('uid', $user_id)
            ->condition('title', $bundle_name)
            ->condition("flat_parent_title.value", $collection, "=");

        // $query = new EntityFieldQuery();
        // $query->entityCondition('entity_type', 'node')
        //     ->entityCondition('bundle', 'flat_bundle')
        //     ->propertyCondition('uid', $user_id)
        //     ->propertyCondition('title', $bundle_name)
        //     ->fieldCondition('flat_parent_title', 'value', "$collection", '=');

        $user_node_nids_in_collection = $query->execute();

        if (!empty($user_node_nids_in_collection)) {
            return FALSE;
        }

        return TRUE;
    }


    /**
     * Moves bundle data and meta data to new location. This can be a different collection or the freeze location
     *
     * @param stdClass $node drupal flat_bundle node.
     *
     * @param string $data_type type of data to be moved: 'metadata' or 'data'.
     *
     * @param string $move_direction Direction in which data will be moved. Allowed values are: 'freeze' and 'unfreeze'
     *
     * @return bool|string new location of the bundle data or false
     */
    public static function moveBundleData($node, $data_type, $move_direction)
    {

        // $wrapper = entity_metadata_wrapper('node', $node);

        try {
            self::validate_node($node);
            $source_location = self::set_original_location($node, $data_type);
            self::validate_source($node, $source_location, $data_type);
            $target_location = self::set_target_location($node, $data_type, $move_direction);
            self::validate_target($node, $data_type, $source_location, $target_location);
            self::validate_naming($node);
            $new_location = self::move_data($node, $data_type, $source_location, $target_location);
        } catch (\FlatBundleException $exception) {
            trigger_error($exception->getMessage(), E_USER_WARNING);
            return FALSE;
        }
        return $new_location;
    }

    /**
     * Validates if bundle or collection with the same name already exists for the parent
     *
     * @param $node
     */
    public static function validate_naming($node)
    {
        $title = $node->getTitle();

        $parent_nid = $node->get('flat_parent_nid_bundle')->value;
        $parent_node = \Drupal::entityTypeManager()->getStorage('node')->load($parent_nid);
        if ($parent_node->hasField('flat_fid')) {
            $parent_fid = $parent_node->get('flat_fid')->value;
        } else {
            return;
        }

        module_load_include('inc', 'flat_deposit', '/inc/class.FlatUtils');
        $values = \FlatUtils::getChildrenTitles($parent_fid);

        if (in_array(strtoupper($title), array_unique(array_map('strtoupper', $values)))) {
            throw new \FlatBundleException("A collection or bundle with the same or very similar name already exists at this location in the archive. Please use a different name.");
        }
    }

    public static function recursiveRmDir($dir)
    {
        $fileSystem = \Drupal::service("file_system");
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $filename => $fileInfo) {
            if ($fileInfo->isDir()) {
                $fileSystem->rmdir($filename);
            } else {
                $fileSystem->unlink($filename);
            }
        }
    }


    public static function validate_node($node)
    {
        if ($node->getType() != 'flat_bundle') {
            throw new \FlatBundleException("Wrong node type for data move");
        }
    }

    public static function set_original_location($node, $data_type)
    {
        if ($data_type == 'metadata') {
            $cmdi = $node->get('flat_cmdi_file')->target_id;
            if ($cmdi) {
                $cmdi_file = File::load($cmdi);
                $original_location = $cmdi_file->getFileUri();
            }
        } else {
            $original_location = $node->get('flat_location')->value;
        }
        return $original_location;
    }

    // generate new target path
    public static function set_target_location($node, $data_type, $move_direction)
    {

        if ($data_type == 'metadata') {
            $base = ($move_direction == 'freeze') ? 'freeze://metadata' : "metadata://";
            /*
            $new_location = "$base/$tail/";

            $new_location .= 'record.cmdi';
*/
            $new_location = $base . '/' . $node->id() . '/record.cmdi';

            // data handling
        } else {
            if ($move_direction == 'freeze') {

                // freeze location is standard the same for all
                $base = 'freeze://data';
                /*
                $new_location = "$base/$tail/";
                */
                $new_location = $base . '/' . $node->id();
            } else {

                $source = $node->get('flat_source')->value;
                // flat_bundle nodes will be put back to original_path location
                if (($source == 'local') || ($source == 'cloud')) {

                    $original_location = $node->get('flat_original_path')->value;
                    $new_location = $original_location;
                } else {

                    // if unfrozen flat_bundle with local MPI_workspace resources is frozen move to freeze location
                    $base =  'external://';
                    /*
                    $new_location = "$base/$tail/";
                    */
                    $new_location = $base . '/' . $node->id();
                }
            }
        }

        return $new_location;
    }

    public static function validate_source($node, $dir, $data_type)
    {
        $source = $node->get('flat_source')->value;

        if ($source == 'local' && $data_type == 'data') {

            #module_load_include('inc','flat_deposit','inc/class.Workspaces');
            // todo implement validation

        } else {

            // Validate that the files' original location is correct
            if (!file_exists($dir)) {
                throw new \FlatBundleException("Source directory does not exist");
            }
        }
    }

    public static function validate_target($node, $data_type, $source_location, $target_location)
    {
        $source_path_elements = array_filter(explode('/', $source_location));
        $target_path_elements = array_filter(explode('/', $target_location));

        if (empty(array_diff($source_path_elements, $target_path_elements))) {

            // do nothing if data stays at same location (e.g. MPI_workspace data when collection is changed)

        } else {

            // check that target path/file is not existing
            if (file_exists($target_location)) {
                throw new \FlatBundleException("Target location already exists");
            }

            // check that target path/file can be placed within parent directory
            $collection_dir = dirname($target_location);

            if (!file_exists($collection_dir)) {

                $check = \Drupal::service("file_system")->mkdir($collection_dir, NULL, TRUE);

                if (!$check) {
                    throw new \FlatBundleException("Unable to create folder at target original_location");
                }
            }

            if ($node->get('flat_source')->value != 'local') {

                if (!is_writable($collection_dir)) {
                    throw new \FlatBundleException("Unable to write at target location");
                }
            }
        }
    }

    public static function move_data($node, $data_type, $source_location, $target_location)
    {
        //bypass move if source and target are identical
        $source_path_elements = array_filter(explode('/', $source_location));
        $target_path_elements = array_filter(explode('/', $target_location));

        // do nothing if data stays at same location (e.g. MPI_workspace data when collection is changed)
        if (empty(array_diff($source_path_elements, $target_path_elements))) {
            return $target_location;
        }

        if ($data_type == 'metadata') {

            $cmdi = $node->get('flat_cmdi_file')->target_id;
            if ($cmdi) {
                $cmdi_file = File::load($cmdi);
                $original_metadata_uri = $cmdi_file->getFileUri();
            }

            //$metadata_file_info    = $node->flat_cmdi_file->value;
            //$original_metadata_uri = $metadata_file_info['uri'];

            //$file = file_load($metadata_file_info['fid']);

            //$new_file = file_move($cmdi_file, $target_location, FILE_EXISTS_REPLACE);
            $new_file = \Drupal::service('file_system')->move($original_metadata_uri, $target_location, FileSystemInterface::EXISTS_REPLACE);

            if (!$new_file) {
                throw new \FlatBundleException("Unable to move metadata to new target original_location");
            }

            // for some unknown reason flat_location and flat_original_path are messed up by attaching the newly created cmdi file, so we need to restore it
            $flat_location_original = $node->get('flat_location')->value; //$node->flat_location->value;
            $flat_original_path_original = $node->get('flat_original_path')->value; //$node->flat_original_path->value;

            // Change node information
            $cmdi_file->setFileuri($target_location);
            $cmdi_file->save();

            //$node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
            //$node->set('flat_location', $flat_location_original);
            //$node->set('flat_original_path', $flat_original_path_original);
            //$node->save();

            if (self::is_dir_empty($original_metadata_uri)) {
                \Drupal::service('file_system')->rmdir($original_metadata_uri);
            }
        } else {

            $moved = \Drupal::service('file_system')
                ->move($source_location, $target_location, FileSystemInterface::EXISTS_REPLACE);
            //$moved = rename(drupal_realpath($source_location), drupal_realpath($target_location)); // Unfortunately, rename does not return true on success

            // hook for additional actions after move
            $actions = \Drupal::moduleHandler()->invokeAll('data_move_actions', [$source_location, $target_location]);

            if (!file_exists($target_location)) {
                throw new \FlatBundleException("Unable to move data to freeze original_location");
            }

            // Change node information
            //$node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
            //$node = Node::load($node->id());
            //$node->set('flat_location', $target_location); //$node->flat_location->value = $target_location;
            //$node->save();

            //$saved_location = $node->get('flat_location')->value;
        }

        return $target_location;
    }


    public static function is_dir_empty($dir)
    {
        if (!is_readable($dir)) {
            return NULL;
        }
        if (!is_dir($dir)) {
            return NULL;
        }
        return (count(scandir($dir)) == 2);
    }
}
