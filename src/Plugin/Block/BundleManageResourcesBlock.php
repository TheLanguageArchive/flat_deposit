<?php

namespace Drupal\flat_deposit\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides form that displays resources to be added to a bundle
 *
 * @Block(
 *   id = "bundle_manage_resources_block",
 *   admin_label = @Translation("Bundle Manage Resources block"),
 *   category = @Translation("Forms")
 * )
 */
class BundleManageResourcesBlock extends BlockBase
{

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        // Return the form @ Form/BundleManageResourcesBlockForm.php
        return \Drupal::formBuilder()->getForm('Drupal\flat_deposit\Form\BundleManageResourcesBlockForm');
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account)
    {
        return AccessResult::allowedIfHasPermission($account, 'use deposit module');
    }
}
