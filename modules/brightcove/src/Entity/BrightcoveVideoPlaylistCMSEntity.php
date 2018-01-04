<?php

namespace Drupal\brightcove\Entity;

use Drupal\brightcove\BrightcoveVideoPlaylistCMSEntityInterface;

/**
 * Common base class for CMS entities like Video and Playlist.
 */
abstract class BrightcoveVideoPlaylistCMSEntity extends BrightcoveCMSEntity implements BrightcoveVideoPlaylistCMSEntityInterface {
  /**
   * {@inheritdoc}
   */
  public function getPlayer() {
    return $this->get('player')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setPlayer($player) {
    return $this->set('player', $player);
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceID() {
    return $this->get('reference_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setReferenceID($reference_id) {
    return $this->set('reference_id', $reference_id);
  }

  /**
   * Default value callback for 'reference_id' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getDefaultReferenceId() {
    return [
      'drupal:' . \Drupal::CORE_COMPATIBILITY . ":" . \Drupal::currentUser()->id() . ":" . md5(microtime()),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTags() {
    return $this->get('tags')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setTags($tags) {
    $this->set('tags', $tags);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    return $this->set('status', $published ? NODE_PUBLISHED : NODE_NOT_PUBLISHED);
  }
}
