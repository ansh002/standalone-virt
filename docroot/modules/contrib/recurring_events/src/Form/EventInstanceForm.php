<?php

namespace Drupal\recurring_events\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Form controller for the eventinstance entity edit forms.
 *
 * @ingroup recurring_events
 */
class EventInstanceForm extends ContentEntityForm {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('current_user')
    );
  }

  /**
   * Construct an EventInstanceForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, Messenger $messenger, AccountProxyInterface $current_user) {
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->time = $time;
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /* @var $entity \Drupal\recurring_events\Entity\EventInstance */
    $event = $this->entity;

    $form['notifications'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => [
        'class' => ['event-notifications'],
      ],
    ];

    $form['notifications']['edit_message'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['event-notification-message'],
      ],
      'title' => [
        '#type' => 'markup',
        '#prefix' => '<h3 class="event-notice-title">',
        '#markup' => $this->t('Data Inheritance'),
        '#suffix' => '</h3>',
      ],
      'message' => [
        '#type' => 'markup',
        '#prefix' => '<p class="event-message">',
        '#markup' => $this->t('Some of the data for event instances is inherited from the event series that the instance belongs to. @link.', [
          '@link' => $event->getEventSeries()->toLink($this->t('Edit the series'), 'edit-form')->toString(),
        ]),
        '#suffix' => '</p>',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();

    // Save as a new revision if requested to do so.
    if (!$form_state->isValueEmpty('revision') && $form_state->getValue('revision') != FALSE) {
      $entity->setNewRevision();

      // If a new revision is created, save the current user as revision author.
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionUserId($this->currentUser->id());
    }
    else {
      $entity->setNewRevision(FALSE);
    }

    parent::save($form, $form_state);

    if ($entity->isDefaultTranslation()) {
      $message = $this->t('Event instance of %label has been saved.', [
        '%label' => $entity->getEventSeries()->title->value,
      ]);
    }
    else {
      $message = $this->t('@language translation of the Event Instance %label has been saved.', [
        '@language' => $entity->language()->getName(),
        '%label' => $entity->getUntranslated()->getEventSeries()->title->value,
      ]);
    }
    $this->messenger->addMessage($message);

    $form_state->setRedirect('entity.eventinstance.canonical', ['eventinstance' => $entity->id()]);
  }

}
