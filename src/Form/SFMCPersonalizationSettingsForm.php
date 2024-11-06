<?php

namespace Drupal\sfmc_personalization\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a configuration form for SFMC Personalization settings.
 */
class SFMCPersonalizationSettingsForm extends ConfigFormBase {

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
   *
   * @param array<string, mixed> $form
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sfmc_personalization.settings');

    $form['beacon_script_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JavaScript Integration Beacon Script URL'),
      '#description' => $this->t('Please enter the JavaScript url. This you will get from Salesforce marketing cloud Personalization configuration under Web -> JavaScript Integration.'),
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
      '#description' => $this->t('If this option is set the async attribute on script tag will be set.'),
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
    ];

    // Allowed Domains
    $form['allowed_domains'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Allowed Domains'),
      '#attributes' => ['id' => 'domains-wrapper'],
    ];

    $domains = $config->get('allowed_domains') ?: [''];
    $domain_count = $form_state->get('domain_count') ?: count($domains);

    $form_state->set('domain_count', $domain_count);

    for ($i = 0; $i < $domain_count; $i++) {
      $form['allowed_domains']['domain_' . $i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Domain name @num', ['@num' => $i + 1]),
        '#default_value' => isset($domains[$i]) ? $domains[$i] : '',
      ];
    }

    $form['allowed_domains']['add_domain'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add more'),
      // '#submit' => [$this, 'addMoreDomains'],
      '#ajax' => [
        'callback' => [$this, 'addMoreDomains'],
        'wrapper' => 'domains-wrapper',
      ],
    ];

    // Global Content Zones
    $form['content_zones'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Global Content Zones'),
      '#attributes' => ['id' => 'zones-wrapper'],
    ];

    $zones = $config->get('content_zones') ?: [['label' => '', 'selector' => '']];
    $zone_count = $form_state->get('zone_count') ?: count($zones);

    $form_state->set('zone_count', $zone_count);

    for ($i = 0; $i < $zone_count; $i++) {
      $form['content_zones']['zone_' . $i] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['container-inline']],
      ];
      $form['content_zones']['zone_' . $i]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Content Zone label'),
        '#default_value' => isset($zones[$i]['label']) ? $zones[$i]['label'] : '',
      ];
      $form['content_zones']['zone_' . $i]['selector'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Content Zone CSS selector'),
        '#default_value' => isset($zones[$i]['selector']) ? $zones[$i]['selector'] : '',
      ];
    }

    $form['content_zones']['add_zone'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add more'),
      // '#submit' => ['::addMoreZones'],
      '#ajax' => [
        'callback' => [$this, 'addMoreZones'],
        'wrapper' => 'zones-wrapper',
      ],
    ];

    // User Attributes
    $form['user_attributes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Expose User Attributes'),
    ];

    $attributes = [
      'first_name' => $this->t('First Name'),
      'last_name' => $this->t('Last Name'),
      'persona' => $this->t('Persona'),
      'zipcode' => $this->t('Zipcode'),
      'recipe_preferences' => $this->t('Recipe Preferences'),
    ];

    foreach ($attributes as $key => $label) {
      $form['user_attributes'][$key] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#default_value' => $config->get('user_attributes.' . $key),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sfmc_personalization.settings');

    // Save basic settings
    $config->set('beacon_script_url', $form_state->getValue('beacon_script_url'))
      ->set('async', $form_state->getValue('async'))
      ->set('script_location', $form_state->getValue('script_location'));

    // Save domains
    $domains = [];
    $domain_count = $form_state->get('domain_count');
    for ($i = 0; $i < $domain_count; $i++) {
      if ($domain = $form_state->getValue('domain_' . $i)) {
        $domains[] = $domain;
      }
    }
    $config->set('allowed_domains', $domains);

    // Save content zones
    $zones = [];
    $zone_count = $form_state->get('zone_count');
    for ($i = 0; $i < $zone_count; $i++) {
      $label = $form_state->getValue(['zone_' . $i, 'label']);
      $selector = $form_state->getValue(['zone_' . $i, 'selector']);
      if ($label || $selector) {
        $zones[] = [
          'label' => $label,
          'selector' => $selector,
        ];
      }
    }
    $config->set('content_zones', $zones);

    // Save user attributes
    $attributes = [
      'first_name',
      'last_name',
      'persona',
      'zipcode',
      'recipe_preferences',
    ];
    foreach ($attributes as $attribute) {
      $config->set('user_attributes.' . $attribute, $form_state->getValue($attribute));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback for adding more domains.
   */
  public function addMoreDomains(array &$form, FormStateInterface $form_state) {
    $domain_count = $form_state->get('domain_count');
    $form_state->set('domain_count', $domain_count + 1);
    $form_state->setRebuild();
    return $form['allowed_domains'];
  }

  /**
   * Ajax callback for adding more content zones.
   */
  public function addMoreZones(array &$form, FormStateInterface $form_state) {
    $zone_count = $form_state->get('zone_count');
    $form_state->set('zone_count', $zone_count + 1);
    $form_state->setRebuild();
    return $form['content_zones'];
  }
}
