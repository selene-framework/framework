<?php
namespace Selenia\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Selenia\Application;
use Selenia\Http\Lib\Http;
use Selenia\Interfaces\Http\RequestHandlerInterface;

/**
 * A middleware that generates a 404 Not Found response.
 */
class URLNotFoundMiddleware implements RequestHandlerInterface
{
  private $app;

  function __construct (Application $app)
  {
    $this->app = $app;
  }

  function __invoke (ServerRequestInterface $request, ResponseInterface $response, callable $next)
  {
    $path = either ($request->getAttribute ('virtualUri', '<i>not set</i>'), '<i>empty</i>');
    $realPath = $request->getUri ()->getPath ();
    return Http::send ($response, 404, "Page Not Found", "<br><br><table align=center cellspacing=20 style='text-align:left'>
<tr><th>Virtual URL:<td><kbd>$path</kbd>
<tr><th>URL path:<td><kbd>$realPath</kbd>
</table>");
  }
}
