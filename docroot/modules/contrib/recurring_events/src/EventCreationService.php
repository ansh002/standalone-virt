<?php

namespace Drupal\recurring_events;

use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\recurring_events\Entity\EventSeries;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\recurring_events\Entity\EventInstance;
use Drupal\field_inheritance\Entity\FieldInheritanceInterface;

/**
 * EventCreationService class.
 */
class EventCreationService {

  use StringTranslationTrait;

  /**
   * The translation interface.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  private $translation;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The field type plugin manager.
   *
   * @var Drupal\Core\Field\FieldTypePluginManager
   */
  protected $fieldTypePluginManager;

  /**
   * The entity field manager.
   *
   * @var Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The key value storage service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactory
   */
  protected $keyValueStore;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation interface.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \Drupal\Core\Field\FieldTypePluginManager $field_type_plugin_manager
   *   The field type plugin manager.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactory $key_value
   *   The key value storage service.
   */
  public function __construct(TranslationInterface $translation, Connection $database, LoggerChannelFactoryInterface $logger, Messenger $messenger, FieldTypePluginManager $field_type_plugin_manager, EntityFieldManager $entity_field_manager, ModuleHandler $module_handler, EntityTypeManagerInterface $entity_type_manager, KeyValueFactory $key_value) {
    $this->translation = $translation;
    $this->database = $database;
    $this->loggerFactory = $logger->get('recurring_events');
    $this->messenger = $messenger;
    $this->fieldTypePluginManager = $field_type_plugin_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->keyValueStore = $key_value;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('entity_field.manager'),
      $container->get('module_handler'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Check whether there have been form recurring configuration changes.
   *
   * @param Drupal\recurring_events\Entity\EventSeries $event
   *   The stored event series entity.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of an updated event series entity.
   *
   * @return bool
   *   TRUE if recurring config changes, FALSE otherwise.
   */
  public function checkForFormRecurConfigChanges(EventSeries $event, FormStateInterface $form_state) {
    $entity_config = $this->convertEntityConfigToArray($event);
    $form_config = $this->convertFormConfigToArray($form_state);
    return !(serialize($entity_config) === serialize($form_config));
  }

  /**
   * Check whether there have been original recurring configuration changes.
   *
   * @param Drupal\recurring_events\Entity\EventSeries $event
   *   The stored event series entity.
   * @param Drupal\recurring_events\Entity\EventSeries $original
   *   The original stored event series entity.
   *
   * @return bool
   *   TRUE if recurring config changes, FALSE otherwise.
   */
  public function checkForOriginalRecurConfigChanges(EventSeries $event, EventSeries $original) {
    $entity_config = $this->convertEntityConfigToArray($event);
    $original_config = $this->convertEntityConfigToArray($original);
    return !(serialize($entity_config) === serialize($original_config));
  }

  /**
   * Converts an EventSeries entity's recurring configuration to an array.
   *
   * @param Drupal\recurring_events\Entity\EventSeries $event
   *   The stored event series entity.
   *
   * @return array
   *   The recurring configuration as an array.
   */
  public function convertEntityConfigToArray(EventSeries $event) {
    $config = [];
    $config['type'] = $event->getRecurType();
    $config['excluded_dates'] = $event->getExcludedDates();
    $config['included_dates'] = $event->getIncludedDates();

    if ($config['type'] === 'custom') {
      $config['custom_dates'] = $event->getCustomDates();
    }
    else {
      $field_definition = $this->fieldTypePluginManager->getDefinition($config['type']);
      $field_class = $field_definition['class'];
      $config += $field_class::convertEntityConfigToArray($event);
    }

    $this->moduleHandler->alter('recurring_events_entity_config_array', $config);

    return $config;
  }

  /**
   * Converts a form state object's recurring configuration to an array.
   *
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of an updated event series entity.
   *
   * @return array
   *   The recurring configuration as an array.
   */
  public function convertFormConfigToArray(FormStateInterface $form_state) {
    $config = [];

    $user_timezone = new \DateTimeZone(date_default_timezone_get());
    $utc_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $user_input = $form_state->getUserInput();

    $config['type'] = $user_input['recur_type'];

    $config['excluded_dates'] = [];
    if (!empty($user_input['excluded_dates'])) {
      $config['excluded_dates'] = $this->getDatesFromForm($user_input['excluded_dates']);
    }

    $config['included_dates'] = [];
    if (!empty($user_input['included_dates'])) {
      $config['included_dates'] = $this->getDatesFromForm($user_input['included_dates']);
    }

    if ($config['type'] === 'custom') {
      foreach ($user_input['custom_date'] as $custom_date) {
        $start_date = $end_date = NULL;

        if (!empty($custom_date['value']['date'])
          && !empty($custom_date['value']['time'])
          && !empty($custom_date['end_value']['date'])
          && !empty($custom_date['end_value']['time'])) {

          // For some reason, sometimes we do not receive seconds from the
          // date range picker.
          if (strlen($custom_date['value']['time']) == 5) {
            $custom_date['value']['time'] .= ':00';
          }
          if (strlen($custom_date['end_value']['time']) == 5) {
            $custom_date['end_value']['time'] .= ':00';
          }

          $start_timestamp = implode('T', $custom_date['value']);
          $start_date = DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $start_timestamp, $user_timezone);
          // Convert the DateTime object back to UTC timezone.
          $start_date->setTimezone($utc_timezone);

          $end_timestamp = implode('T', $custom_date['end_value']);
          $end_date = DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $end_timestamp, $user_timezone);
          // Convert the DateTime object back to UTC timezone.
          $end_date->setTimezone($utc_timezone);

          $config['custom_dates'][] = [
            'start_date' => $start_date,
            'end_date' => $end_date,
          ];
        }
      }
    }
    else {
      $field_definition = $this->fieldTypePluginManager->getDefinition($config['type']);
      $field_class = $field_definition['class'];
      $config += $field_class::convertFormConfigToArray($form_state);
    }

    $this->moduleHandler->alter('recurring_events_form_config_array', $config);

    return $config;
  }

