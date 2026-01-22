<?php

/**
 * Alter a field value before processessing and saving to an entity
 *
 * @param $field_Value
 *  The array representation of the field value before saving
 */
function hook_bulk_update_fields_preprocess_field_alter(&$field_value) {
  if (isset($field_value['add_more'])) {
    unset($field_value['add_more']);
  }
}

/**
 * Alter a single field value before being saved to an entity
 *
 * @param $value
 *  A single field value to save
 * @param $field_definition
 *  The definition of the field to be saved
 */
function hook_bulk_update_fields_process_field_alter(&$value, $field_definition) {
}
