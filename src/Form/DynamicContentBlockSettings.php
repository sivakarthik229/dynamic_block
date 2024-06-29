<?php

namespace Drupal\dynamic_block\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Dynamic content Block plugin.
 */
class DynamicContentBlockSettings extends ConfigFormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    )
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dynamic_content_block.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dynamic_block_custom_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dynamic_content_block.settings');

    // Time based content.
    $morning_time_nodes = $this->loadNodes($config->get('dynamic_block_content_time_based')['dynamic_block_content_morning'] ?? []);
    $evening_time_nodes = $this->loadNodes($config->get('dynamic_block_content_time_based')['dynamic_block_content_evening'] ?? []);

    // Location based nodes.
    $location_based_count = !empty($config->get('dynamic_block_content_location_group')) && is_array($config->get('dynamic_block_content_location_group')) ? count($config->get('dynamic_block_content_location_group')) : 1;

    $form['#tree'] = TRUE;
    $form['dynamic_block_content_priority'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select the priority for which content need to display to user'),
      '#options' => [
        'time_based' => $this->t('Time based'),
        'location_based' => $this->t('Location based'),
        'history_based' => $this->t('User behaviour based'),
      ],
      '#default_value' => $config->get('dynamic_block_content_priority'),
    ];

    $num_items = $form_state->get('num_items') ?? $location_based_count;
    $form_state->set('num_items', $num_items);
    $form['dynamic_block_content_time_based'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dynamic content block based on time'),
    ];
    $form['dynamic_block_content_time_based']['dynamic_block_content_morning'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Morning content'),
      '#target_type' => 'node',
      '#bundles' => ['basic'],
      '#tags' => TRUE,
      '#description' => $this->t('Content shown for user from 12AM to 12PM'),
      '#default_value' => $morning_time_nodes,
    ];
    $locations = [
      'in' => $this->t('India'),
      'us' => $this->t('America'),
      'cn' => $this->t('China'),
      'gb' => $this->t('United kingdom'),
      'fr' => $this->t('France'),
    ];
    $form['dynamic_block_content_time_based']['dynamic_block_content_evening'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Afternoon content'),
      '#target_type' => 'node',
      '#bundles' => ['basic'],
      '#tags' => TRUE,
      '#description' => $this->t('Content shown for user from 12PM to 12AM'),
      '#default_value' => $evening_time_nodes,
    ];
    $form['dynamic_block_content_location_group'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dynamic content block based on location'),
      '#prefix' => '<div id="location-group-wrapper">',
      '#suffix' => '</div>',
    ];
    for ($i = 0; $i < (int) $num_items; $i++) {
      $location_based_nodes = $this->loadNodes($config->get('dynamic_block_content_location_group')[$i]['dynamic_block_content_location_content'] ?? []);
      $form['dynamic_block_content_location_group'][$i] = [
        'dynamic_block_content_locations' => [
          '#type' => 'select',
          '#title' => $this->t('Select location'),
          '#options' => $locations,
          '#empty_option' => $this->t('Select'),
          '#default_value' => $config->get('dynamic_block_content_location_group')[$i]['dynamic_block_content_locations'] ?? NULL,
        ],
        'dynamic_block_content_location_content' => [
          '#type' => 'entity_autocomplete',
          '#title' => $this->t('Content based on above selected location'),
          '#tags' => TRUE,
          '#target_type' => 'node',
          '#bundles' => ['basic'],
          '#description' => $this->t('Content shown for user based on user location'),
          '#default_value' => $location_based_nodes,
        ],
      ];
    }
    $form['dynamic_block_content_location_group']['actions']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add more'),
      '#submit' => ['::addMoreSubmit'],
      '#ajax' => [
        'callback' => '::addMoreCallback',
        'wrapper' => 'location-group-wrapper',
      ],
    ];

    $form['dynamic_block_content_user_behaviour'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Default nodes for user behaviour'),
      '#target_type' => 'node',
      '#bundles' => ['basic'],
      '#tags' => TRUE,
      '#description' => $this->t('Content shown for user if there are no nodes in history'),
      '#default_value' => $this->loadNodes($config->get('dynamic_block_content_user_behaviour') ?? []),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state_values = $form_state->getValue(['dynamic_block_content_location_group']);

    $location_mapping = [];
    foreach ($form_state_values as $key => $value) {
      if (is_numeric($key)) {
        $location_mapping[$key] = $form_state_values[$key];
      }
    }
    $this->configFactory()->getEditable('dynamic_content_block.settings')
      ->set('dynamic_block_content_time_based', $form_state->getValue('dynamic_block_content_time_based'))
      ->set('dynamic_block_content_priority', $form_state->getValue('dynamic_block_content_priority'))
      ->set('dynamic_block_content_location_group', $location_mapping)
      ->set('dynamic_block_content_user_behaviour', $form_state->getValue('dynamic_block_content_user_behaviour'))
      ->save();

    // Invalidate the cache tags.
    Cache::invalidateTags(['dynamic_content_block']);

    // Invalidate view cache on config save.
    $view = Views::getView('dynamic_content_block');
    $view->storage->invalidateCaches();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit callback for 'Add more' button.
   */
  public function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    // Perform actions to dynamically add more fields.
    $num_items = (int) $form_state->get('num_items') + 1;
    $form_state->set('num_items', $num_items);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for 'Add more' button.
   */
  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['dynamic_block_content_location_group'];
  }

  /**
   * Load the nodes based on the ids.
   */
  protected function loadNodes($ids) {
    $nodes = [];
    foreach ($ids as $id) {
      $nodes[] = $this->entityTypeManager->load(reset($id));
    }
    return $nodes;
  }

}
