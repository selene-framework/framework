<?php

namespace Electro\Tasks\Commands;

use Electro\Caching\Config\CachingSettings;
use Electro\Configuration\Lib\IniFile;
use Electro\ConsoleApplication\ConsoleApplication;
use Electro\Exceptions\Fatal\ConfigException;
use Electro\Interfaces\ConsoleIOInterface;
use Electro\Kernel\Config\KernelSettings;
use Electro\Kernel\Services\ModulesInstaller;
use Electro\Tasks\Shared\Base\ComposerTask;
use Robo\Task\FileSystem\CleanDir;
use Robo\Task\FileSystem\FilesystemStack;

/**
 * Implements the Electro Task Runner's pre-set build commands.
 *
 * @property KernelSettings     $kernelSettings
 * @property ConsoleIOInterface $io
 * @property FilesystemStack    $fs
 * @property CachingSettings    $cachingSettings
 * @property ConsoleApplication $consoleApp
 * @property ModulesInstaller   $modulesInstaller
 */
trait MiscCommands
{
  /**
   * Clear all cache contents
   *
   * @throws \Exception
   */
  function cacheClear ()
  {
    $target = $this->kernelSettings->storagePath . DIRECTORY_SEPARATOR . $this->cachingSettings->cachePath;
    $this->task (CleanDir::class, $target)->run ();
  }

  /**
   * Disable the caching subsystem
   *
   * @throws \Exception
   */
  function cacheDisable ()
  {
    (new IniFile('.env'))->load ()->set ('CACHING', 'false')->save ();
    $this->io->writeln ("Caching: <info>disabled</info>");
  }

  /**
   * Enable the caching subsystem
   *
   * @throws \Exception
   */
  function cacheEnable ()
  {
    (new IniFile('.env'))->load ()->set ('CACHING', 'true')->save ();
    $this->io->writeln ("Caching: <info>enabled</info>");
  }

  /**
   * Check whether the caching subsystem is enabled or not
   *
   * @throws \Exception
   */
  function cacheStatus ()
  {
    $file = (new IniFile('.env'))->load ();
    $v    = strtolower ($file->get ('CACHING'));
    $v    = $v == 'true' ? 'enabled' : ($v == 'false' ? 'disabled' : 'not set');
    $this->io->writeln (sprintf ("Caching: <info>%s</info>", $v));
  }

  /**
   * Forces reinstallation and reinitialization of all packages and clears all cache contents
   *
   * @option $overwrite|o Discards the current .env file if it already exists
   *
   * @param array $opts
   * @throws ConfigException
   * @throws \Exception
   */
  function reinstall ($opts = ['overwrite|o' => false])
  {
    $cOut = self::$SHOW_COMPOSER_OUTPUT;

    $this->cacheClear ();

    // Reinstall all packages
    $this->task (ComposerTask::class)->action ('clearcache')->printed ($cOut)->run ();
    $composerUpdate =
      $this->task (ComposerTask::class)->action ('update')->printed ($cOut); // Load class BEFORE its package is removed
    $this->clearDir ($this->kernelSettings->packagesPath);
    $this->clearDir ($this->kernelSettings->pluginsPath);
    $composerUpdate->run ();

    $this->modulesInstaller->rebuildRegistry ();
    $this->init ($opts);
  }

}
