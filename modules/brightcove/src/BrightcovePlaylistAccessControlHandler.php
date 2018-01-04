<?php

namespace Drupal\brightcove;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Brightcove Playlist.
 *
 * @see \Drupal\brightcove\Entity\BrightcovePlaylist.
 */
class BrightcovePlaylistAccessControlHandler extends EntityAccessControlHandler {
  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\brightcove\BrightcovePlaylistInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished brightcove playlists');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published brightcove playlists');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit brightcove playlists');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete brightcove playlists');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add brightcove playlists');
  }

}
