<?php

/**
 * @file
 * Custom hooks exposed by the field_inheritance module.
 */

/**
 * Alter the inheritance class used to build the inherited basefield.
 *
 * @var string $class
 *   The class to alter.
 * @var Drupal\Core\Field\FieldDefinitionInterface $field
 *   The field context.
 */
function hook_field_inheritance_inheritance_class_alter(&$class, $field) {
  if ($field->plugin() === 'entity_reference_inheritance') {
    $class = '\Drupal\my_module\EntityReferenceFieldInheritanceFactory';
  }
}