  /**
   * Build diff array between stored entity and form state.
   *
   * @param Drupal\recurring_events\Entity\EventSeries $event
   *   The stored event series entity.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   (Optional) The form state of an updated event series entity.
   * @param Drupal\recurring_events\Entity\EventSeries $edited
   *   (Optional) The edited event series entity.
   *
   * @return array
   *   An array of differences.
   */
  public function buildDiffArray(EventSeries $event, FormStateInterface $form_state = NULL, EventSeries $edited = NULL) {
    $diff = [];

    $entity_config = $this->convertEntityConfigToArray($event);
    $form_config = [];

    if (!is_null($form_state)) {
      $form_config = $this->convertFormConfigToArray($form_state);
    }
    if (!is_null($edited)) {
      $form_config = $this->convertEntityConfigToArray($edited);
    }

    if (empty($form_config)) {
      return $diff;
    }

    if ($entity_config['type'] !== $form_config['type']) {
      $diff['type'] = [
        'label' => $this->translation->translate('Recur Type'),
        'stored' => $entity_config['type'],
        'override' => $form_config['type'],
      ];
    }
    else {
      if ($entity_config['excluded_dates'] !== $form_config['excluded_dates']) {
        $entity_dates = $this->buildDateString($entity_config['excluded_dates']);
        $config_dates = $this->buildDateString($form_config['excluded_dates']);
        $diff['excluded_dates'] = [
          'label' => $this->translation->translate('Excluded Dates'),
          'stored' => $entity_dates,
          'override' => $config_dates,
        ];
      }
      if ($entity_config['included_dates'] !== $form_config['included_dates']) {
        $entity_dates = $this->buildDateString($entity_config['included_dates']);
        $config_dates = $this->buildDateString($form_config['included_dates']);
        $diff['included_dates'] = [
          'label' => $this->translation->translate('Included Dates'),
          'stored' => $entity_dates,
          'override' => $config_dates,
        ];
      }

      if ($entity_config['type'] === 'custom') {
        if ($entity_config['custom_dates'] !== $form_config['custom_dates']) {
          $stored_start_ends = $overridden_start_ends = [];

          foreach ($entity_config['custom_dates'] as $date) {
            if (!empty($date['start_date']) && !empty($date['end_date'])) {
              $stored_start_ends[] = $date['start_date']->format('Y-m-d h:ia') . ' - ' . $date['end_date']->format('Y-m-d h:ia');
            }
          }

          foreach ($form_config['custom_dates'] as $date) {
            if (!empty($date['start_date']) && !empty($date['end_date'])) {
              $overridden_start_ends[] = $date['start_date']->format('Y-m-d h:ia') . ' - ' . $date['end_date']->format('Y-m-d h:ia');
            }
          }

          $diff['custom_dates'] = [
            'label' => $this->translation->translate('Custom Dates'),
            'stored' => implode(', ', $stored_start_ends),
            'override' => implode(', ', $overridden_start_ends),
          ];
        }
      }
      else {
        $field_definition = $this->fieldTypePluginManager->getDefinition($entity_config['type']);
        $field_class = $field_definition['class'];
        $diff += $field_class::buildDiffArray($entity_config, $form_config);
      }
    }

    $this->moduleHandler->alter('recurring_events_diff_array', $diff);

    return $diff;
  }

