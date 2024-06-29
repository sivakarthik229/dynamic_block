<?php

namespace Drupal\dynamic_block\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for the dynamic block module.
 */
class DynamicBlockService {

  /**
   * Http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a new DynamicBlockService instance.
   */
  public function __construct(Client $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * Gets user location based on ip address.
   */
  public function getUserLocationDetails($ip) {
    $url = 'http://www.geoplugin.net/json.gp?ip=' . $ip;
    try {
      $request = $this->httpClient->get($url);
      $file_contents = $request->getBody()->getContents();
      return json_decode($file_contents, TRUE);
    }
    catch (RequestException $e) {
    }
  }

}
