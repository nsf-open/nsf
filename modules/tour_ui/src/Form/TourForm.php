<?php

namespace Drupal\tour_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Form controller for the tour entity edit forms.
 */
class TourForm extends EntityForm {

  /**
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs a TourForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\comment\CommentManagerInterface $comment_manager
   *   The comment manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $tour = $this->entity;
    $form = parent::form($form, $form_state);
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tour name'),
      '#required' => TRUE,
      '#default_value' => $tour->label(),
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => '\Drupal\tour\Entity\Tour::load',
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
      ],
      '#default_value' => $tour->id(),
      '#disabled' => !$tour->isNew(),
    ];

    $form['langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Language'),
      '#languages' => LanguageInterface::STATE_ALL,
      // Default to the content language opposed to und (language not specified).
      '#default_value' => empty($tour->langcode) ? \Drupal::languageManager()->getCurrentLanguage()->getId() : $tour->langcode,
    ];
    $form['module'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Module name'),
      '#description' => $this->t('Each tour needs a module.'),
      '#required' => TRUE,
      '#default_value' => $tour->get('module'),
    ];

    $default_routes = [];
    if ($routes = $tour->getRoutes()) {
      foreach ($routes as $route) {
        $default_routes[] = $route['route_name'];
      }
    }
    $form['routes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Routes'),
      '#default_value' => implode("\n", $default_routes),
      '#rows' => 5,
      '#description' => $this->t('Provide a list of routes that this tour will be displayed on. Add one route by line.'),
    ];

    // Don't show the tips on the inital add.
    if ($tour->isNew()) {
      return $form;
    }

    // Start building the list of tips assigned to this tour.
    $form['tips'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#caption' => [['#markup' => $this->t('Tips provided by this tour. By clicking on Operations buttons, every changes which are not saved will be lost.')]],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'tip-order-weight'
        ],
      ],
      '#weight' => 40,
    ];

    // Populate the table with the assigned tips.
    $tips = $tour->getTips();
    if (!empty($tips)) {
      foreach ($tips as $key => $tip) {
        $tip_id = $tip->get('id');
        try {
          $form['#data'][$tip_id] = $tip->getConfiguration();
        }
        catch (\Error $e) {
          drupal_set_message($this->t('Tip %tip is not configurable. You cannot save this tour.', ['%tip' => $tip->getLabel()]), 'warning');
        }
        $form['tips'][$tip_id]['#attributes']['class'][] = 'draggable';
        $form['tips'][$tip_id]['label'] = [
          '#plain_text' => $tip->get('label'),
        ];

        $form['tips'][$tip_id]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $tip->get('label')]),
          '#delta' => 100,
          '#title_display' => 'invisible',
          '#default_value' => $tip->get('weight'),
          '#attributes' => [
            'class' => ['tip-order-weight'],
          ],
        ];

        // Provide operations links for the tip.
        $links = [];
        if (method_exists($tip, 'buildConfigurationForm')) {
          $links['edit'] = [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('tour_ui.tip.edit', ['tour' => $tour->id(), 'tip' => $tip_id]),
          ];
        }
        $links['delete'] = [
          'title' => $this->t('Delete'),
          'url' => Url::fromRoute('tour_ui.tip.delete', ['tour' => $tour->id(), 'tip' => $tip_id]),
        ];
        $form['tips'][$tip_id]['operations'] = [
          '#type' => 'operations',
          '#links' => $links,
        ];
      }
    }

    // Build the new tour tip addition form and add it to the tips list.
    $tip_definitions = \Drupal::service('plugin.manager.tour.tip')->getDefinitions();
    $tip_definition_options = [];
    foreach ($tip_definitions as $tip => $definition) {
      if (method_exists($definition['class'], 'buildConfigurationForm')) {
        $tip_definition_options[$tip] = $definition['title'];
      }
    }

    $user_input = $form_state->getUserInput();
    $form['tips']['new'] = [
      '#tree' => FALSE,
      '#weight' => isset($user_input['weight']) ? $user_input['weight'] : 0,
      '#attributes' => [
        'class' => ['draggable'],
      ],
    ];
    $form['tips']['new']['new'] = [
      '#type' => 'select',
      '#title' => $this->t('Tip'),
      '#title_display' => 'invisible',
      '#options' => $tip_definition_options,
      '#empty_option' => $this->t('Select a new tip'),
    ];
    $form['tips']['new']['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight for new tip'),
      '#title_display' => 'invisible',
      '#default_value' => count($form['tips']) - 1,
      '#attributes' => [
        'class' => ['tip-order-weight'],
      ],
    ];
    $form['tips']['new']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#validate' => [[$this, 'tipValidate']],
      '#submit' => [[$this, 'tipAdd']],
    ];

    return $form;
  }

  /**
   * Validate handler.
   */
  public function tipValidate($form, FormStateInterface $form_state) {
    if (!$form_state->getValue('new')) {
      $form_state->setError($form['tips']['new']['new'], $this->t('Select a new tip.'));
    }
  }

  /**
   * Submit handler.
   */
  public function tipAdd($form, FormStateInterface $form_state) {
    $tour = $this->getEntity($form_state);

    $this::submitForm($form, $form_state, FALSE);

    $weight = 0;
    if (!$form_state->isValueEmpty('tips')) {
      // Get last weight.
      foreach ($form_state->getValue('tips') as $tip) {
        if ($tip['weight'] > $weight) {
          $weight = $tip['weight'] + 1;
        }
      }
    }

    $manager = \Drupal::service('plugin.manager.tour.tip');
    $stub = $manager->createInstance($form_state->getValue('new'), []);

    // If a form is available for this tip then redirect to a add page.
    $stub_form = $stub->buildConfigurationForm([], new FormState());
    if (isset($stub_form)) {
      // Redirect to the appropriate page to add this new tip.
      $form_state->setRedirect('tour_ui.tip.add', ['tour' => $tour->id(), 'type' => $form_state->getValue('new')], ['query' => ['weight' => $weight]]);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, $redirect = TRUE) {
    // Form cannot be validated if a tip has no #data, so no way to export
    // configuration.
    if (!$form_state->isValueEmpty('tips')) {
      foreach ($form_state->getValue('tips') as $key => $values) {
        if (!isset($form['#data'][$key])) {
          $form_state->setError($form['tips'][$key], $this->t('You cannot save the tour while %tip tip cannot be exported.', ['%tip' => $this->getEntity()->getTip($key)->getLabel()]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, $redirect = TRUE) {
    // Filter out invalid characters and convert to an array.
    $routes = [];
    $values_routes = preg_replace("/(\r\n?|\n)/", "\n", $form_state->getValue('routes'));
    $values_routes = explode("\n", $values_routes);
    $values_routes = array_map('trim', $values_routes);
    if (!empty($values_routes)) {
      foreach (array_filter($values_routes) as $route) {
        $routes[]['route_name'] = $route;
      }
    }

    $form_state->setValue('routes', array_filter($routes));

    // Merge the form values in with the current configuration.
    if (!$form_state->isValueEmpty('tips')) {
      $tips = [];
      foreach ($form_state->getValue('tips') as $key => $values) {
        $data = $form['#data'][$key];
        $tips[$key] = array_merge($data, $values);
      }
      $form_state->setValue('tips', $tips);
    }
    else {
      $form_state->setValue('tips', []);
    }

    parent::submitForm($form, $form_state);

    // Redirect to Entity edition.
    if ($redirect) {
      $form_state->setRedirect('entity.tour.edit_form', ['tour' => $this->entity->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity($form_state);
    $form_state->setRedirect('entity.tour.delete_form',  ['tour' => $entity->id()]);
  }

}
