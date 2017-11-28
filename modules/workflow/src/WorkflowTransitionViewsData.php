<?php

namespace Drupal\workflow;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the workflow entity type.
 * Partly taken from NodeViewsData.php.
 */
class WorkflowTransitionViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Use flexible $base_table, since both WorkflowTransition and
    // WorkflowScheduledTransition use this.
    $base_table = $this->entityType->getBaseTable();
    $base_field = $this->entityType->getKey('id');

    // @todo D8-port: Add data from D7 function workflow_views_views_data_alter()
    // @see http://cgit.drupalcode.org/workflow/tree/workflow_views/workflow_views.views.inc

    $data[$base_table]['table']['join'] = [
      // This is provided for the many_to_one argument.
      $base_table => [
        'field' => $base_field,
        'left_field' => $base_field,
      ],
    ];

    // @todo: this relationship needs to be reversed. See also taxonomy/src/NodeTermData.php
    //$data[$base_table]['nid'] = [
    //  'title' => $this->t('Content with workflow'),
    //  'help' => $this->t('Relate all content with a workflow.'),
    //  'relationship' => [
    //    'id' => 'standard',
    //    'base' => 'node',
    //    'base field' => 'nid',
    //    'label' => $this->t('node'),
    //    'skip base' => 'node',
    //  ],
    //];

    $data[$base_table]['from_sid']['filter']['id'] = 'workflow_state';
    $data[$base_table]['from_sid']['help'] = $this->t('The name of the previous state of the transition.');

    $data[$base_table]['to_sid']['filter']['id'] = 'workflow_state';
    $data[$base_table]['to_sid']['help'] = $this->t('The name of the new state of the transition. (For the latest transition, this is the current state.)');

    $data[$base_table]['uid']['help'] = $this->t('The user who triggered the transition. If you need more fields than the uid add the content: author relationship');
    $data[$base_table]['uid']['filter']['id'] = 'user_name';
    $data[$base_table]['uid']['relationship']['title'] = $this->t('User');
    $data[$base_table]['uid']['relationship']['help'] = $this->t('The user who triggered the transition.');
    $data[$base_table]['uid']['relationship']['label'] = $this->t('User');

    // @todo Add similar support to any date field
    // @see https://www.drupal.org/node/2337507
    $data[$base_table]['timestamp_fulldate'] = [
      'title' => $this->t('Created date'),
      'help' => $this->t('Date in the form of CCYYMMDD.'),
      'argument' => [
        'field' => 'timestamp',
        'id' => 'date_fulldate',
      ],
    ];

    $data[$base_table]['timestamp_year_month'] = [
      'title' => $this->t('Created year + month'),
      'help' => $this->t('Date in the form of YYYYMM.'),
      'argument' => [
        'field' => 'timestamp',
        'id' => 'date_year_month',
      ],
    ];

    $data[$base_table]['timestamp_year'] = [
      'title' => $this->t('Created year'),
      'help' => $this->t('Date in the form of YYYY.'),
      'argument' => [
        'field' => 'timestamp',
        'id' => 'date_year',
      ],
    ];

    $data[$base_table]['timestamp_month'] = [
      'title' => $this->t('Created month'),
      'help' => $this->t('Date in the form of MM (01 - 12).'),
      'argument' => [
        'field' => 'timestamp',
        'id' => 'date_month',
      ],
    ];

    $data[$base_table]['timestamp_day'] = [
      'title' => $this->t('Created day'),
      'help' => $this->t('Date in the form of DD (01 - 31).'),
      'argument' => [
        'field' => 'timestamp',
        'id' => 'date_day',
      ],
    ];

    $data[$base_table]['timestamp_week'] = [
      'title' => $this->t('Created week'),
      'help' => $this->t('Date in the form of WW (01 - 53).'),
      'argument' => [
        'field' => 'timestamp',
        'id' => 'date_week',
      ],
    ];

    $data[$base_table]['changed_fulldate'] = [
      'title' => $this->t('Updated date'),
      'help' => $this->t('Date in the form of CCYYMMDD.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_fulldate',
      ],
    ];

    $data[$base_table]['changed_year_month'] = [
      'title' => $this->t('Updated year + month'),
      'help' => $this->t('Date in the form of YYYYMM.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_year_month',
      ],
    ];

    $data[$base_table]['changed_year'] = [
      'title' => $this->t('Updated year'),
      'help' => $this->t('Date in the form of YYYY.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_year',
      ],
    ];

    $data[$base_table]['changed_month'] = [
      'title' => $this->t('Updated month'),
      'help' => $this->t('Date in the form of MM (01 - 12).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_month',
      ],
    ];

    $data[$base_table]['changed_day'] = [
      'title' => $this->t('Updated day'),
      'help' => $this->t('Date in the form of DD (01 - 31).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_day',
      ],
    ];

    $data[$base_table]['changed_week'] = [
      'title' => $this->t('Updated week'),
      'help' => $this->t('Date in the form of WW (01 - 53).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_week',
      ],
    ];

    return $data;
  }

}
