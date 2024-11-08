<?php

namespace Drupal\sfmc_personalization\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the SFMC Personalization pages configuration form.
 */
class SFMCPersonalizationPagesForm extends ConfigFormBase {

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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sfmc_personalization.pages');

    // Get existing pages or initialize with one empty page
    $pages = $config->get('pages') ?: [
      [
        'name' => '',
        'path_type' => 'url',
        'path' => '',
        'content_zones' => [],
      ]
    ];

    $page_count = $form_state->get('page_count') ?: count($pages);
    $form_state->set('page_count', $page_count);

    // Add default page type selection at the top
    $form['default_page_type'] = [
      '#type' => 'container',
      '#prefix' => '<div id="default-page-wrapper">',
      '#suffix' => '</div>',
    ];

    // Only show default page selection if there are pages
    if ($page_count > 0) {
      $page_options = [];
      for ($i = 0; $i < $page_count; $i++) {
        $page_name = !empty($pages[$i]['name']) ? $pages[$i]['name'] : $this->t('Page type @num', ['@num' => $i + 1]);
        $page_options[$i] = $page_name;
      }

      $form['default_page_type']['default_page'] = [
        '#type' => 'radios',
        '#title' => $this->t('Default page type'),
        '#options' => $page_options,
        '#default_value' => $config->get('default_page') ?? 0,
      ];
    }

    // Create container for pages
    $form['pages_container'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="pages-wrapper">',
      '#suffix' => '</div>',
    ];

    // Build form elements for each page
    for ($i = 0; $i < $page_count; $i++) {
      $form['pages_container'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Page type @num', ['@num' => $i + 1]),
        '#open' => TRUE,
      ];

      $form['pages_container'][$i]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('name'),
        '#description' => $this->t('Enter the name of the page. This is just for display purpose.'),
        '#default_value' => $pages[$i]['name'] ?? '',
        '#ajax' => [
          'callback' => '::updateDefaultPageOptions',
          'wrapper' => 'default-page-wrapper',
          'event' => 'change',
        ],
      ];

      $form['pages_container'][$i]['path_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Path Type'),
        '#options' => [
          'url' => $this->t('URL'),
          'regex' => $this->t('Regex'),
        ],
        '#default_value' => $pages[$i]['path_type'] ?? 'url',
      ];

      $form['pages_container'][$i]['path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Path'),
        '#description' => $this->t('Enter the path that will match against the website location pathname.'),
        '#default_value' => $pages[$i]['path'] ?? '',
      ];

      // Content Zones fieldset for this page
      $form['pages_container'][$i]['content_zones'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Content Zones'),
        '#prefix' => '<div id="content-zones-wrapper-' . $i . '">',
        '#suffix' => '</div>',
      ];

      // Get or initialize content zones for this page
      $zones = $pages[$i]['content_zones'] ?? [['label' => '', 'selector' => '']];
      $zone_count = $form_state->get('zone_count_' . $i) ?: count($zones);
      $form_state->set('zone_count_' . $i, $zone_count);

      // Build form elements for each content zone
      for ($j = 0; $j < $zone_count; $j++) {
        $form['pages_container'][$i]['content_zones'][$j] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['container-inline']],
        ];

        $form['pages_container'][$i]['content_zones'][$j]['label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Content Zone label'),
          '#default_value' => $zones[$j]['label'] ?? '',
          '#size' => 30,
        ];

        $form['pages_container'][$i]['content_zones'][$j]['selector'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Content Zone CSS selector'),
          '#default_value' => $zones[$j]['selector'] ?? '',
          '#size' => 30,
        ];

        $form['pages_container'][$i]['content_zones'][$j]['delete'] = [
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
      $form['pages_container'][$i]['content_zones']['add_zone'] = [
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
      $form['pages_container'][$i]['delete_page'] = [
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
   * Ajax callback to update the entire form.
   */
  public function updateForm(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Ajax callback to update the default page options.
   */
  public function updateDefaultPageOptions(array &$form, FormStateInterface $form_state) {
    return $form['default_page_type'];
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

    if ($page_count > 1) {
      $form_state->set('page_count', $page_count - 1);
    }

    $form_state->setRebuild();
  }

  /**
   * Submit handler for adding a new content zone.
   */
  public function addZone(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $page_index = $trigger['#page_index'];
    $zone_count = $form_state->get('zone_count_' . $page_index);
    $form_state->set('zone_count_' . $page_index, $zone_count + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a content zone.
   */
  public function removeZone(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $page_index = $trigger['#page_index'];
    $zone_count = $form_state->get('zone_count_' . $page_index);

    if ($zone_count > 1) {
      $form_state->set('zone_count_' . $page_index, $zone_count - 1);
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sfmc_personalization.pages');
    $pages = [];

    // Save default page selection
    if ($form_state->hasValue('default_page')) {
      $config->set('default_page', $form_state->getValue('default_page'));
    }

    $values = $form_state->getValue('pages_container');
    foreach ($values as $page) {
      $content_zones = [];
      foreach ($page['content_zones'] as $zone) {
        if (is_array($zone) && !empty($zone['label'])) {
          $content_zones[] = [
            'label' => $zone['label'],
            'selector' => $zone['selector'],
          ];
        }
      }

      if (!empty($page['name'])) {
        $pages[] = [
          'name' => $page['name'],
          'path_type' => $page['path_type'],
          'path' => $page['path'],
          'content_zones' => $content_zones,
        ];
      }
    }

    $config->set('pages', $pages)->save();
    parent::submitForm($form, $form_state);
  }
}