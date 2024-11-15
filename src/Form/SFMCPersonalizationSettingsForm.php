<?php

namespace Drupal\sfmc_personalization\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SfmcPersonalizationSettingsForm extends ConfigFormBase {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a ConfigForm object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sfmc_personalization.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sfmc_personalization_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sfmc_personalization.settings');

    // JavaScript Integration section
    $form['beacon_script_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JavaScript Integration Beacon Script URL'),
      '#description' => $this->t('Please enter the JavaScript url. This url you will get from Salesforce marketing cloud personalization configuration under Web -> JavaScript Integration.'),
      '#default_value' => $config->get('beacon_script_url'),
      '#required' => TRUE,
    ];

    $form['javascript_integration'] = [
      '#type' => 'details',
      '#title' => $this->t('JavaScript Integration'),
      '#open' => TRUE,
    ];

    $form['javascript_integration']['async'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Async'),
      '#description' => $this->t('If this option is set the <em>async</em> attribute on script tag will be set. That will load the Salesforce Personalization SDK asynchronously. Salesforce Personalization supports <a href="@sync_async_integration" target="_blank" >Synchronous or Asynchronous Integration.</a>.', ['@sync_async_integration' => 'https://developer.salesforce.com/docs/marketing/personalization/guide/web-integration-considerations.html?q=flicker#synchronous-or-asynchronous-integration']),
      '#default_value' => $config->get('async'),
    ];

    $form['script_location'] = [
      '#type' => 'radios',
      '#title' => $this->t('Script Location'),
      '#options' => [
        'header' => $this->t('Header'),
        'footer' => $this->t('Footer'),
      ],
      '#default_value' => $config->get('script_location') ?: 'header',
      '#description' => $this->t('Choose where to place the Salesforce Personalization SDK script. Header placement loads earlier but may block rendering. Footer placement loads after content but may delay personalization. For best performance, use header with async enabled but with header option you will not be able to use <em>window.drupalSettings</em> in your sitemap config. The footer is recommended if you are going to use <em>window.drupalSettings</em> in your site map config. The <a href="@flicker-defender" target="_blank">Flicker Defender</a> only works with Header optoin.', ['@flicker-defender' => 'https://developer.salesforce.com/docs/marketing/personalization/guide/flicker-defender.html']),
    ];

    $form['allowed_domains'] = [
      '#type' => 'details',
      '#title' => $this->t('Allowed Domains'),
      '#open' => TRUE,
    ];

    $form['allowed_domains']['domain'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Domains List'),
      '#default_value' => $config->get('domain'),
      '#description' => $this->t('Specify the domains. You can add multiple domains on separate lines. The same domain must be added under Salesforce Personalization configuration under Web >> Website Configuration >> Allowed Domains.'),
    ];

    // Content Zones fieldset
    $form['content_zones'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Global Content Zones'),
      '#prefix' => '<div id="zones-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    // Get zones from form state or initialize from config
    if (!$form_state->has('content_zones')) {
      $stored_zones = $config->get('content_zones') ?: [];
      $zones = [];
      foreach ($stored_zones as $zone) {
        $zones[] = $zone + ['zone_id' => uniqid()];
      }
      if (empty($zones)) {
        $zones[] = ['zone_id' => uniqid(), 'name' => '', 'selector' => ''];
      }
      $form_state->set('content_zones', $zones);
    }

    $zones = $form_state->get('content_zones');

    // Build zone fields
    foreach ($zones as $delta => $zone) {
      $form['content_zones'][$delta] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['container-inline']],
      ];

      // Hidden field to store the zone_id
      $form['content_zones'][$delta]['zone_id'] = [
        '#type' => 'hidden',
        '#value' => $zone['zone_id'],
      ];

      $form['content_zones'][$delta]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Content Zone name'),
        '#default_value' => $zone['name'],
        '#prefix' => '<div class="zone-field-wrapper">',
      ];

      $form['content_zones'][$delta]['selector'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Content Zone CSS selector'),
        '#default_value' => $zone['selector'],
      ];

      if (count($zones) > 1) {
        $form['content_zones'][$delta]['remove'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_' . $zone['zone_id'],
          '#submit' => ['::removeZone'],
          '#ajax' => [
            'callback' => '::ajaxRefreshContentZones',
            'wrapper' => 'zones-wrapper',
          ],
          '#suffix' => '</div>',
          '#zone_id' => $zone['zone_id'],
        ];
      }
    }

    $form['content_zones']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add more'),
      '#submit' => ['::addMoreZones'],
      '#ajax' => [
        'callback' => '::ajaxRefreshContentZones',
        'wrapper' => 'zones-wrapper',
      ],
    ];

    // User Attributes section
    $user_fields = $this->entityFieldManager->getFieldDefinitions('user', 'user');
    $options = [];
    foreach ($user_fields as $field_name => $field_definition) {
      $options[$field_name] = $field_definition->getLabel();
    }

    $form['user_attributes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Expose User Attributes'),
    ];

    $form['user_attributes']['user_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select User Fields'),
      '#description' => $this->t('Choose the user fields you want to enable.'),
      '#options' => $options,
      '#default_value' => array_intersect_key(array_flip($config->get('user_fields') ?? []), $options),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback for refreshing content zones.
   */
  public function ajaxRefreshContentZones(array &$form, FormStateInterface $form_state) {
    return $form['content_zones'];
  }

  /**
   * Submit handler to add more content zones.
   */
  public function addMoreZones(array &$form, FormStateInterface $form_state) {
    $zones = $form_state->get('content_zones');
    $zones[] = [
      'zone_id' => uniqid(),
      'name' => '',
      'selector' => '',
    ];
    $form_state->set('content_zones', $zones);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler to remove a content zone.
   */
  public function removeZone(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $zone_id_to_remove = $triggering_element['#zone_id'];

    $zones = $form_state->get('content_zones');

    // Find and remove the zone with the matching zone_id
    foreach ($zones as $delta => $zone) {
      if ($zone['zone_id'] === $zone_id_to_remove) {
        unset($zones[$delta]);
        break;
      }
    }

    // Reindex the array
    $zones = array_values($zones);
    $form_state->set('content_zones', $zones);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sfmc_personalization.settings');

    // Save basic settings
    $config
      ->set('beacon_script_url', $form_state->getValue('beacon_script_url'))
      ->set('async', $form_state->getValue('async'))
      ->set('script_location', $form_state->getValue('script_location'))
      ->set('domain', $form_state->getValue('domain'))
      ->set('user_fields', array_filter($form_state->getValue('user_fields')));

    // Save content zones (remove zone_id before saving)
    $zones = [];
    $values = $form_state->getValue('content_zones');
    if (!empty($values) && is_array($values)) {
      foreach ($values as $zone) {
        if (is_array($zone) && isset($zone['name'], $zone['selector']) &&
            !empty($zone['name']) && !empty($zone['selector'])) {
          $zones[] = [
            'name' => $zone['name'],
            'selector' => $zone['selector'],
          ];
        }
      }
    }
    $config->set('content_zones', $zones);

    $config->save();
    parent::submitForm($form, $form_state);
  }
}
