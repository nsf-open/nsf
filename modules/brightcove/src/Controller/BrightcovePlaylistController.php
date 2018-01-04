<?php

namespace Drupal\brightcove\Controller;

use Drupal\brightcove\BrightcoveUtil;
use Drupal\brightcove\Entity\BrightcovePlaylist;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BrightcovePlaylistController extends ControllerBase {
  /**
   * The brightcove_playlist storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $playlist_storage;

  /**
   * The brightcove_video storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $video_storage;

  /**
   * Controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $playlist_storage
   *   Playlist EntityStorage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $video_storage
   *   Video EntityStorage.
   *
   */
  public function __construct(EntityStorageInterface $playlist_storage, EntityStorageInterface $video_storage) {
    $this->playlist_storage = $playlist_storage;
    $this->video_storage = $video_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('brightcove_playlist'),
      $container->get('entity_type.manager')->getStorage('brightcove_video')
    );
  }

  /**
   * Menu callback to update the existing Playlist with the latest version.
   *
   * @param int $entity_id
   *   The ID of the playlist in Drupal.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirection response.
   */
  public function update($entity_id) {
    /** @var \Drupal\brightcove\Entity\BrightcovePlaylist $playlist */
    $playlist = BrightcovePlaylist::load($entity_id);

    /** @var \Brightcove\API\CMS $cms */
    $cms = BrightcoveUtil::getCMSAPI($playlist->getAPIClient());

    // Update playlist.
    BrightcovePlaylist::createOrUpdate($cms->getPlaylist($playlist->getPlaylistId()), $this->playlist_storage, $this->video_storage);

    // Redirect back to the playlist edit form.
    return $this->redirect('entity.brightcove_playlist.edit_form', ['brightcove_playlist' => $entity_id]);
  }
}