<?php

namespace Electro\Navigation\Lib;

use Electro\Exceptions\Fault;
use Electro\Faults\Faults;
use Electro\Http\Lib\Http;
use Electro\Interfaces\Navigation\NavigationInterface;
use Electro\Interfaces\Navigation\NavigationLinkInterface;
use Electro\Traits\InspectionTrait;
use PhpKit\Flow\Flow;
use Psr\Http\Message\ServerRequestInterface;

/**
 * TODO: optimize children list to be evaluated only on iteration.
 */
class NavigationLink implements NavigationLinkInterface
{
  use InspectionTrait;

  private static $INSPECTABLE = [
    'active', 'available', 'cachedUrl', 'current', 'enabled', 'group', 'icon', 'id', 'links', 'selected', 'title',
    'url', 'visible', 'visibleIfUnavailable',
  ];

  /**
   * Note: this will be assigned a reference to an array on a {@see NavigationInterface} instance.
   *
   * @var NavigationLinkInterface[]
   */
  public $IDs;
  /**
   * Note: this is accessible to `Navigation`.
   *
   * @var bool
   */
  public $group = false;
  /** @var bool */
  private $active = false;
  /**
   * `true` when the link's URL can be computed.
   * ><p>The URL can be computed when all route parameters on the link can be resolved.
   *
   * @var bool
   */
  private $available;
  /** @var string|null When set, `url()` will always return its value. */
  private $cachedUrl = null;
  /** @var bool */
  private $current = false;
  /** @var bool|callable */
  private $enabled = true;
  /** @var string */
  private $icon = '';
  /** @var string */
  private $id = '';
  /** @var NavigationLinkInterface[] */
  private $links = [];
  /** @var NavigationInterface */
  private $navigation;
  /** @var NavigationLinkInterface */
  private $parent;
  /** @var bool */
  private $selected = false;
  /** @var string|callable */
  private $title = '';
  /**
   * The raw computed URL, with @ placeholders and all. This is not the same as $rawUrl.
   * <p>When null, the value will be computed on demand
   * @var string|callable|null
   */
  private $url = null;
  /** @var bool|callable */
  private $visible = true;
  /** @var bool */
  private $visibleIfUnavailable = true;

  public function __construct (NavigationInterface $navigation)
  {
    $this->navigation = $navigation;
  }

  /**
   * Checks if the given argument is a valid iterable value. If it's not, it throws a fault.
   *
   * @param NavigationLinkInterface[]|\Traversable|callable $navMap
   * @return \Iterator
   * @throws Fault {@see Faults::ARG_NOT_ITERABLE}
   */
  static function validateNavMap ($navMap)
  {
    if (!is_iterable ($navMap))
      throw new Fault (Faults::ARG_NOT_ITERABLE);
  }

  function __toString ()
  {
    $url = $this->url ();
    return isset($url) ? $url : '';
  }

  function absoluteUrl ()
  {
    return Http::absoluteUrlOf ($this->url (), $this->getRequest ());
  }

  function enabled ($enabled = null)
  {
    if (is_null ($enabled))
      return is_callable ($enabled = $this->enabled) ? $enabled() : $enabled;
    $this->enabled = $enabled;
    return $this;
  }

  function getDescendants ()
  {
    return Flow::from ($this->links)->recursive (
      function (NavigationLinkInterface $link) { return $link->links (); }
    )->reindex ()->getIterator ();
  }

  function getIterator(): \Traversable
	{
    return Flow::from ($this->links)->reindex ()->getIterator ();
  }

  function getMenu ()
  {
    return Flow::from ($this->links)->where (
      function (NavigationLinkInterface $link) { return $link->isActuallyVisible (); }
    )->reindex ()->getIterator ();
  }

  function icon ($icon = null)
  {
    if (is_null ($icon)) return $this->icon;
    $this->icon = $icon;
    return $this;
  }

  function id ($id = null)
  {
    if (is_null ($id)) return $this->id;
    if (isset($this->IDs[$id]))
      throw new Fault (Faults::DUPLICATE_LINK_ID, $id);
    $this->id = $id;
    return $this->IDs[$id] = $this;
  }

  function isAbsolute ()
  {
    $url = $this->url ();
    return isset($url) ? (bool)preg_match ('/^\w+:/', $url) : false;
  }

  function isActive ()
  {
    return $this->active;
  }

  function isActuallyEnabled ()
  {
    $this->url (); // updates $this->available
    return $this->enabled () && $this->available;
  }

