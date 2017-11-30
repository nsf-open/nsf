<?php

/**
 * @file
 * Utility code related to menus and menu blocks.
 */

use \Drupal\block\Entity\Block;

/**
 * Helper function to see if we should alter any menu.
 *
 * @param string $region
 *   The machine name of the region.
 */
function _uswds_process_menu_region($region) {
  $menu_bypass = theme_get_setting('uswds_menu_bypass');
  if (empty($menu_bypass)) {
    $menu_bypass = [];
  }
  return (empty($menu_bypass[$region]));
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function uswds_theme_suggestions_block_alter(array &$suggestions, array $variables) {
  // We need to suggest our custom theming for menu blocks in certain regions.
  if (in_array('block__system_menu_block', $suggestions)) {
    if (!empty($variables['elements']['#id'])) {
      $block = Block::load($variables['elements']['#id']);
      $menu_regions = ['primary_menu', 'secondary_menu', 'mobile_menu', 'sidebar_first', 'footer_menu'];
      if (in_array($block->getRegion(), $menu_regions)) {
        if (_uswds_process_menu_region($block->getRegion())) {
          $suggestions[] = 'block__system_menu_block__' . $block->getRegion();
        }
      }
    }
  }
  return $suggestions;
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function uswds_theme_suggestions_menu_alter(array &$suggestions, array $variables) {
  if (!empty($variables['items'])) {
    foreach ($variables['items'] as $item) {
      if (!empty($item['#uswds_region'])) {
        if (_uswds_process_menu_region($item['#uswds_region'])) {
          $suggestions[] = 'menu__' . $item['#uswds_region'];
        }
      }
      // We only need to look at one.
      break;
    }
  }
  return $suggestions;
}

/**
 * Helper function to mark menu items as being in one of our menu regions.
 *
 * This is the way we communicate a menu block's region to its preprocessor
 * and template.
 * @see preprocess/uswds_preprocess_block__system_menu_block__*
 */
function _uswds_mark_menu_items(&$variables, $region) {
  foreach ($variables['content']['#items'] as $key => &$item) {
    $item['#uswds_region'] = $region;
  }
}

/**
 * Helper function to find the active menu item.
 *
 * @param array $items
 *   Array of items as passed into hook_preprocess_menu().
 *
 * @return
 *   TRUE if found, FALSE if not.
 *
 * @TODO: SURELY there is a better way to mark the current
 * menu item in Drupal 8??
 */
function _uswds_find_active_menu_item(&$items) {
  $current_path = \Drupal::request()->getRequestUri();
  foreach ($items as &$item) {
    if ($item['url']->toString() == $current_path) {
      $item['is_current'] = TRUE;
      // No need to keep looking.
      return TRUE;
    }
    if (!empty($item['below'])) {
      $found = _uswds_find_active_menu_item($item['below']);
      if ($found) {
        return TRUE;
      }
    }
  }
  return FALSE;
}
