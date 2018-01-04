<?php

namespace Drupal\brightcove\Form;

use Brightcove\API\Exception\APIException;
use Drupal\brightcove\Entity\BrightcovePlaylist;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Form controller for Brightcove Playlist edit forms.
 *
 * @ingroup brightcove
 */
class BrightcovePlaylistForm extends BrightcoveVideoPlaylistForm {
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /* @var $entity \Drupal\brightcove\Entity\BrightcovePlaylist */
    $entity = $this->entity;

    // Get api client from the form settings.
    if (!empty($api_client_value = $form_state->getValue('api_client'))) {
      if (is_array($api_client_value)) {
        $api_client = $api_client_value[0]['target_id'];
      }
      else {
        $api_client =  $api_client_value;
      }
    }
    elseif (!empty($user_input = $form_state->getUserInput()) && isset($user_input['api_client'])) {
      $api_client =  $user_input['api_client'];
    }
    else {
      $api_client = $form['api_client']['widget']['#default_value'];
      if (is_array($api_client)) {
        $api_client = reset($api_client);
      }
    }

    if ($entity->isNew()) {
      // Class this class's update form method instead of the supper class's.
      $form['api_client']['widget']['#ajax']['callback'] = [
        self::class, 'apiClientUpdateForm',
      ];

      // Add ajax wrapper for videos.
      $form['videos']['#ajax_id'] = 'ajax-videos-wrapper';
      $form['videos']['#prefix'] = '<div id="' . $form['videos']['#ajax_id'] . '">';
      $form['videos']['#suffix'] = '</div>';
    }

    // Set videos reference field argument.
    foreach (Element::children($form['videos']['widget']) as $delta) {
      if (is_numeric($delta)) {
        if (empty($form['videos']['widget'][$delta]['target_id']['#selection_settings']['view']['arguments'])) {
          $form['videos']['widget'][$delta]['target_id']['#selection_settings']['view']['arguments'] = [$api_client];
        }
      }
    }

    // Manual playlist: no search, only videos.
    $manual_type = array_keys(BrightcovePlaylist::getTypes(BrightcovePlaylist::TYPE_MANUAL));
    $form['videos']['#states'] = [
      'visible' => [
        ':input[name="type"]' => ['value' => reset($manual_type)],
      ],
    ];

    // Smart playlist: no videos, only search.
    $smart_types = [];
    foreach (array_keys(BrightcovePlaylist::getTypes(BrightcovePlaylist::TYPE_SMART)) as $smart_type) {
      $smart_types[] = ['value' => $smart_type];
    }

    $form['tags_search_condition']['#states'] = [
      'visible' => [
        ':input[name="type"]' => $smart_types,
      ],
    ];

    $form['tags']['#states'] = [
      'visible' => [
        ':input[name="type"]' => $smart_types,
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var $entity \Drupal\brightcove\Entity\BrightcovePlaylist */
    $entity = $this->entity;

    try {
      $status = $entity->save(TRUE);

      switch ($status) {
        case SAVED_NEW:
          drupal_set_message($this->t('Created the %label Brightcove Playlist.', [
            '%label' => $entity->label(),
          ]));
          break;

        default:
          drupal_set_message($this->t('Saved the %label Brightcove Playlist.', [
            '%label' => $entity->label(),
          ]));
      }
      $form_state->setRedirect('entity.brightcove_playlist.canonical', ['brightcove_playlist' => $entity->id()]);
    }
    catch (APIException $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * Ajax callback to update the profile and player options list.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public static function apiClientUpdateForm($form, FormStateInterface $form_state) {
    $response = parent::apiClientUpdateForm($form, $form_state);

    // Remove videos from the field if the api client is changed.
    foreach (Element::children($form['videos']['widget']) as $delta) {
      if (is_numeric($delta)) {
        $form['videos']['widget'][$delta]['target_id']['#value'] = '';
      }
    }

    // Update videos fields.
    $response->addCommand(new ReplaceCommand(
      '#' . $form['videos']['#ajax_id'],
      $form['videos']
    ));

    return $response;
  }
}
