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
     * {@inheritdoc}  
     */
    protected function getEditableConfigNames()
    {
        return [
            'flat_workspaces.settings',
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

        $config = \Drupal::config('flat_workspaces.settings');

        $form = [];

        $form['overview'] = array(
            '#markup' => t('Enable the use of local network share workspaces for data upload'),
            '#prefix' => '<p>',
            '#suffix' => '</p>',
        );

        $form['workspace_settings'] = array(
            '#type' => 'fieldset',
            '#title' => t('Workspace settings'),
            '#tree' => TRUE,
        );

        $form['workspace_settings']['activated'] = array(
            '#title' => t('Use local workspaces'),
            '#description' => t('Enable the use of local network share workspaces for data upload'),
            '#type' => 'checkbox',
            '#default_value' => $config->get('activated'),
            '#required' => FALSE
        );

        $form['workspace_settings']['mount_folder'] = array(
            '#type' => 'textfield',
            '#title' => t('Workspaces root'),
            '#description' => t('Path of the workspaces root directory'),
            '#default_value' => $config->get('mount_folder'),
        );

        $form['workspace_settings']['department_mapping'] = array(
            '#type' => 'textarea',
            '#title' => t('Department directory mapping'),
            '#description' => t('Directory name to department name mapping (one mapping per line in the form: dirname = "department name"'),
            '#default_value' => format_department_mapping($config->get('department_mapping')),
        );

        $form['workspace_settings']['workspace_folder'] = array(
            '#type' => 'textfield',
            '#title' => t('Workspaces folder name'),
            '#description' => t('Name of the folder that contains the workspaces within each department folder'),
            '#default_value' => $config->get('workspace_folder'),
            '#required' => TRUE,
        );

        $form['workspace_settings']['archive_folder'] = array(
            '#type' => 'textfield',
            '#title' => t('Archive Deposit folder name'),
            '#description' => t('Name of archive folder within each workspace, to be used for deposits'),
            '#default_value' => $config->get('archive_folder'),
            '#required' => TRUE,
        );

        return parent::buildForm($form, $form_state);
    }
}

function format_department_mapping($mapping)
{  
    $output = '';
        foreach ($mapping as $key => $value) {
            //$ouput .= $key . ' = "' . $value . '"\n';
            $output .= $key . ' = ' . $value . '&#10;';
    }
    //dd($output);
    return html_entity_decode($output);
}