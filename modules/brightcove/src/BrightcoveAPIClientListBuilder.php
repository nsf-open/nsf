<?php

namespace Drupal\brightcove;

use Drupal\brightcove\Entity\BrightcovePlayer;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Brightcove API Client entities.
 */
class BrightcoveAPIClientListBuilder extends ConfigEntityListBuilder {

  /**
   * The default API Client.
   *
   * @var string
   */
  protected static $defaultAPIClient;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entityTypeInterface) {
    self::$defaultAPIClient = $container->get('config.factory')->get('brightcove.settings')->get('defaultAPIClient');
    return parent::createInstance($container, $entityTypeInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Brightcove API Client');
    $header['id'] = $this->t('Machine name');
    $header['account_id'] = $this->t('Account ID');
    $header['default_player'] = $this->t('Default player');
    $header['client_status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\brightcove\Entity\BrightcoveAPIClient $entity */

    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    if (self::$defaultAPIClient == $entity->id()) {
      $row['id'] .= $this->t(' (default)');
    }
    $row['account_id'] = $entity->getAccountID();
    $row['default_player'] = BrightcovePlayer::getList($entity->id())[$entity->getDefaultPlayer()];

    // Try to authorize client to get client status.
    try {
      $entity->authorizeClient();
      $row['client_status'] = $entity->getClientStatus() ? $this->t('OK') : $this->t('Error');
    }
    catch (\Exception $e) {
      $row['client_status'] = $this->t('Error');
    }

    return $row + parent::buildRow($entity);
  }

}
