<?php

namespace Drupal\sfmc_personalization\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the SFMC Personalization pages configuration form.
 */
class SfmcPersonalizationPagesForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SfmcPersonalizationPagesForm.
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

    // Get existing pages or initialize with one empty page.
    $pages = $config->get('pages') ?: [
      [
        'name' => '',
        'condition_type' => 'path',
        'path' => '',
        'regex' => '',
        'content_types' => [],
        'content_zones' => [],
      ],
    ];

    $pages = $form_state->getValue('pages_container', $pages);

    // $page_count = $form_state->get('page_count') ?: count($pages);
    $page_count = count($pages);
    $form_state->set('page_count', $page_count);

    // Create container for pages.
    $form['pages_container'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="pages-wrapper">',
      '#suffix' => '</div>',
    ];

    // Build form elements for each page
    // for ($i = 0; $i < $page_count; $i++) {.
    foreach ($pages as $index => $page) {
      $form['pages_container'][$index] = [
        '#type' => 'details',
        '#title' => $this->t('Page type @num - @name', ['@num' => $index + 1, '@name' => $pages[$index]['name']]),
        '#open' => FALSE,
      ];

      $form['pages_container'][$index]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('name'),
        '#description' => $this->t('Enter the name of the page(s) or page types that you are targetting. This is just for display purpose.'),
        '#default_value' => $pages[$index]['name'] ?? '',
        // '#ajax' => [
        //   'callback' => '::updateDefaultPageOptions',
        //   'wrapper' => 'default-page-wrapper',
        //   'event' => 'change',
        // ],
      ];

      $form['pages_container'][$index]['condition_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Conditoin Type'),
        '#options' => [
          'path' => $this->t('Path'),
          'regex' => $this->t('Regex'),
          'content_type' => $this->t('Content Type'),
        ],
        '#default_value' => $pages[$index]['condition_type'] ?? 'path',
        '#description' => $this->t('The condition let you choose whether you want to target pages based on path, regex or content types. In future more conditions can be added further.'),
        '#ajax' => [
          'callback' => '::updateConditionField',
          'wrapper' => 'condition-field-wrapper-' . $index,
          'event' => 'change',
        ],
      ];

      // Container for condition field with ajax wrapper.
      $form['pages_container'][$index]['condition_wrapper'] = [
        '#type' => 'container',
        '#prefix' => '<div id="condition-field-wrapper-' . $index . '">',
        '#suffix' => '</div>',
      ];

      // Get the current condition type from form state or default.
      $condition_type = $form_state->getValue(['pages_container', $index, 'condition_type'])
        ?? $pages[$index]['condition_type']
        ?? 'path';

      // Build appropriate condition field based on condition type.
      switch ($condition_type) {
        case 'path':
          $form['pages_container'][$index]['condition_wrapper']['path'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Path'),
            '#description' => $this->t('Enter the relative path (e.g., /about-us). It will test against <em>window.location.pathname</em>. If you want to test against url differently, you need to extend you logic in Salesforce Personalize SiteMap config accordingly.'),
            '#default_value' => $pages[$index]['path'] ?? '',
            '#element_validate' => ['::validatePathPattern'],
            // '#field_prefix' => '/',
          ];
          break;

        case 'regex':
          $form['pages_container'][$index]['condition_wrapper']['regex'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Regex Pattern'),
            '#description' => $this->t('Enter a valid regular expression (e.g., ^/blog/.*$ or /^\/node\/\d+/). You can use regex to test against the page url or any class on page that will match against these page types. You need to extend you logic in Salesforce Personalize SiteMap config accordingly.'),
            '#default_value' => $pages[$index]['regex'] ?? '',
            '#element_validate' => ['::validateRegexPattern'],
          ];
          break;

        case 'content_type':
          $form['pages_container'][$index]['condition_wrapper']['content_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Content Type'),
            '#description' => $this->t('Select the content type to apply personalization. This will test if the current page is any of selected content type by checking the page-node-type-[content-type] class on &lt;body&gt; tag.'),
            '#options' => $this->getContentTypes(),
            '#multiple' => TRUE,
            '#default_value' => $pages[$index]['content_type'] ?? '',
          ];
          break;
      }

      // Content Zones fieldset for this page.
      $form['pages_container'][$index]['content_zones'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Content Zones'),
        '#prefix' => '<div id="content-zones-wrapper-' . $index . '">',
        '#suffix' => '</div>',
      ];

      // Get or initialize content zones for this page.
      $zones = $pages[$index]['content_zones'] ?? [['name' => '', 'selector' => '']];
      // When form rebuilds after ajax callback, always take the values from
      // $form_state instead of from stored config.
      $zones = $form_state->getValue(['pages_container', $index, 'content_zones'], $zones);
      $zone_count = $form_state->get(['page_count_' . $index, 'zone_count']) ?: count($zones);
      $form_state->set(['page_count_' . $index, 'zone_count'], $zone_count);

      // Build form elements for each content zone
      // Remove the add zone button. It will be added later in content zones
      // array. This is removed to avoid looping it through.
      unset($zones['add_zone']);
      foreach ($zones as $key => $zone) {
        $form['pages_container'][$index]['content_zones'][$key] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['container-inline']],
        ];

        $form['pages_container'][$index]['content_zones'][$key]['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Content Zone name'),
          '#default_value' => $zone['name'] ?? '',
          '#size' => 30,
          // '#description' => $this->t('The content zone name.'),
        ];

        $form['pages_container'][$index]['content_zones'][$key]['selector'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Content Zone CSS selector'),
          '#default_value' => $zone['selector'] ?? '',
          '#size' => 30,
          // '#description' => $this->t('The css selector to target that zone(s).'),
        ];

        $form['pages_container'][$index]['content_zones'][$key]['delete'] = [
          '#type' => 'submit',
          '#value' => $this->t('Delete'),
          '#name' => 'delete_zone_' . $index . '_' . $key,
          '#submit' => ['::removeZone'],
          '#ajax' => [
            'callback' => '::updateContentZones',
            'wrapper' => 'content-zones-wrapper-' . $index,
          ],
          '#page_index' => $index,
          '#zone_index' => $key,
        ];
      }

      // Add more zones button.
      $form['pages_container'][$index]['content_zones']['add_zone'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add more'),
        '#name' => 'add_zone_' . $index,
        '#submit' => ['::addZone'],
        '#ajax' => [
          'callback' => '::updateContentZones',
          'wrapper' => 'content-zones-wrapper-' . $index,
        ],
        '#page_index' => $index,
      ];

      // Delete page button.
      $form['pages_container'][$index]['delete_page'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete page'),
        '#name' => 'delete_page_' . $index,
        '#submit' => ['::removePage'],
        '#ajax' => [
          'callback' => '::updateForm',
          'wrapper' => 'sfmc-personalization-pages-form',
        ],
        '#page_index' => $index,
      ];
    }

    // Add more pages button.
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
   * Validates the path pattern.
   */
  public function validatePathPattern($element, FormStateInterface $form_state, $form) {
    $value = $element['#value'];
    if (!empty($value) && !str_starts_with($value, '/')) {
      $form_state->setError($element, $this->t('Path must start with a forward slash (/).'));
    }
  }

  /**
   * Validates the regex pattern.
   */
  public function validateRegexPattern($element, FormStateInterface $form_state, $form) {
    $value = $element['#value'];
    if (!empty($value)) {
      // Test if the pattern is valid.
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
    return $form['pages_container'][$page_index]['content_zones'];
  }

  /**
   * Submit handler for adding a new page.
   */
  public function addPage(array &$form, FormStateInterface $form_state) {
    // $trigger = $form_state->getTriggeringElement();
    // $page_index = $trigger['#page_index'];
    $empty_page = [
      'name' => '',
      'condition_type' => 'path',
      'condition_wrapper' => [],
      'content_zones' => [],
    ];
    $pages = $form_state->getValue('pages_container', []);
    $pages[] = $empty_page;
    $form_state->setValue('pages_container', $pages);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for adding a new content zone.
   */
  public function addZone(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $page_index = $trigger['#page_index'];

    $empty_zone = ['name' => '', 'selector' => ''];
    $zones = $form_state->getValue(['pages_container', $page_index, 'content_zones'], []);
    $zones[] = $empty_zone;
    $form_state->setValue(['pages_container', $page_index, 'content_zones'], $zones);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a page.
   */
  public function removePage(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $page_index = $trigger['#page_index'];

    // Get the current pages.
    $values = $form_state->getValue('pages_container');

    // Remove the triggered page.
    unset($values[$page_index]);

    // Set the updated values back.
    $form_state->setValue('pages_container', $values);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a content zone.
   */
  public function removeZone(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $page_index = $trigger['#page_index'];

    // Get the current content zones.
    $content_zones = $form_state->getValue(['pages_container', $page_index, 'content_zones']);

    // Remove the triggered zone.
    unset($content_zones[$trigger['#zone_index']]);


    // Set the updated values back.
    $form_state->setValue(['pages_container', $page_index, 'content_zones'], $content_zones);

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
