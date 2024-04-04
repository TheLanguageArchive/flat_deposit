<?php

/**
 * @file
 * Contains \Drupal\flat_workspaces\Form\AdminForm.
 */

namespace Drupal\flat_workspaces\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AdminForm extends ConfigFormBase
{

    /**
     * Config settings.
     *
     * @var string
     */
    const SETTINGS = 'flat_workspaces.settings';

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
            static::SETTINGS,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'flat_workspaces_admin_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $config = $this->config(static::SETTINGS);

        $form = [];

        $form['overview'] = array(
            '#markup' => t('Enable the use of local network share workspaces for data upload'),
            '#prefix' => '<p>',
            '#suffix' => '</p>',
        );

        $form['activated'] = array(
            '#title' => t('Use local workspaces'),
            '#description' => t('Enable the use of local network share workspaces for data upload'),
            '#type' => 'checkbox',
            '#default_value' => $config->get('activated'),
            '#required' => FALSE
        );

        $form['mount_folder'] = array(
            '#type' => 'textfield',
            '#title' => t('Workspaces root'),
            '#description' => t('Path of the workspaces root directory'),
            '#default_value' => $config->get('mount_folder'),
        );

        $form['department_mapping'] = array(
            '#type' => 'textarea',
            '#title' => t('Department directory mapping'),
            '#description' => t('Directory name to department name mapping (one mapping per line in the form: dirname = "department name"'),
            '#default_value' => decode_department_mapping($config->get('department_mapping')),
        );

        $form['workspace_folder'] = array(
            '#type' => 'textfield',
            '#title' => t('Workspaces folder name'),
            '#description' => t('Name of the folder that contains the workspaces within each department folder'),
            '#default_value' => $config->get('workspace_folder'),
            '#required' => TRUE,
        );

        $form['archive_folder'] = array(
            '#type' => 'textfield',
            '#title' => t('Archive Deposit folder name'),
            '#description' => t('Name of archive folder within each workspace, to be used for deposits'),
            '#default_value' => $config->get('archive_folder'),
            '#required' => TRUE,
        );

        // BUTTONS
        $form['buttons']['restore'] = [
            '#type' => 'submit',
            '#value' => t('Reset to defaults'),
            '#submit' => [
                '::setDefault'
            ],
            '#limit_validation_errors' => [],
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $department_mapping = encode_department_mapping($form_state->getValue('department_mapping'));

        //dd($department_mapping);

        $this->config(static::SETTINGS)

            ->set('activated', $form_state->getValue('activated'))
            ->set('mount_folder', $form_state->getValue('mount_folder'))
            ->set('department_mapping', $department_mapping)
            ->set('workspace_folder', $form_state->getValue('workspace_folder'))
            ->set('archive_folder', $form_state->getValue('archive_folder'))
            ->save();

        parent::submitForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function setDefault()
    {

        $config_name = static::SETTINGS;

        //if ($form_state->getTriggeringElement()['#name'] == 'reset') {
        $config = \Drupal::configFactory()->getEditable($config_name);

         // Get the extension path.
        $module_handler = \Drupal::service('module_handler');
        $module_path = $module_handler->getModule('flat_workspaces')->getPath();
        $config_install_path = $module_path . '/config/install/';

        // setup file storage
        $file_storage = new \Drupal\Core\Config\FileStorage($config_install_path);

        if ($file_storage->exists($config_name)) {
            // Get the default values
            $default_values = $file_storage->read($config_name);

            // Set the configuration to the default values and save.
            $config->setData($default_values)->save();

            \Drupal::messenger()->addMessage(t('Settings have been reset to defaults.'));
        }
    }
}

/**
 * @param $array config array with dept. dir as key and name as value
 *
 * @return
 *  Formatted string with dirname = "department name" on each line
 */
function decode_department_mapping($mapping)
{

    $output = '';

    foreach ($mapping as $key => $value) {

        $output .= $key . ' = ' . $value . '&#10;';
    }

    return html_entity_decode($output);
}

/**
 * @param $string  with dirname = "department name" on each line
 *
 * @return
 *  $array config array with dept. dir as key and name as value
 */
function encode_department_mapping($mapping)
{
    $output = [];

    foreach (preg_split("/((\r?\n)|(\r\n?))/", $mapping) as $line) {
        if (str_contains($line, "=")) {
            $key_val = preg_split("/=/", $line);
            $key = trim($key_val[0]);
            $value = trim($key_val[1]);
            $output[$key] = $value;
        }
    }

    return $output;
}
