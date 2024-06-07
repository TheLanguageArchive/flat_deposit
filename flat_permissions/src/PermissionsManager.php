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
        // If node doesn't have an access policy, go up the hierarchy
        if ($node && $node->hasField('field_member_of')) {
            $parentNodes = $node->get('field_member_of')->referencedEntities();
            if ($parentNodes) {
                $policy = $this->fetchAccessPolicy($parentNodes[0]->id());
                if ($policy) {
                    return $policy;
                }
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
        if (property_exists($read, 'mimes')) {
            $rules = $read->mimes;
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
        usort($policy->read->mimes, array($this, 'compareEffectiveRoles'));
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


    /**
     * Get current permissions grouped by users and roles
     *
     * @return array
     */
    public function getCurrentPermissions($clearCache = false)
    {
        if (true === $clearCache) {
            $this->permissions = null;
        }

        if (null === $this->permissions) {

            $this->permissions = [

                'visibility' => $this->xacml->viewingRule->isPopulated(),

                'read'       => [

                    'users' => $this->xacml->datastreamRule->getUsers(),
                    'roles' => $this->xacml->datastreamRule->getRoles(),
                ],

                'management' => [

                    'users' => $this->xacml->managementRule->getUsers(),
                    'roles' => $this->xacml->managementRule->getRoles(),
                ],

                'datastream' => [

                    'enabled' => $this->xacml->datastreamRule->isPopulated(),
                    'dsids'   => $this->xacml->datastreamRule->getDsids(),
                ],
            ];
        }

        return $this->permissions;
    }

    /**
     * @return boolean
     */
    public function getVisibility()
    {
        return $this->permissions['visibility'];
    }

    /**
     * Determining current biggest group
     * based on strength
     *
     * @param array  $groups
     * @param string $type ['modify_least', 'modify_most']
     *
     * @return string
     */
    public function determineGroup(array $groups, $type = self::MODIFY_LEAST)
    {
        $sortedGroups  = [];
        $groupStrength = $type === self::MODIFY_MOST ? $this->groupStrengthMost : $this->groupStrengthLeast;

        foreach ($groups as $group) {

            if (isset($groupStrength[$group])) {
                $sortedGroups[$group] = $groupStrength[$group];
            }
        }

        if (count($sortedGroups) === 0) {
            return self::ROLE_SPECIFIC;
        }

        arsort($sortedGroups);
        return key($sortedGroups);
    }

    /**
     * @param string $type ['modify_least', 'modify_most']
     *
     * @return string
     */
    public function determineReadGroup($type = self::MODIFY_LEAST)
    {
        return $this->determineGroup($this->permissions['read']['roles'], $type);
    }

    /**
     * @param string $type ['modify_least', 'modify_most']
     *
     * @return string
     */
    public function determineManagementGroup($type = self::MODIFY_LEAST)
    {
        return $this->determineGroup($this->permissions['management']['roles'], $type);
    }

    /**
     * @return array
     */
    public function datastreamEnabled()
    {
        return $this->permissions['datastream']['enabled'];
    }

    /**
     * @return boolean
     */
    public function isManagementAllowed()
    {
        global $user;
        return in_array(self::ROLE_MANAGER, $user->roles) || in_array(self::ROLE_ADMINISTRATOR, $user->roles);
    }

    /**
     * @return array
     */
    public function getReadUsers()
    {
        return $this->permissions['read']['users'];
    }

    /**
     * @return array
     */
    public function getReadRoles()
    {
        return $this->permissions['read']['roles'];
    }

    /**
     * @return array
     */
    public function getManagementUsers()
    {
        return $this->permissions['management']['users'];
    }

    /**
     * Get xacml
     *
     * @return IslandoraXacml
     */
    public function getXacml()
    {
        return $this->xacml;
    }

    /**
     * @param boolean $visibility
     *
     * @return void
     */
    public function toggleVisibility(bool $visibility)
    {
        $this->permissions['visibility'] = $visibility;
    }

    /**
     * @param array $users
     *
     * @return void
     */
    public function writeReadUsers(array $users)
    {
        $this->permissions['read']['users'] = array_unique(array_merge($users, self::DEFAULT_USERS));
    }

    /**
     * Get roles by group selected in form
     *
     * @param string $group
     *
     * @return array
     */
    public function getRolesByGroup(string $group)
    {
        switch ($group) {

            case self::ROLE_ANONYMOUS:
                $roles = [self::ROLE_ANONYMOUS, self::ROLE_AUTHENTICATED];
                break;

            case self::ROLE_AUTHENTICATED:
                $roles = [self::ROLE_AUTHENTICATED];
                break;

            case self::ROLE_ACADEMIC:
                $roles = [self::ROLE_ACADEMIC];
                break;

            case self::ROLE_SPECIFIC:
            default:
                $roles = [];
                break;
        }

        return $roles;
    }

    /**
     * @param string $group
     *
     * @return void
     */
    public function writeReadGroup(string $group)
    {
        $roles = $this->getRolesByGroup($group);
        $this->permissions['read']['roles'] = array_unique(array_merge($roles, self::DEFAULT_GROUPS));
    }

    /**
     * @param array $users
     *
     * @return void
     */
    public function writeManagementUsers(array $users)
    {
        $this->permissions['management']['users'] = array_unique(array_merge($users, self::DEFAULT_USERS));
    }

    /**
     * @param string $group
     *
     * @return void
     */
    public function writeManagementGroup(string $group)
    {
        $roles = $this->getRolesByGroup($group);
        $this->permissions['management']['roles'] = array_unique(array_merge($roles, self::DEFAULT_GROUPS));
    }

    /**
     * writing back to fedora
     */
    public function commit()
    {
        $this->xacml->datastreamRule->clear();
        $this->xacml->datastreamRule->addUser(array_unique(array_merge($this->permissions['read']['users'], self::DEFAULT_USERS)));
        $this->xacml->datastreamRule->addRole(array_unique(array_merge($this->permissions['read']['roles'], self::DEFAULT_GROUPS)));
        $this->xacml->datastreamRule->addDsid(self::DEFAULT_DSIDS);

        $models = flat_permissions_get_content_models();
        foreach ($models as $model) {

            if (!in_array($model['id'], $this->object->models)) {
                continue;
            }

            foreach ($model['derivatives'] as $derivative) {

                if (!in_array($derivative, self::IGNORE_DSIDS)) {
                    $this->xacml->datastreamRule->addDsid($derivative);
                }
            }
        }

        $this->xacml->viewingRule->clear();

        if (true === $this->permissions['visibility']) {

            $this->xacml->viewingRule->addUser(array_unique(array_merge($this->permissions['read']['users'], self::DEFAULT_USERS)));
            $this->xacml->viewingRule->addRole(array_unique(array_merge($this->permissions['read']['roles'], self::DEFAULT_GROUPS)));
        }

        $this->xacml->managementRule->clear();
        $this->xacml->managementRule->addUser(array_unique(array_merge($this->permissions['management']['users'], self::DEFAULT_USERS)));
        $this->xacml->managementRule->addRole(array_unique(array_merge($this->permissions['management']['roles'], self::DEFAULT_GROUPS)));

        $this->xacml->writeBackToFedora();
    }

    /**
     * Sparql for fetching collection children
     *
     * @param array $mimes
     *
     * @return array
     */
    public function getSparql(array $mimes = [])
    {
        $filters     = [];
        $mimeFilters = '';

        foreach ($mimes as $mime) {
            $filters[] = format_string('?mime = "!mime"', ['!mime' => $mime]);
        }

        if (count($filters) > 0) {

            $mimeFilters = format_string(
                '
                {
                    ?ds <info:fedora/fedora-system:def/view#disseminationType> <info:fedora/*/OBJ> .
                    ?ds <info:fedora/fedora-system:def/view#mimeType> ?mime .
                    FILTER(!filters)

                }',
                ['!filters' => implode(' || ', $filters)]
            );
        }

        $query = format_string('
            SELECT ?object WHERE
            {
                 ?object ?policy ?ds .
                 { ?object <fedora-model:state> <info:fedora/fedora-system:def/model#Active> }
                 { ?object <fedora-rels-ext:isMemberOfCollection> <info:fedora/!pid> }
                 UNION
                 { ?object <fedora-rels-ext:isMemberOf> <info:fedora/!pid> }
                 UNION
                 { ?object <fedora-rels-ext:isConstituentOf> <info:fedora/!pid> }
                 !mimeFilters
            }', ['!mimeFilters' => $mimeFilters, '!pid' => $this->object->id]);

        return [

            'type'        => 'sparql',
            'query'       => $query,
            'description' => 'All children of this collection and collections within this collection (existing and new)',
        ];
    }
}
