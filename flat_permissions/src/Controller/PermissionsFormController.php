<?php

namespace Drupal\flat_permissions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;

class PermissionsFormController extends ControllerBase
{

    public function editPermissions()
    {
        module_load_include('inc', 'node', 'node.pages');

        $form = \Drupal::formBuilder()->getForm('Drupal\flat_permissions\Form\EditPermissionsForm');

        return $form;
    }

    public function editPermissionsCheckAccess(Node $node, AccountInterface $account)
    {
        $node = \Drupal::routeMatch()->getParameter('node');
        $content_type = $node->bundle();
        $model = NULL;
        if ($content_type === 'islandora_object') {
            $model = $node->get('field_model')->referencedEntities()[0]->getName();
        }
        return AccessResult::allowedif($content_type === 'islandora_object' && ($model == "Collection" || $model == "Compound Object") && $account->hasPermission('use deposit module')); // TODO add permission
    }
}
