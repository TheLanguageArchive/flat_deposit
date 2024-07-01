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

    // fetch access policies for the current node
    $read_policy = $manager->fetchAccessPolicy($nid, 'read');
    $write_policy = $manager->fetchAccessPolicy($nid, 'write');

    if ($read_policy) {
      // current node has a read access policy, we need to use it to set the default values of the form
      $defaults = $read_policy;
      $action = "Modify";
    } else {
      // current node has no read access policy, find the effective policy up the hierarchy for displaying it in the form
      $read_policy = $manager->fetchEffectiveAccessPolicy($nid, 'read');
      $defaults = NULL;
      $action = "Define";
    }

    if ($write_policy) {
      // current node has a write access policy, we need to use it to set the default values of the form
      $write_defaults = $write_policy;
      $write_action = "Modify";
    } else {
      // current node has no write access policy, find the effective policy up the hierarchy for displaying it in the form
      $write_policy = $manager->fetchEffectiveAccessPolicy($nid, 'write');
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
      if ($this->objectAndPropertiesExist($defaults, 'types')) {
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

    // the effective access rules from higher up in the hierarch will be displayed in this info section, if any.
    // rendering is done with the flat-permissions-policy twig template
    $form['info'] = [
      '#type' => 'container',
      '#title' => 'Effective Access Rules',
      '#prefix' => '<h2>Effective Access Rules</h2>
      <p>The nearest access rules that are defined in the collection hierarchy and that are applicable to this item.</p>',
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
      <p>' . ucfirst($action) . ' read rules that will apply to this item and anything below it. Defined read rules here will override any read rule in the parent collection(s). Read rules defined anywhere lower in the hiearchy will take precedence over read rules defined here.</p>',
    ];

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

    $form['rules']['all']['all_fieldset']['hidden-users'] = $this->build_hidden_field('all', 'all', 'users', $form_state, $defaults);

    $form['rules']['types'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => ['rule-type-wrapper'],
      ],
    ];

    $form['rules']['types']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule(s) for specific file and/or mime types',
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

      // not a collection, should have media in theory so we need to create a "files" rule

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

    ddm($read_policy);

    $form['rules']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete read rules'),
      '#submit' => ['::deleteReadPolicy'],
      '#disabled' => empty($read_policy) ? true : false,
      '#attributes' => [
        'class' => ['btn-danger'],
      ],
    ];

    $form['write'] = [
      '#type' => 'container',
      '#tree' => true,
      '#prefix' => '<h2>'. $write_action . ' Write Access</h2>
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

    $form['write']['hidden-users'] = $this->build_hidden_field('write', 'write', 'users', $form_state, $write_defaults);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    $form['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete write rules'),
      '#submit' => ['::deleteWritePolicy'],
      '#disabled' => empty($write_policy) ? true : false,
      '#attributes' => [
        'class' => ['btn-danger'],
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
   * @param string $manager The manager class.
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
    } elseif ($this->objectAndPropertiesExist($defaults, 'types')) {
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
    } elseif ($this->objectAndPropertiesExist($defaults, 'types')) {
      if (array_key_exists(($i), $defaults->types)) {
        $default_filetypes_value = property_exists($defaults->types[$i], 'filetypes') ? $defaults->types[$i]->filetypes : [];
      } else {
        $default_filetypes_value = [];
      }
    } else {
      $default_filetypes_value = [];
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
      'filetypes'        => [
        '#type' => 'checkboxes',
        '#title' => t('File type(s)'),
        '#prefix' => '<span class="label">' . t('File type(s)') . '</span>',
        '#options' => $manager::TYPES,
        '#default_value' => $default_filetypes_value,
      ],
      'mimetypes'        => [
        '#type' => 'textfield',
        '#title' => t('Mime type(s)'),
        '#attributes' => [
          'class' => ['multi-autocomplete'],
          'data-autocomplete-url' => 'permissions/autocomplete/mime_type',
          'data-hidden-input-name' => 'hidden_types_mimetypes_field_' . $i,
        ],
      ],
      'hidden-mimes'       => $this->build_hidden_field($i, 'types', 'mimetypes', $form_state, $defaults),
      'hidden-users'       => $this->build_hidden_field($i, 'types', 'users', $form_state, $defaults),
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
   * @param string $manager The manager class.
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
      if ($this->objectAndPropertiesExist($defaults, 'files')) {
        $default_level_value = array_search($defaults->files[$i]->level, $manager::LEVELS);
      } else {
        $default_level_value = '';
      }
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
      ],      'users'        => [
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

      'hidden-users'       => $this->build_hidden_field($i, 'files', 'users', $form_state, $defaults),
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
  public function build_hidden_field($index, $rule_type, $field_type, FormStateInterface $form_state, $defaults)
  {
    $user_input = $form_state->getUserInput();
    // Determine the default value, either from the form_state user input, or from the stored json permissions (passed via $defaults).
    if (isset($user_input['hidden_' . $rule_type . '_' . $field_type . '_field_' . $index])) {
      $default_value = $user_input['hidden_' . $rule_type . '_' . $field_type . '_field_' . $index];
    } elseif ($this->objectAndPropertiesExist($defaults, "{$rule_type}->users")) {
      $default_value = implode(',', $defaults->{$rule_type}->users);
    } elseif ($this->objectAndPropertiesExist($defaults, "users")) {
      // Write rule only has users
      $default_value = implode(',', $defaults->users);
    } elseif ($this->objectAndPropertiesExist($defaults, $rule_type)) {
      if (is_array($defaults->{$rule_type})) {
        // "type" and "file" rules are in an array as there can be more than one
        if (array_key_exists(($index), $defaults->{$rule_type})) {
          $default_value = property_exists($defaults->{$rule_type}[$index], $field_type) ? implode(',', $defaults->{$rule_type}[$index]->{$field_type}) : '';
        } else {
          $default_value = '';
        }
      }
      else {
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
    $user_input = $form_state->getUserInput();
    if (isset($user_input[$rule_type . '_level_' . $index])) {
      $default_value = $user_input[$rule_type . '_level_' . $index];
    } elseif ($this->objectAndPropertiesExist($defaults, $rule_type)) {
      if (is_array($defaults->{$rule_type})) {
        if (array_key_exists(($index), $defaults->types)) {
          $default_value = array_search($defaults->types[$index]->level, $levels);
        } else {
          $default_value = '';
        }
      }
      else {
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
   * Visibility checkbox fieldset
   *
   * @return array
   */
  public function build_visibility_fieldset($visibility)
  {

    return [

      '#type'      => 'fieldset',
      '#title'     => 'Visibility',
      'visibility' => [

        '#type'          => 'checkbox',
        '#title'         => 'Invisible',
        '#default_value' => $visibility,
        '#attributes'    => [
          'data-role' => 'visibility',
        ],
      ],
    ];
  }

  /**
   * Finds duplicate values in multiple arrays.
   *
   * This function takes an array of arrays as input and returns an array of values
   * that occur more than once in any of the input arrays. It iterates over each
   * array, removes duplicate values, and keeps track of the count of each value.
   * Finally, it filters out values that occur only once and returns the remaining
   * values.
   *
   * @param array[] $arrays The array of arrays to search for duplicate values.
   * @return array The array of duplicate values.
   */
  private function findDuplicateValuesInMultipleArrays($arrays)
  {
    $valueCounts = [];
    foreach ($arrays as $array) {
      $uniqueValues = array_unique($array);
      foreach ($uniqueValues as $value) {
        if ($value !== '' and $value !== 0) {
          if (!isset($valueCounts[$value])) {
            $valueCounts[$value] = 0;
          }
          $valueCounts[$value]++;
        }
      }
    }
    $duplicateValuesInMultipleArrays = [];
    foreach ($valueCounts as $value => $count) {
      if ($count > 1) {
        $duplicateValuesInMultipleArrays[] = $value;
      }
    }

    return $duplicateValuesInMultipleArrays;
  }

  /**
   * Finds values occurring more than once in the given array.
   *
   * @param array $array The input array to search for duplicate values.
   * @return array The array of values that occur more than once.
   */
  private function findValuesOccurringMoreThanOnce($array)
  {

    $valueCounts = array_count_values($array);
    $multipleValues = array_filter($valueCounts, function ($count) {
      return $count > 1;
    });
    $valuesWithMultipleOccurrences = array_keys($multipleValues);

    return $valuesWithMultipleOccurrences;
  }

  /**
   * Checks if any of the checkboxes in the given array have a non-empty value.
   *
   * @param array $checkboxes_values An array of checkbox values.
   * @return bool Returns TRUE if any of the checkboxes have a non-empty value, FALSE otherwise.
   */
  private function checkboxesAreChecked(array $checkboxes_values)
  {
    foreach ($checkboxes_values as $value) {
      if (!empty($value)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Helper function that checks if the given object and its nested properties exist.
   *
   * @param mixed $object The object to check.
   * @param string $propertyPath The property path to check, separated by '->'.
   * @return bool Returns true if the object and all its nested properties exist, false otherwise.
   */
  public function objectAndPropertiesExist($object, $propertyPath)
  {
    // Check if the initial object itself is null
    if ($object === null) {
      return false;
    }

    // Split the property path into an array of properties
    $properties = explode('->', $propertyPath);

    foreach ($properties as $property) {
      // If the object is null or the property does not exist, return false
      if ($object === null || !is_object($object) || !property_exists($object, $property)) {
        return false;
      }
      // Move to the next nested property
      $object = $object->$property;
    }

    return true;
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
    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $errors = [];
    $level_options = $manager::LEVELS;
    $rules = $form_state->getValue(['rules']);
    // for some reason, form_state values are not present for some of the dynamically added fields,
    // whereas the user input array does have them, so we'll get them from there.
    $user_input = $form_state->getUserInput();
    if (array_key_exists('radio', $user_input)) {
      $rule_type = $user_input['radio'];
      if ($rule_type === 'types') {
        // check for empty rules and duplicate filetype and mimetype values
        $types_rules = $rules['types']['types_fieldset']['fieldsets'];
        foreach ($types_rules as $key => $type_rule) {
          $filetypes = $type_rule['filetypes'];
          $empty_checkboxes = !$this->checkboxesAreChecked($filetypes);
          $type_options = $form['rules']['types']['types_fieldset']['fieldsets'][$key]['filetypes']['#options'];
          $types_rules_filetypes[] = $filetypes;
          $hidden_mimetypes = $user_input['hidden_types_mimetypes_field_' . $key];
          if ($empty_checkboxes && empty($hidden_mimetypes)) {
            $errors[] = 'You have created a rule with no file or mime types selected.';
          }
          $types_rules_mimetypes[] = explode(',', $hidden_mimetypes);
          $rule_levels[] = $user_input['types_level_' . $key];
          $rule_users[] = $user_input['hidden_types_users_field_' . $key];
        }
        $type_duplicates = $this->findDuplicateValuesInMultipleArrays($types_rules_filetypes);
        foreach ($type_duplicates as $key => $value) {
          $type_duplicate_names[] = $type_options[$value];
        }
        $mime_duplicates = $this->findDuplicateValuesInMultipleArrays($types_rules_mimetypes);
        if (!empty($type_duplicates)) {
          $errors[] = 'You have used the same file type(s) in more than one rule: ' . implode(', ', $type_duplicate_names);
        }
        if (!empty($mime_duplicates)) {
          $errors[] = 'You have used the same mime type(s) in more than one rule: ' . implode(', ', $mime_duplicates);
        }
      }


      if ($rule_type === 'files') {
        // check for empty rules and duplicate file values
        $files_rules = $rules['files']['files_fieldset']['fieldsets'];
        foreach ($files_rules as $key => $file_rule) {
          $files = $file_rule['files'];
          $empty_checkboxes = !$this->checkboxesAreChecked($files);
          if ($empty_checkboxes) {
            $errors[] = 'You have created a rule for specific files with no files selected';
          }
          $file_options = $form['rules']['files']['files_fieldset']['fieldsets'][$key]['files']['#options'];
          $files_rules_files[] = $files;
          $rule_levels[] = $user_input['files_level_' . $key];
          $rule_users[] = $user_input['hidden_files_users_field_' . $key];
        }
        $file_duplicates = $this->findDuplicateValuesInMultipleArrays($files_rules_files);
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
        $level_duplicates = $this->findValuesOccurringMoreThanOnce($rule_levels);
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
              Please create a single rule for these levels or make sure that theusers are distinct for each rule.';
            }
          }
        }
      }
    } else {
      $write_users = $user_input['hidden_write_users_field_write'];
      if (empty($write_users)) {
        // no write users defined and no read rules defined
        $errors[] = 'No Read or Write access has been defined.';
      }
    }
    if (!empty($errors)) {
      if (count($errors) > 1) {
        $errors = [
          '#theme' => 'item_list',
          '#type' => 'ul',
          '#attributes' => ['class' => 'mylist'],
          '#items' => $errors,
          '#prefix' => '<h4>There are some conflicts in your access rules:</h4>',
        ];
      } else {
        $errors = $errors[0];
      }
      $form_state->setErrorByName('', $errors);
      return $form;
    }
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
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $manager = \Drupal::service('flat_permissions.permissions_manager');

    $node = \Drupal::routeMatch()->getParameter('node');

    $nid = $node->id();

    $read_rule_output = [];
    $write_rule_output = [];
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
        $read_rule = $manager->fieldsetToRule($all_rule_output);
        $read_rule_output['all'] = $read_rule;
      }
      if ($rule_type === 'types') {
        $types_rules = $rules['types']['types_fieldset']['fieldsets'];
        foreach ($types_rules as $key => $types_rule) {
          $level = $user_input['types_level_' . $key];
          $types_rule_output['level'] = $level;
          $filetypes = $types_rule['filetypes'];
          $empty_checkboxes = !$this->checkboxesAreChecked($filetypes);
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
          $read_rule = $manager->fieldsetToRule($types_rule_output);
          $read_rule_output['types'][] = $read_rule;
        }
      }
      if ($rule_type === 'files') {
        $files_rules = $rules['files']['files_fieldset']['fieldsets'];
        foreach ($files_rules as $key => $files_rule) {
          $level = $user_input['files_level_' . $key];
          $files_rule_output['level'] = $level;
          $files = $files_rule['files'];
          $empty_checkboxes = !$this->checkboxesAreChecked($files);
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
          $read_rule = $manager->fieldsetToRule($files_rule_output);
          $read_rule_output['files'][] = $read_rule;
        }
      }
    }
    if (array_key_exists('hidden_write_users_field_write', $user_input)) {
      $write_users = $user_input['hidden_write_users_field_write'];
      if (!empty($write_users)) {
        $write_rule_output['users'] = explode(',', $write_users);
      }
    }
    if (!empty($read_rule_output)) {
      $read_output_json = json_encode($read_rule_output);
      $manager->storeAccessPolicy($nid, $read_output_json, 'read');
    }
    if (!empty($write_rule_output)) {
      $write_output_json = json_encode($write_rule_output);
      $manager->storeAccessPolicy($nid, $write_output_json, 'write');
    }

    \Drupal::messenger()->addMessage('Your access rules have been saved.', 'status');
  }

  public function deleteReadPolicy() {
    $nid = \Drupal::routeMatch()->getParameter('node')->id();
    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $manager->deleteAccessPolicy($nid, 'read');
    \Drupal::messenger()->addMessage('Your read access rules have been deleted.', 'status');

  }

  public function deleteWritePolicy() {
    $nid = \Drupal::routeMatch()->getParameter('node')->id();
    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $manager->deleteAccessPolicy($nid, 'write');
    \Drupal::messenger()->addMessage('Your write access rule has been deleted.', 'status');
  }
}
