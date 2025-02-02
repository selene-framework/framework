<?php
namespace Electro\WebServer;

use Electro\Configuration\Lib\DotEnvLoader;
use Electro\Exceptions\Fatal\ConfigException;
use Electro\Interfaces\BootloaderInterface;
use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\KernelInterface;
use Electro\Kernel\Config\KernelModule;
use Electro\Kernel\Config\KernelSettings;
use Electro\Kernel\Services\ModulesRegistry;

/**
 * Provides the standard bootstrap sequence for web applications.
 *
 * Boot up the framework's kernel.
 *
 * This occurs before the framework's main startup sequence.
 * Unlike the later, which is managed automatically, this pre-startup process is manually defined and consists of
 * just a core service that must be setup before any other module loads.
 */
class WebBootloader implements BootloaderInterface
{
  /**
   * @var InjectorInterface
   */
  private $injector;
  /**
   * @var KernelSettings
   */
  private $kernelSettings;

  function __construct (InjectorInterface $injector)
  {
    $this->injector = $injector;
  }

  function boot ($rootDir, $urlDepth = 0, callable $onStartUp = null)
  {
    $rootDir = normalizePath ($rootDir);

    // Initialize some settings from environment variables

    DotEnvLoader::load ($rootDir);

    // Load the kernel's configuration.

    /** @var KernelSettings $kernelSettings */
    $kernelSettings = $this->kernelSettings = $this->injector
      ->share (KernelSettings::class, 'app')
      ->make (KernelSettings::class);

    $kernelSettings->isWebBased = true;
    $kernelSettings->setApplicationRoot ($rootDir, $urlDepth);

    // Boot up the framework's kernel.

    $this->injector->execute ([KernelModule::class, 'register']);

    // Boot up the framework's subsytems and the application's modules.

    /** @var KernelInterface $kernel */
    $kernel = $this->injector->make (KernelInterface::class);

    if ($onStartUp)
      $onStartUp ($kernel);

    // Boot up all modules.
    try {
      $kernel->boot ();
    }
    catch (ConfigException $e) {
      $NL = "<br>\n";
      echo $e->getMessage () . $NL . $NL;

      if ($e->getCode () == -1)
        echo sprintf ('Possile error causes:%2$s%2$s- the class name may be misspelled,%2$s- the class may no longer exist,%2$s- module %1$s may be missing or it may be corrupted.%2$s%2$s',
          str_match ($e->getMessage (), '/from module (\S+)/')[1], $NL);

      $path = "$kernelSettings->storagePath/" . ModulesRegistry::REGISTRY_FILE;
      if (file_exists ($path))
        echo "Tip: one possible solution is to remove the '$path' file and run 'workman' to rebuild the module registry.";
    }

    return $kernel->getExitCode ();
  }

}
