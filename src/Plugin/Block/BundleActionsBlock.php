<?php

namespace Drupal\flat_deposit\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides Action buttons for FLAT Deposit Bundles
 *
 * @Block(
 *   id = "bundle_actions_block",
 *   admin_label = @Translation("Bundle Actions block"),
 *   category = @Translation("Forms")
 * )
 */
class BundleActionsBlock extends BlockBase
{

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        // Return the form @ Form/BundleActionsBlockForm.php
        return \Drupal::formBuilder()->getForm('Drupal\flat_deposit\Form\BundleActionsBlockForm');
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account)
    {
        return AccessResult::allowedIfHasPermission($account, 'use deposit module');
    }
}
