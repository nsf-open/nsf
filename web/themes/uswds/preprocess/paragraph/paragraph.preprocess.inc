<?php
/**
 * @file
 * Preprocess function for blocks.
 */

/**
 * Implements hook_preprocess_paragraph().
 */
function uswds_preprocess_paragraph(&$variables) {

  // Always set the USWDS-specific "uswds_grid_class" variable.
  if (!isset($variables['uswds_grid_class'])) {
    $variables['uswds_grid_class'] = FALSE;
  }

  // All of this is only necessary if edge-to-edge mode is on.
  if (!_uswds_edge_to_edge_mode()) {
    // But we should still set the grid class to usa-grid-full.
    $variables['uswds_grid_class'] = 'usa-grid-full';
    return;
  }

  // We want to make sure that Paragraphs are on the grid.
  // But there are some exceptions. We don't need to add the grid class if...

  // ...if this is a Paragraph on a Paragraph.
  $paragraph = $variables['paragraph'];
  if ('paragraph' == $paragraph->getParentEntity()->getEntityTypeId()) {
    return;
  }

  // ...if this is one of a few Paragraph types that shouldn't be on the grid.
  // These types are provided by the USWDS Paragraphs module.
  $exclude_types = [
    'uswds_hero_callout',
    'uswds_media_block',
  ];
  if (in_array($paragraph->bundle(), $exclude_types)) {
    return;
  }

  // If still here, add the grid class.
  $variables['uswds_grid_class'] = 'usa-grid';
}