  /**
   * Create an event based on the form submitted values.
   *
   * @param Drupal\recurring_events\Entity\EventSeries $event
   *   The stored event series entity.
   * @param Drupal\recurring_events\Entity\EventSeries $original
   *   The original, unsaved event series entity.
   */
  public function saveEvent(EventSeries $event, EventSeries $original = NULL) {
    // We want to always create instances if this is a brand new series.
    if ($event->isNew()) {
      $create_instances = TRUE;
    }
    else {
      // If there are date differences, we need to clear out the instances.
      $create_instances = $this->checkForOriginalRecurConfigChanges($event, $original);
      if ($create_instances) {
        // Allow other modules to react prior to the deletion of all instances.
        $this->moduleHandler->invokeAll('recurring_events_save_pre_instances_deletion', [$event, $original]);

        // Find all the instances and delete them.
        $instances = $event->event_instances->referencedEntities();
        if (!empty($instances)) {
          foreach ($instances as $instance) {
            // Allow other modules to react prior to deleting a specific
            // instance after a date configuration change.
            $this->moduleHandler->invokeAll('recurring_events_save_pre_instance_deletion', [$event, $instance]);

            $instance->delete();

            // Allow other modules to react after deleting a specific instance
            // after a date configuration change.
            $this->moduleHandler->invokeAll('recurring_events_save_post_instance_deletion', [$event, $instance]);
          }
          $this->messenger->addStatus($this->translation->translate('A total of %count existing event instances were removed', [
            '%count' => count($instances),
          ]));
        }

        // Allow other modules to react after the deletion of all instances.
        $this->moduleHandler->invokeAll('recurring_events_save_post_instances_deletion', [$event, $original]);
      }
    }

    // Only create instances if date changes have been made or the event is new.
    if ($create_instances) {
      $this->createInstances($event);
    }
  }

  /**
   * Create the event instances from the form state.
   *
   * @param Drupal\recurring_events\Entity\EventSeries $event
   *   The stored event series entity.
   */
  public function createInstances(EventSeries $event) {
    $form_data = $this->convertEntityConfigToArray($event);
    $event_instances = [];

    if (!empty($form_data['type'])) {
      if ($form_data['type'] === 'custom') {
        if (!empty($form_data['custom_dates'])) {
          $events_to_create = [];
          foreach ($form_data['custom_dates'] as $date_range) {
            // Set this event to be created.
            $events_to_create[$date_range['start_date']->format('r')] = [
              'start_date' => $date_range['start_date'],
              'end_date' => $date_range['end_date'],
            ];
          }

          // Allow modules to alter the array of event instances before they
          // get created.
          $this->moduleHandler->alter('recurring_events_event_instances_pre_create', $events_to_create, $event);

          if (!empty($events_to_create)) {
            foreach ($events_to_create as $custom_event) {
              $event_instances[] = $this->createEventInstance($event, $custom_event['start_date'], $custom_event['end_date']);
            }
          }
        }
      }
      else {
        $field_definition = $this->fieldTypePluginManager->getDefinition($form_data['type']);
        $field_class = $field_definition['class'];
        $events_to_create = $field_class::calculateInstances($form_data);

        // Allow modules to alter the array of event instances before they
        // get created.
        $this->moduleHandler->alter('recurring_events_event_instances_pre_create', $events_to_create, $event);

        if (!empty($events_to_create)) {
          foreach ($events_to_create as $event_to_create) {
            $event_instances[] = $this->createEventInstance($event, $event_to_create['start_date'], $event_to_create['end_date']);
          }
        }
      }
    }

    // Create a message to indicate how many instances were changed.
    $this->messenger->addMessage($this->translation->translate('A total of %items event instances were created as part of this event series.', [
      '%items' => count($event_instances),
    ]));
    $event->set('event_instances', $event_instances);
  }

