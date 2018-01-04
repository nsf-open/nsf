<?php

namespace Drupal\brightcove;

use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Brightcove Videos.
 *
 * @ingroup brightcove
 */
class BrightcoveVideoListBuilder extends EntityListBuilder {
  use LinkGeneratorTrait;

  /**
   * Account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $accountProxy;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   * @param \Drupal\Core\Session\AccountProxy $account_proxy
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, AccountProxy $account_proxy, DateFormatter $date_formatter) {
    parent::__construct($entity_type, $storage);
    $this->accountProxy = $account_proxy;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('current_user'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort('changed', 'DESC');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Assemble header columns.
    $header = [
      'video' => $this->t('Video'),
      'name' => $this->t('Name'),
      'status' => $this->t('Status'),
      'updated' => $this->t('Updated'),
      'reference_id' => $this->t('Reference ID'),
      'created' => $this->t('Created'),
    ];

    // Add operations header column only if the user has access.
    if ($this->accountProxy->hasPermission('edit brightcove videos') || $this->accountProxy->hasPermission('delete brightcove videos')) {
      $header += parent::buildHeader();
    }

    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\brightcove\Entity\BrightcoveVideo */
    if (($entity->isPublished() && $this->accountProxy->hasPermission('view published brightcove videos')) || (!$entity->isPublished() && $this->accountProxy->hasPermission('view unpublished brightcove videos'))) {
      $name = $this->l(
        $entity->label(),
        new Url(
          'entity.brightcove_video.canonical', array(
            'brightcove_video' => $entity->id(),
          )
        )
      );
    }
    else {
      $name = $entity->label();
    }

    // Get thumbnail image style and render it.
    $thumbnail = $entity->getThumbnail();
    $thumbnail_image = '';
    if (!empty($thumbnail['target_id'])) {
      /** @var \Drupal\file\Entity\File $thumbnail_file */
      $thumbnail_file = File::load($thumbnail['target_id']);
      /** @var \Drupal\image\Entity\ImageStyle $image_style */
      $image_style = ImageStyle::load('brightcove_videos_list_thumbnail');
      if (!is_null($image_style)) {
        $image_uri = $image_style->buildUrl($thumbnail_file->getFileUri());
        $thumbnail_image = "<img src='{$image_uri}' alt='{$entity->getName()}'>";
      }
    }

    // Assemble row.
    $row = [
      'video' => [
        'data' => [
          '#markup' => $thumbnail_image,
        ],
      ],
      'name' => $name,
      'status' => $entity->isPublished() ? $this->t('Active') : $this->t('Inactive'),
      'updated' => $this->dateFormatter->format($entity->getChangedTime(), 'short'),
      'reference_id' => $entity->getReferenceID(),
      'created' => $this->dateFormatter->format($entity->getCreatedTime(), 'short'),
    ];

    // Add operations column only if the user has access.
    if ($this->accountProxy->hasPermission('edit brightcove videos') || $this->accountProxy->hasPermission('delete brightcove videos')) {
      $row += parent::buildRow($entity);
    }

    return $row;
  }
}
