<?php

namespace Drupal\bridge_subuser\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\user\Entity\User;

/**
 * Provides a 'Notification for Subuser Use Count' block.
 *
 * @Block(
 *   id = "bridge_subuser_count",
 *   admin_label = @Translation("Subuser Use Count")
 * )
 */
class SubuserCountBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $current_path = \Drupal::service('path.current')->getPath();
    $path_args = explode('/', $current_path);

    $query = \Drupal::entityQuery('user');
    $query->condition('status', 1);
    $query->condition('field_organization_adminstrator', $path_args[2]);
    $entity_ids = $query->execute();
    $subscription_count = count($entity_ids) + 1;
    $user_source = User::load($path_args[2]);
    $max_subscriptions = $user_source->get('field_max_user_accounts')->value;
    if ($subscription_count >= $max_subscriptions) {
      $message = t(
        '(Your account has reached the maximum number of users.) <a href="/subscription-rates-general-users">Upgrade your subscription</a>'
      );
      $message .= t('<h3 class="disabled">+ Add User</h3>');
    }
    else {
      $message = t(
        'There are @subscription_count active users on your account of @max_subscriptions available', [
          '@subscription_count' => $subscription_count,
          '@max_subscriptions' => $max_subscriptions,
        ]
      );
      $message .= t(
        '<h3><a href="/user/@admin_id/subscriptions/add">+ Add User</a></h3>', ['@admin_id' => $path_args[2]]
      );
    }

    return [
      '#markup' => $message,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
