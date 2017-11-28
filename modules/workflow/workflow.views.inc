<?php

/**
 * @file
 * Provide Views data for workflow.module.
 *
 * @ingroup views_module_handlers
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function workflow_field_views_data(FieldStorageConfigInterface $field) {

  $data = views_field_default_views_data($field);
  $settings = $field->getSettings();

  foreach ($data as $table_name => $table_data) {
    foreach ($table_data as $field_name => $field_data) {
      if (isset($field_data['filter']) && $field_name != 'delta') {
        $data[$table_name][$field_name]['filter']['wid'] = (array_key_exists('workflow_type', $settings)) ? $settings['workflow_type'] : '';
        $data[$table_name][$field_name]['filter']['id'] = 'workflow_state';
      }
    }
  }

  return $data;
}
