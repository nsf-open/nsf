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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Brightcove Playlists.
 *
 * @ingroup brightcove
 */
class BrightcovePlaylistListBuilder extends EntityListBuilder {
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
      'name' => $this->t('Name'),
      'updated' => $this->t('Updated'),
      'reference_id' => $this->t('Reference ID'),
    ];

    // Add operations header column only if the user has access.
    if ($this->accountProxy->hasPermission('edit brightcove playlists') || $this->accountProxy->hasPermission('delete brightcove playlists')) {
      $header += parent::buildHeader();
    }

    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\brightcove\Entity\BrightcovePlaylist */
    if (($entity->isPublished() && $this->accountProxy->hasPermission('view published brightcove playlists')) || (!$entity->isPublished() && $this->accountProxy->hasPermission('view unpublished brightcove playlists'))) {
      $name = $this->l(
        $entity->label(),
        new Url(
          'entity.brightcove_playlist.canonical', array(
            'brightcove_playlist' => $entity->id(),
          )
        )
      );
    }
    else {
      $name = $entity->label();
    }

    // Assemble row.
    $row = [
      'name' => $name,
      'updated' => $this->dateFormatter->format($entity->getChangedTime(), 'short'),
      'reference_id' => $entity->getReferenceID(),
    ];

    // Add operations column only if the user has access.
    if ($this->accountProxy->hasPermission('edit brightcove playlists') || $this->accountProxy->hasPermission('delete brightcove playlists')) {
      $row += parent::buildRow($entity);
    }

    return $row;
  }

}
