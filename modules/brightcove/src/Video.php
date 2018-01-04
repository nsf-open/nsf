<?php

namespace Drupal\brightcove;

use Brightcove\Object\Video\Video as BrightcoveAPIWrapperVideo;

/**
 * Override Brightcove API Wrapper's Video class.
 */
class Video extends BrightcoveAPIWrapperVideo {
  /**
   * Override patch request to be able to alter the fields.
   */
  public function patchJSON() {
    $data = parent::patchJSON();

    // Remove fields which are not needed to be sent to Brightcove.
    if (isset($data['account_id'])) {
      unset($data['account_id']);
    }
    if (isset($data['id'])) {
      unset($data['id']);
    }

    return $data;
  }
}