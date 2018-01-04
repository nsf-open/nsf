<?php

namespace Drupal\brightcove\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Brightcove Videos.
 */
class BrightcoveVideoViewsData extends EntityViewsData implements EntityViewsDataInterface {
  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['brightcove_video']['table']['base'] = array(
      'field' => 'bcvid',
      'title' => $this->t('Brightcove Video'),
      'help' => $this->t('The Brightcove Video ID.'),
    );

    return $data;
  }

}
