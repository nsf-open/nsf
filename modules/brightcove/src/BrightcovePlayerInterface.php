<?php

namespace Drupal\brightcove;

/**
 * Provides an interface for defining Brightcove Player.
 *
 * @ingroup brightcove
 */
interface BrightcovePlayerInterface {
  /**
   * Returns the Brightcove Player ID.
   *
   * @return string
   *   The Brightcove Player ID (not the entity's).
   */
  public function getPlayerId();

  /**
   * Sets The Brightcove Player ID.
   *
   * @param string $player_id
   *   The Brightcove Player ID (not the entity's).
   *
   * @return \Drupal\brightcove\BrightcovePlayerInterface
   *   The called Brightcove Player.
   */
  public function setPlayerId($player_id);

  /**
   * Returns whether the player is adjusted for the playlist or not.
   *
   * @return bool|NULL
   *   TRUE or FALSE whether the player is adjusted or not, or NULL if not set.
   */
  public function isAdjusted();

  /**
   * Sets the Player as adjusted.
   *
   * @param bool|NULL adjusted
   *   TRUE or FALSE whether the player is adjusted or not, or NULL to unset
   *   the value.
   *
   * @return \Drupal\brightcove\BrightcovePlayerInterface
   *   The called Brightcove Player.
   */
  public function setAdjusted($adjusted);

  /**
   * Returns the height of the player.
   *
   * @return float
   *   The height of the player.
   */
  public function getHeight();

  /**
   * Sets the height of the player.
   *
   * @param float $height
   *   The height of the player.
   *
   * @return \Drupal\brightcove\BrightcovePlayerInterface
   *   The called Brightcove Player.
   */
  public function setHeight($height);

  /**
   * Returns the width of the player.
   *
   * @return float
   *   The width of the player.
   */
  public function getWidth();

  /**
   * Sets the width of the player.
   *
   * @param float $width
   *   The width of the player.
   *
   * @return \Drupal\brightcove\BrightcovePlayerInterface
   *   The called Brightcove Player.
   */
  public function setWidth($width);
}
