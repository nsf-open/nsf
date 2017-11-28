<?php

namespace Drupal\tour_ui;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Component\Utility\Html;

/**
 * Provides a listing of tours.
 */
class TourListBuilder extends EntityListBuilder {

  /**
   * Constructs a new TourListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage
   *   The config storage class.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigEntityStorageInterface $storage) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id())
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $row['id'] = t('Id');
    $row['label'] = t('Label');
    $row['routes'] = t('routes');
    $row['tips'] = t('Number of tips');
    $row['operations'] = t('Operations');
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];

    $row = parent::buildRow($entity);

    $data['id'] = Html::escape($entity->id());
    $data['label'] = Html::escape($entity->label());
    // Include the routes this tour is used on.
    $routes_name = [];
    if ($routes = $entity->getRoutes()) {
      foreach ($routes as $route) {
        $routes_name[] = $route['route_name'];
      }
    }
    $data['routes'] = [
      'data' => [
        '#type' => 'inline_template',
        '#template' => '<div class="tour-routes">{{ routes|safe_join("<br />") }}</div>',
        '#context' => array('routes' => $routes_name),
      ],
    ];

    // Count the number of tips.
    $data['tips'] = count($entity->getTips());
    $data['operations'] = $row['operations'];
    // Wrap the whole row so that the entity ID is used as a class.
    return [
      'data' => $data,
      'class' => [
        $entity->id(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);

    $operations['edit'] = [
      'title' => t('Edit'),
      'url' => $entity->toUrl('edit-form'),
      'weight' => 1,
    ];
    $operations['delete'] = [
      'title' => t('Delete'),
      'url' => $entity->toUrl('delete-form'),
      'weight' => 2,
    ];

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['#empty'] = $this->t('No tours available. <a href="@link">Add tour</a>.', [
      '@link' => 'tour_ui.tour.add',
    ]);
    return $build;
  }

}
