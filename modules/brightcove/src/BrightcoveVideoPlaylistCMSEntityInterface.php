<?php

namespace Drupal\brightcove;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

interface BrightcoveVideoPlaylistCMSEntityInterface extends BrightcoveCMSEntityInterface {
  /**
   * Returns the player.
   *
   * @return int
   *   Target ID of the Brightcove Player.
   */
  public function getPlayer();

  /**
   * Sets the player.
   *
   * @param int $player
   *   The Brightcove Player's entity ID.
   *
   * @return \Drupal\brightcove\BrightcoveVideoPlaylistCMSEntityInterface
   *   The called Brightcove Video or Playlist.
   */
  public function setPlayer($player);

  /**
   * Returns the reference ID.
   *
   * @return string
   *   Reference ID.
   */
  public function getReferenceID();

  /**
   * Sets the reference ID.
   *
   * @param string $reference_id
   *   The reference ID.
   *
   * @return \Drupal\brightcove\BrightcoveVideoPlaylistCMSEntityInterface
   *   The called Brightcove Video or Playlist.
   */
  public function setReferenceID($reference_id);

  /**
   * Returns the tags.
   *
   * @return string
   *   The list of tags.
   */
  public function getTags();

  /**
   * Sets the tags.
   *
   * @param array $tags
   *   List of tags.
   *
   * @return \Drupal\brightcove\BrightcoveVideoPlaylistCMSEntityInterface
   *   The called Brightcove Video or Playlist.
   */
  public function setTags($tags);

  /**
   * Returns the entity published status indicator.
   *
   * Unpublished entities are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the entity is published.
   */
  public function isPublished();

  /**
   * Sets the published status of the entity.
   *
   * @param bool $published
   *   TRUE to set this entity to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\brightcove\BrightcoveVideoPlaylistCMSEntityInterface
   *   The called Brightcove Video or Playlist.
   */
  public function setPublished($published);
}
