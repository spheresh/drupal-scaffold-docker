<?php

namespace DrupalComposer\DrupalScaffoldDocker;

use Composer\Composer;
use Composer\Factory;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Util\Filesystem;
use Composer\Script\Event;
use Composer\Installer\PackageEvent;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Composer custom handler for drupal scaffold docker.
 */
class Handler {

  const PRE_DRUPAL_SCAFFOLD_DOCKER_CMD = 'pre-drupal-scaffold-docker-cmd';
  const POST_DRUPAL_SCAFFOLD_DOCKER_CMD = 'post-drupal-scaffold-docker-cmd';

  /**
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * @var \Composer\Package\PackageInterface
   */
  protected $drupalCorePackage;

  /**
   * @var \DrupalComposer\DrupalScaffold\Resolver
   */
  protected $resolver;

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
   * @param $operation
   * @return mixed
   */
  protected function getCorePackage($operation) {
    if ($operation instanceof InstallOperation) {
      $package = $operation->getPackage();
    }
    elseif ($operation instanceof UpdateOperation) {
      $package = $operation->getTargetPackage();
    }
    if (isset($package) && $package instanceof PackageInterface && $package->getName() == 'drupal/core') {
      return $package;
    }
    return NULL;
  }

  /**
   * Marks scaffolding to be processed after an install or update command.
   *
   * @param \Composer\Installer\PackageEvent $event
   */
  public function onPostPackageEvent(PackageEvent $event) {
    $package = $this->getCorePackage($event->getOperation());
    if ($package) {
      // By explicitly setting the core package, the onPostCmdEvent() will
      // process the scaffolding automatically.
      $this->drupalCorePackage = $package;
    }
  }

  /**
   * Post install command event to execute the scaffolding.
   *
   * @param \Composer\Script\Event $event
   */
  public function onPostCmdEvent(Event $event) {
    // Only install the scaffolding if drupal/core was installed,
    // AND there are no scaffolding files present.
    if (isset($this->drupalCorePackage)) {
      $this->downloadScaffold();
    }
  }

  /**
   * Downloads drupal scaffold docker files for the current process.
   */
  public function downloadScaffold() {
    $composerFile = Factory::getComposerFile();
    $dispatcher = new EventDispatcher($this->composer, $this->io);
    $filesystem = new SymfonyFilesystem();
    $config = [];

    $projDir = realpath(dirname($composerFile));
    $scaffoldDir = $this->getVendorPath() . '/drupalwxt/drupal-scaffold-docker';
    $webroot = realpath($this->getWebRoot());

    // Call any pre-scaffold scripts that may be defined.
    $dispatcher->dispatch(self::PRE_DRUPAL_SCAFFOLD_DOCKER_CMD);

    // Copy template files.
    if ($filesystem->exists($scaffoldDir)) {
      if (!$filesystem->exists($projDir . '/docker')) {
        $filesystem->mkdir($projDir);
      }

      // Ensure don't overwrite custom config.
      if ($filesystem->exists($projDir . '/docker/config.yml')) {
        $config = Yaml::parse(file_get_contents($projDir . '/docker/config.yml'));
      }

      // Mirror the docker configuration.
      $filesystem->mirror($scaffoldDir . '/template/docker', $projDir . '/docker');

      // Put back the custom configuration if exists.
      if (!empty($config)) {
        file_put_contents($projDir . '/docker/config.yml', Yaml::dump($config));
      }

      $name = explode("/", $this->composer->getPackage()->getName());
      if (isset($name)) {
        $shortname = preg_replace('/[^A-Za-z0-9]/', '', $name[1]);
      }

      // Gather installation profiles.
      $profiles = $this->getProfiles();
      $profile = 'standard';
      if (!empty($profiles)) {
        $resolved = [];
        $unresolved = [];
        $deps = [];
        foreach (array_keys($profiles) as $profileTemp) {
          try {
            $this->resolver = new Resolver($this->composer, $this->io);
            list ($resolved, $unresolved) = $this->resolver->depResolve($profileTemp, $profiles, $resolved, $unresolved);
          }
          catch (Exception $e) {
            die("Dependency resolution unexpectedly failed " . $e->getMessage());
          }
        }
        foreach ($resolved as $profileTemp) {
          $deps = [];
          if (count($profiles[$profileTemp]) > count($deps)) {
            $deps = $profiles[$profileTemp];
            $profileTemp = explode("/", $profileTemp);
            if ($filesystem->exists($webroot . '/profiles/' . $profileTemp[1])) {
              $profile = $profileTemp[1];
            }
          }
        }
      }

      // Behat.
      // TODO: Baseline detection.
      $behat = '';

      // Docker folder.
      $finder = new Finder();
      foreach ($finder->files()->ignoreDotFiles(FALSE)->contains('/{{__(BEHAT|DOCROOT|PROFILE|ORG|REPO|REPO_SHORT)__}}/i')->in($projDir . '/docker') as $file) {
        $name = explode("/", $this->composer->getPackage()->getName());
        if (!empty($name)) {
          $file_contents = str_replace("{{__BEHAT__}}", $behat, $file->getContents());
          $file_contents = str_replace("{{__DOCROOT__}}", $this->getWebRoot(), $file_contents);
          $file_contents = str_replace("{{__PROFILE__}}", $profile, $file_contents);
          $file_contents = str_replace("{{__ORG__}}", $name[0], $file_contents);
          $file_contents = str_replace("{{__REPO__}}", $name[1], $file_contents);
          $file_contents = str_replace("{{__REPO_SHORT__}}", $shortname, $file_contents);
          file_put_contents($file->getRealPath(), $file_contents);
        }
      }

      // Docker controller + CI files.
      $finder = new Finder();
      foreach ($finder->files()->ignoreDotFiles(FALSE)->depth('== 0')->in($scaffoldDir . '/template') as $file) {
        if (!empty($name)) {
          $file_contents = str_replace("{{__BEHAT__}}", $behat, $file->getContents());
          $file_contents = str_replace("{{__DOCROOT__}}", $this->getWebRoot(), $file_contents);
          $file_contents = str_replace("{{__PROFILE__}}", $profile, $file_contents);
          $file_contents = str_replace("{{__ORG__}}", $name[0], $file_contents);
          $file_contents = str_replace("{{__REPO__}}", $name[1], $file_contents);
          $file_contents = str_replace("{{__REPO_SHORT__}}", $shortname, $file_contents);
          if (!$filesystem->exists(getcwd() . '/' . $file->getFilename())) {
            file_put_contents(getcwd() . '/' . $file->getFilename(), $file_contents);
          }
          else {
            $this->io->write('<info>' . $file->getFilename() . ' not copied as file already exists.</info>');
          }
        }
      }

      // Copy composer.json to required Docker folders.
      if ($filesystem->exists(getcwd() . '/composer.json')) {
        $filesystem->copy(getcwd() . '/composer.json', $projDir . '/docker/composer.json', TRUE);
        $filesystem->copy(getcwd() . '/composer.json', $projDir . '/docker/images/1.0-alpha1/composer.json', TRUE);
      }

      // Override native ScriptHandler.php with project's own.
      if ($filesystem->exists(getcwd() . '/scripts/ScriptHandler.php')) {
        $filesystem->copy(getcwd() . '/scripts/ScriptHandler.php', $projDir . '/docker/scripts/ScriptHandler.php', TRUE);
        $filesystem->copy(getcwd() . '/scripts/ScriptHandler.php', $projDir . '/docker/images/1.0-alpha1/scripts/ScriptHandler.php', TRUE);
      }

      $this->io->write('<info>Successfully installed Drupal Scaffold Docker!</info>');
    }

    // Call post-scaffold scripts.
    $dispatcher->dispatch(self::POST_DRUPAL_SCAFFOLD_DOCKER_CMD);
  }

