<?php

namespace Drupal\flat_deposit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Access\AccessResult;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;

class BundleController extends ControllerBase
{

    public function addBundle()
    {

        /*         $user = \Drupal::currentUser();
        $userId = $user->id();
        $userName = $user->getAccountName();
        $pid = null;

        // Query all flat_collection nodes that are owned by the user and have no empty fedora_fid (flat_fid) value.
        // Resulting nodes will be added as options with the node's nid as option-key and node's title as option-labels.
        module_load_include('inc', 'flat_deposit', 'inc/class.FlatCollection');
        $user_collection_nodes = \FlatCollection::getUserCollectionNodes($userId, $pid);

        //dd($user_collection_nodes);

        if (!empty($user_collection_nodes['node']) and count($user_collection_nodes['node']) == 1) {
            foreach ($user_collection_nodes['node'] as $node) {
                $parent_nid = $node->nid;

                $parent_node = \Drupal::entityTypeManager()->getStorage('node')->load($parent_nid);
                $parent_title = $parent_node->title;
                $custom = ['parent_nid' => $parent_nid, 'parent_title' => $parent_title];
            }

        } else {

            $custom = null;
            \Drupal::messenger()->addMessage(t('No active parent collections available.'), 'warning');

        } */

        module_load_include('inc', 'node', 'node.pages');

        $node_type = 'flat_bundle';
        $node = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->create([
                'type' => $node_type,
            ]);


        $form = \Drupal::service('entity.form_builder')
            ->getForm($node);

        return $form;
    }

    public function enterMetadata()
    {
        module_load_include('inc', 'node', 'node.pages');

        $form = \Drupal::formBuilder()->getForm('Drupal\flat_deposit\Form\BundleEditCmdiForm');

        return $form;
    }

    public function addBundleCheckAccess(Node $node, AccountInterface $account)
    {
        return AccessResult::allowedif($node->bundle() === 'flat_collection' && $account->hasPermission('use deposit module'));
    }

    public function updateBundle()
    {
    }

    public function updateBundleCheckAccess(Node $node, AccountInterface $account)
    {
        return AccessResult::allowedif($node->bundle() === 'flat_bundle' && $account->hasPermission('use deposit module'));
    }

    public function updateMetadata()
    {
    }

    public function enterMetadataCheckAccess(Node $node, AccountInterface $account)
    {
        return AccessResult::allowedif($node->bundle() === 'flat_bundle' && $account->hasPermission('use deposit module'));
    }

    public function updateMetadataCheckAccess(Node $node, AccountInterface $account)
    {
        return AccessResult::allowedif($node->bundle() === 'flat_bundle' && $account->hasPermission('use deposit module'));
    }
}
