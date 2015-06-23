<?php
namespace Selene\Traits;

use Selene\Exceptions\FatalException;

trait InspectionTrait
{
  function __debugInfo ()
  {
    $o = [];
    if (!defined ('static::INSPECTABLE'))
      throw new FatalException ('The <kbd>' . get_class () . "::INSPECTABLE</kbd> constant is expected but it's not defined.");
    $i = static::INSPECTABLE;
    if (!is_array ($i) || !isset($i[0]))
      throw new FatalException ('<kbd>' . get_class () . '::INSPECTABLE</kbd> is not a valid list of property names.');
    foreach ($i as $prop) $o[$prop] = $this->$prop;

    return $o;
  }
}
