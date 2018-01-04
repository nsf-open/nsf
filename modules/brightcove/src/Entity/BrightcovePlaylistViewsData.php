<?php

namespace Drupal\brightcove\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Brightcove Playlists.
 */
class BrightcovePlaylistViewsData extends EntityViewsData implements EntityViewsDataInterface {
  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['brightcove_playlist']['table']['base'] = array(
      'field' => 'id',
      'title' => $this->t('Brightcove Playlist'),
      'help' => $this->t('The Brightcove Playlist ID.'),
    );

    return $data;
  }

}
