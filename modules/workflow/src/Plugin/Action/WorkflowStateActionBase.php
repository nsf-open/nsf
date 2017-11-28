<?php

namespace Drupal\workflow\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Element\WorkflowTransitionElement;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowTransition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets an entity to a new, given state.
 *
 * Example Annotation @ Action(
 *   id = "workflow_given_state_action",
 *   label = @Translation("Change a node to new Workflow state"),
 *   type = "workflow"
 * )
 */
abstract class WorkflowStateActionBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return parent::calculateDependencies() + [
      'module' => ['workflow',],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration += $this->configuration;
    $configuration += [
      'field_name' => '',
      'to_sid' => '',
      'comment' => "New state is set by a triggered Action.",
      'force' => 0,
    ];
    return $configuration;
  }

  /**
   * @param EntityInterface $entity
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   */
  protected function getTransitionForExecution(EntityInterface $entity) {
    $user = workflow_current_user();

    if (!$entity) {
      \Drupal::logger('workflow_action')->notice('Unable to get current entity - entity is not defined.', []);
      return NULL;
    }

    // Get the entity type and numeric ID.
    $entity_id = $entity->id();
    if (!$entity_id) {
      \Drupal::logger('workflow_action')->notice('Unable to get current entity ID - entity is not yet saved.', []);
      return NULL;
    }

    // In 'after saving new content', the node is already saved. Avoid second insert.
    // @todo: clone?
    $entity->enforceIsNew(FALSE);

    $config = $this->configuration;
    $field_name = workflow_get_field_name($entity, $config['field_name']);
    $current_sid = workflow_node_current_state($entity, $field_name);
    if (!$current_sid) {
      \Drupal::logger('workflow_action')->notice('Unable to get current workflow state of entity %id.', ['%id' => $entity_id]);
      return NULL;
    }

    $to_sid = isset($config['to_sid']) ? $config['to_sid'] : '';
    // Get the Comment. Parse the $comment variables.
    $comment_string = $this->configuration['comment'];
    $comment = t($comment_string, [
      '%title' => $entity->label(),
      // "@" and "%" will automatically run check_plain().
      '%state' => workflow_get_sid_name($to_sid),
      '%user' => $user->getDisplayName(),
    ]);
    $force = $this->configuration['force'];

    $transition = WorkflowTransition::create([$current_sid, 'field_name' => $field_name]);
    $transition->setTargetEntity($entity);
    $transition->setValues($to_sid, $user->id(), \Drupal::time()->getRequestTime(), $comment);
    $transition->force($force);

    return $transition;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = [];

    // If we are on admin/config/system/actions and use CREATE AN ADVANCED ACTION
    // Then $context only contains:
    // - $context['actions_label'] = "Change workflow state of post to new state"
    // - $context['actions_type'] = "entity"
    //
    // If we are on a VBO action form, then $context only contains:
    // - $context['entity_type'] = "node"
    // - $context['view'] = "(Object) view"
    // - $context['settings'] = "[]"

    $config = $this->configuration;
    $field_name = $config['field_name'];
    $wids = workflow_get_workflow_names();

    if (empty($field_name) && count($wids) > 1) {
      drupal_set_message('You have more then one workflow in the system. Please first select the field name
          and save the form. Then, revisit the form to set the correct state value.', 'warning');
    }
    if (empty($field_name)) {
      $wid = count($wids) ? array_keys($wids)[0] : '';
    }
    else {
      $fields = _workflow_info_fields($entity = NULL, $entity_type = '', $entity_bundle = '', $field_name);
      $wid = count($fields) ? reset($fields)->getSetting('workflow_type') : '';
    }

    // Get the common Workflow, or create a dummy Workflow.
    $workflow = $wid ? Workflow::load($wid) : Workflow::create(['id' => 'dummy_action', 'label' => 'dummy_action']);
    $current_state = $workflow->getCreationState();

    /*
    // @todo D8-port for VBO
    // Show the current state and the Workflow form to allow state changing.
    // N.B. This part is replicated in hook_node_view, workflow_tab_page, workflow_vbo.
    if ($workflow) {
      $field = _workflow_info_field($field_name, $workflow);
      $field_name = $field['field_name'];
      $field_id = $field['id'];
      $instance = field_info_instance($entity_type, $field_name, $entity_bundle);

      // Hide the submit button. VBO has its own 'next' button.
      $instance['widget']['settings']['submit_function'] = '';
      if (!$field_id) {
        // This is a Workflow Node workflow. Set widget options as in v7.x-1.2
        $field['settings']['widget']['comment'] = $workflow->options['comment_log_node']; // 'comment_log_tab' is removed;
        $field['settings']['widget']['current_status'] = TRUE;
        // As stated above, the options list is probably very long, so let's use select list.
        $field['settings']['widget']['options'] = 'select';
        // Do not show the default [Update workflow] button on the form.
        $instance['widget']['settings']['submit_function'] = '';
      }
    }

    // Add the form/widget to the formatter, and include the nid and field_id in the form id,
    // to allow multiple forms per page (in listings, with hook_forms() ).
    // Ultimately, this is a wrapper for WorkflowDefaultWidget.
    // $form['workflow_current_state'] = workflow_state_formatter($entity_type, $entity, $field, $instance);
    $form_id = implode('_', [
      'workflow_transition_form',
      $entity_type,
      $entity_id,
      $field_id
    ]);
     */
    $user = workflow_current_user();
    $transition = WorkflowTransition::create([$current_state, 'field_name' => $field_name]);
    $transition->setValues(
      $to_sid = $config['to_sid'],
      $user->id(),
      \Drupal::time()->getRequestTime(),
      $comment = $config['comment'],
      $force = $config['force']
  );

    // Add the WorkflowTransitionForm to the page.

    $element = []; // Just to be explicit.
    $element['#default_value'] = $transition;

    // Avoid Action Buttons. That removes the options box&more. No Buttons in config screens!
    $original_options = $transition->getWorkflow()->options['options'];
    $transition->getWorkflow()->options['options'] = 'select';
    // Generate and add the Workflow form element.
    $element = WorkflowTransitionElement::transitionElement($element, $form_state, $form);
    // Just to be sure, reset the options box setting.
    $transition->getWorkflow()->options['options'] = $original_options;

    // Make adaptations for VBO-form:
    $element['field_name']['#access'] = TRUE;
    $element['force']['#access'] = TRUE;
    $element['to_sid']['#description'] = t('Please select the state that should be assigned when this action runs.');
    $element['comment']['#title'] = $this->t('Message');
    $element['comment']['#description'] = $this->t('This message will be written into the workflow history log when the action
      runs. You may include the following variables: %state, %title, %user.');

    $form['workflow_transition_action_config'] = $element;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $form_state->getValue('workflow_transition_action_config');
    // Remove the transition: generates an error upon saving the action definition.
    unset($configuration['workflow_transition']);

    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = AccessResult::allowed();
    return $return_as_object ? $access : $access->isAllowed();
  }

}
