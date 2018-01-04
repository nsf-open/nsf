<?php

namespace Drupal\brightcove\Form;

use Brightcove\API\Exception\APIException;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form for Brightcove Subscription delete.
 */
class BrightcoveSubscriptionDeleteForm extends EntityDeleteForm {
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\brightcove\Entity\BrightcoveSubscription $subscription */
    $subscription = $this->entity;

    // Prevent deletion of the default Subscription entity.
    if ($subscription->isDefault()) {
      drupal_set_message($this->t('The API client default Subscription cannot be deleted.'), 'error');
      return $this->redirect('entity.brightcove_subscription.collection');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      parent::submitForm($form, $form_state);
    }
    catch (APIException $e) {
      drupal_set_message($e->getMessage(), 'error');
      $form_state->setRedirect('entity.brightcove_subscription.collection');
    }
  }
}
