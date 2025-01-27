<?php

namespace Drupal\recurring_events_registration\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\recurring_events_registration\RegistrationCreationService;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Form controller for Registrant edit forms.
 *
 * @ingroup recurring_events_registration
 */
class RegistrantForm extends ContentEntityForm {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The creation service.
   *
   * @var \Drupal\recurring_events_registration\RegistrationCreationService
   */
  protected $creationService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('recurring_events_registration.creation_service'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('cache_tags.invalidator')
    );
  }

  /**
   * Construct an RegistrantForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \Drupal\recurring_events_registration\RegistrationCreationService $creation_service
   *   The registrant creation service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    Messenger $messenger,
    RegistrationCreationService $creation_service,
    AccountProxyInterface $current_user,
    ConfigFactory $config,
    EntityFieldManager $field_manager,
    RouteMatchInterface $route_match,
    EntityTypeManagerInterface $entity_type_manager,
    CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->messenger = $messenger;
    $this->creationService = $creation_service;
    $this->currentUser = $current_user;
    $this->config = $config;
    $this->fieldManager = $field_manager;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /* @var $entity \Drupal\recurring_events_registration\Entity\Registrant */
    $entity = $this->entity;

    $event_instance = $this->routeMatch->getParameter('eventinstance');
    $editing = !$entity->isNew();

    if (empty($event_instance)) {
      throw new NotFoundHttpException();
    }

    // Use the registration creation service to grab relevant data.
    $this->creationService->setEventInstance($event_instance);
    $availability = $this->creationService->retrieveAvailability();
    $waitlist = $this->creationService->hasWaitlist();
    $registration_open = $this->creationService->registrationIsOpen();
    $reg_type = $this->creationService->getRegistrationType();

    $form['notifications'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => [
        'class' => ['registration-notifications'],
      ],
      // Do not show notifications if we are in edit mode.
      '#printed' => $editing,
    ];

    // If space has run out, but there is a waitlist.
    $form['notifications']['waitlist_notification'] = [
      '#type' => 'container',
      '#access' => ($availability == 0 && $waitlist && $registration_open),
      '#attributes' => [
        'class' => ['registration-notification-message'],
      ],
      'title' => [
        '#type' => 'markup',
        '#prefix' => '<h3 class="registration-notice-title">',
        '#markup' => $this->t('Registration full.'),
        '#suffix' => '</h3>',
      ],
      'message' => [
        '#type' => 'markup',
        '#prefix' => '<p class="registration-message">',
        '#markup' => $this->t('Unfortunately, there are no spaces left for this @type. However, we can add you to the waitlist. If a space becomes available, the first registrant on the waitlist will be automatically registered.', [
          '@type' => $reg_type === 'series' ? 'series' : 'event',
        ]),
        '#suffix' => '</p>',
      ],
    ];

    // If space has run out, but there is no waitlist.
    $form['notifications']['availability_notification'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['registration-notification-message'],
      ],
      '#access' => ($availability == 0 && !$waitlist && $registration_open),
      'title' => [
        '#type' => 'markup',
        '#prefix' => '<h3 class="registration-notice-title">',
        '#markup' => $this->t('Registration full.'),
        '#suffix' => '</h3>',
      ],
      'message' => [
        '#type' => 'markup',
        '#prefix' => '<p class="registration-message">',
        '#markup' => $this->t('Unfortunately, this @type is at capacity and there are no spaces available.', [
          '@type' => $reg_type === 'series' ? 'series' : 'event',
        ]),
        '#suffix' => '</p>',
      ],
    ];

    // If registration is not open.
    $form['notifications']['registration_closed'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['registration-notification-message'],
      ],
      '#access' => !$registration_open,
      'title' => [
        '#type' => 'markup',
        '#prefix' => '<h3 class="registration-notice-title">',
        '#markup' => $this->t('Registration is closed.'),
        '#suffix' => '</h3>',
      ],
      'message' => [
        '#type' => 'markup',
        '#prefix' => '<p class="registration-message">',
        '#markup' => $this->t('Unfortunately, registration for this @type is closed.', [
          '@type' => $reg_type === 'series' ? 'series' : 'event',
        ]),
        '#suffix' => '</p>',
      ],
    ];

    if ($this->config('recurring_events_registration.registrant.config')->get('show_capacity')) {
      $form['availability'] = [
        '#type' => 'markup',
        '#prefix' => '<span class="registration-availability">',
        '#markup' => $this->t('Spaces Available: @availability', [
          '@availability' => ($availability == -1) ? $this->t('Unlimited') : $availability,
        ]),
        '#suffix' => '</span>',
        '#weight' => -99,
      ];
    }

    $add_to_waitlist = ($availability == 0 && $waitlist) ? 1 : 0;

    $form['add_to_waitlist'] = [
      '#type' => 'hidden',
      '#value' => $add_to_waitlist,
      '#weight' => 98,
    ];

    $link = $event_instance->toLink($this->t('Go Back to Event Details'));

    $form['back_link'] = [
      '#type' => 'markup',
      '#prefix' => '<span class="registration-back-link">',
      '#markup' => $link->toString(),
      '#suffix' => '</span>',
      '#weight' => 100,
    ];

    if ($this->currentUser->hasPermission('modify registrant waitlist') && $waitlist) {
      $form['add_to_waitlist']['#type'] = 'select';
      $form['add_to_waitlist']['#options'] = [
        1 => $this->t('Yes'),
        0 => $this->t('No'),
      ];
      $form['add_to_waitlist']['#title'] = $this->t('Add user to waitlist');
      $value = !$entity->isNew() ? $entity->getWaitlist() : $add_to_waitlist;
      $form['add_to_waitlist']['#default_value'] = $value;
      unset($form['add_to_waitlist']['#value']);
    }

    $this->hideFormFields($form, $form_state);

    // Because the form gets modified depending on the number of registrations
    // we need to prevent caching.
    $form['#cache'] = ['max-age' => 0];
    $form_state->setCached(FALSE);
    return $form;
  }

  /**
   * Hide form fields depending on registration status.
   *
   * @var array $form
   *   The form configuration array.
   * @var Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  protected function hideFormFields(array &$form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\recurring_events_registration\Entity\Registrant */
    $entity = $this->entity;
    $new = $entity->isNew();

    $form_fields = $this->fieldManager->getFieldDefinitions('registrant', $this->entity->getBundle());

    $availability = $this->creationService->retrieveAvailability();
    $waitlist = $this->creationService->hasWaitlist();
    $registration_open = $this->creationService->registrationIsOpen();

    // Prevent the form being displayed if registration is closed, or there are
    // no spaces left, and no waitlist.
    if (($availability === 0 && !$waitlist) || !$registration_open) {
      foreach ($form_fields as $field_name => $field) {
        if (isset($form[$field_name]) && $new) {
          $form[$field_name]['#printed'] = TRUE;
        }
      }
      $form['actions']['#printed'] = TRUE;
      if (isset($form['availability'])) {
        $form['availability']['#printed'] = TRUE;
      }
      if (isset($form['add_to_waitlist'])) {
        $form['add_to_waitlist']['#printed'] = TRUE;
      }
    }

    if (!$this->currentUser->hasPermission('modify registrant author')) {
      $form['user_id']['#access'] = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /* @var $entity \Drupal\omega_events\Entity\Registrant */
    $entity = $this->entity;

    // Only perform the checks if the entity is new.
    if ($entity->isNew()) {

      $event_instance = $this->routeMatch->getParameter('eventinstance');
      $event_series = $event_instance->getEventSeries();

      // Use the registration creation service to grab relevant data.
      $this->creationService->setEventInstance($event_instance);
      // Just to be sure we have a fresh copy of the event series.
      $this->creationService->setEventSeries($event_series);

      $availability = $this->creationService->retrieveAvailability();
      $waitlist = $this->creationService->hasWaitlist();
      $registration_open = $this->creationService->registrationIsOpen();

      $add_to_waitlist = $form_state->getValue('add_to_waitlist');

      // Registration has closed.
      if (!$registration_open) {
        $form_state->setError($form, $this->t('Unfortunately, registration has closed.'));
      }
      // Capacity is full, there is a waitlist, but user was not being added to
      // the waitlist.
      elseif (!$add_to_waitlist && $availability == 0 && $waitlist) {
        $form_state->setError($form, $this->t('Unfortunately, this event is now full and you must join the waitlist.'));
      }
      // There are no spaces left, and there is no waitlist.
      elseif ($availability == 0 && !$waitlist) {
        $form_state->setError($form, $this->t('Unfortunately, this event is now full.'));
      }
    }
    else {
      if ($this->currentUser->hasPermission('modify registrant waitlist')) {
        // Update the user's waitlist value.
        $entity->setWaitlist($form_state->getValue('add_to_waitlist'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $event_instance = $this->routeMatch->getParameter('eventinstance');
    $event_series = $event_instance->getEventSeries();

    // Use the registration creation service to grab relevant data.
    $this->creationService->setEventInstance($event_instance);
    // Just to be sure we have a fresh copy of the event series.
    $this->creationService->setEventSeries($event_series);

    $availability = $this->creationService->retrieveAvailability();
    $waitlist = $this->creationService->hasWaitlist();
    $registration_open = $this->creationService->registrationIsOpen();
    $reg_type = $this->creationService->getRegistrationType();
    $registration = $this->creationService->hasRegistration();

    $form_state->setRedirect('entity.registrant.add_form', ['eventinstance' => $event_instance->id()]);

    if ($registration && $registration_open && ($availability > 0 || $waitlist)) {
      $add_to_waitlist = (int) $form_state->getValue('add_to_waitlist');
      $this->entity->setEventSeries($event_series);
      $this->entity->setEventInstance($event_instance);
      $this->entity->setWaitlist($add_to_waitlist);
      $this->entity->setRegistrationType($reg_type);
      $status = parent::save($form, $form_state);

      switch ($status) {
        case SAVED_NEW:
          $message = $this->t('Registrant successfully created.');
          if ($add_to_waitlist) {
            $message = $this->t('Successfully registered to the waitlist.');
          }
          break;

        default:
          $message = $this->t('Registrant successfully updated.');
          if ($add_to_waitlist) {
            $message = $this->t('Successfully updated waitlist registrant.');
          }
          break;
      }

      $this->messenger->addMessage($message);

      // Invalidate tags to ensure that views count fields are updated.
      $tags = [];
      switch ($this->creationService->getRegistrationType()) {
        case 'series':
          $tags[] = 'eventseries:' . $event_series->id();
          break;

        case 'instance':
        default:
          $tags[] = 'eventinstance:' . $event_instance->id();
          break;
      }
      $this->cacheTagsInvalidator->invalidateTags($tags);
    }
    else {
      $this->messenger->addMessage($this->t('Unfortunately, registration is not available at this time.'));
    }

  }

}
