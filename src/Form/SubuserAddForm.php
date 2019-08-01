<?php

namespace Drupal\bridge_subuser\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserStorageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\user\Entity\User;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a singe text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class SubuserAddForm extends FormBase {

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a UserPasswordForm object.
   *
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(UserStorageInterface $user_storage, LanguageManagerInterface $language_manager) {
    $this->userStorage = $user_storage;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('user'),
      $container->get('language_manager')
    );
  }

  /**
   * Build the simple form.
   *
   * A build form method constructs an array that defines how markup and
   * other form elements are included in an HTML form.
   *
   * @param array $form
   *   Default form array structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object containing current form state.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Provide Email and Name for the new user'),
    ];

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#description' => '',
      '#required' => TRUE,
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name / Surname'),
      '#description' => '',
      '#required' => TRUE,
    ];
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('E-mail address'),
      '#size' => 60,
      '#maxlength' => max(USERNAME_MAX_LENGTH, Email::EMAIL_MAX_LENGTH),
      '#required' => TRUE,
      '#attributes' => [
        'autocorrect' => 'off',
        'autocapitalize' => 'off',
        'spellcheck' => 'false',
        'autofocus' => 'autofocus',
      ],
      '#description' => $this->t('A valid email address. All emails from the system will be sent to this address. The email address is not made public and will only be used if you wish to receive a new password or wish to receive certain news or notifications by email.'),
    ];
    $form['footer'] = [
      '#type' => 'item',
      '#markup' => $this->t('Additional fields will be inherited from this account'),
    ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create New Account'),
    ];

    return $form;
  }

  /**
   * Getter method for Form ID.
   *
   * The form ID is used in implementations of hook_form_alter() to allow other
   * modules to alter the render array built by this form controller. It must be
   * unique site wide. It normally starts with the providing module's name.
   *
   * @return string
   *   The unique ID of the form defined by this class.
   */
  public function getFormId() {
    return 'bridge_subuser_add_form';
  }

  /**
   * Implements form validation.
   *
   * The validateForm method is the default method called to validate input on
   * a form.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

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
      $form_state->setErrorByName('subscriptions', $this->t('(Your account has reached the maximum number of users.) <a href="/subscription-rates-general-users">Upgrade your subscription</a>'));
    }

    $first_name = $form_state->getValue('first_name');
    $last_name = $form_state->getValue('last_name');
    $email = $form_state->getValue('email');
    $username = $first_name . ' ' . $last_name;
    $users = $this->userStorage->loadByProperties(['mail' => $email]);
    if (!empty($users)) {
      $form_state->setErrorByName('email', $this->t('This email is already in use.'));
    }

    $users = $this->userStorage->loadByProperties(['name' => $username]);
    if (!empty($users)) {
      $form_state->setErrorByName('name', $this->t('This username is already in use.'));
    }
  }

  /**
   * Implements a form submit handler.
   *
   * The submitForm method is the default method called for any submit elements.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   *
   * @throws
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $first_name = $form_state->getValue('first_name');
    $last_name = $form_state->getValue('last_name');
    $email = $form_state->getValue('email');
    $username = $first_name . ' ' . $last_name;

    $current_path = \Drupal::service('path.current')->getPath();
    $path_args = explode('/', $current_path);

    $user_source = User::load($path_args[2]);
    $user_object = $user_source->createDuplicate();
    $user_object->enforceIsNew();
    $user_object->set('created', time());
    $user_object->setLastLoginTime(time());
    $user_object->setLastAccessTime(time());
    $user_object->set('field_first_name', $first_name);
    $user_object->set('field_last_name', $last_name);
    $user_object->set('field_organization_adminstrator', $path_args[2]);
    $user_object->setPassword("password");
    $user_object->setEmail($email);
    $user_object->setUsername($username);
    $userRolesArray = $user_object->getRoles();
    foreach ($userRolesArray as $key => $role) {
      $user_object->removeRole($role);
    }
    $user_object->addRole('user');
    $user_object->activate();
    $user_object->set('init', $email);

    $user_object->save();
    _user_mail_notify('register_admin_created', $user_object);

    drupal_set_message($this->t('New User Added: %thename.', ['%thename' => $username]));
    $form_state->setRedirect('view.user_subscriptions.page_1', ['arg_0' => $path_args[2]]);

  }

}
