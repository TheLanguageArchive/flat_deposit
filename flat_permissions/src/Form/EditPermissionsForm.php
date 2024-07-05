<?php

/**
 * @file
 * Contains \Drupal\flat_permissions\Form\EditPermissionsForm.
 *
 * Form to edit access permissions for a Repository Item
 */

namespace Drupal\flat_permissions\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

class EditPermissionsForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'flat_permissions_edit_permissions_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $user = \Drupal::currentUser();

    $node = \Drupal::routeMatch()->getParameter('node');

    $nid = $node->id();

    $content_type = $node->bundle();
    $model = NULL;
    if ($content_type === 'islandora_object') {
      $model = $node->get('field_model')->referencedEntities()[0]->getName();
    }

    $is_collection = ($model === 'Collection');

    $manager = \Drupal::service('flat_permissions.permissions_manager');

    $read_parent = NULL;
    $write_parent = NULL;

    // fetch access policies for the current node
    $read_policy = $manager->fetchAccessPolicy($nid, 'read');
    $write_policy = $manager->fetchAccessPolicy($nid, 'write');

    if ($read_policy) {
      // current node has a read access policy, we need to use it to set the default values of the form
      $defaults = $read_policy;
      $read_parent = NULL;
      $action = "Modify";
    } else {
      // current node has no read access policy, find the effective policy up the hierarchy for displaying it in the form
      $effective_read_policy = $manager->fetchEffectiveAccessPolicy($nid, 'read');
      if ($effective_read_policy) {
        $read_policy = $effective_read_policy['policy'];
        $read_parent = $effective_read_policy['nid'];
      }
      $defaults = NULL;
      $action = "Define";
    }

    if ($write_policy) {
      // current node has a write access policy, we need to use it to set the default values of the form
      $write_defaults = $write_policy;
      $write_action = "Modify";
    } else {
      // current node has no write access policy, find the effective policy up the hierarchy for displaying it in the form
      $effective_write_policy = $manager->fetchEffectiveAccessPolicy($nid, 'write');
      if ($effective_write_policy) {
        $write_policy = $effective_write_policy['policy'];
        $write_parent = $effective_write_policy['nid'];
      }
      $write_defaults = NULL;
      $write_action = "Define";
    }

    // add levels to policy and sort by effective role
    $read_policy = $read_policy ? $manager->addLevels($read_policy) : null;
    $read_policy = $read_policy ? $manager->sortByEffectiveRole($read_policy) : null;

    $user_input = $form_state->getUserInput();
    if (array_key_exists('radio', $user_input)) {
      $radio_value = $user_input['radio'];
    } elseif ($defaults) {
      $radio_value = array_keys((array)$defaults)[0];
    } else {
      $radio_value = NULL;
    }

    // Using Drupal tempstore to store the number of fieldsets
    $tempstore = \Drupal::service('tempstore.private');

    $store = $tempstore->get('flat_permissions_collection');

    // If tempstore value is not yet set or if the form is reloaded other than through an AJAX call, we set it to 0
    $request = \Drupal::request();
    $is_ajax = $request->isXmlHttpRequest();

    $types_fieldsets_indexes = $store->get('types_fieldsets_indexes');

    if (!$types_fieldsets_indexes || !$is_ajax) {
      if ($manager->objectAndPropertiesExist($defaults, 'types')) {
        $num_fieldsets = count($defaults->types);
        $types_fieldsets_indexes = range(0, ($num_fieldsets - 1));
      } else {
        $types_fieldsets_indexes = [0];
      }
      $store->set('types_fieldsets_indexes', $types_fieldsets_indexes);
    }

    $form_state->set('types_fieldsets_indexes', $types_fieldsets_indexes);

    $form['#cache'] = ['max-age' => 0];

    $form['#tree'] = true;

    // Create an info message for the effective access rules, either inherited from the parents or defined on the current item
    $info_message =  'The nearest access rules that are defined in the collection hierarchy and that are applicable to this item.';

    $suffix = '/permissions';

    if ($read_parent) {
      $read_parent_node = \Drupal::entityTypeManager()->getStorage('node')->load($read_parent);
      if ($node instanceof \Drupal\node\NodeInterface) {
        $read_parent_name = $read_parent_node->getTitle();
        $read_parent_url = Url::fromRoute('entity.node.canonical', ['node' => $read_parent]);
        $read_parent_path = $read_parent_url->getInternalPath() . $suffix;
        $read_parent_url = Url::fromUserInput('/' . $read_parent_path);
        $read_parent_link = Link::fromTextAndUrl($read_parent_name, $read_parent_url)->toString();
        $info_message .= '<br/>Read access is defined on ' . $read_parent_link . '.';
      }
    } elseif ($read_policy) {
      $info_message .= '<br/>Read access is defined on the current item.';
    }

    if ($write_parent) {
      $write_parent_node = \Drupal::entityTypeManager()->getStorage('node')->load($write_parent);
      if ($node instanceof \Drupal\node\NodeInterface) {
        $write_parent_name = $write_parent_node->getTitle();
        $write_parent_url = Url::fromRoute('entity.node.canonical', ['node' => $write_parent]);
        $write_parent_path = $write_parent_url->getInternalPath() . $suffix;
        $write_parent_url = Url::fromUserInput('/' . $write_parent_path);
        $write_parent_link = Link::fromTextAndUrl($write_parent_name, $write_parent_url)->toString();
        $info_message .= '<br/>Write access is defined on ' . $write_parent_link . '.';
      }
    } elseif ($write_policy) {
      $info_message .= '<br/>Write access is defined on the current item.';
    }

    // the effective access rules from higher up in the hierarch will be displayed in this info section, if any.
    // rendering is done with the flat-permissions-policy twig template
    $form['info'] = [
      '#type' => 'container',
      '#title' => 'Effective Access Rules',
      '#prefix' => '<h2>Effective Access Rules</h2>
      <p>' . $info_message . '</p>',
    ];

    $form['info']['read'] = [
      '#type' => 'html_tag',
      '#title' => 'Effective Read Access Rules',
      '#tag' => 'div',
      '#theme' => 'flat_permissions_read_policy',
      '#attributes' => [
        'class' => ['effective-rules'],
      ],
      '#data' => $read_policy,
    ];

    $form['info']['write'] = [
      '#type' => 'html_tag',
      '#title' => 'Effective Write Access Rules',
      '#tag' => 'div',
      '#theme' => 'flat_permissions_write_policy',
      '#attributes' => [
        'class' => ['effective-rules'],
      ],
      '#data' => $write_policy,
    ];

    $form['rules'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<h2>' . $action . ' Read Access</h2>
      <p>' . $action . ' read rules that will apply to this item and anything below it. Defined read rules here will override any read rule in the parent collection(s). Read rules defined anywhere lower in the hiearchy will take precedence over read rules defined here.</p>',
    ];

    // Read rules that apply to all files
    $form['rules']['all'] = [
      '#type' => 'fieldset',
      '#tree' => true,
      '#attributes' => [
        'class' => ['rule-type-wrapper'],
      ],
    ];

    $form['rules']['all']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule for all files',
      '#return_value' => 'all',
      '#name' => 'radio',
      '#default_value' => $radio_value === 'all' ? 'all' : NULL,
    ];

    $form['rules']['all']['all_fieldset'] = [
      '#type' => 'fieldset',
      '#tree' => true,
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'all'],
        ],
      ],
    ];

    $levels = $manager::LEVELS;

    $form['rules']['all']['all_fieldset']['level'] = $this->build_levels('1', 'all', $levels, $form_state, $defaults);

    $form['rules']['all']['all_fieldset']['users'] = [
      '#type' => 'textfield',
      '#title' => t('Specific users'),
      '#states' => [
        'visible' => [
          [':input[name="all_level_1"]' => ['value' => 'none']],
          [':input[name="all_level_1"]' => ['value' => 'academic']],
        ],
      ],
      '#attributes' => [
        'class' => ['multi-autocomplete'],
        'data-autocomplete-url' => 'permissions/autocomplete/users',
        'data-hidden-input-name' => 'hidden_all_users_field_all',
      ],
    ];

    if (isset($user_input['rules']['all']['all_fieldset']['visibility'])) {
      $default_visibility_value = isset($user_input['rules']['all']['all_fieldset']['visibility']);
    } elseif ($manager->objectAndPropertiesExist($defaults, 'all')) {
      $default_visibility_value = property_exists($defaults->all, 'visible') ? isset($defaults->all->visible) : 0;
    } else {
      $default_visibility_value = 0;
    }

    $form['rules']['all']['all_fieldset']['visibility'] = [
      '#type' => 'checkbox',
      '#title' => t('Invisible'),
      '#states' => [
        'visible' => [
          [':input[name="all_level_1"]' => ['value' => 'none']],
        ],
        '#default_value' => $default_visibility_value,
      ],
    ];

    $form['rules']['all']['all_fieldset']['hidden-users'] = $this->build_hidden_field('all', 'all', 'users', $manager, $form_state, $defaults);

    // Read rules that apply to specific file and/or types
    $form['rules']['types'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => ['rule-type-wrapper'],
      ],
    ];

    $form['rules']['types']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule(s) for specific file and/or MIME types',
      '#return_value' => 'types',
      '#name' => 'radio',
      '#default_value' => $radio_value === 'types' ? 'types' : NULL,
    ];

    $form['rules']['types']['types_fieldset'] = [
      '#type' => 'container',
      '#tree' => true,
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'types'],
        ],
      ],
    ];

    $form['rules']['types']['types_fieldset']['fieldsets'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<div id="types-fieldsets-wrapper" class="rules-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'types'],
        ],
      ],
    ];

    foreach ($types_fieldsets_indexes as $i) {
      // we only need a "remove" button if there's more than one fieldset
      if (sizeof($types_fieldsets_indexes) == 1) {
        $remove = false;
      } else {
        $remove = true;
      }
      $form['rules']['types']['types_fieldset']['fieldsets'][$i] = $this->build_types_fieldset($i, $remove, $manager, $form_state, $defaults);
    }

    $form['rules']['types']['types_fieldset']['add_more'] = [
      '#id' => 'types-fieldset-add',
      '#name' => 'types_fieldset_add',
      '#type' => 'submit',
      '#title' => t('Add rule'),
      '#value' => t('Add rule'),
      '#attributes' => [
        'class' => ['add-button'],
      ],
      '#ajax' => [
        'callback' => '::typesCallback',
        'wrapper' => 'types-fieldsets-wrapper',
      ],
      '#submit' => ['::addTypesFieldset'],
      '#limit_validation_errors' => [],
    ];

    if (!$is_collection) {

      // not a collection, should have media and files (in theory) so we need to create the fields for rules that apply to specific files
      $files_fieldsets_indexes = $store->get('files_fieldsets_indexes');

      if (!$files_fieldsets_indexes || !$is_ajax) {
        $store->set('files_fieldsets_indexes', [1]);
        $files_fieldsets_indexes = [1];
      }

      $form_state->set('files_fieldsets_indexes', $files_fieldsets_indexes);

      $media = $manager->getMediaEntitiesByNodeId($nid);

      $options = [];

      foreach ($media as $key => $m) {
        $name = $m->get('name')->value;
        $options[$key] = ['filename' => $name];
      }

      asort($options);

      $form['rules']['files'] = [
        '#type' => 'fieldset',
        '#attributes' => [
          'class' => 'rule-type-wrapper',
        ],
      ];

      $form['rules']['files']['radio'] = [
        '#type' => 'radio',
        '#title' => 'Rule(s) for specific files',
        '#return_value' => 'files',
        '#name' => 'radio',
        '#default_value' => $radio_value === 'files' ? 'files' : NULL,
      ];

      $form['rules']['files']['files_fieldset'] = [
        '#type' => 'container',
        '#tree' => true,
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'files'],
          ],
        ],
      ];

      $form['rules']['files']['files_fieldset']['fieldsets'] = [
        '#type' => 'container',
        '#tree' => true,
        '#prefix' => '<div id="files-fieldsets-wrapper" class="rules-wrapper">',
        '#suffix' => '</div>',
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'files'],
          ],
        ],
      ];

      foreach ($files_fieldsets_indexes as $i) {
        // we only need a "remove" button if there's more than one fieldset
        if (sizeof($files_fieldsets_indexes) == 1) {
          $remove = false;
        } else {
          $remove = true;
        }
        $form['rules']['files']['files_fieldset']['fieldsets'][$i] = $this->build_files_fieldset($i, $remove, $options, $manager, $form_state, $defaults);
      }

      $form['rules']['files']['files_fieldset']['add_more'] = [
        '#id' => 'files-fieldset-add',
        '#name' => 'files_fieldset_add',
        '#type' => 'submit',
        '#title' => t('Add rule'),
        '#value' => t('Add rule'),
        '#attributes' => [
          'class' => ['add-button'],
        ],
        '#ajax' => [
          'callback' => '::filesCallback',
          'wrapper' => 'files-fieldsets-wrapper',
        ],
        '#submit' => ['::addFilesFieldset'],
        '#limit_validation_errors' => [],
      ];
    }

    $form['rules']['submit_read'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Read Rules'),
      '#submit' => ['::submitReadPolicy'],
    ];

    $form['rules']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Read Rules'),
      '#submit' => ['::deleteReadPolicy'],
      '#disabled' => $defaults ? false : true,
      '#attributes' => [
        'class' => ['btn-danger'],
        'onclick' => 'if(!confirm("Really delete the read rules?")){return false;}',
      ],
    ];

    $form['write'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<h2>' . $write_action . ' Write Access</h2>
      <p>' . $write_action . ' which users have write access to this item and anything below it. Defined write rules here will override any write rule in the parent collection(s). Write rules defined anywhere lower in the hiearchy will take precedence over write rules defined here.</p>',
    ];

    $form['write']['users'] = [
      '#type' => 'textfield',
      '#title' => t('Users'),
      '#attributes' => [
        'class' => ['multi-autocomplete'],
        'data-autocomplete-url' => 'permissions/autocomplete/users',
        'data-hidden-input-name' => 'hidden_write_users_field_write',
      ],
    ];

    $form['write']['hidden-users'] = $this->build_hidden_field('write', 'write', 'users', $manager, $form_state, $write_defaults);

    $form['write']['submit_write'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Write Rule'),
      '#submit' => ['::submitWritePolicy'],
    ];

    $form['write']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Write Rule'),
      '#submit' => ['::deleteWritePolicy'],
      '#disabled' => $write_defaults ? false : true,
      '#attributes' => [
        'class' => ['btn-danger'],
        'onclick' => 'if(!confirm("Really delete the write rule?")){return false;}',
      ],
    ];

    $form['#attached']['library'][] = 'flat_permissions/multivalue_autocomplete';
    $form['#attached']['library'][] = 'flat_permissions/flat_permissions';

    return $form;
  }

  /**
   * Ajax callback for updating the mime fieldsets after adding or removing a fieldset
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @return array The mime fieldsets element from the given form array.
   */
  public function typesCallback(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'types_fieldset')) {
      return $form['rules']['types']['types_fieldset']['fieldsets'];
    }
  }

  /**
   * Ajax callback for updating the mime fieldsets after adding or removing a fieldset
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @return array The mime fieldsets element from the given form array.
   */
  public function filesCallback(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'files_fieldset')) {
      return $form['rules']['files']['files_fieldset']['fieldsets'];
    }
  }

  /**
   * Adds a new value to the "indexes", thereby creating a new fieldset in the form and stores the new indexes in the temporary store.
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   *
   * @return void
   */
  public function addTypesFieldset(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'types_fieldset_add')) {
      $form_state->setRebuild();
      $tempstore = \Drupal::service('tempstore.private');
      $store = $tempstore->get('flat_permissions_collection');
      $indexes = $form_state->get('types_fieldsets_indexes');
      $next_index = max($indexes) + 1;
      $indexes[] = $next_index;
      $store->set('types_fieldsets_indexes', $indexes);
    }
  }

  /**
   * Adds a new value to the "indexes", thereby creating a new fieldset in the form and stores the new indexes in the temporary store.
   *
   * @param array &$form The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   *
   * @return void
   */
  public function addFilesFieldset(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'files_fieldset_add')) {
      $form_state->setRebuild();
      $tempstore = \Drupal::service('tempstore.private');
      $store = $tempstore->get('flat_permissions_collection');
      $indexes = $form_state->get('files_fieldsets_indexes');
      $next_index = max($indexes) + 1;
      $indexes[] = $next_index;
      $store->set('files_fieldsets_indexes', $indexes);
    }
  }

  /**
   * Removes a fieldset from the form based on the triggering element.
   *
   * @param array &$form The form array.
   * @param FormStateInterface $form_state The current state of the form.
   * @return void
   */
  public function removeTypesFieldset(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'types_fieldset_remove')) {
      // getting the index of the fieldset to remove from the triggering element
      $pattern = "/types_fieldset_remove_(\d+)/";
      $match = preg_match($pattern, $triggeringElementName, $matches);
      if ($match) {
        $index = $matches[1];
      } else {
        return;
      }
      $tempstore = \Drupal::service('tempstore.private');
      $store = $tempstore->get('flat_permissions_collection');
      $indexes = $form_state->get('types_fieldsets_indexes');
      if (($key = array_search($index, $indexes)) !== false) {
        unset($indexes[$key]);
      }
      $store->set('types_fieldsets_indexes', $indexes);
      $form_state->setRebuild();
    }
  }

  /**
   * Removes a fieldset from the form based on the triggering element.
   *
   * @param array &$form The form array.
   * @param FormStateInterface $form_state The current state of the form.
   * @return void
   */
  public function removeFilesFieldset(array &$form, FormStateInterface $form_state)
  {
    $triggeringElement = $form_state->getTriggeringElement();
    $triggeringElementName = $triggeringElement['#name'];
    if (str_starts_with($triggeringElementName, 'files_fieldset_remove')) {
      // getting the index of the fieldset to remove from the triggering element
      $pattern = "/files_fieldset_remove_(\d+)/";
      $match = preg_match($pattern, $triggeringElementName, $matches);
      if ($match) {
        $index = $matches[1];
      } else {
        return;
      }
      $tempstore = \Drupal::service('tempstore.private');
      $store = $tempstore->get('flat_permissions_collection');
      $indexes = $form_state->get('files_fieldsets_indexes');
      if (($key = array_search($index, $indexes)) !== false) {
        unset($indexes[$key]);
      }
      $store->set('files_fieldsets_indexes', $indexes);
      $form_state->setRebuild();
    }
  }

  /**
   * Builds a fieldset for adding file and/or mime type rules.
   *
   * @param int $i The index of the fieldset.
   * @param bool $remove Whether to include a remove button.
   * @param object $manager The manager object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @param mixed $defaults The default values for the fieldset.
   * @return array The built fieldset.
   */
  public function build_types_fieldset($i, $remove, $manager, FormStateInterface $form_state, $defaults)
  {

    // if the fieldset is added via the add button, the defaults should be empty rather than using any previous value from a removed fieldset
    $triggeringElement = $form_state->getTriggeringElement();
    if (isset($triggeringElement)) {
      $triggeringElementName = $triggeringElement['#name'];
      if (str_starts_with($triggeringElementName, 'types_fieldset_add')) {
        $defaults = NULL;
      }
    }

    // Determine the default values, either from the form_state user input, or from the stored json permissions (passed via $defaults).
    $levels = $manager::LEVELS;

    $user_input = $form_state->getUserInput();
    if (isset($user_input['types_level_' . $i])) {
      $default_level_value = $user_input['types_level_' . $i];
    } elseif ($manager->objectAndPropertiesExist($defaults, 'types')) {
      if (array_key_exists(($i), $defaults->types)) {
        $default_level_value = array_search($defaults->types[$i]->level, $manager::LEVELS);
      } else {
        $default_level_value = '';
      }
    } else {
      $default_level_value = '';
    }

    if (isset($user_input['types_filetypes_' . $i])) {
      $default_filetypes_value = $user_input['types_filetypes_' . $i];
    } elseif ($manager->objectAndPropertiesExist($defaults, 'types')) {
      if (array_key_exists(($i), $defaults->types)) {
        $default_filetypes_value = property_exists($defaults->types[$i], 'filetypes') ? $defaults->types[$i]->filetypes : [];
      } else {
        $default_filetypes_value = [];
      }
    } else {
      $default_filetypes_value = [];
    }

    if (isset($user_input['rules']['types']['types_fieldset']['fieldsets'][$i]['visibility'])) {
      $default_visibility_value = isset($user_input['rules']['types']['types_fieldset']['fieldsets'][$i]['visibility']);
    } elseif ($manager->objectAndPropertiesExist($defaults, 'types')) {
      if (array_key_exists(($i), $defaults->types)) {
        $default_visibility_value = property_exists($defaults->types[$i], 'visible') ? isset($defaults->types[$i]->visible) : 0;
      } else {
        $default_visibility_value = 0;
      }
    } else {
      $default_visibility_value = 0;
    }

    $fieldset = [

      '#tree'        => true,
      '#type'        => 'fieldset',
      'level'        =>  [
        '#type' => 'select',
        '#title' => t('Access level'),
        '#options' => $levels,
        '#default_value' => $default_level_value,
        '#name' => 'types_level_' . $i,
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'types'],
          ],
        ],
      ],
      'users'        => [
        '#type' => 'textfield',
        '#title' => t('Specific users'),
        '#states' => [
          'visible' => [
            [':input[name="types_level_' . $i . '"]' => ['value' => 'none']],
            [':input[name="types_level_' . $i . '"]' => ['value' => 'academic']],
          ],
        ],
        '#attributes' => [
          'class' => ['multi-autocomplete'],
          'data-autocomplete-url' => 'permissions/autocomplete/users',
          'data-hidden-input-name' => 'hidden_types_users_field_' . $i,
        ],
      ],
      'visibility' => [
        '#type' => 'checkbox',
        '#title' => t('Invisible'),
        '#states' => [
          'visible' => [
            [':input[name="types_level_' . $i . '"]' => ['value' => 'none']],
          ],
        ],
        '#default_value' => $default_visibility_value,
      ],
      'filetypes'        => [
        '#type' => 'checkboxes',
        '#title' => t('File type(s)'),
        '#prefix' => '<span class="label">' . t('File type(s)') . '</span>',
        '#options' => $manager::TYPES,
        '#default_value' => $default_filetypes_value,
      ],
      'mimetypes'        => [
        '#type' => 'textfield',
        '#title' => t('MIME type(s)'),
        '#attributes' => [
          'class' => ['multi-autocomplete'],
          'data-autocomplete-url' => 'permissions/autocomplete/mime_type',
          'data-hidden-input-name' => 'hidden_types_mimetypes_field_' . $i,
        ],
      ],
      'hidden-mimes'       => $this->build_hidden_field($i, 'types', 'mimetypes', $manager, $form_state, $defaults),
      'hidden-users'       => $this->build_hidden_field($i, 'types', 'users', $manager, $form_state, $defaults),
      '#attributes' => [
        'class' => 'rule-wrapper',
      ],
    ];

    if ($remove) {
      $fieldset['remove'] = $this->build_types_remove($i);
    }

    return $fieldset;
  }

  /**
   * Builds a fieldset for adding file rules.
   *
   * @param int $i The index of the fieldset.
   * @param bool $remove Whether to include a remove button.
   * @param array $options The options for the files fieldset.
   * @param object $manager The manager object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @param mixed $defaults The default values for the fieldset.
   * @return array The fieldset array.
   */
  public function build_files_fieldset($i, $remove, $options, $manager, FormStateInterface $form_state, $defaults)
  {

    // if the fieldset is added via the add button, the defaults should be empty rather than any previous value from a removed fieldset
    $triggeringElement = $form_state->getTriggeringElement();
    if (isset($triggeringElement)) {
      $triggeringElementName = $triggeringElement['#name'];
      if (str_starts_with($triggeringElementName, 'types_fieldset_add')) {
        $defaults = NULL;
      }
    }

    $levels = $manager::LEVELS;

    // Determine the default values, either from the form_state user input, or from the stored json permissions (passed via $defaults).
    $user_input = $form_state->getUserInput();
    if (isset($user_input['types_level_' . $i])) {
      $default_level_value = $user_input['files_level_' . $i];
    } else {
      if ($manager->objectAndPropertiesExist($defaults, 'files')) {
        $default_level_value = array_search($defaults->files[$i]->level, $manager::LEVELS);
      } else {
        $default_level_value = '';
      }
    }

    if (isset($user_input['rules']['files']['files_fieldset']['fieldsets'][$i]['visibility'])) {
      $default_visibility_value = isset($user_input['rules']['files']['files_fieldset']['fieldsets'][$i]['visibility']);
    } elseif ($manager->objectAndPropertiesExist($defaults, 'files')) {
      if (array_key_exists(($i), $defaults->files)) {
        $default_visibility_value = property_exists($defaults->files[$i], 'visible') ? isset($defaults->files[$i]->visible) : 0;
      } else {
        $default_visibility_value = 0;
      }
    } else {
      $default_visibility_value = 0;
    }

    $fieldset = [

      '#tree'        => true,
      '#type'        => 'fieldset',
      'level'        =>  [
        '#type' => 'select',
        '#title' => t('Access level'),
        '#options' => $levels,
        '#default_value' => $default_level_value,
        '#name' => 'files_level_' . $i,
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'files'],
          ],
        ],
      ],
      'users' => [
        '#type' => 'textfield',
        '#title' => t('Specific users'),
        '#states' => [
          'visible' => [
            [':input[name="files_level_' . $i . '"]' => ['value' => 'none']],
            [':input[name="files_level_' . $i . '"]' => ['value' => 'academic']],
          ],
        ],
        '#attributes' => [
          'class' => ['multi-autocomplete'],
          'data-autocomplete-url' => 'permissions/autocomplete/users',
          'data-hidden-input-name' => 'hidden_files_users_field_' . $i,
        ],
      ],
      'visibility' => [
        '#type' => 'checkbox',
        '#title' => t('Invisible'),
        '#states' => [
          'visible' => [
            [':input[name="files_level_' . $i . '"]' => ['value' => 'none']],
          ],
        ],
        '#default_value' => $default_visibility_value,
      ],
      'files' => [
        '#type' => 'tableselect',
        '#header' => ['filename' => t('Filename')],
        '#title' => t('Files'),
        '#id' => 'select_files_' . $i,
        '#options' => $options,
        '#name' => 'files_level',
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'files'],
          ],
        ],
      ],

      'hidden-users'       => $this->build_hidden_field($i, 'files', 'users', $manager, $form_state, $defaults),
      '#attributes' => [
        'class' => 'rule-wrapper',
      ]
    ];

    if ($remove) {
      $fieldset['remove'] = $this->build_files_remove($i, 'files');
    }

    return $fieldset;
  }

  /**
   * Builds a hidden field that will contain the selected mime type or user values.
   *
   * @param int $i The index of the field.
   * @param \Drupal\Core\Form\FormStateInterface $form_state The form state object.
   * @return array The hidden field array.
   */
  public function build_hidden_field($index, $rule_type, $field_type, $manager, FormStateInterface $form_state, $defaults)
  {
    $manager = \Drupal::service('flat_permissions.permissions_manager');

    $user_input = $form_state->getUserInput();
    // Determine the default value, either from the form_state user input, or from the stored json permissions (passed via $defaults).
    if (isset($user_input['hidden_' . $rule_type . '_' . $field_type . '_field_' . $index])) {
      $default_value = $user_input['hidden_' . $rule_type . '_' . $field_type . '_field_' . $index];
    } elseif ($manager->objectAndPropertiesExist($defaults, "{$rule_type}->users")) {
      $default_value = implode(',', $defaults->{$rule_type}->users);
    } elseif ($manager->objectAndPropertiesExist($defaults, "users")) {
      // Write rule only has users
      $default_value = implode(',', $defaults->users);
    } elseif ($manager->objectAndPropertiesExist($defaults, $rule_type)) {
      if (is_array($defaults->{$rule_type})) {
        // "type" and "file" rules are in an array as there can be more than one
        if (array_key_exists(($index), $defaults->{$rule_type})) {
          $default_value = property_exists($defaults->{$rule_type}[$index], $field_type) ? implode(',', $defaults->{$rule_type}[$index]->{$field_type}) : '';
        } else {
          $default_value = '';
        }
      } else {
        // "all" rule is only in a single object
        $default_value = property_exists($defaults->{$rule_type}, $field_type) ? implode(',', $defaults->{$rule_type}->{$field_type}) : '';
      }
    } else {
      $default_value = '';
    }

    $field = [
      '#type' => 'hidden',
      '#default_value' => $default_value,
      '#attributes' => [
        'class' => ['hidden-multi-autocomplete'],
        'name' => 'hidden_' . $rule_type . '_' . $field_type . '_field_' . $index,
      ],
    ];

    return $field;
  }

  /**
   * Builds a select element for the access level
   *
   * @param array $levels The array of levels to be used as options.
   * @return array The select element for the access level.
   */
  public function build_levels($index, $rule_type, $levels, FormStateInterface $form_state, $defaults)
  {

    $manager = \Drupal::service('flat_permissions.permissions_manager');

    $user_input = $form_state->getUserInput();
    if (isset($user_input[$rule_type . '_level_' . $index])) {
      $default_value = $user_input[$rule_type . '_level_' . $index];
    } elseif ($manager->objectAndPropertiesExist($defaults, $rule_type)) {
      if (is_array($defaults->{$rule_type})) {
        if (array_key_exists(($index), $defaults->types)) {
          $default_value = array_search($defaults->types[$index]->level, $levels);
        } else {
          $default_value = '';
        }
      } else {
        $default_value = array_search($defaults->{$rule_type}->level, $levels);
      }
    } else {
      $default_value = '';
    }
    $level_element = [
      '#type' => 'select',
      '#title' => t('Access level'),
      '#options' => $levels,
      '#default_value' => $default_value,
      '#name' => $rule_type . '_level_' . $index,
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => $rule_type],
        ],
      ],
    ];
    return $level_element;
  }

  /**
   * Builds a remove element for the mimes fieldset.
   *
   * @param int $index The index of the rule to be removed.
   * @return array The remove element with the specified index.
   */
  public function build_types_remove($index)
  {

    $remove_element = [
      '#type' => 'submit',
      '#value' => t('Remove rule'),
      '#name' => 'types_fieldset_remove_' . $index,
      '#ajax' => [
        'callback' => '::typesCallback',
        'wrapper' => 'types-fieldsets-wrapper',
      ],
      '#attributes' => [
        'class' => ['remove-button btn-danger'],
      ],
      '#submit' => ['::removeTypesFieldset'],
      '#limit_validation_errors' => [],
    ];
    return $remove_element;
  }

  /**
   * Builds a remove element for the files fieldset.
   *
   * @param int $index The index of the rule to be removed.
   * @return array The remove element with the specified index.
   */
  public function build_files_remove($index)
  {

    $remove_element = [
      '#type' => 'submit',
      '#value' => t('Remove rule'),
      '#name' => 'files_fieldset_remove_' . $index,
      '#ajax' => [
        'callback' => '::filesCallback',
        'wrapper' => 'files-fieldsets-wrapper',
      ],
      '#attributes' => [
        'class' => ['remove-button btn-danger'],
      ],
      '#submit' => ['::removeFilesFieldset'],
      '#limit_validation_errors' => [],
    ];
    return $remove_element;
  }

  /**
   * Form validation to check whether access rules are valid
   *  - no empty rules
   *  - no rules with the same access level "anonymous" or "registered users"
   *  - in case of multiple rules with "academic" or "restricted" access level, the set of entered users must not be identical
   *  - no duplicate files, filetypes and mimetypes
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    $user_input = $form_state->getUserInput();
    $errors = [];

    if (in_array('submit_read', $parents)) {
      // validate read rules
      $manager = \Drupal::service('flat_permissions.permissions_manager');
      $level_options = $manager::LEVELS;
      $rules = $form_state->getValue(['rules']);
      // for some reason, form_state values are not present for some of the dynamically added fields,
      // whereas the user input array does have them, so we'll get them from there.
      if (array_key_exists('radio', $user_input)) {
        $rule_type = $user_input['radio'];
        if ($rule_type === 'types') {
          // check for empty rules and duplicate filetype and mimetype values
          $types_rules = $rules['types']['types_fieldset']['fieldsets'];
          foreach ($types_rules as $key => $type_rule) {
            $filetypes = $type_rule['filetypes'];
            $empty_checkboxes = !$manager->checkboxesAreChecked($filetypes);
            $type_options = $form['rules']['types']['types_fieldset']['fieldsets'][$key]['filetypes']['#options'];
            $types_rules_filetypes[] = $filetypes;
            $hidden_mimetypes = $user_input['hidden_types_mimetypes_field_' . $key];
            if ($empty_checkboxes && empty($hidden_mimetypes)) {
              $errors[] = 'You have created a rule with no file or MIME types selected.';
            }
            $hidden_mime_types_array = explode(',', $hidden_mimetypes);
            $types_rules_mimetypes[] = $hidden_mime_types_array;
            $rule_levels[] = $user_input['types_level_' . $key];
            $rule_users[] = $user_input['hidden_types_users_field_' . $key];
          }
          $type_duplicates = $manager->findDuplicateValuesInMultipleArrays($types_rules_filetypes);
          foreach ($type_duplicates as $key => $value) {
            $type_duplicate_names[] = $type_options[$value];
          }
          $mime_duplicates = $manager->findDuplicateValuesInMultipleArrays($types_rules_mimetypes);
          if (!empty($type_duplicates)) {
            $errors[] = 'You have used the same file type(s) in more than one rule: ' . implode(', ', $type_duplicate_names);
          }
          if (!empty($mime_duplicates)) {
            $errors[] = 'You have used the same MIME type(s) in more than one rule: ' . implode(', ', $mime_duplicates);
          }
          $mime_and_type_overlaps = [];
          foreach ($types_rules_mimetypes as $type_rule_mimetypes) {
            $overlap = $manager->findMimeAndTypeOverlaps($type_rule_mimetypes, $types_rules_filetypes);
            if (!empty($overlap)) {
              $mime_and_type_overlaps[] = $overlap;
            }
          }
          if (!empty($mime_and_type_overlaps)) {
            $result = '';
            foreach ($mime_and_type_overlaps as $mime_and_type_overlap) {
              foreach ($mime_and_type_overlap as $key => $value) {
                $result .= $key . ': ' . implode(', ', $value) . '. ';
              }
            }
            $errors[] = 'You have selected file type(s) that overlap with selected MIME type(s): ' . $result . 'Please use either the file type(s) or the MIME type(s).';
          }
        }


        if ($rule_type === 'files') {
          // check for empty rules and duplicate file values
          $files_rules = $rules['files']['files_fieldset']['fieldsets'];
          foreach ($files_rules as $key => $file_rule) {
            $files = $file_rule['files'];
            $empty_checkboxes = !$manager->checkboxesAreChecked($files);
            if ($empty_checkboxes) {
              $errors[] = 'You have created a rule for specific files with no files selected';
            }
            $file_options = $form['rules']['files']['files_fieldset']['fieldsets'][$key]['files']['#options'];
            $files_rules_files[] = $files;
            $rule_levels[] = $user_input['files_level_' . $key];
            $rule_users[] = $user_input['hidden_files_users_field_' . $key];
          }
          $file_duplicates = $manager->findDuplicateValuesInMultipleArrays($files_rules_files);
          foreach ($file_duplicates as $key => $value) {
            $file_duplicate_names[] = $file_options[$value]['filename'];
          }
          if (!empty($file_duplicate_names)) {
            $errors[] = 'You have used the same file(s) in more than one rule: ' . implode(', ', $file_duplicate_names);
          }
        }

        if ($rule_type !== 'all') {
          // check whether there are multiple rules with the same access level.
          // in case of academic or restricted (none) access, that is OK as long as the users are distinct
          $level_duplicates = $manager->findValuesOccurringMoreThanOnce($rule_levels);
          if (!empty($level_duplicates)) {
            $no_users_duplicate_levels = array_intersect(array_values($level_duplicates), ['anonymous', 'authenticated']);
            if (!empty($no_users_duplicate_levels)) {
              foreach ($no_users_duplicate_levels as $key => $value) {
                $no_users_duplicate_level_names[] = $level_options[$value];
              }
              $errors[] = 'You have used these access level(s) for more than one rule: ' . implode(', ', $no_users_duplicate_level_names) . '.
            Please create a single rule for them.';
            }
            $users_duplicate_levels = array_intersect(array_values($level_duplicates), ['academic', 'none']);
            if (!empty($users_duplicate_levels)) {
              $unique_users = array_unique($rule_users);
              if (sizeof($unique_users) === 1) {
                foreach ($users_duplicate_levels as $key => $value) {
                  $users_duplicate_level_names[] = $level_options[$value];
                }
                $errors[] = 'You have used the the same access level(s) in more than one rule with (some of) the same users: ' . implode(', ', $users_duplicate_level_names) . '.
              Please create a single rule for these levels or make sure that the users are distinct for each rule.';
              }
            }
          }
        }
      } else {
        $errors[] = 'You have not created a Read Access Rule.';
      }
    }
    if (in_array('submit_write', $parents)) {
      // validate write rule
      $write_users = $user_input['hidden_write_users_field_write'];
      if (empty($write_users)) {
        // no write users defined and no read rules defined
        $errors[] = 'The Write Access rule contains no users.';
      }
    }
    if (!empty($errors)) {
      $errors = [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#attributes' => ['class' => 'mylist'],
        '#items' => $errors,
        '#prefix' => '<h5>There are some conflicts in your Read Access rules:</h5>',
      ];
      $form_state->setErrorByName('', $errors);
      return $form;
    }
  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * Form submit handler that transforms the form data into a json structure, which is saved in the Access Policy
   * field of the node
   *
   * @param array &$form The form array.
   * @param FormStateInterface $form_state The form state object.
   * @throws None
   * @return void
   */
  public function submitReadPolicy(array &$form, FormStateInterface $form_state)
  {
    $manager = \Drupal::service('flat_permissions.permissions_manager');

    $nid = \Drupal::routeMatch()->getParameter('node')->id();

    $read_rule_output = [];
    $rules = $form_state->getValue(['rules']);
    // for some reason, form_state values are not present for some of the dynamically added fields,
    // whereas the user input array does have them, so we'll get them from there.
    $user_input = $form_state->getUserInput();
    if (array_key_exists('radio', $user_input)) {
      $rule_type = $user_input['radio'];
      if ($rule_type === 'all') {
        $level = $user_input['all_level_1'];
        $all_rule_output['level'] = $level;
        $hidden_all_users = $user_input['hidden_all_users_field_all'];
        if (!empty($hidden_all_users)) {
          $all_rule_output['hidden-users'] = explode(',', $hidden_all_users);
        } else {
          unset($all_rule_output['hidden-users']);
        }
        $visibility = $rules['all']['all_fieldset']['visibility'] ? FALSE : TRUE;
        $all_rule_output['visible'] = $visibility;
        $read_rule = $manager->fieldsetToRule($all_rule_output);
        $read_rule_output['all'] = $read_rule;
      }
      if ($rule_type === 'types') {
        $types_rules = $rules['types']['types_fieldset']['fieldsets'];
        foreach ($types_rules as $key => $types_rule) {
          $level = $user_input['types_level_' . $key];
          $types_rule_output['level'] = $level;
          $filetypes = $types_rule['filetypes'];
          $empty_checkboxes = !$manager->checkboxesAreChecked($filetypes);
          if (!$empty_checkboxes) {
            $types_rule_output['filetypes'] = $filetypes;
          } else {
            unset($types_rule_output['filetypes']);
          }
          $hidden_types_mimetypes = $user_input['hidden_types_mimetypes_field_' . $key];
          if (!empty($hidden_types_mimetypes)) {
            $types_rule_output['hidden-mimetypes'] = explode(',', $hidden_types_mimetypes);
          } else {
            unset($types_rule_output['hidden-mimetypes']);
          }
          $hidden_types_users = $user_input['hidden_types_users_field_' . $key];
          if (!empty($hidden_types_users)) {
            $types_rule_output['hidden-users'] = explode(',', $hidden_types_users);
          } else {
            unset($types_rule_output['hidden-users']);
          }
          $visibility = $types_rule['visibility'] ? FALSE : TRUE;
          $types_rule_output['visible'] = $visibility;
          $read_rule = $manager->fieldsetToRule($types_rule_output);
          $read_rule_output['types'][] = $read_rule;
        }
      }
      if ($rule_type === 'files') {
        $files_rules = $rules['files']['files_fieldset']['fieldsets'];
        foreach ($files_rules as $key => $files_rule) {
          $level = $user_input['files_level_' . $key];
          $files_rule_output['level'] = $level;
          $visibility = $user_input['files_visibility_' . $key] ? FALSE : TRUE;
          $files_rule_output['visible'] = $visibility;
          $files = $files_rule['files'];
          $empty_checkboxes = !$manager->checkboxesAreChecked($files);
          if (!$empty_checkboxes) {
            $files_rule_output['files'] = $files;
          } else {
            unset($files_rule_output['files']);
          }
          $hidden_files_users = $user_input['hidden_files_users_field_' . $key];
          if (!empty($hidden_files_users)) {
            $files_rule_output['hidden-users'] = explode(',', $hidden_files_users);
          } else {
            unset($files_rule_output['hidden-users']);
          }
          $visibility = $files_rule['visibility'] ? FALSE : TRUE;
          $files_rule_output['visible'] = $visibility;
          $read_rule = $manager->fieldsetToRule($files_rule_output);
          $read_rule_output['files'][] = $read_rule;
        }
      }
    }

    ddm($user_input);

    if (!empty($read_rule_output)) {
      $read_output_json = json_encode($read_rule_output);
      ddm($read_output_json);
      $manager->storeAccessPolicy($nid, $read_output_json, 'read');
      \Drupal::messenger()->addMessage('Your Read Access Policy has been saved.');
    }
  }

  public function submitWritePolicy(array &$form, FormStateInterface $form_state)
  {

    $manager = \Drupal::service('flat_permissions.permissions_manager');

    $user_input = $form_state->getUserInput();
    $nid = \Drupal::routeMatch()->getParameter('node')->id();

    $write_rule_output = [];

    if (array_key_exists('hidden_write_users_field_write', $user_input)) {
      $write_users = $user_input['hidden_write_users_field_write'];
      if (!empty($write_users)) {
        $write_rule_output['users'] = explode(',', $write_users);
      }
    }

    if (!empty($write_rule_output)) {
      $write_output_json = json_encode($write_rule_output);
      $manager->storeAccessPolicy($nid, $write_output_json, 'write');
      \Drupal::messenger()->addMessage('Your Write Access Policy has been saved.');
    }
  }

  public function deleteReadPolicy()
  {
    $nid = \Drupal::routeMatch()->getParameter('node')->id();
    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $manager->deleteAccessPolicy($nid, 'read');
    \Drupal::messenger()->addMessage('Your Read Access Policy has been deleted.');
  }

  public function deleteWritePolicy()
  {
    $nid = \Drupal::routeMatch()->getParameter('node')->id();
    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $manager->deleteAccessPolicy($nid, 'write');
    \Drupal::messenger()->addMessage('Your Write Access Policy has been deleted.');
  }
}
