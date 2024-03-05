<?php

/**
 * @file
 * Contains \Drupal\flat_deposit\Form\CollectionUpdateForm.
 */

namespace Drupal\flat_deposit\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Ajax\RedirectCommand;

use Symfony\Component\HttpFoundation\Request;

class CollectionUpdateForm extends FormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'flat_collection_add_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $fedoraObject = NULL)
    {


        // @FIXME
        // drupal_set_title() has been removed. There are now a few ways to set the title
        // dynamically, depending on the situation.
        //
        //
        // @see https://www.drupal.org/node/2067859
        // drupal_set_title(t('Update Collection Metadata'));

        module_load_include('inc', 'flat_deposit', '/inc/class.FlatUtils');

        module_load_include('inc', 'flat_deposit', '/Helpers/CMDI/class.CmdiHandler');

        //$ds = islandora_datastream_load("CMD", $fedora_object->id);

        $nid = \Drupal::routeMatch()->getParameter('node')->id();

        $cmdi_path = \FlatUtils::getCmdiFile($nid);

        if (!$cmdi_path) {
            \Drupal::messenger()->addMessage(t('No CMDI file found for this collection'), 'error');
            return $form;
        }

        $inheritedData = \CmdiHandler::simplexml_load_cmdi_file($cmdi_path);

        if ($inheritedData) {
            $profile = $inheritedData->getNameById();
        } else {
            $profile = null;
        }

        $form['#prefix'] = "<div id='flat_cmdi_form_wrapper'>";
        $form['#suffix'] = "</div>";

        $user = \Drupal::currentUser();

        $form['owner'] = array(
            '#title' => t('Owner of the collection'),
            '#type' => 'textfield',
            '#required' => true,
            '#default_value' => $user->getAccountName(),
            '#disabled' => true,
        );

        if ($user->hasPermission('admin deposit module')) {
            $form['owner']['#disabled'] = false;
        }

        $form['select_profile_name'] = array(
            '#type' => 'hidden',
            '#value' => $profile,
        );

        $form['select_policy'] = array(
            '#title' => t('Which access policy do you want to apply?'),
            '#type' => 'select',
            '#description' => t('Select which access policy should be applied as the default for bundles within this collection. "Public" materials can be accessed by anyone without having to log in. "Authenticated Users" means any user with a valid account for the archive. "Academic Users" are users that logs in with an academic account or whose academic status has been verified. "Private" means that the materials are only accessible to the depositor. Access policies can be refined later.'),
            '#required' => true,
            '#options' => array('public' => t('public'), 'authenticated' => t('authenticated users'), 'academic' => t('academic users'), 'private' => t('private')),
            '#empty_option' => t('-- Select a policy --'),
        );

        $form['visibility'] = array(
            '#title' => t('Visibility'),
            '#type' => 'select',
            '#description' => t('Hidden collections are not visible to anyone but the depositor and the archive managers. This is to be used only in rare cases in which the name or other metadata fields reveal too much information about work in progresss. The visibility will be applied to this collection and will be the default value for any sub-collections or bundles within this collection. Only materials with a private access policy can be hidden.'),
            '#required' => true,
            '#options' => array(
                'show' => 'visible',
                'hide' => 'hidden',
            ),
            '#default_value' => 'visible',
            '#states' => array(
                'visible' => array(
                    ':input[name="select_policy"]' => array('value' => 'private'),
                ),
            ),
        );

        $form['template_container'] = array(
            '#type' => 'container',
            '#tree' => true,
            '#attributes' => array(
                'id' => array('template-form'),
            ),
        );

        // @TODO get actual fedora ID once we have this
        $fid = 'lat:123456789';

        // attach hidden data
        $form['data'] = array(
            '#type' => 'value',
            '#value' => array(
                'fid' => $fid,
            ),
        );

        if ($inheritedData) {
            $form['data']['#value']['handle'] = (string) $inheritedData->Header->MdSelfLink;
        }

        $form['Submit'] = array(
            '#type' => 'submit',
            '#value' => t('Submit'),
            '#ajax' => array(
                'callback' => 'flat_collection_update_form_ajax_handler',
                'wrapper' => 'flat_cmdi_form_wrapper',
                'effect' => 'fade',
            ),
            '#validate' => array('flat_collection_update_form_final_validate'),
        );

        //********************************************************************
        // Generate profile specific form render array and attach to container
        //********************************************************************
        include_once DRUPAL_ROOT . '/' . \Drupal::service('extension.path.resolver')->getPath('module', 'flat_deposit') . '/Helpers/CMDI/FormBuilder/class.FormBuilder.inc';
        //module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/FormBuilder/class.CmdiTemplateManager');
        include_once DRUPAL_ROOT . '/' . \Drupal::service('extension.path.resolver')->getPath('module', 'flat_deposit') . '/Helpers/CMDI/CmdiPreset/class.CmdiPresetManager.inc';

        // load template if selected
        \CmdiPresetManager::load($form_state);

        // adding modal component to form
        $form['flat_modal'] = \CmdiPresetManager::modal();

        // adding save cmdi template feature
        $saved = \CmdiPresetManager::save($form_state);

        $availableFormTemplates = \FormBuilder::getAvailableTemplates('flat_collection');

        if (array_key_exists($profile, $availableFormTemplates)) {
            // Load form builder app
            $formBuilder = new \FormBuilder(null);

            // count button presses per field
            $formBuilder->aggregatePressedButtons($form_state);

            // get the node in nested array from which we can start iterating all button presses per field
            if ($form_state->has(['pressedButtons', 'template_container', 'elements'])) {
                $pressedButtonsRoot = $form_state->get(['pressedButtons', 'template_container', 'elements']);
            } else {
                $pressedButtonsRoot = NULL;
            }

            // Generate the form elements
            $formBuilder->setForm($profile, $inheritedData, $pressedButtonsRoot, $inheritAll = true);

            // Attach form elements to base form
            $elements = $formBuilder->getForm();
            $form['template_container']['elements'] = $elements;

            // check if everything worked as expected
            if (!is_array($form['template_container']['elements'])) {
                \Drupal::messenger()->addWarning('Unable to generate cmdi form based on profile');
            }
        } else {
            \Drupal::messenger()->addMessage('Online editing of the metadata of this collection is not supported');
            $form['Submit']['#disabled'] = true;
        }

        return $form;
    }

    public function flat_collection_update_form_ajax_handler(&$form, &$form_state)
    {
        module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiTemplate/class.CmdiValueSyncer');
        CmdiValueSyncer::sync($form, $form_state);

        return $form;
    }

    //public function flat_collection_update_form_final_validate($form, &$form_state)
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        // stop validation if errors have previously occurred
        if (form_get_errors()) {
            return $form;
        }

        // Validate that selected profile is not empty
        if ($form_state['values']['select_profile_name'] === '-- Select --') {
            $form_state->setErrorByName('select_profile_name', 'Please choose correct option');
            return $form;
        }

        // Validate if owner exists.
        $owner = $form_state['values']['owner'];
        if (!user_load_by_name($owner)) {
            $form_state->setErrorByName('owner', t('Specified owner is unknown'));
            return $form;
        };

        // preparation
        module_load_include('inc', 'flat_deposit', 'inc/class.FlatTuque');

        $fid = $form_state['values']['data']['fid'];
        $namespace = explode(':', $fid)[0];
        $fidParent = FlatTuque::getIsPartOfCollection($fid);

        //*******************//
        // Title Validations //
        //*******************//
        $new_title = $form_state['values']['template_container']['elements']['title_field'][0];

        // 1. Title uniqueness validation.
        // Gets fedoraObject->id of parent, uses this id to search all children (fObjects with isPartOfCollection attribute
        // set to parent), extract their fedoraObject->label(s) and checks whether one of these labels is the same as the title
        // for the current object.
        // Note. Only do this if title has actually changed.

        $old_title = $form['template_container']['elements']['title_field'][0]['#default_value'];

        if ($old_title !== $new_title) {
            $values = FlatTuque::getChildrenLabels($fidParent);

            if ($values === false) {
                $form_state->setErrorByName('title', t('Unable to validate that collection name is unique at this location'));
                return $form;
            }

            if (in_array(strtoupper($new_title), array_unique(array_map('strtoupper', $values)))) {
                $form_state->setErrorByName('title', t('Another collection or bundle with same name exists at this location. Please use a different name'));
                return $form;
            }
        }

        // 2. Validate that output directory for new cmdi exists or can be created
        $export_dir = 'metadata://' . str_replace('@', '_at_', $owner) . "/.collection/";
        if (!file_exists($export_dir)) {
            \Drupal::service("file_system")->mkdir($export_dir, null, true);
        }

        if (!file_exists($export_dir)) {
            $form_state->setErrorByName('error', t('Cannot create directory to temporarily store cmdi files'));
            return $form;
        }

        //*******************//
        // Generate Cmdi file//
        //*******************//

        $profile = $form_state['values']['select_profile_name'];
        $cmdiFile = $export_dir . '/' . $profile . '_' . uniqid() . '.cmdi';
        $form_state['values']['cmdiFile'] = $cmdiFile;

        module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiCreator/class.CmdiCreator');
        module_load_include('inc', 'flat_deposit', 'Helpers/CMDI/CmdiTemplate/class.CmdiValueExtractor');

        $templateName = $form_state['values']['select_profile_name'];
        $owner = $form_state['values']['owner'];
        $form_data = CmdiValueExtractor::extract($form_state);

        $creator = new CmdiCreator();

        try {
            $creator->setCmdi($templateName, $form_data, $owner);
            $generatedCmdi = $creator->getCmdi();
        } catch (CmdiCreatorException $e) {
            $form_state->setErrorByName('error', $e->getMessage());
            return $form;
        }

        // get original cmdi by datastream
        $ds = islandora_datastream_load('CMD', $fid);
        $originalCmdi = CmdiHandler::simplexml_load_cmdi_string($ds->content);
        // add resource Section to updated components
        $newlyGeneratedCmdi = CmdiHandler::simplexml_load_cmdi_string($generatedCmdi->asXML());
        $newlyGeneratedCmdi->addResourceSectionToComponents($originalCmdi);

        // merge original cmdi and and edited components section
        $path = '//cmd:Components';

        $targetComponentNode = $originalCmdi->xpath($path)[0];
        $sourceNode = $newlyGeneratedCmdi->xpath($path)[0];

        unset($targetComponentNode->children('cmd', true)->{$profile});

        $domTarget = dom_import_simplexml($targetComponentNode);

        $domSource = dom_import_simplexml($sourceNode->children('cmd', true)->{$profile});

        $domSource = $domTarget->ownerDocument->importNode($domSource, true);

        $domTarget->appendChild($domSource);

        //save result

        $exported = $originalCmdi->asXML($cmdiFile);

        if (!$exported) {
            $form_state->setErrorByName('error', 'Unable to save cmdi file');
            return $form;
        }

        //********************************//
        // Do ingest using the Doorkeeper //
        //********************************//
        $sipType = 'Collection';
        $test = false;

        module_load_include('php', 'flat_deposit', 'Helpers/IngestService/IngestClient');

        try {
            $ingest_client = new IngestClient($sipType, $owner, $cmdiFile, $fidParent, $test, $namespace);
        } catch (IngestServiceException $exception) {
            $form_state->setErrorByName('debug', $exception->getMessage());
            return $form;
        }

        $options = [];
        $options['policy'] = $form_state['values']['select_policy'];
        $options['content_type'] = 'flat_collection';
        $options['visibility'] = $form_state['values']['visibility'];

        $fid = $ingest_client->requestSipIngest($options);

        $fObject = islandora_object_load($fid);

        if (!$fObject) {
            $form_state->setErrorByName('error', t('Check of FID for new collection item did not reveal valid data. Error message:' . $fid));
            return $form;
        }

        $form_state['values']['data']['fid'] = (string) $fid;
        $form_state['values']['data']['label'] = $fObject->label;
        $form_state['values']['data']['owner'] = $fObject->owner;

        // Ingest succeeded, in case the title has changed we need to modify the title of any existing Drupal node for this collection ("My Collections" entries for all users)

        if ($old_title !== $new_title) {
            $query = new EntityQuery('node');
            $query->condition('type', 'flat_collection')
                ->condition('flat_fid.value', $fid, '=');

            $collection_nodes = $query->execute();

            foreach ($collection_nodes['node'] as $collection_node) {
                $nid = $collection_node->nid;
                $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
                $node->title = $new_title;
                $node->save();
            }
        }

        return $form;
    }

    /**
     * Updates flat_collection node and redirects to parent node.
     *
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $owner = user_load_by_name($form_state->getValue(['data', 'owner']));
        $uid = $owner->uid;
        $label = $form_state->getValue(['data', 'label']);
        $fid = $form_state->getValue(['data', 'fid']);
        //$target = 'islandora/object/' . $form_state->getValue(['data', 'fid']);
        //create_collection_node($label, $uid, $fid);

        \Drupal::messenger()->addMessage(t('Collection has been updated'));

        $response = new AjaxResponse();
        //$response->addCommand(new RedirectCommand($target));

        return $response;
    }
}
