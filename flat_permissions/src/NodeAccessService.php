<?php

namespace Drupal\flat_permissions;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

class NodeAccessService
{

  public function userHasAccess(NodeInterface $node, $op, $current_user)
  {

    ddm('userHasAccess called');

    ddm('op: ' . $op);

    if ($op == 'view' || $op == 'view all revisions') {

      //ddm('op is view');

      $type = $node->getType();

      if ($type == 'islandora_object') {

        //ddm('islandora_object');
        // If there's any "visible" value in the policy set to true, allow access.
        // If not, check if the user has any of the allowed roles or users

        $manager = \Drupal::service('flat_permissions.permissions_manager');

        $nid = $node->id();

        $userid = $current_user->id();

        ddm('user id: ' . $userid);

        ddm('node id: ' . $nid);

        //ddm('node id: ' . $nid);

        $policy = $manager->fetchAccessPolicy($nid, 'read');

        if (!$policy) {
          $policy = $manager->fetchEffectiveAccessPolicy($nid, 'read');
        }

        if (!$policy) {
          //return \Drupal\Core\Access\AccessResult::allowed()->addCacheContexts(['ip', 'user']);
          return TRUE;
        } else {
          $visibility = check_visibility($policy);


          //ddm('policy');
          //ddm($policy);

          //ddm('visibility');
          //ddm($visibility);

          if ($visibility == 'visible') {
            //return \Drupal\Core\Access\AccessResult::allowed()->addCacheContexts(['ip', 'user']);
            return TRUE;
          } else {

            if ($manager->objectAndPropertiesExist($policy, 'all')) {
              if (property_exists($policy->all, "roles")) {
                $allowed_roles = $policy->all->roles;
                $user_roles = $current_user->getRoles();
                foreach ($user_roles as $user_role) {
                  if (in_array($user_role, $allowed_roles)) {
                    //return AccessResult::allowed()->addCacheContexts(['ip', 'user']);
                    return TRUE;
                  }
                }
              } elseif (property_exists($policy->all, "users")) {
                $allowed_users = $policy->all->users;
                if (in_array($current_user->id(), $allowed_users)) {
                  //return AccessResult::allowed()->addCacheContexts(['ip', 'user']);
                  return TRUE;
                }
              }
            } elseif ($manager->objectAndPropertiesExist($policy, 'types')) {
              foreach ($policy->types as $type_rule) {
                if (property_exists($type_rule, "visible")) {
                  if (property_exists($type_rule, "roles")) {
                    if ($type_rule->roles == 'none') {
                      if (property_exists($type_rule, "users"))
                        $allowed_users = $type_rule->users;
                      if (in_array($current_user->id(), $allowed_users)) {
                        //return AccessResult::allowed()->addCacheContexts(['ip', 'user']);
                        return TRUE;
                      }
                    }
                  }
                }
              }
            } elseif ($manager->objectAndPropertiesExist($policy, 'files')) {
              foreach ($policy->files as $file_rule) {
                if (property_exists($file_rule, "visible")) {
                  if (property_exists($file_rule, "roles")) {
                    if ($file_rule->roles == 'none') {
                      if (property_exists($file_rule, "users"))
                        $allowed_users = $file_rule->users;
                      if (in_array($current_user->id(), $allowed_users)) {
                        //return AccessResult::allowed()->addCacheContexts(['ip', 'user']);
                        return TRUE;
                      }
                    }
                  }
                }
              }
            }
          }

          //return \Drupal\Core\Access\AccessResult::forbidden()->addCacheContexts(['ip', 'user']);
          ddm('forbidden');
          return FALSE;
        }
      }
    }
  }
}
