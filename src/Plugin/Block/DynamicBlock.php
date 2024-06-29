<?php

namespace Drupal\dynamic_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dynamic_block\Service\DynamicBlockService;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Dynamic content' Block.
 *
 * @Block(
 *   id = "dynamic_content_block",
 *   admin_label = @Translation("Dynamic content"),
 * )
 */
class DynamicBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Request handler.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * Config for display of cv upload field.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   *   cv upload config for display.
   */
  protected $configFactory;

  /**
   * A cache backend interface.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Dynamic block service.
   *
   * @var \Drupal\dynamic_block\Service\DynamicBlockService
   */
  protected $dynamicBlockService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $request,
    ConfigFactoryInterface $config_factory,
    CacheBackendInterface $cache,
    DynamicBlockService $dynamic_block_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->request = $request->getCurrentRequest();
    $this->configFactory = $config_factory;
    $this->cache = $cache;
    $this->dynamicBlockService = $dynamic_block_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('cache.default'),
      $container->get('dynamic_block.service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['#theme'] = 'dynamic_block';
    $cid = 'dynamic_block_content_demo';
    $dynamic_block_settings = $this->configFactory->get('dynamic_content_block.settings');
    $dynamic_content_priority = $dynamic_block_settings->get('dynamic_block_content_priority') ?? 'time_based';
    if ($dynamic_content_priority == 'time_based') {
      $current_time = strtotime(date('H:i:s'));
      $midnight = strtotime('00:00:00');
      $noon = strtotime('12:00:00');
      if ($current_time >= $midnight && $current_time < $noon) {
        $cid = 'dynamic_block_content_demo_morning';
      }
      else {
        $cid = 'dynamic_block_content_demo_evening';
      }
    }
    elseif ($dynamic_content_priority == 'location_based') {
      // India 49.43.232.136.
      // Uk 178.238.11.6.
      // France 176.31.84.249.
      // China 218.107.132.66.
      $ip = $this->request->getClientIp();
      $ip = '218.107.132.66';
      $location_details = $this->dynamicBlockService->getUserLocationDetails($ip);
      $cid = 'dynamic_block_content_demo_' . $location_details['geoplugin_countryCode'];
    }
    elseif ($dynamic_content_priority == 'history_based') {
      $session = $this->request->getSession();
      $cid = 'dynamic_block_content_demo_' . $session->getId();
    }
    $cache_data = $this->cache->get($cid);
    if ($cache_data) {
      $data = $cache_data->data;
    }
    else {
      $data = $this->setCache($cid);
    }
    $build['#dynamic_block_content'] = $data;
    return $build;
  }

  /**
   * Build node ids as array.
   */
  protected function buildNodeIds($nodes) {
    $nids = [];
    foreach ($nodes as $node) {
      $nids[] = (int) reset($node);
    }
    return $nids;
  }

  /**
   * Using drupal cache instead of connecting to database.
   */
  protected function setCache($cid) {
    $dynamic_block_settings = $this->configFactory->get('dynamic_content_block.settings');
    $dynamic_content_priority = $dynamic_block_settings->get('dynamic_block_content_priority') ?? 'time_based';
    $nodes = [];
    $cid = 'dynamic_block_content_demo';
    if (!empty($dynamic_content_priority)) {
      // Build nodes based on time based.
      if ($dynamic_content_priority == 'time_based') {
        $time_based_setting = $dynamic_block_settings->get('dynamic_block_content_time_based');
        $current_time = strtotime(date('H:i:s'));
        $midnight = strtotime('00:00:00');
        $noon = strtotime('12:00:00');
        if ($current_time >= $midnight && $current_time < $noon) {
          $nodes = !empty($time_based_setting['dynamic_block_content_morning']) ? $this->buildNodeIds($time_based_setting['dynamic_block_content_morning']) : [];
        }
        else {
          $nodes = !empty($time_based_setting['dynamic_block_content_evening']) ? $this->buildNodeIds($time_based_setting['dynamic_block_content_evening']) : [];
        }
      }
      // Build the nodes for Location based.
      elseif ($dynamic_content_priority == 'location_based') {
        $location_based_settings = $dynamic_block_settings->get('dynamic_block_content_location_group');
        $ip = $this->request->getClientIp();
        $ip = '218.107.132.66';
        $location_details = $this->dynamicBlockService->getUserLocationDetails($ip);
        foreach ($location_based_settings as $location_settings) {
          if (!empty($location_details['geoplugin_countryCode']) && strtolower($location_details['geoplugin_countryCode']) == $location_settings['dynamic_block_content_locations']) {
            $nodes = $this->buildNodeIds($location_settings['dynamic_block_content_location_content']) ?? [];
          }
        }
      }
      // Build the nodes for history based.
      elseif ($dynamic_content_priority == 'history_based') {
        $nodes = $this->request->getSession()->get('node_history', []);
        if (!empty($nodes) && is_array($nodes)) {
          $nodes = count($nodes) > 3 ? array_slice($nodes, 0, 3) : $nodes;
        }
        else {
          $nodes = $this->buildNodeIds($dynamic_block_settings->get('dynamic_block_content_user_behaviour')) ?? [];
        }
      }
    }
    $args = [implode('+', $nodes)];
    $view = Views::getView('dynamic_content_block');
    $view->setDisplay('block_1');
    $view->setArguments($args);
    $view->execute();
    $content = [
      'content' => $view->render(),
      'items' => count($nodes),
    ];
    $tags = [
      'dynamic_content_block',
    ];
    $this->cache->set($cid, $content, time() + 86400, $tags);
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['dynamic_content_block'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user_country']);
  }

}
