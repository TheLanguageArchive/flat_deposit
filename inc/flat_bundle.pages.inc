<?php
/**
 * Created by PhpStorm.
 * User: danrhe
 * Date: 03/08/16
 * Time: 16:14
 */

/**
 * Menu callback; displays a Drupal page containing recent upload bundles of a given user.
 */
function flat_bundle_page_user($account) {
    $user = \Drupal::currentUser();

    // @FIXME
// drupal_set_title() has been removed. There are now a few ways to set the title
// dynamically, depending on the situation.
//
//
// @see https://www.drupal.org/node/2067859
// drupal_set_title($title = t("@name's upload bundle", array('@name' => format_username($account))), PASS_THROUGH);


    $build = array();
    $build['#title'] = t("@name's upload bundle", array('@name' => format_username($account)));

    $query = \Drupal::database()->select('node', 'n')->extend('PagerDefault');
    // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// $nids = $query
//         ->fields('n', array('nid', 'sticky', 'created'))
//         ->condition('type', 'flat_bundle')
//         ->condition('uid', $account->uid)
//         ->condition('status', 1)
//         ->orderBy('sticky', 'DESC')
//         ->orderBy('created', 'DESC')
//         ->limit(variable_get('default_nodes_main', 10))
//         ->addTag('node_access')
//         ->execute()
//         ->fetchCol();
    $default_nodes_main = \Drupal::config('flat_deposit.settings')->get('default_nodes_main') ?? 10;
    $nids = $query
            ->fields('n', array('nid', 'sticky', 'created'))
            ->condition('type', 'flat_bundle')
            ->condition('uid', $account->uid)
            ->condition('status', 1)
            ->orderBy('sticky', 'DESC')
            ->orderBy('created', 'DESC')
            ->limit($default_nodes_main)
            ->addTag('node_access')
            ->execute()
            ->fetchCol();

    if (!empty($nids)) {
        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
        $build += node_view_multiple($nodes);
        $build['pager'] = array(
            '#theme' => 'pager',
            '#weight' => 5,
        );
    }
    else {
        if ($account->uid == $user->uid) {
            \Drupal::messenger()->addMessage(t('You have not created any upload bundle.'));
        }
        else {
            // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// \Drupal::messenger()->addMessage(t('!author has not created any upload bundle entries.', array('!author' => theme('username', array('account' => $account)))));
            \Drupal::messenger()->addMessage(t('!author has not created any upload bundle entries.', array(
                '!author' => drupal_render([
                    '#theme' => 'username',
                    '#account' => $account
                ])
            )));
        }
    }

    return $build;
}
