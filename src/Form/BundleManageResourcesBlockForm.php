<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\BundleManageResourcesForm.
 * 
 * Form to display the list of files that will be added to the bundle
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystem;

class BundleManageResourcesBlockForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'flat_bundle_manage_resources_block_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $fedora_object = NULL)
    {

        $node = \Drupal::routeMatch()->getParameter('node');
        if ($node instanceof \Drupal\node\NodeInterface) {
            if ($node->bundle() === 'flat_bundle') {

                $location = $node->hasField('flat_location') ? $node->get('flat_location')->value : NULL;

                $files = [];

                if ($location) {
                    if (file_exists($location)) {

                        // We ignore hidden files (starting with a dot). These will not be added
                        // to the bundle later on either.
                        $files = array_diff(preg_grep('/^([^.])/', scandir($location)), ['..', '.']);
                    } else {
                        $form['flat_bundle_manage_resources']['#prefix'] = '<i>The selected folder for files to be added to this bundle cannot be found! Go to "Edit bundle properties" to choose a different one.</i>';
                        return $form;
                    }
                } else {
                    $form['flat_bundle_manage_resources']['#prefix'] = '<i>No folder selected for files to be added to this bundle! Go to "Edit bundle properties" to choose one.</i>';
                    return $form;
                }

                if (empty($files)) {
                    $form['flat_bundle_manage_resources']['#prefix'] = '<i>The selected folder is empty!</i>';
                    return $form;
                }

                $options = [];

                foreach ($files as $file) {

                    // using md5 on the filename to differentiate the resources
                    $id = md5($file);
                    /*
                    $form['flat_bundle_manage_resources'][$id]['encrypt'] = [

                        '#type' => 'checkbox',
                        '#default_value' => in_array($id, $marked),
                    ]; */

                    $options[$id]['filename'] = $file;
                }

                /*                 $form['flat_bundle_manage_resources'] = [

                    //'#theme' => 'flat_bundle_manage_resources',
                    '#type' => 'table',
                    '#header' => array('filename' => $this
                    ->t('File name')),
                    '#empty' => t('No files found'),
                    '#options' => $options,
                    // Need to set #tree to be able to differentiate
                    // between the various delete buttons upon
                    // submission.
                    '#tree' => TRUE,
                ]; */

                $form['flat_bundle_manage_resources'] = [

                    //'#theme' => 'flat_bundle_manage_resources',
                    '#type' => 'table',
                    '#header' => array(),
                    // Need to set #tree to be able to differentiate
                    // between the various delete buttons upon
                    // submission.
                    '#tree' => TRUE,
                ];

                foreach ($files as $file) {

                    // using md5 on the filename to differentiate the resources
                    $id = md5($file);
                    /*
                    $form['flat_bundle_manage_resources'][$id]['encrypt'] = [

                        '#type' => 'checkbox',
                        '#default_value' => in_array($id, $marked),
                    ]; */

                    $form['flat_bundle_manage_resources'][$id]['filename']['#markup'] =
                        '<span>' . $file . '</span><input type="hidden" name="flat_bundle_manage_resources[' . $id . '][filename]" value="';
                }

                if ($location) {

                    $dirname = \Drupal::service('file_system')->basename($location);
                    $form['flat_bundle_manage_resources']['#prefix'] = '<h2>Files in folder <i>"' . $dirname . '"</i> to be added to this bundle:</h2>';
                }

                // normalizing currently saved metadata, null, empty str will be marked as empty array
                /*                 $marked = $node->get('flat_encrypted_resources')->value;
                $marked = empty($marked) ? [] : explode(',', $marked);
 */


                $form['buttons']['save'] = [

                    '#type' => 'submit',
                    '#value' => t('Save'),
                    //'#submit' => ['flat_bundle_manage_resources_form_submit'],
                    '#attributes' => [
                        'class' => ['btn-success']
                    ]
                ];

                return $form;
            }
        }
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {

        $node = \Drupal::routeMatch()->getParameter('node');

        $marked = [];

        foreach ($form_state['values']['flat_bundle_manage_resources'] as $filenameMd5 => $fields) {

            if (isset($fields['encrypt']) && $fields['encrypt'] === 1) {

                // file has been marked as encrypted
                $marked[] = $filenameMd5;
            }
        }

        $node->set('flat_encrypted_resources', implode(',', $marked));
        $node->save();
    }
}
