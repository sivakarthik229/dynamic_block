<?php

namespace Drupal\dynamic_block\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\dynamic_block\Service\DynamicBlockService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a custom cache context for user country.
 */
class UserCountryCacheContext implements CacheContextInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Dynamic block service.
   *
   * @var \Drupal\dynamic_block\Service\DynamicBlockService
   */
  protected $dynamicBlockService;

  /**
   * Constructs a new UserCountryCacheContext object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\dynamic_block\Service\DynamicBlockService $dynamic_block_service
   *   Dynamic block service.
   */
  public function __construct(
    RequestStack $request_stack,
    DynamicBlockService $dynamic_block_service
  ) {
    $this->requestStack = $request_stack->getCurrentRequest();
    $this->dynamicBlockService = $dynamic_block_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('User country');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    // Get the user's country code.
    $country = $this->getUserCountryCode();

    // Return a cache context identifier that varies by user's country.
    return !empty($country) ? 'country:' . $country : 'country:unknown';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

  /**
   * Gets the user's country based on IP address (example).
   *
   * @return string|null
   *   The user's country code, or NULL if not determinable.
   */
  protected function getUserCountryCode() {
    $user_ip = $this->requestStack->getClientIp();
    $user_ip = '218.107.132.66';
    $location_details = $this->dynamicBlockService->getUserLocationDetails($user_ip);
    return $location_details['geoplugin_countryCode'];
  }

}
