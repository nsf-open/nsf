<?php

namespace Drupal\brightcove;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Brightcove API Client entities.
 */
class BrightcoveSubscriptionListBuilder extends ConfigEntityListBuilder {
  /**
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('link_generator')
    );
  }

  /**
   * BrightcoveSubscriptionListBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, LinkGeneratorInterface $link_generator) {
    parent::__construct($entity_type, $storage);
    $this->linkGenerator = $link_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['endpoint'] = $this->t('Endpoint');
    $header['api_client'] = $this->t('API Client');
    $header['events'] = $this->t('Events');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\brightcove\Entity\BrightcoveSubscription $entity */

    $api_client = $entity->getAPIClient();

    $row['endpoint'] = $entity->getEndpoint() . ($entity->isDefault() ? " ({$this->t('default')})" : '');
    $row['api_client'] = !empty($api_client) ? $this->linkGenerator->generate($api_client->label(), Url::fromRoute('entity.brightcove_api_client.edit_form', ['brightcove_api_client' => $api_client->id()])) : '';

    $row['events'] = implode(', ', array_filter($entity->getEvents(), function($value) {
      return !empty($value);
    }));

    if ($entity->isDefault()) {
      return $row + [
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'change_status' => [
                'title' => $entity->isActive() ? $this->t('Disable') : $this->t('Enable'),
                'weight' => 10,
                'url' => $entity->isActive() ? $entity->urlInfo('disable') : $entity->urlInfo('enable'),
              ],
            ],
          ],
        ],
      ];
    }

    return $row + parent::buildRow($entity);
  }
}