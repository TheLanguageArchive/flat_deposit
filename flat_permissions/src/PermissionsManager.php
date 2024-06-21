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
    public function fetchEffectiveAccessPolicy($nid): ?stdClass
    {
        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $nodeStorage->load($nid);

        if ($node && $node->hasField('field_access_policy')) {
            $accessPolicyField = $node->get('field_access_policy');
            if ($accessPolicyField->value) {
                $policy_json = $accessPolicyField->value;
                return json_decode($policy_json);
            }
        }
        // If node doesn't have an access policy, go up the hierarchy
        if ($node && $node->hasField('field_member_of')) {
            $parentNodes = $node->get('field_member_of')->referencedEntities();
            if ($parentNodes) {
                $policy = $this->fetchEffectiveAccessPolicy($parentNodes[0]->id());
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
    public function fetchAccessPolicy($nid): ?stdClass
    {
        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $node = $nodeStorage->load($nid);

        if ($node && $node->hasField('field_access_policy')) {
            $accessPolicyField = $node->get('field_access_policy');
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
        $read = $policy->read;
        if (property_exists($read, 'all')) {
            $rules[] = $read->all;
        };
        if (property_exists($read, 'types')) {
            $rules = $read->types;
        };
        if (property_exists($read, 'files')) {
            $rules = $read->files;
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
        $key = array_keys((array)$policy->read)[0];
        usort($policy->read->{$key}, array($this, 'compareEffectiveRoles'));
        return ($policy);
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
            $rule['filetypes'] = array_keys(array_filter($fieldset['filetypes']));
        }
        if (array_key_exists('hidden-mimes', $fieldset)) {
            $rule['mimetypes'] = $fieldset['hidden-mimes'];
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
            return ['anonymous'];
        } elseif ($level === 'authenticated') {
            return ['anonymous', 'authenticated'];
        } elseif ($level === 'academic') {
            return ['anonymous', 'authenticated', 'academic'];
        } else {
            return [];
        }
    }
}
