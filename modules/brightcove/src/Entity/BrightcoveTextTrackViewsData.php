<?php

namespace Drupal\brightcove\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Brightcove Text Track entities.
 */
class BrightcoveTextTrackViewsData extends EntityViewsData implements EntityViewsDataInterface {
  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['brightcove_text_track']['table']['base'] = array(
      'field' => 'id',
      'title' => $this->t('Brightcove Text Track'),
      'help' => $this->t('The Brightcove Text Track ID.'),
    );

    return $data;
  }

}
