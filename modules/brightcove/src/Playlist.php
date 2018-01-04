<?php

namespace Drupal\brightcove;

use Brightcove\Object\Playlist as BrightcoveAPIWrapperPlaylist;

/**
 * Override Brightcove API Wrapper's Playlist class.
 */
class Playlist extends BrightcoveAPIWrapperPlaylist {
  /**
   * Override patch request to be able to alter the fields.
   */
  public function patchJSON() {
    $data = parent::patchJSON();

    // Remove fields which are not needed to be sent to Brightcove.
    if (isset($data['id'])) {
      unset($data['id']);
    }

    return $data;
  }
}