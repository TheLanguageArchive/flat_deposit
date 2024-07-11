<?php

namespace Drupal\flat_permissions;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

class NodeAccessService
{

  /**
   * Determines whether the node can be accessed by the current user, depending on
   * the "visibility" and the current user's permissions in the policy.
   *
   * If any rule has visibility set to 'visible', allow access to the node. Otherwise, the node is invisible
   * to anyone except users who have explicit permissions in any of the rules.
   *
   * @param NodeInterface $node The node to check access for.
   * @param string $op The operation being performed on the node.
   * @param AccountProxyInterface $current_user The current user.
   * @return bool Returns true if the user has access, false otherwise.
   */
  public function userHasNodeAccess(NodeInterface $node, $op, $current_user)
  {

    if ($op == 'view' || $op == 'view all revisions') {

      $type = $node->getType();

      if ($type == 'islandora_object') {

        $manager = \Drupal::service('flat_permissions.permissions_manager');

        $nid = $node->id();

        $username = $current_user->getAccountName();

        $policy = $manager->fetchAccessPolicy($nid, 'read');

        if (!$policy) {
          $effective_policy = $manager->fetchEffectiveAccessPolicy($nid, 'read');
          if ($effective_policy) {
            $policy = $effective_policy['policy'];
          }
        }

        if (!$policy) {
          return TRUE;
        } else {
          // if any rule has visibility set to 'visible', allow access to the node
          $visibility = check_visibility($policy);

          if ($visibility == 'visible') {
            return TRUE;
          } else {

            // all rules have visibility set to 'invisible'.
            // Check if user has permissions in any of the rules
            if ($manager->objectAndPropertiesExist($policy, 'all')) {
              if (property_exists($policy->all, "visibility")) {
                if ($policy->all->visibility == 'invisible') {
                  if (property_exists($policy->all, "roles")) {
                    if ($policy->all->roles[0] == 'none') {
                      if (property_exists($policy->all, "users"))
                        $allowed_users = $policy->all->users;
                      if (in_array($username, $allowed_users)) {
                        return TRUE;
                      }
                    }
                  }
                }
              }
            } elseif ($manager->objectAndPropertiesExist($policy, 'types')) {
              foreach ($policy->types as $type_rule) {
                if (property_exists($type_rule, "visibility")) {
                  if ($type_rule->visibility == 'invisible') {
                    if (property_exists($type_rule, "roles")) {
                      if ($type_rule->roles[0] == 'none') {
                        if (property_exists($type_rule, "users"))
                          $allowed_users = $type_rule->users;
                        if (in_array($username, $allowed_users)) {
                          return TRUE;
                        }
                      }
                    }
                  }
                }
              }
            } elseif ($manager->objectAndPropertiesExist($policy, 'files')) {
              foreach ($policy->files as $file_rule) {
                if (property_exists($file_rule, "visibility")) {
                  if ($file_rule->visibility == 'invisible') {

                    if (property_exists($file_rule, "roles")) {
                      if ($file_rule->roles[0] == 'none') {
                        if (property_exists($file_rule, "users"))
                          $allowed_users = $file_rule->users;
                        if (in_array($username, $allowed_users)) {
                          return TRUE;
                        }
                      }
                    }
                  }
                }
              }
            }
          }
          // user doesn't have access
          return FALSE;
        }
      }
    }
  }
}
