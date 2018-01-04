<?php

namespace Drupal\brightcove\Access;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Brightcove Text Track entity.
 *
 * @see \Drupal\brightcove\Entity\BrightcoveTextTrack.
 */
class BrightcoveTextTrackAccessControlHandler extends EntityAccessControlHandler {
  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\brightcove\BrightcoveTextTrackInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished brightcove text track entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published brightcove text track entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete brightcove text track entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add brightcove text track entities');
  }
}
