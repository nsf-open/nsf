<?php

namespace Drupal\tour_ui\Form;

use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tour\TipPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the tour tip plugin edit forms.
 */
class TourTipForm extends FormBase {

  /**
   * The Tour tip plugin manager.
   *
   * @var \Drupal\Tour\TipPluginManager
   */
  protected $pluginManager;

  /**
   * Constructs a new TourTipForm object.
   *
   * @param \Drupal\Tour\TipPluginManager $plugin_manager
   *   The Tour tip plugin manager.
   */
  public function __construct(TipPluginManager $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.tour.tip')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tour_ui_tip_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $tip = $storage['#tip'];

    $form += $tip->buildConfigurationForm($form, $form_state);

    // Retrieve and add the form actions array.
    $actions = $this->actionsElement($form, $form_state);
    if (!empty($actions)) {
      $form['actions'] = $actions;
    }

    return $form;
  }

  /**
   * Returns the action form element for the current entity form.
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = $this->actions($form, $form_state);

    if (isset($element['delete'])) {
      // Move the delete action as last one, unless weights are explicitly
      // provided.
      $delete = $element['delete'];
      unset($element['delete']);
      $element['delete'] = $delete;
      $element['delete']['#button_type'] = 'danger';
    }

    if (isset($element['submit'])) {
      // Give the primary submit button a #button_type of primary.
      $element['submit']['#button_type'] = 'primary';
    }

    $count = 0;
    foreach (Element::children($element) as $action) {
      $element[$action] += array(
        '#weight' => ++$count * 5,
      );
    }

    if (!empty($element)) {
      $element['#type'] = 'actions';
    }

    return $element;
  }

  /**
   * Returns an array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => array('::submitForm'),
    );

    $actions['delete'] = array(
      '#type' => 'link',
      '#title' => $this->t('Delete'),
      '#attributes' => array(
        'class' => array('button', 'button--danger'),
      ),
    );

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Determine if one of our tips already exist.
    $storage = $form_state->getStorage();
    $tour = $storage['#tour'];
    $tips = $tour->getTips();
    // If there are no initial tips then we don't need to check.
    if (empty($tips)) {
      return;
    }

    $tip_ids = array_map(function($data) {
      return $data->id();
    }, $tips);

    if (in_array($form_state->getValue('id'), $tip_ids) && isset($storage['#new'])) {
      $form_state->setError($form['label'], $this->t('A tip with the same identifier exists.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $tour = $storage['#tour'];
    $tip = $storage['#tip'];
    // Get available fields from current tip plugin.
    $configuration = $tip->getConfiguration();

    // Build a new tip.
    $new_tip = $tip->getConfiguration();
    foreach ($configuration as $name => $configuration_value) {
      $value = $form_state->getValue($name);
      $new_tip[$name] = is_array($value) ? array_filter($value) : $value;
    }

    // Rebuild the tips.
    $new_tip_list = $tour->getTips();
    $new_tips = array();
    if (!empty($new_tip_list)) {
      foreach ($new_tip_list as $tip) {
        $new_tips[$tip->id()] = $tip->getConfiguration();
      }
    }

    // Add our tip and save.
    $new_tips[$new_tip['id']] = $new_tip;
    $tour->set('tips', $new_tips);
    $tour->save();

    if (isset($storage['#new'])) {
      drupal_set_message($this->t('The %tip tip has been created.', array('%tip' => $new_tip['label'])));
    }
    else {
      drupal_set_message($this->t('Updated the %tip tip.', array('%tip' => $new_tip['label'])));
    }

    $form_state->setRedirect('entity.tour.edit_form', ['tour' => $tour->id()]);
    return $tour;
  }

}
