<?php

namespace DrupalComposer\DrupalScaffoldDocker;

use Composer\Composer;
use Composer\IO\IOInterface;

/**
 * Composer dependency resolver for drupal scaffold docker.
 */
class Resolver {

  /**
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * Handler constructor.
   *
   * @param Composer $composer
   * @param IOInterface $io
   */
  public function __construct(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * Recursive dependency resolution.
   *
   * Blog article: ({@link http://mamchenkov.net/wordpress/2016/11/22/dependency-resolution-with-graphs-in-php}).
   *
   * @param string $item
   *   Item to resolve dependencies for
   * @param array $items
   *   List of all items with dependencies
   * @param array $resolved
   *   List of resolved items
   * @param array $unresolved
   *   List of unresolved items
   *
   * @return array
   */
  public function depResolve($item, array $items, array $resolved, array $unresolved) {
    array_push($unresolved, $item);
    foreach ($items[$item]['dependencies'] as $dep) {
      if (!in_array($dep, $resolved)) {
        if (!in_array($dep, $unresolved)) {
          array_push($unresolved, $dep);
          list($resolved, $unresolved) = $this->depResolve($dep, $items, $resolved, $unresolved);
        }
        else {
          throw new RuntimeException("Circular dependency: $item -> $dep");
        }
      }
    }
    // Add $item to $resolved if it's not already there.
    if (!in_array($item, $resolved)) {
      array_push($resolved, $item);
    }
    // Remove all occurrences of $item in $unresolved.
    while (($index = array_search($item, $unresolved)) !== FALSE) {
      unset($unresolved[$index]);
    }
    return [$resolved, $unresolved];
  }

}
