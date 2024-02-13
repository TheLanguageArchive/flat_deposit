<?php

namespace Drupal\flat_deposit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\search_api\Entity\Index;


class CollectionController extends ControllerBase
{

    public function addCollection()
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

        /*         $node_type = 'flat_collection';
        $node = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->create([
                'type' => $node_type,
            ]);


        $form = \Drupal::service('entity.form_builder')
            ->getForm($node); */

        $form = \Drupal::formBuilder()->getForm('Drupal\flat_deposit\Form\CollectionAddForm');

        return $form;
    }

    public function getFedoraId()
    {
        $mapper = \Drupal::service('islandora.entity_mapper');
        $path = $mapper->getFedoraPath($entity->uuid());

        return $path;
    }

    public function addCollectionCheckAccess(Node $node, AccountInterface $account)
    {
        $node = \Drupal::routeMatch()->getParameter('node');
        $content_type = $node->bundle();
        $model = NULL;
        if ($content_type === 'islandora_object') {
            $model = $node->get('field_model')->referencedEntities()[0]->getName();
        }
        return AccessResult::allowedif($node->bundle() === 'islandora_object' && $model == "Collection" && $account->hasPermission('use deposit module'));
    }

    public function activateCollection()
    {

        // Get the current node from the route.
        $node = \Drupal::routeMatch()->getParameter('node');

        if ($node instanceof Node) {
            // Get the field values.
            $parents = $this->getFedoraId();

            return new Response(
                var_dump($parents),
                Response::HTTP_OK
            );
        }
    }

    public function activateCollectionCheckAccess(Node $node, AccountInterface $account)
    {
        $node = \Drupal::routeMatch()->getParameter('node');
        $content_type = $node->bundle();
        $model = NULL;
        if ($content_type === 'islandora_object') {
            $model = $node->get('field_model')->referencedEntities()[0]->getName();
        }
        return AccessResult::allowedif($node->bundle() === 'islandora_object' && $model == "Collection" && $account->hasPermission('use deposit module'));
    }

    public function updateCollection()
    {
    }

    public function updateCollectionCheckAccess(Node $node, AccountInterface $account)
    {
        $node = \Drupal::routeMatch()->getParameter('node');
        $content_type = $node->bundle();
        $model = NULL;
        if ($content_type === 'islandora_object') {
            $model = $node->get('field_model')->referencedEntities()[0]->getName();
        }
        return AccessResult::allowedif($node->bundle() === 'islandora_object' && $model == "Collection" && $account->hasPermission('use deposit module'));
    }
}
