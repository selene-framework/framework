<?php
namespace Selenia\Matisse\Parser;

use Selenia\Interfaces\InjectorInterface;
use Selenia\Matisse\Lib\AssetsContext;
use Selenia\Matisse\Lib\DataBinder;
use Selenia\Matisse\Traits\Context\AssetsAPITrait;
use Selenia\Matisse\Traits\Context\BlocksAPITrait;
use Selenia\Matisse\Traits\Context\ComponentsAPITrait;
use Selenia\Matisse\Traits\Context\FiltersAPITrait;
use Selenia\Matisse\Traits\Context\MacrosAPITrait;
use Selenia\Matisse\Traits\Context\ViewsAPITrait;

/**
 * A Matisse rendering context.
 *
 * <p>The context holds state and configuration information shared between all components on a document.
 * It also provides APIs for accessing/managing Assets, Blocks and Macros.
 */
class Context
{
  use AssetsAPITrait;
  use BlocksAPITrait;
  use ComponentsAPITrait;
  use FiltersAPITrait;
  use MacrosAPITrait;
  use ViewsAPITrait;

  const FORM_ID = 'selenia-form';

  /**
   * Remove white space around raw markup blocks.
   *
   * @var bool
   */
  public $condenseLiterals = false;
  /**
   * Set to true to generate pretty-printed markup.
   *
   * @var bool
   */
  public $debugMode = false;
  /**
   * The injector allows the creation of components with yet unknown dependencies.
   *
   * @var InjectorInterface
   */
  public $injector;
  /**
   * A stack of presets.
   *
   * Each preset is an instance of a class where methods are named with component class names.
   * When components are being instantiated, if they match a class name on any of the stacked presets,
   * they will be passed to the corresponding methods for additional initialization.
   * Callbacks also receive a nullable array argument with the properties being applied.
   *
   * @var array
   */
  public $presets = [];
  /**
   * @var DataBinder|null
   */
  private $dataBinder = null;

  function __construct ()
  {
    $this->tags   = self::$coreTags;
    $this->assets = $this->mainAssets = new AssetsContext;
  }

  /**
   * Signals the start of a rendering session, which encompasses the rendering of a complete document fragment.
   *
   * <p>This resets the rendering context before the rendering starts.
   *
   * <p>You MUST call this before rendering a view.
   * <p>Do NOT call this when rendering a single component from a larger document.
   */
  public function beginRendering ()
  {
    $this->dataBinder = new DataBinder ($this);
  }

  /**
   * Sets main form's `enctype` to `multipart/form-data`, allowing file upload fields.
   *
   * > <p>This can be called multiple times.
   */
  public function enableFileUpload ()
  {
    $FORM_ID = self::FORM_ID;
    $this->addInlineScript ("$('#$FORM_ID').attr('enctype','multipart/form-data');", 'setEncType');
  }

  /**
   * Ends a rendering session begun with a previous call to {@see beginRendering}, discarding any changes made to the
   * rendering context during the rendering process.
   */
  public function endRendering ()
  {
    $this->dataBinder = null;
  }

  /**
   * Returns an API for the view's data-binding context.
   *
   * @return DataBinder
   */
  public function getDataBinder ()
  {
    return $this->dataBinder;
  }

}
