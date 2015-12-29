<?php
namespace Selenia\Matisse\Properties\Base;

use Selenia\Matisse\Components\Base\Component;
use Selenia\Matisse\Interfaces\ComponentPropertiesInterface;
use Selenia\Matisse\Properties\TypeSystem\type;

abstract class AbstractProperties implements ComponentPropertiesInterface
{
  /**
   * The component that owns these properties.
   *
   * @var Component
   */
  protected $component;

  function __construct (Component $ownerComponent)
  {
    $this->component = $ownerComponent;
  }

  /**
   * Checks if the component supports the given attribute.
   *
   * @param string $propName
   * @param bool   $asSubtag When true, the attribute MUST be able to be specified in subtag form.
   *                         When false, the attribute can be either a tag attribute or a subtag.
   * @return bool
   */
  abstract function defines ($propName, $asSubtag = false);

  /**
   * @param string $propName Property name.
   * @return array Always returns an array, even if no enumeration is defined for the target property.
   * @throws \Selenia\Matisse\Exceptions\ReflectionPropertyException
   */
  abstract function getEnumOf ($propName);

  /**
   * Returns all declared property names.
   *
   * @return string[]
   */
  abstract function getPropertyNames ();

  /**
   * Returns the type ID of a property.
   *
   * @param string $propName
   * @return string
   */
  abstract function getTypeOf ($propName);

  /**
   * Checks if a property type is restricted to a set of allowed values.
   *
   * @param string $propName
   * @return bool
   */
  abstract function isEnum ($propName);

  /**
   * Validates, typecasts and assigns a value to a property.
   *
   * @param string $propName
   * @param mixed  $value
   */
  abstract function set ($propName, $value);

  function apply (array $props)
  {
    foreach ($props as $k => $v)
      $this->set ($k, $v);
  }

  function get ($propName, $default = null)
  {
    return property ($this, $propName, $default);
  }

  /**
   * Returns all property values, indexed by property name.
   *
   * @return array A map of property name => property value.
   */
  function getAll ()
  {
    $p = $this->getPropertyNames ();
    $r = [];
    foreach ($p as $prop)
      $r[$prop] = $this->{$prop};
    return $r;
  }

  /**
   * Returns a subset of the available properties, filtered by the a specific type ID.
   *
   * @param string $type One of the {@see type}::XXX constants.
   * @return array A map of property name => property value.
   */
  function getPropertiesOf ($type)
  {
    $result = [];
    $names  = $this->getPropertyNames ();
    if (isset($names))
      foreach ($names as $name)
        if ($this->getTypeOf ($name) == $type)
          $result[$name] = $this->get ($name);
    return $result;
  }

  /**
   * Returns the type name of a property.
   *
   * @param string $propName
   * @return false|string
   */
  function getTypeNameOf ($propName)
  {
    $id = static::getTypeOf ($propName);
    return type::getNameOf ($id);
  }

  /**
   * Checks if a property is of a scalar type.
   *
   * @param string $propName
   * @return bool
   */
  function isScalar ($propName)
  {
    $type = $this->getTypeOf ($propName);
    return $type == type::bool || $type == type::id || $type == type::number ||
           $type == type::string;
  }

  /**
   * Checks if a property is of a component type or component collection.
   *
   * @param string $propName
   * @return bool
   */
  function isSubtag ($propName)
  {
    if ($this->defines ($propName)) {
      $type = $this->getTypeOf ($propName);
      switch ($type) {
        case type::content:
        case type::collection:
        case type::metadata:
          return true;
      }
    }
    return false;
  }

  /**
   * Assign a new owner to the properties object. This will also do a deep clone of the component's properties.
   *
   * @param Component $owner
   */
  function setComponent (Component $owner)
  {
    $this->component = $owner;
    $props           = $this->getPropertiesOf (type::content);
    foreach ($props as $name => $value)
      if (!is_null ($value)) {
        /** @var Component $c */
        $c = clone $value;
        $c->attachTo ($owner);
        $this->$name = $c;
      }
    $props = $this->getPropertiesOf (type::collection);
    foreach ($props as $name => $values)
      if (!empty($values))
        $this->$name = Component::cloneComponents ($values, $owner);
  }

}