  /**
   * Get the path to the 'vendor' directory.
   *
   * @return string
   */
  public function getVendorPath() {
    $config = $this->composer->getConfig();
    $filesystem = new Filesystem();
    $filesystem->ensureDirectoryExists($config->get('vendor-dir'));
    $vendorPath = $filesystem->normalizePath(realpath($config->get('vendor-dir')));

    return $vendorPath;
  }

  /**
   * Look up the Drupal core package object, or return it from where we cached
   * it in the $drupalCorePackage field.
   *
   * @return PackageInterface
   */
  public function getDrupalCorePackage() {
    if (!isset($this->drupalCorePackage)) {
      $this->drupalCorePackage = $this->getPackage('drupal/core');
    }
    return $this->drupalCorePackage;
  }

  /**
   * Retrieve the path to the web root.
   *
   * @return string
   */
  public function getWebRoot() {
    $drupalCorePackage = $this->getDrupalCorePackage();
    $installationManager = $this->composer->getInstallationManager();
    $corePath = $installationManager->getInstallPath($drupalCorePackage);
    // Webroot is the parent path of the drupal core installation path.
    $webroot = dirname($corePath);
    return $webroot;
  }

  /**
   * Retrieve a package from the current composer process.
   *
   * @param string $name
   *   Name of the package to get from the current composer installation.
   *
   * @return PackageInterface
   */
  protected function getPackage($name) {
    return $this->composer->getRepositoryManager()->getLocalRepository()->findPackage($name, '*');
  }

  /**
   * Retrieve a list of installation profiles along with dependencies handled
   * via composer.
   *
   * @param string $name
   *   Name of the package to get from the current composer installation.
   *
   * @return array
   */
  protected function getProfiles() {
    $profiles = [];
    foreach ($this->composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
      if ($package->getType() == 'drupal-profile') {
        $profiles[$package->getName()] = [];
      }
    }
    foreach ($this->composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
      foreach ($package->getRequires() as $link) {
        foreach ($profiles as $profile_name => $profile_dep) {
          if (in_array($link->getTarget(), array_keys($profiles)) && ($profile_name == $package->getName())) {
            $profiles[$package->getName()][] = $link->getTarget();
          }
        }
      }
    }
    return $profiles;
  }

}
