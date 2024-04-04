<?php

/**
 * @file
 * Contains \Drupal\flat_nextcloud\Form\AdminForm.
 */

namespace Drupal\flat_nextcloud\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AdminForm extends ConfigFormBase
{

    /**
     * Config settings.
     *
     * @var string
     */
    const SETTINGS = 'flat_nextcloud.settings';

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
        return 'flat_nextcloud_admin_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $config = $this->config(static::SETTINGS);

        $form = [];

        $form['overview'] = array(
            '#markup' => t('Configure local Nextcloud instance settings for user data upload. Nextcloud needs to run on the same server as this Drupal installation'),
            '#prefix' => '<p>',
            '#suffix' => '</p>',
        );

        $form['activated'] = array(
            '#title' => t('Use Nextcloud'),
            '#description' => t('Use Nextcloud for data upload '),
            '#type' => 'checkbox',
            '#default_value' => $config->get('activated'),
            '#required' => FALSE
        );


        $form['admin_name'] = array(
            '#type' => 'textfield',
            '#title' => 'Nextcloud admin name',
            '#description' => t('Nextcloud admin user name'),
            '#default_value' => $config->get('admin_name'),
            '#required' => TRUE,
        );

        $form['admin_pass'] = array(
            '#type' => 'password',
            '#title' => 'Nextcloud admin password',
            '#description' => t('Nextcloud admin user password'),
            '#default_value' => $config->get('admin_pass'),
            '#required' => FALSE,
        );

        $form['schema'] = array(
            '#type' => 'textfield',
            '#title' => 'Host schema',
            '#description' => t('HTTP or HTTPS'),
            '#default_value' => $config->get('schema'),
            '#required' => TRUE,
        );

        $form['host'] = array(
            '#type' => 'textfield',
            '#title' => 'Host name',
            '#description' => t('IP address or hostname'),
            '#default_value' => $config->get('host'),
            '#required' => TRUE,
        );

        $form['root_dir'] = array(
            '#type' => 'textfield',
            '#title' => 'Nextcloud root directory',
            '#description' => t('Nextcloud installation path'),
            '#default_value' => $config->get('root_dir'),
            '#required' => TRUE,
        );

        $form['data_dir'] = array(
            '#type' => 'textfield',
            '#title' => 'Nextcloud data directory',
            '#description' => t('Directory where Nextcloud stores uploaded user data'),
            '#default_value' => $config->get('data_dir'),
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

        $this->config(static::SETTINGS)
            ->set('activated', $form_state->getValue('activated'))
            ->set('admin_name', $form_state->getValue('admin_name'))
            ->set('admin_pass', $form_state->getValue('admin_pass'))
            ->set('schema', $form_state->getValue('schema'))
            ->set('host', $form_state->getValue('host'))
            ->set('root_dir', $form_state->getValue('root_dir'))
            ->set('data_dir', $form_state->getValue('data_dir'))
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
        $module_path = $module_handler->getModule('flat_nextcloud')->getPath();
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
 * keeps the default password in case no password has been entered
 */
function keep_default_password($form, &$form_state)
{


    if (empty($form_state['admin_pass'])) {
        $form_state['values']['admin_pass'] = variable_get('flat_nextcloud')['admin_pass'];
    }
}

/**
 * function call to check if nextcloud is reachable using the nextcloud command line tool (occ).
 *
 *
 * @return array|bool if successfully status of occ otherwise FALSE
 */
function check_nextcloud_status()
{
    $status = FALSE;

    $config = variable_get('flat_nextcloud');

    $cmd = $config['root_dir'] . '/occ status --output=json';
    exec($cmd, $output, $return_val);

    if (!$return_val) {
        $formatted = (array)json_decode($output[0]);

        if ($formatted['installed']) {
            return $formatted;
        }
    }

    return $status;
}


function flat_nextcloud_admin_form_validate($form, &$form_state)
{
    $button = $form_state['values']['op'];

    switch ($button) {
        case 'Save': {

                break;
            }

        case 'Reset to defaults': {
                break;
            }

        case 'Check nextcloud connection': {
                $status = check_nextcloud_status();

                if (!$status) {
                    $form_state->setErrorByName('activated', 'Nextcloud status check failed');
                } else {
                    $form_state['values']['status'] = $status;
                }

                break;
            }
    }

    return $form;
}
