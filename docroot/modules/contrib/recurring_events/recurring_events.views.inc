<?php

/**
 * @file
 * Views functionality for the recurring_events module.
 */

/**
 * Implements hook_views_data_alter().
 */
function recurring_events_views_data_alter(array &$data) {
  // Create a field to show the number of instances of an eventseries.
  $data['eventseries_field_data']['eventseries_instance_count'] = [
    'title' => t('Event series instance count'),
    'field' => [
      'title' => t('Event series instance count'),
      'help' => t('The number of event instances in a series.'),
      'id' => 'eventseries_instance_count',
      'click sortable' => FALSE,
    ],
  ];

  // Create a field to show the start date of an event series.
  $data['eventseries_field_data']['eventseries_start_date'] = [
    'title' => t('Event series start date'),
    'field' => [
      'title' => t('Event series start date'),
      'help' => t('The date on which an event first occurs.'),
      'id' => 'eventseries_start_date',
      'click sortable' => FALSE,
    ],
  ];

  // Set the default field for a view based on eventinstances.
  $data['eventinstance_field_data']['table']['base']['defaults']['field'] = 'id';

  // @todo Remove these declarations when
  // https://www.drupal.org/project/drupal/issues/2489476 is resolved.
  $data['eventinstance_field_data']['date__value']['filter']['id'] = 'datetime';
  $data['eventinstance_field_data']['date__value']['filter']['field_name'] = 'date';
  $data['eventinstance_field_data']['date__value']['sort']['id'] = 'datetime';
  $data['eventinstance_field_data']['date__value']['sort']['field_name'] = 'date';
  $data['eventinstance_field_data']['date__value']['argument']['id'] = 'datetime';
  $data['eventinstance_field_data']['date__value']['argument']['field_name'] = 'date';

  $data['eventinstance_field_data']['date__end_value']['filter']['id'] = 'datetime';
  $data['eventinstance_field_data']['date__end_value']['filter']['field_name'] = 'date';
  $data['eventinstance_field_data']['date__end_value']['sort']['id'] = 'datetime';
  $data['eventinstance_field_data']['date__end_value']['sort']['field_name'] = 'date';
  $data['eventinstance_field_data']['date__end_value']['argument']['id'] = 'datetime';
  $data['eventinstance_field_data']['date__end_value']['argument']['field_name'] = 'date';

  // We do not want people adding the recurrence fields to views as they will
  // not work. Instead for any fields necessary we create them above.
  $fields = [
    'consecutive_recurring_date__',
    'daily_recurring_date__',
    'weekly_recurring_date__',
    'monthly_recurring_date__',
  ];
  foreach ($fields as $field) {
    foreach ($data['eventseries_field_data'] as $field_name => $field_info) {
      if (strpos($field_name, $field) === 0) {
        unset($data['eventseries_field_data'][$field_name]);
      }
    }

    foreach ($data['eventseries_field_revision'] as $field_name => $field_info) {
      if (strpos($field_name, $field) === 0) {
        unset($data['eventseries_field_revision'][$field_name]);
      }
    }
  }
}
