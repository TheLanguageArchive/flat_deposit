<?php

namespace Drupal\flat_permissions;

use stdClass;

class PermissionsManager
{
    /** @var string */
    const ROLE_ANONYMOUS     = 'anonymous user';

    /** @var string */
    const ROLE_AUTHENTICATED = 'authenticated user';

    /** @var stirng */
    const ROLE_ACADEMIC      = 'academic user';

    /** @var string */
    const ROLE_SPECIFIC      = 'specific';

    /** @var string */
    const ROLE_MANAGER       = 'manager';

    /** @var string */
    const ROLE_ADMINISTRATOR = 'administrator';

    /** @var array */
    const DEFAULT_USERS      = ['fedoraAdmin', 'admin'];

    /** @var array */
    const DEFAULT_GROUPS     = ['administrator'];

    /** @var array */
    const LEVELS      = ['anonymous' => 'Open', 'authenticated' => 'Registered Users', 'academic' => 'Academic Users', 'none' => 'Restricted'];

    /** @var array */
    const TYPES     = ['audio' => 'Audio', 'video' => 'Video', 'image' => 'Images', 'text'  => 'Written/Annotations', 'other' => 'Other'];

    /** Fetch the access policy for the given node, or go up the hierarchy if it doesn't have one
     *
     * @param string $nid
     * @return object | null
     *      access policy object
     */
    public function fetchEffectiveAccessPolicy($nid, $class): ?stdClass
    {
        if ($class === 'read') {
            $field = 'field_read_access_policy';
        } elseif ($class === 'write') {
            $field = 'field_write_access_policy';
        }

        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $nodeStorage->load($nid);

        if ($node && $node->hasField($field)) {
            $accessPolicyField = $node->get($field);
            if ($accessPolicyField->value) {
                $policy_json = $accessPolicyField->value;
                return json_decode($policy_json);
            }
        }
        // If node doesn't have an access policy, go up the hierarchy
        if ($node && $node->hasField('field_member_of')) {
            $parentNodes = $node->get('field_member_of')->referencedEntities();
            if ($parentNodes) {
                $policy = $this->fetchEffectiveAccessPolicy($parentNodes[0]->id(), $class);
                if ($policy) {
                    return $policy;
                }
            }
        }

        return null;
    }


    /** Fetch the access policy for the given node
     *
     * @param string $nid
     * @return object | null
     *      access policy object
     */
    public function fetchAccessPolicy($nid, $class): ?stdClass
    {
        if ($class === 'read') {
            $field = 'field_read_access_policy';
        } elseif ($class === 'write') {
            $field = 'field_write_access_policy';
        }

        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $nodeStorage->load($nid);

        if ($node && $node->hasField($field)) {
            $accessPolicyField = $node->get($field);
            if ($accessPolicyField->value) {
                $policy_json = $accessPolicyField->value;
                return json_decode($policy_json);
            }
        }

        return null;
    }

    /**
     * Interpret roles and add access level to the policy (for display purposes only)
     *
     * @param object $policy
     * @return object
     */
    public function addLevels($policy)
    {
        $rules = [];
        if (property_exists($policy, 'all')) {
            $rules[] = $policy->all;
        };
        if (property_exists($policy, 'types')) {
            $rules = $policy->types;
        };
        if (property_exists($policy, 'files')) {
            $rules = $policy->files;
        };
        foreach ($rules as $rule) {
            if (property_exists($rule, 'roles')) {
                if (in_array('anonymous', $rule->roles)) {
                    $rule->effective_role = 'anonymous';
                    $rule->level = $this::LEVELS['anonymous'];
                } elseif (in_array('authenticated', $rule->roles)) {
                    $rule->effective_role = 'authenticated';
                    $rule->level = $this::LEVELS['authenticated'];
                } elseif (in_array('academic', $rule->roles)) {
                    $rule->effective_role = 'academic';
                    $rule->level = $this::LEVELS['academic'];
                } else {
                    $rule->effective_role = 'none';
                    $rule->level = $this::LEVELS['none'];
                };
            } else {
                $rule->effective_role = 'none';
                $rule->level = 'Restricted';
            }
        }
        return $policy;
    }

    public function sortByEffectiveRole($policy)
    {
        $key = array_keys((array)$policy)[0];
        if (!array_key_exists('effective_role', (array)$policy->{$key}[0])) {
            return ($policy);
        } else {
            usort($policy->{$key}, array($this, 'compareEffectiveRoles'));
            return ($policy);
        }
    }

    public function compareEffectiveRoles($a, $b)
    {
        $roleOrder = array_keys($this::LEVELS);

        $orderA = array_search($a->effective_role, $roleOrder);
        $orderB = array_search($b->effective_role, $roleOrder);

        $orderA = ($orderA !== false) ? $orderA : PHP_INT_MAX;
        $orderB = ($orderB !== false) ? $orderB : PHP_INT_MAX;

        if ($orderA == $orderB) {
            return 0;
        }
        return ($orderA < $orderB) ? -1 : 1;
    }

    /**
     * Gets all "original file" media entities linked to a given node ID.
     *
     * @param int $nid
     *   The node ID.
     *
     * @return \Drupal\media\MediaInterface[]
     *   An array of media entities.
     */
    public function getMediaEntitiesByNodeId($nid)
    {
        $entity_type_manager = \Drupal::entityTypeManager();
        $entity_query = \Drupal::entityQuery('media');

        $query = $entity_query->condition('field_media_of', $nid)
            ->accessCheck(TRUE);

        $mids = $query->execute();

        $media_entities = $entity_type_manager->getStorage('media')->loadMultiple($mids);

        return $media_entities;
    }

    public function fieldsetToRule($fieldset)
    {
        $rule = [];
        $level = $fieldset['level'];
        $roles = $this->levelToRoles($level);
        $rule['roles'] = $roles;
        if ($level === 'academic' || $level === 'none') {
            if (array_key_exists('hidden-users', $fieldset)) {
                $rule['users'] = $fieldset['hidden-users'];
            }
        }
        if (array_key_exists('filetypes', $fieldset)) {
            if (!empty($fieldset['filetypes'])) {
                $rule['filetypes'] = array_keys(array_filter($fieldset['filetypes']));
            }
        }
        if (array_key_exists('hidden-mimetypes', $fieldset)) {
            $rule['mimetypes'] = $fieldset['hidden-mimetypes'];
        }
        if (array_key_exists('files', $fieldset)) {
            $rule['files'] = $fieldset['files'];
        }
        if (array_key_exists('visible', $fieldset)) {
            $rule['visible'] = $fieldset['visible'];
        }
        return $rule;
    }

    public function levelToRoles($level)
    {
        if ($level === 'anonymous') {
            return ['anonymous', 'authenticated'];
        } elseif ($level === 'authenticated') {
            return ['authenticated'];
        } elseif ($level === 'academic') {
            return ['academic'];
        } elseif ($level === 'none') {
            return ['none'];
        }
    }

    public function storeAccessPolicy($nid, $policy, $class)
    {
        if ($class === 'read') {
            $field = 'field_read_access_policy';
        } elseif ($class === 'write') {
            $field = 'field_write_access_policy';
        }
        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $nodeStorage->load($nid);
        $node->set($field, $policy);
        $node->save();
    }
}
