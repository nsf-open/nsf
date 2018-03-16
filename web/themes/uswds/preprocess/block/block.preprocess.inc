<?php
/**
 * @file
 * Preprocess function for blocks.
 */

/**
 * Implements hook_preprocess_block().
 */
function uswds_preprocess_block(&$variables) {
  if (_uswds_edge_to_edge_mode()) {
    // If edge-to-edge paragraph detection is on, then the page.html.twig
    // will NOT wrap the content region in "usa-grid". In order to keep
    // blocks like page titles, breadcrumbs, and node tabs "on the grid", we
    // need to add that class to all of these blocks. We do this for all
    // blocks except the "system_main_block".
    $no_grid = ['system_main_block'];
    if (!in_array($variables['plugin_id'], $no_grid)) {
      $block = \Drupal\block\Entity\Block::load($variables['elements']['#id']);
      $region = $block->getRegion();
      if ('content' == $region) {
        $variables['attributes']['class'][] = 'usa-grid';
      }
    }
  }
}
