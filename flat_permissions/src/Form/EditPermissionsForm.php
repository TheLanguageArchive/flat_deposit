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
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

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

    $manager = \Drupal::service('flat_permissions.permissions_manager');
    $policy = $manager->fetchAccessPolicy($nid);

    $policy = $policy ? $manager->addLevels($policy) : null;


    $form['info'] = [
      '#type' => 'html_tag',
      '#title' => 'Effective Access Rules',
      '#prefix' => '<h3>Effective Access Rules</h3>
      <p>The nearest access rules that are defined in the collection hierarchy and that are applicable to files below this item.</p>',
      '#tag' => 'div',
      '#theme' => 'flat_permissions_policy',
      '#data' => $policy,
    ];

    $form['rules'] = array(
      '#type' => 'container',
      '#prefix' => '<h3>Define Access Rules</h3>
      <p>Define access rules that will apply to files below this item. Defining a rule here will override any rule in the parent collection(s).</p>',
    );

    $form['rules']['all'] = [
      '#type' => 'fieldset',
    ];

    $form['rules']['all']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule for all files',
      '#return_value' => 'all',
      '#name' => 'radio',
    ];

    $form['rules']['all']['level'] = [
      '#type' => 'select',
      '#title' => t('Access level'),
      '#options' => $manager::LEVELS,
      '#name' => 'all_level',
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'all'],
        ],
      ],
    ];

    $form['rules']['all']['users'] = [
      '#type' => 'textfield',
      '#title' => t('Specific users'),
      '#states' => [
        'visible' => [
          [':input[name="all_level"]' => ['value' => 'none']],
          [':input[name="all_level"]' => ['value' => 'academic']],
        ],
      ],
    ];

    $form['rules']['mimes'] = [
      '#type' => 'fieldset',
    ];

    $form['rules']['mimes'] = $this->getAvailableMimes() ? $this->build_mimes_fieldset($form_state, $this->getAvailableMimes()) : [];

    $form['rules']['mimes']['level'] = [
      '#type' => 'select',
      '#title' => t('Access level'),
      '#options' => $manager::LEVELS,
      '#name' => 'mimes_level',
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'mimes'],
        ],
      ],
    ];

    $form['rules']['mimes']['users'] = [
      '#type' => 'textfield',
      '#title' => t('Specific users'),
      '#states' => [
        'visible' => [
          [':input[name="mimes_level"]' => ['value' => 'none']],
          [':input[name="mimes_level"]' => ['value' => 'academic']],
        ],
      ],
    ];

    $form['rules']['files'] = [
      '#type' => 'fieldset',
    ];

    $form['rules']['files']['radio'] = [
      '#type' => 'radio',
      '#title' => 'Rule(s) for specific files',
      '#return_value' => 'files',
      '#name' => 'radio',
    ];

    $form['rules']['files']['level'] = [
      '#type' => 'select',
      '#title' => t('Access level'),
      '#options' => $manager::LEVELS,
      '#name' => 'files_level',
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'files'],
        ],
      ],
    ];

    $form['rules']['files']['users'] = [
      '#type' => 'textfield',
      '#title' => t('Specific users'),
      '#states' => [
        'visible' => [
          [':input[name="files_level"]' => ['value' => 'none']],
          [':input[name="files_level"]' => ['value' => 'academic']],
        ],
      ],
    ];


    /*     $manager = \Drupal::service('flat_permissions.permissions_manager');

    $form['#validate']              = ['flat_permissions_form_validate'];
    $form['#group']                 = $manager->determineReadGroup();
    $form['#is_management_allowed'] = $isManagementAllowed;

    $form['visibility']             = build_visibility_fieldset($manager->getVisibility());
    $form['mimes']                  = build_mimes_fieldset($form_state, $object->id, $availableMimes, $manager->datastreamEnabled());
    $form['read_group']             = build_read_group_fieldset($form['#group']);
    $form['read_users']             = build_users_fieldset($form_state, 'read', $manager->getReadUsers());

    if ($isManagementAllowed) {
        $form['management_users']   = build_users_fieldset($form_state, 'management', $manager->getManagementUsers());
    } */

    return $form;
  }

  public function getAvailableMimes()
  {
    $database = \Drupal::database();
    $query = $database->select('media__field_mime_type', 'mfmt');
    $query->addField('mfmt', 'field_mime_type_value');
    $query->distinct(TRUE);
    $mimes = $query->execute()->fetchCol();
    return $mimes;
  }

  /**
   * Mimes fieldset
   *
   * @param FormStateInterface  $form_state
   * @param string $pid
   * @param array  $mimes
   * @param array  $availableMimes
   * @param bool   $enabled
   *
   * @return array
   */
  public function build_mimes_fieldset(FormStateInterface &$form_state, $availableMimes = [], $enabled = true)
  {

    $mimes = [];


    $availableMimes = array_map(function ($mime) {
      return ['field' => $mime, 'label' => $mime];
    }, $availableMimes);

    $fieldset = [

      '#tree'        => true,
      '#type'        => 'fieldset',
      'delete'       => $this->build_delete_mimes_fieldset($mimes),
      'hidden'       => $this->build_hidden_mimes_fieldset($mimes),
      'radio'      => $this->build_enabled_mimes_fieldset($mimes, $enabled),
      'autocomplete' => $this->build_static_autocomplete_fieldset(

        $availableMimes,
        'Add mime type',
        'add_mime',
        'flat_permissions_form_add_mime_submit',
        'flat_permissions_form_add_mime_validate',
        'flat_permissions_form_add_mime_js'
      ),
    ];

    return $fieldset;
  }

  /**
   * @param array $mimes
   *
   * @return array
   */
  public function build_hidden_mimes_fieldset($mimes)
  {

    $fieldset = [

      '#tree'       => true,
      '#type'       => 'container',
      '#attributes' => [
        'data-role' => 'hidden-mimes',
      ],
    ];

    foreach ($mimes as $key => $mime) {

      $fieldset[$key + 1] = [

        '#type'       => 'hidden',
        '#value'      => $mime,
        '#attributes' => [
          'data-mime' => $mime,
        ],
      ];
    }

    return $fieldset;
  }

  /**
   * Deleted mimes fieldset
   *
   * @param array $mimes
   *
   * @return array
   */
  public function build_delete_mimes_fieldset(array $mimes)
  {

    $fieldset = [];

    $i = 1;

    foreach ($mimes as $mime) {

      $fieldset[$i] = [

        '#type'         => 'checkbox',
        '#title'        => $mime,
        '#return_value' => $mime,
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'mimes'],
          ],
        ],
      ];

      $i += 1;
    }

    return $fieldset;
  }

  /**
   * @param array   $mimes
   * @param boolean $enabled
   *
   * @return boolean
   */
  public function build_enabled_mimes_fieldset(array $mimes, $enabled)
  {

    return [

      '#type'          => 'radio',
      '#default_value' => (count($mimes) > 0 ? $enabled : false),
      '#title'         => t('Rule(s) for specific file types'),
      '#return_value' => 'mimes',
      '#name' => 'radio',
    ];
  }

  /**
   * Read group fieldset
   *
   * @param string $currentGroup
   *
   * @return array
   */
  public function build_read_group_fieldset($currentGroup)
  {

    return [

      '#type'      => 'fieldset',
      '#title'     => 'Read groups',
      'read_group' => [

        '#type'          => 'radios',
        '#default_value' => $currentGroup,
        '#attributes'    => [
          'data-role' => 'select-group',
        ],
        '#options'       => [

          PermissionsManager::ROLE_ANONYMOUS     => 'Public',
          PermissionsManager::ROLE_AUTHENTICATED => 'Registered Users',
          PermissionsManager::ROLE_ACADEMIC      => 'Academic Users',
          PermissionsManager::ROLE_SPECIFIC      => 'Specific Users',
        ],
      ],
    ];
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
   * Abstract add user fieldset (for read and management)
   *
   * @param array  $form
   * @param array  $form_state
   * @param string $type (usually read/management)
   * @param array  $users
   *
   * @return array
   */
  public function build_users_fieldset(&$form_state, $type, $users)
  {

    if (count($form_state['new_users'][$type]) > 0) {
      $users = array_merge($users, $form_state['new_users'][$type]);
    }

    $fieldset = [

      '#tree'        => true,
      '#type'        => 'container',
      'delete'       => build_delete_users_fieldset($type, $users),
      'hidden'       => build_hidden_users_fieldset($type, $users),
      'autocomplete' => build_autocomplete_fieldset(

        'user/autocomplete',
        'Add user',
        'add_' . $type . '_user',
        'flat_permissions_form_add_' . $type . '_user_submit',
        'flat_permissions_form_add_user_validate',
        'flat_permissions_form_add_' . $type . '_user_js'
      ),
    ];

    return $fieldset;
  }

  /**
   * @param array  $results
   * @param string $title
   * @param string $name
   * @param string $submit
   * @param string $validation
   * @param string $ajax
   *
   * @return array
   */
  public function build_static_autocomplete_fieldset($results, $title, $name, $submit, $validation, $ajax)
  {

    return [

      '#type'  => 'fieldset',
      '#title' => $title,
      '#states' => [
        'visible' => [
          ':input[name="radio"]' => ['value' => 'mimes'],
        ],
      ],
      'field'  => [
        'input' => [

          '#type'       => 'textfield',
          '#prefix'     => '<div class="input-group form-autocomplete">',
          '#suffix'     => '<span class="input-group-addon"><span class="icon glyphicon glyphicon-refresh"></span></span></div>',
          '#states' => [
            'visible' => [
              ':input[name="radio"]' => ['value' => 'mimes'],
            ],
          ],
          '#attributes' => [

            'data-role'    => 'static-autocomplete',
            'data-results' => json_encode($results),
          ],
        ],
      ],
      'button' => [
        '#type'     => 'submit',
        '#prefix'   => '<div class="mt-1">',
        '#suffix'   => '</div>',
        '#validate' => ['::flat_permissions_form_add_mime_validate'],
        '#name'     => $name,
        '#value'    => t($title),
        '#states' => [
          'visible' => [
            ':input[name="radio"]' => ['value' => 'mimes'],
          ],
        ],
        '#submit'   => ['::flat_permissions_form_add_mime_submit'],
        '#ajax'     => [
          'callback' => ['::flat_permissions_form_add_mime_js'],
        ],
      ],
    ];
  }

  /**
   * Autocomplete fieldset
   *
   * @param string $autocomplete
   * @param string $title
   * @param string $name
   * @param string $submit
   * @param string $validation
   * @param string $ajax
   *
   * @return array
   */
  public function build_autocomplete_fieldset($autocomplete, $title, $name, $submit, $validation, $ajax)
  {

    return [

      '#type'  => 'fieldset',
      '#title' => t($title),
      'field'  => [

        '#type'              => 'textfield',
        '#autocomplete_path' => $autocomplete,
      ],
      'button' => [

        '#type'     => 'submit',
        '#validate' => [$this, $validation],
        '#name'     => $name,
        '#value'    => t($title),
        '#submit'   => [$this, $submit],
        '#ajax'     => [
          'callback' =>  'blahblah',
        ],
      ],
    ];
  }

  /**
   * Deleted users fieldset
   *
   * @param string $type
   * @param array  $users
   *
   * @return array
   */
  public function build_delete_users_fieldset($type, $users)
  {

    $fieldset = [];

    $i = 1;

    foreach ($users as $user) {

      $fieldset[$i] = [

        '#type'         => 'checkbox',
        '#title'        => $user,
        '#return_value' => $user,
      ];

      $i += 1;
    }

    return $fieldset;
  }

  /**
   * @param string $type
   * @param array  $users
   *
   * @return array
   */
  public function build_hidden_users_fieldset($type, $users)
  {

    $fieldset = [

      '#tree'       => true,
      '#type'       => 'container',
      '#attributes' => [
        'data-role' => 'hidden-' . $type . '-users',
      ],
    ];

    foreach ($users as $key => $user) {

      $fieldset[$key + 1] = [

        '#type'       => 'hidden',
        '#value'      => $user,
        '#attributes' => [

          'data-type' => $type,
          'data-user' => $user,
        ],
      ];
    }

    return $fieldset;
  }

  /**
   * Abstract add user submit handler, adding new user
   * to the list using AJAX if javascript enabled
   *
   * @param array  $form
   * @param FormStateInterface  $form_state
   * @param string $type (usually read/management)
   */
  public function flat_permissions_form_add_user_submit(array &$form, FormStateInterface $form_state, $type)
  {

    if (isset($form_state['values']['add_' . $type . '_user'])) {

      // if checkbox is checked, it has a string, otherwise value is 0,
      // so let's filter by is_string
      $users        = [];
      $removedUsers = array_filter($form_state['values'][$type . '_users']['delete'], 'is_string');

      foreach ($form[$type . '_users']['delete'] as $field) {

        if ($field['#type'] === 'checkbox' && !in_array($field['#return_value'], $removedUsers)) {
          $users[] = $field['#return_value'];
        }
      }

      foreach ($form_state['new_users'][$type] as $user) {

        if (!in_array($user, $removedUsers)) {
          $users[] = $user;
        }
      }

      if (!in_array($form_state['values'][$type . '_users']['autocomplete']['field'], $users)) {

        $form_state['rebuild']            = true;
        $form_state['new_users'][$type][] = $form_state['values'][$type . '_users']['autocomplete']['field'];
        $form_state['new_users'][$type]   = array_unique($form_state['new_users'][$type]);
      }
    }
  }

  /**
   * Concrete implementation of read users submit handler
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_read_user_submit(array &$form, FormStateInterface $form_state)
  {
    $this->flat_permissions_form_add_user_submit($form, $form_state, 'read');
  }

  /**
   * Concrete implementation of management users submit handler
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_management_user_submit(array &$form, FormStateInterface $form_state)
  {
    return $this->flat_permissions_form_add_user_submit($form, $form_state, 'management');
  }

  /**
   * Abstract response handler ajax for adidng user
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param string $type (usually read/management)
   *
   * @return array
   */
  public function flat_permissions_form_add_user_js(array &$form, FormStateInterface $form_state)
  {

    $count    = 0;
    $username = '';

    foreach ($form[$type . '_users']['delete'] as $field) {

      if ($field['#type'] === 'checkbox') {

        $count   += 1;
        $username = $field['#return_value'];
      }
    }

    unset($form[$type . '_users']['delete'][$count]['#title']);

    return [

      '#type'     => 'ajax',
      '#commands' => [

        ajax_command_invoke(null, 'onAddUser', [

          $type,
          $username,
          drupal_render($form[$type . '_users']['delete'][$count]),
          drupal_render($form[$type . '_users']['hidden'][$count]),
          $form_state['rebuild']
        ]),
      ],
    ];
  }

  /**
   * Concrete implementation of read users ajax response
   * it calls the jQuery.fn.onAddUser method to add new line
   * in the users table
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_read_user_js(array &$form, FormStateInterface $form_state)
  {
    return $this->flat_permissions_form_add_user_js($form, $form_state, 'read');
  }

  /**
   * Concrete implementation of management users ajax response
   * it calls the jQuery.fn.onAddUser method to add new line
   * in the users table
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_management_user_js(array &$form, FormStateInterface $form_state)
  {
    return $this->flat_permissions_form_add_user_js($form, $form_state, 'management');
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @param array $mimes
   */
  public function flat_permissions_form_add_mime_submit(array &$form, FormStateInterface $form_state)
  {

    if ($form_state->hasValue('add_mime')) {

      // if checkbox is checked, it has a string, otherwise value is 0,
      // so let's filter by is_string
      $mimes        = [];
      $removedMimes = $form_state->getValue(['mimes', 'delete']);

      foreach ($form['rules']['mimes']['delete'] as $field) {

        if (is_array($field)) {

          if (array_key_exists('#type', $field)) {
            if ($field['#type'] === 'checkbox' && !in_array($field['#return_value'], $removedMimes)) {
              $mimes[] = $field['#return_value'];
            }
          }
        }
      }

      ddm($form_state->getValue(['mimes', 'delete']));

      foreach ($form_state->getValue(['mimes', 'delete']) as $mime) {

        if (!in_array($mime, $removedMimes)) {
          $mimes[] = $mime;
        }
      }

      if (!in_array($form_state->getValue(['rules', 'mimes', 'autocomplete', 'field', 'input']), $mimes)) {

        $form_state->set('rebuild', true);
        $form_state->set('rules', 'mimes', 'mimes', $form_state->getValue(['rules', 'mimes', 'autocomplete', 'field', 'input']));
        $form_state->set('rules', 'mimes', 'mimes', array_unique($form_state->getValue(['rules', 'mimes', 'mimes'])));
      }
    }
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function flat_permissions_form_add_mime_validate(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
  public function flat_permissions_form_add_mime_js(array &$form, FormStateInterface $form_state)
  {

    $count = 0;
    $mime  = '';

    foreach ($form['rules']['mimes']['mimes']['delete'] as $field) {

      if ($field['#type'] === 'checkbox') {

        $count += 1;
        $mime   = $field['#return_value'];
      }
    }

    unset($form['rules']['mimes']['mimes']['delete'][$count]['#title']);

    return [

      '#type'     => 'ajax',
      '#commands' => [

        ajax_command_invoke(null, 'onAddMime', [

          $mime,
          drupal_render($form['rules']['mimes']['mimes']['delete'][$count]),
          drupal_render($form['rules']['mimes']['mimes']['hidden'][$count]),
          $form_state['rebuild']
        ]),
      ],
    ];
  }

  /**
   * Instead of using validateForm, which is called every time either ajax or form requests
   * are processed, we hook up this custom validation handler to the submit button so that this
   * handler will only be called if user presses the submit button.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    //$owner = $form_state->getValue(['owner']);
  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->id();
  }
}
