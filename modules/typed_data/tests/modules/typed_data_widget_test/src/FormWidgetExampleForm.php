<?php

namespace Drupal\typed_data_widget_test;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_data\Form\SubformState;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\typed_data\Util\StateTrait;
use Drupal\typed_data\Widget\FormWidgetManagerTrait;

/**
 * Class FormWidgetExampleForm.
 */
class FormWidgetExampleForm extends FormBase {

  use FormWidgetManagerTrait;
  use StateTrait;
  use TypedDataTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'typed_data_widget_test_form';
  }

  /**
   * Gets some example context definition.
   *
   * @param string $widget_id
   *   The widget id.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface
   *   The definition.
   */
  public function getExampleContextDefinition($widget_id) {
    switch ($widget_id) {
      default:
      case 'text_input':
        return ContextDefinition::create('string')
          ->setLabel('Example string')
          ->setDescription('Some example string with max. 8 characters.')
          ->setDefaultValue('default')
          ->addConstraint('Length', ['max' => 8]);
      case 'select':
        return ContextDefinition::create('filter_format')
          ->setLabel('Filter format')
          ->setDescription('Some example selection.');
    }

  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $widget_id = NULL) {
    $widget = $this->getFormWidgetManager()->createInstance($widget_id);

    // Read and write widget configuration from the state.
    // Allow tests to define a custom context definition.
    $context_definition = $this->getState()->get('typed_data_widgets.definition');
    $context_definition = $context_definition ?: $this->getExampleContextDefinition($widget_id);
    $form_state->set('widget_id', $widget_id);
    $form_state->set('context_definition', $context_definition);

    // Create a typed data object.
    $data = $this->getTypedDataManager()
      ->create($context_definition->getDataDefinition());
    $value = $this->getState()->get('typed_data_widgets.' . $widget_id);
    $value = isset($value) ? $value : $context_definition->getDefaultValue();
    $data->setValue($value);

    $subform_state = SubformState::createWithParents(['data'], $form, $form_state);
    $form['data'] = $widget->form($data, $subform_state);

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $context_definition = $form_state->get('context_definition');
    $widget_id = $form_state->get('widget_id');
    $widget = $this->getFormWidgetManager()->createInstance($widget_id);

    $subform_state = SubformState::createWithParents(['data'], $form, $form_state);
    $data = $this->getTypedDataManager()
      ->create($context_definition->getDataDefinition());
    $widget->extractFormValues($data, $subform_state);

    // Validate the data and flag possible violations.
    $violations = $data->validate();
    $widget->flagViolations($data, $violations, $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $context_definition = $form_state->get('context_definition');
    $widget_id = $form_state->get('widget_id');
    $widget = $this->getFormWidgetManager()->createInstance($widget_id);

    $subform_state = SubformState::createWithParents(['data'], $form, $form_state);
    $data = $this->getTypedDataManager()
      ->create($context_definition->getDataDefinition());
    $widget->extractFormValues($data, $subform_state);

    // Read and write widget configuration via the state.
    $this->getState()->set('typed_data_widgets.' . $widget_id, $data->getValue());
    drupal_set_message($this->t('Value saved'));
  }

}
