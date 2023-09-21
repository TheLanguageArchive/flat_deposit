<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\AdminForm.
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AdminForm extends ConfigFormBase
{

  /** 
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'flat_deposit.settings';

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
    return 'flat_deposit_admin_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $config = $this->config(static::SETTINGS);

    $form = [];

    $form['overview'] = [
      '#markup' => t('Settings for the FLAT Deposit module'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    // GENERAL
    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => t('General settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $general = $config->get('flat_deposit_general');

    $form['general']['namespace'] = [
      '#type' => 'textfield',
      '#title' => t('Fedora namespace'),
      '#description' => t('Fedora namespace to be used for ingests'),
      '#default_value' => $general['namespace'],
      '#required' => TRUE,
    ];

    $form['general']['external'] = [
      '#type' => 'textfield',
      '#title' => t('Upload folder for external data'),
      '#description' => t('Directory where uploaded data will be temporarily stored'),
      '#default_value' => $general['external'],
      '#required' => TRUE,
    ];

    $form['general']['metadata'] = [
      '#type' => 'textfield',
      '#title' => t('Metadata folder'),
      '#description' => t('Directory for temporarily storing metadata'),
      '#default_value' => $general['metadata'],
      '#required' => TRUE,
    ];

    $form['general']['freeze'] = [
      '#type' => 'textfield',
      '#title' => t('Freeze folder'),
      '#description' => t('Directory where user bundles will be placed upon validation'),
      '#default_value' => $general['freeze'],
      '#required' => TRUE,
    ];

    // CMDI profiles
    $form['cmdi_profiles'] = [
      '#type' => 'fieldset',
      '#title' => t('CMDI profile settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $profiles = $config->get('flat_deposit_cmdi_profiles');

    $form['cmdi_profiles']['collection_profile_ids'] = [
      '#type' => 'textfield',
      '#title' => t('Collection CMDI profile IDs'),
      '#description' => t('CMDI profiles that should be treated as collections'),
      '#default_value' => $profiles['collection_profile_ids'],
      '#required' => TRUE,
    ];

    $form['cmdi_profiles']['bundle_profile_ids'] = [
      '#type' => 'textfield',
      '#title' => t('Bundle CMDI profile IDs'),
      '#description' => t('CMDI profiles that should be treated as bundles'),
      '#default_value' => $profiles['bundle_profile_ids'],
      '#required' => TRUE,
    ];

    // INGEST SERVICE
    $form['ingest_service'] = [
      '#type' => 'fieldset',
      '#title' => t('Ingest service'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $service = $config->get('flat_deposit_ingest_service');

    $form['ingest_service']['host_name'] = [
      '#type' => 'textfield',
      '#title' => t('Host name'),
      '#description' => t('Host name or IP of the http/php server'),
      '#default_value' => $service['host_name'],
      '#required' => TRUE,
    ];

    $form['ingest_service']['host_scheme'] = [
      '#type' => 'textfield',
      '#title' => t('Host Scheme '),
      '#description' => t('HTTP or HTTPS'),
      '#default_value' => $service['host_scheme'],
      '#required' => TRUE,
    ];

    $form['ingest_service']['java_home'] = [
      '#type' => 'textfield',
      '#title' => t('Java home'),
      '#description' => t('Specific Java home path'),
      '#default_value' => $service['java_home'],
      '#required' => TRUE,
    ];

    $form['ingest_service']['bag_exe'] = [
      '#type' => 'textfield',
      '#title' => t('Bagit executable'),
      '#description' => t('Specific path to bagit executable'),
      '#default_value' => $service['bag_exe'],
      '#required' => TRUE,
    ];

    $form['ingest_service']['bag_dir'] = [
      '#type' => 'textfield',
      '#title' => t('Bag folder'),
      '#description' => t('Backend directory where bags will be placed'),
      '#default_value' => $service['bag_dir'],
      '#required' => TRUE,
    ];

    $form['ingest_service']['sword_tmp_dir'] = [
      '#type' => 'textfield',
      '#title' => t('Sword temporary folder'),
      '#description' => t('Directory sword uses to temporarily save bags'),
      '#default_value' => $service['sword_tmp_dir'],
      '#required' => TRUE,
    ];

    $form['ingest_service']['log_errors'] = [
      '#type' => 'checkbox',
      '#title' => t('Log backend service errors'),
      '#description' => t('Should errors during backend ingest be written to disk?'),
      '#default_value' => $service['log_errors'],
      '#required' => FALSE,
    ];


    $form['ingest_service']['error_log_file'] = [
      '#type' => 'textfield',
      '#title' => t('Path to error log directory'),
      '#default_value' => $service['error_log_file'],
      '#required' => TRUE,
    ];

    $form['ingest_service']['log_all'] = [
      '#type' => 'checkbox',
      '#title' => t('Log all backend actions'),
      '#description' => t('Should all backend service activities be logged?'),
      '#default_value' => $service['log_all'],
      '#required' => FALSE,
    ];
    $form['ingest_service']['max_ingest_files'] = [
      '#type' => 'textfield',
      '#title' => t('Maximal number of files per bundle'),
      '#description' => t('Validation parameter for bundles before ingest'),
      '#default_value' => $service['max_ingest_files'],
      '#required' => TRUE,
    ];
    $form['ingest_service']['max_file_size'] = [
      '#type' => 'textfield',
      '#title' => t('Maximum size per ingested file (GB)'),
      '#description' => t('Validation parameter for bundles before ingest'),
      '#default_value' => $service['max_file_size'],
      '#required' => TRUE,
    ];

    $form['ingest_service']['allowed_extensions'] = [
      '#type' => 'textarea',
      '#title' => t('Allowed file extensions (comma separated list)'),
      '#description' => t('Validation parameter for bundles before ingest'),
      '#default_value' => $service['allowed_extensions'],
      '#required' => TRUE,
    ];

    // FITS
    $form['fits'] = [
      '#type' => 'fieldset',
      '#title' => t('FITS'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $fits = $config->get('flat_deposit_fits');

    $form['fits']['url'] = [
      '#type' => 'textfield',
      '#title' => t('URL of FITS service'),
      '#default_value' => $fits['url'],
      '#required' => TRUE,
    ];

    $form['fits']['port'] = [
      '#type' => 'textfield',
      '#title' => t('Port of FITS service'),
      '#default_value' => $fits['port'],
      '#required' => TRUE,
    ];

    // SWORD
    $form['sword'] = [
      '#type' => 'fieldset',
      '#title' => t('Sword configuration'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $sword = $config->get('flat_deposit_sword');

    $form['sword']['url'] = [
      '#type' => 'textfield',
      '#title' => t('URL of the easy-deposit service'),
      '#default_value' => $sword['url'],
      '#required' => TRUE,
    ];

    $form['sword']['port'] = [
      '#type' => 'textfield',
      '#title' => t('Port used by easy-deposit service'),
      '#default_value' => $sword['port'],
      '#required' => TRUE,
    ];

    $form['sword']['user'] = [
      '#type' => 'textfield',
      '#title' => t('User ID used to connect to easy-deposit service'),
      '#default_value' => $sword['user'],
      '#required' => TRUE,
    ];

    $form['sword']['password'] = [
      '#type' => 'password',
      '#title' => t('Password corresponding to this user'),
    ];

    // DOORKEEPER
    $form['doorkeeper'] = [
      '#type' => 'fieldset',
      '#title' => t('DoorKeeper configuration'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $doorkeeper = $config->get('flat_deposit_doorkeeper');

    $form['doorkeeper']['url'] = [
      '#type' => 'textfield',
      '#title' => t('URL of the doorkeeper servlet'),
      '#default_value' => $doorkeeper['url'],
      '#required' => TRUE,
    ];

    $form['doorkeeper']['port'] = [
      '#type' => 'textfield',
      '#title' => t('Port used by DoorKeeper servlet'),
      '#default_value' => $doorkeeper['port'],
      '#required' => TRUE,
    ];

    // FEDORA
    $form['fedora'] = [
      '#type' => 'fieldset',
      '#title' => t('Fedora configuration'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $fedora = $config->get('flat_deposit_fedora');

    $form['fedora']['host_name'] = [
      '#type' => 'textfield',
      '#title' => t('Host name of fedora commons server'),
      '#default_value' => $fedora['host_name'],
      '#required' => TRUE,
    ];

    $form['fedora']['host_ip'] = [
      '#type' => 'textfield',
      '#title' => t('IP address of fedora commons server'),
      '#default_value' => $fedora['host_ip'],
      '#required' => TRUE,
    ];

    $form['fedora']['port'] = [
      '#type' => 'textfield',
      '#title' => t('Port used by fedora commons server'),
      '#default_value' => $fedora['port'],
      '#required' => TRUE,
    ];

    $form['fedora']['scheme'] = [
      '#type' => 'textfield',
      '#title' => t('Scheme used by fedora commons server'),
      '#descriptions' => t('http or https'),
      '#default_value' => $fedora['scheme'],
      '#required' => TRUE,
    ];

    $form['fedora']['user'] = [
      '#type' => 'textfield',
      '#title' => t('User ID used to connect to fedora commons server'),
      '#default_value' => $fedora['user'],
      '#required' => TRUE,
    ];

    $form['fedora']['password'] = [
      '#type' => 'password',
      '#title' => t('Password corresponding to this user'),
    ];

    $form['fedora']['context'] = [
      '#type' => 'textfield',
      '#title' => t('Context'),
      '#description' => t('Variable specifying the context of the connection'),
      '#default_value' => $fedora['context'],
      '#required' => TRUE,
    ];

    // SOLR
    $form['solr'] = [
      '#type' => 'fieldset',
      '#title' => t('solr configuration'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];

    $solr = $config->get('flat_deposit_solr');

    $form['solr']['host_name'] = [
      '#type' => 'textfield',
      '#title' => t('Host name of SOLR'),
      '#default_value' => $solr['host_name'],
      '#required' => TRUE,
    ];

    $form['solr']['port'] = [
      '#type' => 'textfield',
      '#title' => t('Port used by solr'),
      '#default_value' => $solr['port'],
      '#required' => TRUE,
    ];

    $form['solr']['schema'] = [
      '#type' => 'textfield',
      '#title' => t('scheme to connect to solr'),
      '#descriptions' => t('http or https'),
      '#default_value' => $solr['schema'],
      '#required' => TRUE,
    ];

    $form['solr']['path'] = [
      '#type' => 'textfield',
      '#title' => t('Solr path'),
      '#default_value' => $solr['path'],
      '#required' => TRUE,
    ];

    $form['solr']['core'] = [
      '#type' => 'textfield',
      '#title' => t('Solr core'),
      '#default_value' => $solr['core'],
      '#required' => TRUE,
    ];

    // BUTTONS
    $form['buttons']['restore'] = [
      '#type' => 'submit',
      '#value' => t('Reset to defaults'),
      '#submit' => [
        'flat_deposit_admin_form_reset_submit'
      ],
      '#limit_validation_errors' => [],
    ];

    $form['buttons']['import'] = [
      '#type' => 'submit',
      '#value' => t('Import settings'),
      '#submit' => [
        'flat_deposit_admin_form_import_submit'
      ],
      '#validate' => ['flat_deposit_admin_form_import_validate'],
    ];

    $form['buttons']['export'] = [
      '#type' => 'submit',
      '#value' => t('Export settings'),
      '#submit' => [
        'flat_deposit_admin_form_export_submit'
      ],
    ];

    $form['file']['import'] = [
      '#name' => 'File_selector',
      '#type' => 'file',
      '#title' => t('Choose a file'),
      '#title_display' => 'invisible',
      '#size' => 22,
      '#theme_wrappers' => [],
      '#weight' => 999,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config(static::SETTINGS)

      ->set('flat_deposit_general', $form_state->getValue('general'))
      ->set('flat_deposit_cmdi_profiles', $form_state->getValue('cmdi_profiles'))
      ->set('flat_deposit_fits', $form_state->getValue('fits'))
      ->set('flat_deposit_ingest_service', $form_state->getValue('ingest_service'))
      ->set('flat_deposit_sword', $form_state->getValue('sword'))
      ->set('flat_deposit_doorkeeper', $form_state->getValue('doorkeeper'))
      ->set('flat_deposit_fedora', $form_state->getValue('fedora'))
      ->set('flat_deposit_solr', $form_state->getValue('solr'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
