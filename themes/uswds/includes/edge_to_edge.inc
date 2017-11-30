<?php

/**
 * @file
 * Utility code related to paragraphs.
 */

use \Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Determine whether edge-to-edge mode is on.
 *
 * @ret bool
 *   TRUE if edge-to-edge mode is on.
 */
function _uswds_edge_to_edge_mode() {
  $result = &drupal_static(__FUNCTION__);
  if (!isset($result)) {
    $result = FALSE;
    if (theme_get_setting('uswds_paragraphs_edge_to_edge')) {
      if (_uswds_current_entity_references_paragraphs()) {
        // Make sure we are not editing a node.
        // @TODO: Do this in a better way that includes all entity types.
        $current_path = \Drupal::service('path.current')->getPath();
        if(!preg_match('/node\/(\d+)\/edit/', $current_path, $matches)) {
          $result = TRUE;
        }
      }
    }
  }
  return $result;
}

/**
 * Determine whether the current entity references paragraphs.
 *
 * @ret bool
 *   TRUE if the current entity references paragraphs, FALSE otherwise.
 */
function _uswds_current_entity_references_paragraphs() {
  // @TODO: Is there a better way to do this? This seems overly complicated.
  foreach (\Drupal::routeMatch()->getParameters() as $entity) {
    if ($entity instanceof FieldableEntityInterface) {
      $entity_type = $entity->getEntityType()->id();
      $bundle = $entity->bundle();
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
      foreach ($fields as $field) {
        if ('entity_reference_revisions' == $field->getType()) {
          if ('paragraph' == $field->getSetting('target_type')) {
            if (!empty($entity->get($field->getName())->getValue())) {
              // Yes, this entity references paragraphs.
              return TRUE;
              break;
            }
          }
        }
      }
      break;
    }
  }
  return FALSE;
}
