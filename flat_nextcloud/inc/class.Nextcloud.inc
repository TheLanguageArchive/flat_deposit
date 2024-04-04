<?php

use Drupal\Core\Form\FormStateInterface;

module_load_include('inc', 'node', 'node.pages');
/**
 * Created by PhpStorm.
 * User: danrhe
 * Date: 04/05/2017
 * Time: 11:57
 */
class Nextcloud
{
    static function get_container()
    {

        $max_files = \Drupal::config('flat_deposit.settings')->get('flat_deposit_ingest_service')['max_ingest_files'];

        return array(
            '#weight' => 11,
            '#type' => 'container',
            '#tree' => FALSE,
            '#attributes' => array(
                'id' => array('nextcloud_navigation'),
            ),
            'field' => array(
                '#type' => 'fieldset',
                '#description' => t("Select the folder that contains the file(s) for this bundle. The final folder can have no sub-folders and should not contain more than @max_files files.", array('@max_files' => $max_files)),
                '#states' => array(
                    // Hide this select menu if source of resources is not cloud.
                    'visible' => array(
                        ':input[name="flat_source"]' => array('value' => 'cloud'),
                    ),
                ),

                'current_directory' => array(
                    '#markup' => '',
                ),

                'chosen_nc_path' => array(
                    '#type' => 'textfield',
                    '#title' => t('Chosen Nextcloud path'),
                ),

                'next_nc_directories' => array(
                    '#title' => 'Navigate to',
                    '#type' => 'select',
                    '#empty_option' => '-- Select Subfolder --',
                    '#empty_value' => '',
                    '#access' => FALSE,
                    '#options' => array(),
                    '#ajax' => array(
                        'callback' => 'reload_nextcloud_navigation_container_ajax_callback',
                        'wrapper' => 'nextcloud_navigation',
                        'effect' => 'fade',
                        'progress' => 'throbber',
                    ),

                ),

            )
        );
    }

    /**
     * Get the path of a bundles" resource files. If value of node field 'original_path' is not set yet, path is set to project root.
     * Otherwise, if project root folder has changed set 'original path' to root folder. Otherwise take saved value

     * @param $form_state
     * @param $project_root
     * @return string
     */
    static function get_flat_nc_path(FormStateInterface $form_state, $project_root)
    {
        if ($form_state->hasValue(['flat_source', 0, 'value'])) {
            if ($form_state->getValue(['flat_source', 0, 'value']) == 'external') {

                return '';
            }
        }
        // 1) If not set yet, set to project root. Otherwise,
        // 2) if project root folder has changed set to root folder.
        // 3) Otherwise take saved value
        $value = $form_state->hasValue(['flat_nc_path', 0, 'value']) ? $form_state->getValue(['flat_nc_path', 0, 'value']) : $project_root;

        if (strpos($value, $project_root) === FALSE) {

            $current = $project_root;
        } else {

            $current = $value;
        }

        return $current;
    }

    static function listAccessibleFolderContent($directory, $root, $filters = [])
    {

        $content = [];
        if (!file_exists($directory)) {
            return $content;
        }

        if ($directory == $root) {
            // if we are in the user's Nextcloud data root, do not list the .. option to go one directory up
            $filters[] = 'isNotDoubleDot';
        }

        $results = scandir($directory);

        foreach ($results as $result) {

            $full_path = "$directory/$result";

            $check = self::passedAllChecks($full_path, $filters);

            if (!$check) {

                continue;
            } else {

                $content[] = $result;
            }
        }

        return $content;
    }

    static function passedAllChecks($directory, $filters = [])
    {

        foreach ($filters as $filter) {
            if (!self::$filter($directory)) {
                return false;
            }
        }
        return TRUE;
    }

    static function isAccessibleDir($directory)
    {
        return is_dir($directory) and is_readable($directory) ? TRUE : FALSE;
    }

    static function isAccessible($directory)
    {
        return is_readable($directory);
    }

    static function hasValidDirectoryName($directory)
    {

        $dir_name = basename($directory);
        if ($dir_name == '.' or $dir_name == '..') {
            return true;
        }

        $pattern = '/^(?!\\.)[\w .-]+$/';
        return preg_match($pattern, $dir_name) != 0;
    }

    static function isDir($directory)
    {
        return is_dir($directory);
    }

    static function isNotDot($directory)
    {

        return basename($directory) != '.';
    }

    static function isNotDoubleDot($directory)
    {

        return basename($directory) != '..';
    }

    static function hasSubfolders($directory)
    {
        if (is_dir($directory)) {

            $content = \Drupal::service('file_system')->scanDirectory($directory, '/.*/', ['recurse' => FALSE]);
            if (!empty($content)) {
                foreach ($content as $item) {
                    if (is_dir($item->uri)) {
                        return TRUE;
                    }
                }
            }
        }
    }

    static function userHasReadPermissions($directory)
    {
        if (is_dir($directory)) {

            module_load_include('inc', 'flat_workspaces', 'inc/class.AclHandler');
            $acl = new \AclHandler(\Drupal::service('file_system')->realpath($directory));
            if (empty($acl->getInfo())) {
                return false;
            }

            $username = \Drupal::currentUser()->getAccountName();
            $id = $username == 'admin' ? exec('whoami') : $username;
            $id = str_replace('@mpi.nl', '', $id);

            return $acl->checkReadAccess($id);
        }

        return false;
    }

    static function webUserHasReadPermissions($directory)
    {
        if (is_dir($directory)) {

            module_load_include('inc', 'flat_workspaces', 'inc/class.AclHandler');
            $acl = new \AclHandler(\Drupal::service('file_system')->realpath($directory));

            if (empty($acl->getInfo())) {
                return false;
            }

            $id = exec('whoami');

            return $acl->checkReadAccess($id);
        }

        return false;
    }

    static function userHasWritePermissions($directory)
    {
        if (is_dir($directory)) {

            module_load_include('inc', 'flat_workspaces', 'inc/class.AclHandler');
            $acl = new \AclHandler(\Drupal::service('file_system')->realpath($directory));

            if (empty($acl->getInfo())) {
                return false;
            }

            $username = \Drupal::currentUser()->getAccountName();
            $id = $username == 'admin' ? exec('whoami') : $username;
            $id = str_replace('@mpi.nl', '', $id);

            return $acl->checkWriteAccess($id);
        }

        return false;
    }

    static function webUserHasWritePermissions($directory)
    {
        if (is_dir($directory)) {

            module_load_include('inc', 'flat_workspaces', 'inc/class.AclHandler');
            $acl = new \AclHandler(\Drupal::service('file_system')->realpath($directory));

            if (empty($acl->getInfo())) {
                return false;
            }

            $id = exec('whoami');

            return $acl->checkWriteAccess($id);
        }

        return false;
    }
}

function reload_nextcloud_navigation_container_ajax_callback(&$form, FormStateInterface $form_state)
{
    $next_dirs = $form_state->getValue(['next_nc_directories']);
    if ($next_dirs == '..') {
        $form['nextcloud_navigation']['field']['next_nc_directories']['#value'] = '';
    }
    return $form['nextcloud_navigation'];
}
