<?php

namespace Drupal\bridge_subuser\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\user\Entity\User;

/**
 * Controller routines for bridge_subuser routes.
 */
class BridgeSubuserController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'bridge_subuser';
  }

  /**
   * Verify permissions and ask for conformation.
   *
   * @param string $admin_user_id
   *   Current user ID, should be a number.
   * @param string $target_user_id
   *   user ID, should be a number.
   *
   * @return array
   *   An associative array suitable for a render array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the parameters are invalid.
   */
  public function delete($admin_user_id, $target_user_id) {
    if (!is_numeric($admin_user_id) || !is_numeric($target_user_id)) {
      throw new AccessDeniedHttpException();
    }

    $thisuser = \Drupal::currentUser();
    $this_user_id = $thisuser->id();
    // Check if current user is the account owner or has admin user permission.
    if ($this_user_id != $admin_user_id && !$thisuser->hasPermission('administer users')) {
      throw new AccessDeniedHttpException();
    }

    // Check that the $target_user_id is connected to this user.
    $targetuser = User::load($target_user_id);
    $targetusers_organizer_id = $targetuser->get('field_organization_adminstrator')
      ->getValue()[0]['target_id'];
    if ($targetusers_organizer_id != $admin_user_id) {
      throw new AccessDeniedHttpException();
    }

    $username = $targetuser->getAccountName();
    $message = t("<div class='delete-actions'>Are you sure you want to delete the user <strong>@username?</strong></div>", [
      '@iserid' => $target_user_id,
      '@username' => $username,
    ]);
    $deletelink = t("<a class='delete-user' href='/user/@adminid/subuser/@targetid/deleteconfirm'>Delete</a> ", [
      '@adminid' => $admin_user_id,
      '@targetid' => $target_user_id,
    ]);
    $cancellink = t("<a class='delete-cancel' href='/user/@adminid/subscriptions'>Cancel</a>", [
      '@adminid' => $admin_user_id
    ]);

    $render_array['bridge_subuser_delete'] = [
      'first_para' => [
        '#type' => 'markup',
        '#markup' => $message,
      ],
      'actions' => [
        '#type' => 'markup',
        '#markup' => $deletelink . $cancellink,
      ],

    ];
    return $render_array;
  }

  /**
   * Execute the confirmed delete user request.
   *
   * @param string $admin_user_id
   *   Current user ID, should be a number.
   * @param string $target_user_id
   *   user ID, should be a number.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects user to new url.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the parameters are invalid.
   */
  public function deleteconfirm($admin_user_id, $target_user_id) {
    if (!is_numeric($admin_user_id) || !is_numeric($target_user_id)) {
      throw new AccessDeniedHttpException();
    }

    $thisuser = \Drupal::currentUser();
    $this_user_id = $thisuser->id();
    // Check if current user is the account owner or has admin user permission.
    if ($this_user_id != $admin_user_id && !$thisuser->hasPermission('administer users')) {
      throw new AccessDeniedHttpException();
    }

    // Check that the $target_user_id is connected to this current user.
    $targetuser = User::load($target_user_id);
    $targetusers_organizer_id = $targetuser->get('field_organization_adminstrator')
      ->getValue()[0]['target_id'];
    if ($targetusers_organizer_id != $admin_user_id) {
      throw new AccessDeniedHttpException();
    }

    // Delete user and redirect.
    user_delete($target_user_id);
    drupal_set_message(t('User @id deleted', ['@id' => $target_user_id)]), 'status', TRUE);
    return $this->redirect('view.user_subscriptions.page_1', ['arg_0' => $admin_user_id]);
  }

}