  /**
   * Create an event instance from an event series.
   *
   * @param Drupal\recurring_events\Entity\EventSeries $event
   *   The stored event series entity.
   * @param Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The start date and time of the event.
   * @param Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The end date and time of the event.
   *
   * @return static
   *   The created event instance entity object.
   */
  public function createEventInstance(EventSeries $event, DrupalDateTime $start_date, DrupalDateTime $end_date) {
    $data = [
      'eventseries_id' => $event->id(),
      'date' => [
        'value' => $start_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
      'type' => $event->getType(),
    ];

    $this->moduleHandler->alter('recurring_events_event_instance', $data);

    $entity = $this->entityTypeManager->getStorage('eventinstance')->create($data);
    $entity->save();

    return $entity;
  }

  /**
   * Configure the default field inheritances for event instances.
   *
   * @param Drupal\recurring_events\Entity\EventInstance $instance
   *   The event instance.
   * @param int $series_id
   *   The event series entity ID.
   */
  public function configureDefaultInheritances(EventInstance $instance, int $series_id = NULL) {
    if (is_null($series_id)) {
      $series_id = $instance->eventseries_id->target_id;
    }

    if (!empty($series_id)) {
      // Configure the field inheritances for this instance.
      $entity_type = $instance->getEntityTypeId();
      $bundle = $instance->bundle();

      $inherited_fields = $this->entityTypeManager->getStorage('field_inheritance')->loadByProperties([
        'sourceEntityType' => 'eventseries',
        'destinationEntityType' => $entity_type,
        'destinationEntityBundle' => $bundle,
      ]);

      if (!empty($inherited_fields)) {
        $state_key = $entity_type . ':' . $instance->uuid();
        $state = $this->keyValueStore->get('field_inheritance');
        $state_values = $state->get($state_key);
        if (empty($state_values)) {
          $state_values = [
            'enabled' => TRUE,
          ];
          if (!empty($inherited_fields)) {
            foreach ($inherited_fields as $inherited_field) {
              $name = $inherited_field->idWithoutTypeAndBundle();
              $state_values[$name] = [
                'entity' => $series_id,
              ];
            }
          }
          $state->set($state_key, $state_values);
        }
      }
    }
  }

  /**
   * When adding a new field inheritance, add the default values for it.
   *
   * @param Drupal\recurring_events\Entity\EventInstance $instance
   *   The event instance for which to configure default inheritance values.
   * @param Drupal\field_inheritance\Entity\FieldInheritanceInterface $field_inheritance
   *   The field inheritance being created or updated.
   */
  public function addNewDefaultInheritance(EventInstance $instance, FieldInheritanceInterface $field_inheritance) {
    $state_key = 'eventinstance:' . $instance->uuid();
    $state = $this->keyValueStore->get('field_inheritance');
    $state_values = $state->get($state_key);
    $name = $field_inheritance->idWithoutTypeAndBundle();

    if (!empty($state_values[$name])) {
      return;
    }

    $state_values[$name] = [
      'entity' => $instance->eventseries_id->target_id,
    ];

    $state->set($state_key, $state_values);
  }

  /**
   * Get exclude/include dates from form.
   *
   * @param array $field
   *   The field from which to retrieve the dates.
   *
   * @return array
   *   An array of dates.
   */
  private function getDatesFromForm(array $field) {
    $dates = [];

    if (!empty($field)) {
      foreach ($field as $date) {
        if (!empty($date['value']['date']) && !empty($date['end_value']['date'])) {
          $dates[] = [
            'value' => $date['value']['date'],
            'end_value' => $date['end_value']['date'],
          ];
        }
      }
    }
    return $dates;
  }

  /**
   * Build a string from excluded or included date ranges.
   *
   * @var array $config
   *   The configuration from which to build a string.
   *
   * @return string
   *   The formatted date string.
   */
  private function buildDateString(array $config) {
    $string = '';

    $string_parts = [];
    if (!empty($config)) {
      foreach ($config as $date) {
        $range = $this->translation->translate('@start_date to @end_date', [
          '@start_date' => $date['value'],
          '@end_date' => $date['end_value'],
        ]);
        $string_parts[] = '(' . $range . ')';
      }

      $string = implode(', ', $string_parts);
    }
    return $string;
  }

  /**
   * Retrieve the recur field types.
   *
   * @param bool $allow_alter
   *   Allow altering of the field types.
   *
   * @return array
   *   An array of field types.
   */
  public function getRecurFieldTypes($allow_alter = TRUE) {
    // Build an array of recur type field options based on FieldTypes that
    // implement the Drupal\recurring_events\RecurringEventsFieldTypeInterface
    // interface. Allow for other modules to customize this list with an alter
    // hook.
    $recur_fields = [];
    $fields = $this->entityFieldManager->getBaseFieldDefinitions('eventseries');
    foreach ($fields as $field) {
      $field_definition = $this->fieldTypePluginManager->getDefinition($field->getType());
      $class = new \ReflectionClass($field_definition['class']);
      if ($class->implementsInterface('\Drupal\recurring_events\RecurringEventsFieldTypeInterface')) {
        $recur_fields[$field->getName()] = $field->getLabel();
      }
    }

    $recur_fields['custom'] = $this->t('Custom Event');
    if ($allow_alter) {
      $this->moduleHandler->alter('recurring_events_recur_field_types', $recur_fields);
    }
    return $recur_fields;
  }

}
