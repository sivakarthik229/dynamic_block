<?php

namespace Drupal\dynamic_block\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\path_alias\AliasManager;
use Drupal\views\Views;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Eventsubscriber to nodes to history.
 */
class DynamicBlockEventSubscriber implements EventSubscriberInterface {

  /**
   * An alias manager to find the alias for the current system path.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a new DynamicBlockEventSubscriber instance.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The Path Alias Manager.
   */
  public function __construct(AliasManager $alias_manager) {
    $this->aliasManager = $alias_manager;
  }

  /**
   * Add the node pages to user session from history.
   */
  public function nodeHistoryStorage(RequestEvent $event) {
    $request = $event->getRequest();
    $current_uri = $request->server->get('REQUEST_URI', NULL);
    // Current url should not be admin url.
    if (!empty($current_uri) && strpos($current_uri, '/admin') === FALSE) {
      // Get the original url from alias.
      $alias = $this->aliasManager->getPathByAlias($current_uri);
      // Check the original url is node or not.
      if (preg_match('/node\/(\d+)/', $alias, $matches)) {
        $old_nodes = $existing_nodes = $request->getSession()->get('node_history', []);
        if (!empty($existing_nodes)) {
          if (is_array($existing_nodes) && !in_array((int) $matches[1], $existing_nodes)) {
            $existing_nodes[] = (int) $matches[1];
          }
          elseif (is_string($existing_nodes) && $existing_nodes != $matches[1]) {
            $existing_nodes = [(int) $existing_nodes, $matches[1]];
          }
        }
        else {
          $existing_nodes = [(int) $matches[1]];
        }

        // Get only the latest 3 nodes from history and set them.
        if (count($existing_nodes) > 3) {
          $existing_nodes = array_slice(array_reverse($existing_nodes), 0, 3);
        }

        // Invalidate the block cache
        // when ever the node history changes for the user.
        if (!empty(array_diff($old_nodes, $existing_nodes))) {
          // Invalidate the cache tags.
          Cache::invalidateTags(['dynamic_content_block']);
          // Invalidate view cache on config save.
          $view = Views::getView('dynamic_content_block');
          $view->storage->invalidateCaches();
        }
        // Set the history for the nodes in session.
        $request->getSession()->set('node_history', $existing_nodes);
      }
    }
    return TRUE;
  }

  /**
   * Listen to kernel.request events and call nodeHistoryStorage.
   *
   * @return array
   *   Event names to listen to (key) and methods to call (value).
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['nodeHistoryStorage', 300];
    return $events;
  }

}
