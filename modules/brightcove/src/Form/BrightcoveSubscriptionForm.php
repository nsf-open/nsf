<?php

namespace Drupal\brightcove\Form;

use Drupal\brightcove\Entity\BrightcoveAPIClient;
use Drupal\brightcove\Entity\BrightcoveSubscription;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form for Brightcove Subscription add, edit.
 */
class BrightcoveSubscriptionForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var BrightcoveSubscription $subscription */
    $subscription = $this->entity;

    /** @var BrightcoveAPIClient[] $api_clients */
    $api_clients = BrightcoveAPIClient::loadMultiple();
    $api_client_options = [];
    foreach ($api_clients as $api_client) {
      $api_client_options[$api_client->id()] = $api_client->label();
    }

    $form['api_client_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Client'),
      '#options' => $api_client_options,
      '#required' => TRUE,
    ];

    if (empty($api_client_options)) {
      $form['api_client_id']['#empty_option'] = $this->t('No API clients available');
    }
    elseif (empty($form['api_client_id']['#default'])) {
      $api_client_ids = array_keys($api_client_options);
      $default = reset($api_client_ids);
      $form['api_client_id']['#default_value'] = $default;
    }

    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('The notifications endpoint.'),
      '#required' => TRUE,
      '#default_value' => $subscription->getEndpoint(),
    ];

    // Hard-code "video-change" event since it's the only one.
    $form['events'] = [
      '#type' => 'hidden',
      '#value' => ['video-change'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\brightcove\Entity\BrightcoveSubscription $entity */
    $entity = $this->entity;

    // Validate endpoint, it should be unique.
    if (!empty($entity::loadByEndpoint($entity->getEndpoint()))) {
      $form_state->setErrorByName('endpoint', $this->t('A subscription with the %endpoint endpoint is already exists.', ['%endpoint' => $entity->getEndpoint()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\brightcove\Entity\BrightcoveSubscription $entity */
    $entity = $this->entity;

    try {
      $status = parent::save($form, $form_state);

      switch ($status) {
        case SAVED_NEW:
          drupal_set_message($this->t('Created Brightcove Subscription with %endpoint endpoint.', [
            '%endpoint' => $entity->getEndpoint(),
          ]));
          break;

        default:
          drupal_set_message($this->t('Saved Brightcove Subscription with %endpoint endpoint.', [
            '%endpoint' => $entity->getEndpoint(),
          ]));
      }

      // Redirect back to the Subscriptions list.
      $form_state->setRedirect('entity.brightcove_subscription.collection');
    }
    catch (\Exception $e) {
      // In case of an exception, show an error message and rebuild the form.
      if ($e->getMessage()) {
        drupal_set_message($this->t('Failed to create subscription: %error', ['%error' => $e->getMessage()]), 'error');
      }
      else {
        drupal_set_message($this->t('Failed to create subscription.'), 'error');
      }

      $form_state->setRebuild(TRUE);
    }

    return !empty($status) ? $status : NULL;
  }
}