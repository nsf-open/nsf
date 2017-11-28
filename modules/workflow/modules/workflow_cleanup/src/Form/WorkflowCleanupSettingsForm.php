<?php

namespace Drupal\workflow_cleanup\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Entity\WorkflowConfigTransition;
use Drupal\workflow\Entity\WorkflowState;

/**
 * Class WorkflowCleanupSettingsForm
 *
 * @package Drupal\workflow_cleanup\Form
 */
class WorkflowCleanupSettingsForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return 'workflow_cleanup_settings';
  }

  /**
   * @inheritdoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    // Get all of the states, indexed by sid.
    $orphans = $inactive = [];

    /* @var $states WorkflowState[] */
    /* @var $state WorkflowState */
    $states = WorkflowState::loadMultiple();

    foreach ($states as $state) {
      // Does the associated workflow exist?
      if (!$state->getWorkflow()) {
        $orphans[$state->id()] = $state;
      }
      else {
        // Is the state still active?
        if (!$state->isActive()) {
          $inactive[$state->id()] = $state;
        }
      }
    }

    // Save the relevant states in an indexed array.
    $form['#workflow_states'] = $orphans + $inactive;

    $form['no_workflow'] = [
      '#type' => 'details',
      '#title' => t('Orphaned States'),
      '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
      '#description' => t('These states no longer belong to an existing workflow.'),
      '#tree' => TRUE,
    ];
    foreach ($orphans as $sid => $state) {
      $form['no_workflow'][$sid]['check'] = [
        '#type' => 'checkbox',
        '#title' => $state->label(),
        '#return_value' => $sid,
      ];
    }

    $form['inactive'] = [
      '#type' => 'details',
      '#title' => t('Inactive (Deleted) States'),
      '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
      '#description' => t('These states belong to a workflow, but have been marked inactive (deleted).'),
      '#tree' => TRUE,
    ];
    foreach ($inactive as $sid => $state) {
      $form['inactive'][$sid]['check'] = [
        '#type' => 'checkbox',
        '#title' => $state->label() . ' (' . $state->getWorkflow()->label() . ')',
        '#return_value' => $sid,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Delete selected states'),
    ];

    return $form;
  }

  /**
   * @inheritdoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $states = $form['#workflow_states'];
    $values = $form_state->getValues();
    foreach (['no_workflow', 'inactive'] as $section) {
      if (!isset($values[$section])) {
        continue;
      }

      foreach ($values[$section] as $sid => $data) {
        if ($data['check']) {
          /* @var $state WorkflowState */
          $state = $states[$sid];
          $state_name = $state->label();

          // Delete any transitions this state is involved in.
          $count = 0;
          foreach (WorkflowConfigTransition::loadMultiple() as $config_transition) {
            /* @var $config_transition WorkflowConfigTransition */
            if ($config_transition->getFromSid() == $sid || $config_transition->getToSid() == $sid) {
              $config_transition->delete();
              $count++;
            }
          }
          if ($count) {
            drupal_set_message(t('@count transitions for the "@state" state have been deleted.',
              ['@state' => $state_name, '@count' => $count]));
          }

          // @todo: Remove history records too.
          $count = 0;
          // $count = db_delete('workflow_node_history')->condition('sid', $sid)->execute();
          if ($count) {
            drupal_set_message(t('@count history records for the "@state" state have been deleted.',
              ['@state' => $state_name, '@count' => $count]));
          }

          $state->delete();
          drupal_set_message(t('The "@state" state has been deleted.',
            ['@state' => $state_name]));
        }
      }
    }
  }

}
