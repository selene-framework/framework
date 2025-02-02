<?php
namespace Electro\Tasks\Config;

use Electro\ConsoleApplication\Config\ConsoleSettings;
use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\KernelInterface;
use Electro\Interfaces\ModuleInterface;
use Electro\Kernel\Lib\ModuleInfo;
use Electro\Profiles\ConsoleProfile;
use Electro\Tasks\Tasks\CoreTasks;
use Robo\Config\Config;
use Robo\Robo;

class TasksModule implements ModuleInterface
{
  static function getCompatibleProfiles ()
  {
    return [ConsoleProfile::class];
  }

  static function startUp (KernelInterface $kernel, ModuleInfo $moduleInfo)
  {
    $kernel
      ->onRegisterServices (function (InjectorInterface $injector) {
        $injector->share (TasksSettings::class);
      })
      //
      ->onConfigure (function (ConsoleSettings $consoleSettings) {
        $consoleSettings->registerTasksFromClass (CoreTasks::class);
      });
  }

}