  function isActuallyVisible ()
  {
    $this->url (); // updates $this->available
    return $this->visible () && ($this->available && $this->isActive () || $this->visibleIfUnavailable);
  }

  function isCurrent ()
  {
    return $this->current;
  }

  function isGroup ()
  {
    return $this->group;
  }

  function isSelected ()
  {
    return $this->selected;
  }

  function links ($navigationMap = null)
  {
    if (is_null ($navigationMap)) return $this->links;
    $this->links = [];
    return $this->merge ($navigationMap);
  }

  function merge ($navigationMap, $prepend = false)
  {
    self::validateNavMap ($navigationMap);
    /**
     * @var string                  $key
     * @var NavigationLinkInterface $link
     */
    foreach (iterator ($navigationMap) as $key => $link) {
      $link->parent ($this);
      if (is_string ($key) && !exists ($link->rawUrl ()))
        $link->url ($key);
    }
    if ($prepend)
      $this->links = array_merge ($navigationMap, $this->links);
    else $this->links = array_merge ($this->links, $navigationMap);
    return $this;
  }

  function parent (NavigationLinkInterface $parent = null)
  {
    if (is_null ($parent)) return $this->parent;
    $this->parent = $parent;
    return $this;
  }

  function rawUrl ()
  {
    return $this->url;
  }

  function request (ServerRequestInterface $request = null)
  {
    return $this->navigation->request ();
  }

  function setState ($active, $selected, $current)
  {
    $this->active   = $active;
    $this->selected = $selected;
    $this->current  = $current;
  }

  function title ($title = null)
  {
    if (is_null ($title))
      return is_callable ($title = $this->title) ? $title() : $title;
    $this->title = $title;
    return $this;
  }

  function url ($url = null)
  {
    if (is_null ($url)) {
      if (isset($this->cachedUrl))
        return $this->cachedUrl;

      if (is_callable ($url = $this->url))
        $url = $url();

      // Relative URLs are converted to a full path.
      if (isset($url) && $this->parent && ($url === '' || $url[0] != '/') && !$this->navigation->isAbsolute ($url)) {
        $base = $this->parent->url ();
        $url  = exists ($base) ? (exists ($url) ? "$base/$url" : $base) : $url;
      }
      else if ($url && $url[0] == '/')
        $url = $this->getRequest ()->getAttribute ('baseUri') . $url;
      else if($url === '' && $this->parent)
        $url = $this->parent->url ();
      $this->url = $url;

      if (exists ($url))
        $url = $this->evaluateUrl ($url);

      return $this->cachedUrl = $url;
    }
    //else DO NOT CACHE IT YET!
    $this->url = $url;
    $this->cachedUrl = null;  // Clear the cache.
    return $this;
  }

  function urlOf (array $params)
  {
    $this->available = true;
    $i               = 0;
    $this->url (); // Calculate full URL, if it has not been done yet
    $url = preg_replace_callback ('/@\w+/', function ($m) use ($params, &$i) {
      $v = get ($params, $i++);
      if (is_null ($v)) {
        $paramName = $m[0];
        $v = get ($params, $paramName);
        if (is_null ($v))
          return ''; //to preg_replace
      }
      return urlencode ($v);
    }, $this->url);
    return $url;
  }

  function visible ($visible = null)
  {
    if (is_null ($visible))
      return is_callable ($visible = $this->visible) ? $visible() : $visible;
    $this->visible = $visible;
    return $this;
  }

  function visibleIfUnavailable ($visible = null)
  {
    if (is_null ($visible)) return $this->visibleIfUnavailable;
    $this->visibleIfUnavailable = $visible;
    return $this;
  }

  private function evaluateUrl ($url)
  {
    $request         = null;
    $this->available = true;
    $url             = preg_replace_callback ('/@\w+/', function ($m) use (&$request) {
      if (!$request)
        $request = $this->getRequest (); // Call only if it's truly required.
      $v = $request->getAttribute ($m[0]);
      if (is_null ($v)) {
        $this->available = false;
        return ''; //to preg_replace
      }
      return $v;
    }, $url);
    return $url;
  }

  /**
   * @return ServerRequestInterface
   * @throws Fault Faults::REQUEST_NOT_SET
   */
  private function getRequest ()
  {
    $request = $this->request ();
    if (!$request)
      throw new Fault (Faults::REQUEST_NOT_SET);
    return $request;
  }

}
