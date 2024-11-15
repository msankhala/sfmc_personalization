<?php

namespace Drupal\sfmc_personalization\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the SFMC Personalization pages configuration form.
 */
class SFMCPersonalizationPagesForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SFMCPersonalizationPagesForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sfmc_personalization.pages'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sfmc_personalization_pages_form';
  }

  /**
   * Gets available content types.
   *
   * @return array
   *   Array of content type labels keyed by machine name.
   */
  protected function getContentTypes() {
    $content_types = [];
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($types as $type) {
      $content_types[$type->id()] = $type->label();
    }
    return $content_types;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sfmc_personalization.pages');

    // Get existing pages or initialize with one empty page
    $pages = $config->get('pages') ?: [
      [
        'name' => '',
        'condition_type' => 'path',
        'path' => '',
        'regex' => '',
        'content_types' => [],
        'content_zones' => [],
      ]
    ];

    $pages = $form_state->getValue('pages_container', $pages);

    $page_count = $form_state->get('page_count') ?: count($pages);
    $form_state->set('page_count', $page_count);

    // Add a wrapper div for the draggable elements.
    $form['draggable_elements'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['draggable-elements-wrapper'],
      ],
    ];

    // Create container for pages
    $form['draggable_elements']['pages_container'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="pages-wrapper">',
      '#suffix' => '</div>',
    ];

    // Build form elements for each page
    for ($i = 0; $i < $page_count; $i++) {
      $form['draggable_elements']['pages_container'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Page type @num - @name', ['@num' => $i + 1, '@name' => $pages[$i]['name']]),
        '#open' => FALSE,
      ];

      $form['draggable_elements']['pages_container'][$i]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('name'),
        '#description' => $this->t('Enter the name of the page. This is just for display purpose.'),
        '#default_value' => $pages[$i]['name'] ?? '',
        // '#ajax' => [
        //   'callback' => '::updateDefaultPageOptions',
        //   'wrapper' => 'default-page-wrapper',
        //   'event' => 'change',
        // ],
      ];

      $form['draggable_elements']['pages_container'][$i]['condition_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Conditoin Type'),
        '#options' => [
          'path' => $this->t('Path'),
          'regex' => $this->t('Regex'),
          'content_type' => $this->t('Content Type'),
        ],
        '#default_value' => $pages[$i]['condition_type'] ?? 'path',
        '#ajax' => [
          'callback' => '::updateConditionField',
          'wrapper' => 'condition-field-wrapper-' . $i,
          'event' => 'change',
        ],
      ];

      // Container for condition field with ajax wrapper
      $form['draggable_elements']['pages_container'][$i]['condition_wrapper'] = [
        '#type' => 'container',
        '#prefix' => '<div id="condition-field-wrapper-' . $i . '">',
        '#suffix' => '</div>',
      ];

      // Get the current condition type from form state or default
      $condition_type = $form_state->getValue(['draggable_elements', 'pages_container', $i, 'condition_type'])
        ?? $pages[$i]['condition_type']
        ?? 'path';

      // Build appropriate condition field based on condition type.
      switch ($condition_type) {
        case 'path':
          $form['draggable_elements']['pages_container'][$i]['condition_wrapper']['path'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Path'),
            '#description' => $this->t('Enter the relative path (e.g., /about-us)'),
            '#default_value' => $pages[$i]['path'] ?? '',
            // '#field_prefix' => '/',
          ];
          break;

        case 'regex':
          $form['draggable_elements']['pages_container'][$i]['condition_wrapper']['regex'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Regex Pattern'),
            '#description' => $this->t('Enter a valid regular expression (e.g., ^/blog/.*$)'),
            '#default_value' => $pages[$i]['regex'] ?? '',
            '#element_validate' => ['::validateRegexPattern'],
          ];
          break;

        case 'content_type':
          $form['draggable_elements']['pages_container'][$i]['condition_wrapper']['content_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Content Type'),
            '#description' => $this->t('Select the content type to apply personalization'),
            '#options' => $this->getContentTypes(),
            '#multiple' => TRUE,
            '#default_value' => $pages[$i]['content_type'] ?? '',
          ];
          break;
      }

      // Content Zones fieldset for this page
      $form['draggable_elements']['pages_container'][$i]['content_zones'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Content Zones'),
        '#prefix' => '<div id="content-zones-wrapper-' . $i . '">',
        '#suffix' => '</div>',
      ];

      // Get or initialize content zones for this page
      $zones = $pages[$i]['content_zones'] ?? [['name' => '', 'selector' => '']];
      // When form rebuilds after ajax callback, always take the values from
      // $form_state instead of from stored config.
      $zones = $form_state->getValue(['pages_container', $i, 'content_zones'], $zones);
      $zone_count = $form_state->get(['page_count_' . $i, 'zone_count']) ?: count($zones);
      $form_state->set(['page_count_' . $i, 'zone_count'], $zone_count);
      $test = $form_state->get(['page_count_' . $i, 'zone_count']);

      // Build form elements for each content zone
      for ($j = 0; $j < $zone_count; $j++) {
        $form['draggable_elements']['pages_container'][$i]['content_zones'][$j] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['container-inline']],
        ];

        $form['draggable_elements']['pages_container'][$i]['content_zones'][$j]['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Content Zone name'),
          '#default_value' => $zones[$j]['name'] ?? '',
          '#size' => 30,
        ];

        $form['draggable_elements']['pages_container'][$i]['content_zones'][$j]['selector'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Content Zone CSS selector'),
          '#default_value' => $zones[$j]['selector'] ?? '',
          '#size' => 30,
        ];

        $form['draggable_elements']['pages_container'][$i]['content_zones'][$j]['delete'] = [
          '#type' => 'submit',
          '#value' => $this->t('Delete'),
          '#name' => 'delete_zone_' . $i . '_' . $j,
          '#submit' => ['::removeZone'],
          '#ajax' => [
            'callback' => '::updateContentZones',
            'wrapper' => 'content-zones-wrapper-' . $i,
          ],
          '#page_index' => $i,
          '#zone_index' => $j,
        ];
      }

      // Add more zones button
      $form['draggable_elements']['pages_container'][$i]['content_zones']['add_zone'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add more'),
        '#name' => 'add_zone_' . $i,
        '#submit' => ['::addZone'],
        '#ajax' => [
          'callback' => '::updateContentZones',
          'wrapper' => 'content-zones-wrapper-' . $i,
        ],
        '#page_index' => $i,
      ];

      // Delete page button
      $form['draggable_elements']['pages_container'][$i]['delete_page'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete page'),
        '#name' => 'delete_page_' . $i,
        '#submit' => ['::removePage'],
        '#ajax' => [
          'callback' => '::updateForm',
          'wrapper' => 'sfmc-personalization-pages-form',
        ],
        '#page_index' => $i,
      ];
    }

    // Add the necessary library.
    $form['#attached']['library'][] = 'sfmc_personalization/page_sortable';
    // Add the necessary JavaScript to make the elements draggable.
    $form['#attached']['drupalSettings']['sfmc_personalization']['sortable'] = [
      'selector' => '.draggable-elements-wrapper #edit-pages-container details',
    ];

    // Add more pages button
    $form['add_page'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add more page'),
      '#submit' => ['::addPage'],
      '#ajax' => [
        'callback' => '::updateForm',
        'wrapper' => 'sfmc-personalization-pages-form',
      ],
    ];

    $form['#prefix'] = '<div id="sfmc-personalization-pages-form">';
    $form['#suffix'] = '</div>';

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback to update the path field based on path type selection.
   */
  public function updateConditionField(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#parents'];
    $page_index = $parents[1];
    return $form['pages_container'][$page_index]['condition_wrapper'];
  }

  /**
   * Validates the regex pattern.
   */
  public function validateRegexPattern($element, FormStateInterface $form_state, $form) {
    $value = $element['#value'];
    if (!empty($value)) {
      // Test if the pattern is valid
      if (@preg_match($value, '') === FALSE) {
        $form_state->setError($element, $this->t('Invalid regular expression pattern.'));
      }
    }
  }

  /**
   * Ajax callback to update the entire form.
   */
  public function updateForm(array &$form, FormStateInterface $form_state) {
    return $form;
  }


  /**
   * Ajax callback to update the pages container.
   */
  public function updatePages(array &$form, FormStateInterface $form_state) {
    return $form['pages_container'];
  }

  /**
   * Ajax callback to update a specific page's content zones.
   */
  public function updateContentZones(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $page_index = $trigger['#page_index'];
    // $zone_index = $trigger['#zone_index'];
    // unset($form['pages_container'][$page_index]['content_zones'][$zone_index]);
    return $form['pages_container'][$page_index]['content_zones'];
  }

  /**
   * Submit handler for adding a new page.
   */
  public function addPage(array &$form, FormStateInterface $form_state) {
    $page_count = $form_state->get('page_count');
    $form_state->set('page_count', $page_count + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a page.
   */
  public function removePage(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $page_index = $trigger['#page_index'];
    $page_count = $form_state->get('page_count');
    $zone_count = $form_state->get(['page_count_' . $page_index, 'zone_count']);
    // Get the current values
    $values = $form_state->getValue('pages_container');

    // Remove the triggered page
    unset($values[$page_index]);

    // Re-index the array
    $values = array_values($values);

    // Set the updated values back
    $form_state->setValue('pages_container', $values);

    if ($page_count > 1) {
      $form_state->set('page_count', $page_count - 1);
      $form_state->unsetValue(['page_count_' . $page_index, 'zone_count']);
    }

    $form_state->setRebuild();
  }

  /**
   * Submit handler for adding a new content zone.
   */
  public function addZone(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $page_index = $trigger['#page_index'];
    // $zone_count = $form_state->get('zone_count_' . $page_index);
    $zone_count = $form_state->get(['page_count_' . $page_index, 'zone_count']);
    $form_state->set(['page_count_' . $page_index, 'zone_count'], $zone_count + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a content zone.
   */
  public function removeZone(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $page_index = $trigger['#page_index'];
    $zone_count = $form_state->get(['page_count_' . $page_index, 'zone_count']);

    // Get the current values
    $values = $form_state->getValue(['pages_container', $page_index, 'content_zones']);

    // Remove the triggered zone
    unset($values[$trigger['#zone_index']]);


    // Backup 'add_zone' button.
    $add_zone = $values['add_zone'];

    unset($values['add_zone']);
    // Re-index the array
    $values = array_values($values);

    // Add 'add_zone' button again.
    $values['add_zone'] = $add_zone;

    // Set the updated values back
    $form_state->setValue(['pages_container', $page_index, 'content_zones'], $values);

    if ($zone_count > 1) {
      $form_state->set(['page_count_' . $page_index, 'zone_count'], $zone_count - 1);
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sfmc_personalization.pages');
    $pages = [];

    $values = $form_state->getValue('pages_container');
    foreach ($values as $page) {
      $content_zones = [];
      foreach ($page['content_zones'] as $zone) {
        if (is_array($zone) && !empty($zone['name'])) {
          $content_zones[] = [
            'name' => $zone['name'],
            'selector' => $zone['selector'],
          ];
        }
      }

      if (!empty($page['name'])) {
        $pages[] = [
          'name' => $page['name'],
          'condition_type' => $page['condition_type'],
          'path' => $page['condition_wrapper']['path'] ?? '',
          'regex' => $page['condition_wrapper']['regex'] ?? '',
          'content_type' => $page['condition_wrapper']['content_type'] ?? [],
          'content_zones' => $content_zones,
        ];
      }
    }

    $config->set('pages', $pages)->save();
    parent::submitForm($form, $form_state);
  }
}
