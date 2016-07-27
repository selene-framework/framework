<?php
// Router for the PHP built in server that shows directory listings.

return call_user_func (function () {

  $path = $_SERVER['DOCUMENT_ROOT'] . $_SERVER["REQUEST_URI"];
  $uri  = $_SERVER["REQUEST_URI"];

  // let server handle files or 404s
  if (!file_exists ($path) || is_file ($path))
    return false;

  // append / to directories
  if (is_dir ($path) && $uri[strlen ($uri) - 1] != '/') {
    header ('Location: ' . $uri . '/');
    return;
  }

  // send index.html and index.php
  $indexes = ['index.php', 'index.html'];
  foreach ($indexes as $index) {
    $file = $path . '/' . $index;
    if (is_file ($file)) {
      require $file;
      return;
    }
  }

  $SVGs = <<<'HTML'
<svg version="1.1" display=none>
  <defs>
  <symbol id="home" viewBox="0 0 512 512">
    <polygon points="448 288 256 64 64 288 112 288 112 448 208 448 208 320 304 320 304 448 400 448 400 288 "/>
  </symbol>
  <symbol id="folder" viewBox="0 0 512 512">
    <path fill=#DDD stroke-width=40 d="M213 96H75C51 96 32 115 32 139v235C32 397 51 416 75 416h363C461 416 480 397 480 373V187C480 163 461 144 437 144H256L213 96z"/>
  </symbol>
  <symbol id="file" viewBox="0 32 512 450">
    <path fill=#FFF stroke-width=40 d="M288 48H136c-22.1 0-40 17.9-40 40v336c0 22.1 17.9 40 40 40h240c22.1 0 40-17.9 40-40V176L288 48zM272 192V80l112 112H272z"/>
  </symbol>
  <symbol id="back" viewBox="0 0 512 512">
    <path d="M256 213.7L256 213.7 256 213.7l174.2 167.2c4.3 4.2 11.4 4.1 15.8-0.2l30.6-29.9c4.4-4.3 4.5-11.3 0.2-15.5L264.1 131.1c-2.2-2.2-5.2-3.2-8.1-3 -3-0.1-5.9 0.9-8.1 3L35.2 335.3c-4.3 4.2-4.2 11.2 0.2 15.5L66 380.7c4.4 4.3 11.5 4.4 15.8 0.2L256 213.7z"/>
  </symbol>
  </defs>
</svg>
HTML;

  $HOME_ICON   = '<svg width=16 height=16 fill=#88A><use xlink:href="#home"></use></svg>';
  $FOLDER_ICON = '<svg width=16 height=16 ><use xlink:href="#folder"></use></svg>';
  $FILE_ICON   = '<svg width=16 height=16 ><use xlink:href="#file"></use></svg>';
  $BACK_ICON   = '<svg width=16 height=16 ><use xlink:href="#back"></use></svg>';

  $prev = '';
  $uriT = trim ($uri, '/');
  $uriT = implode (' ▸ ',
    array_map (
      function ($e) {
        return "<a href='$e[0]'>$e[1]</a>";
      },
      array_merge (
        [['/', $HOME_ICON]],
        $uriT ? array_map (
          function ($e) use (&$prev) {
            $prev = "$prev/$e";
            return [$prev, $e];
          },
          explode ('/', $uriT)
        ) : []
      )
    )
  );

  echo "<!DOCTYPE html>
<html>
<head>
<meta charset=utf-8>
<style>
body {
  color: #666;
  font-family: 'Helvetica Neue', Arial, Verdana, sans-serif;
  font-size: 14px;
  background: #E8E7E6;
}
article {
  width: 600px;
  margin: 30px auto;
  background: #f8f8f8;
  border: 1px solid #CCC;
  box-shadow: 1px 1px 3px rgba(0,0,0,0.2);
  padding: 30px;
}
nav a {
  display: block;
  padding: 5px 10px;
  margin: 0 -10px;
  text-decoration: none;
  color: inherit;
  letter-spacing: 0.5px;
}
nav a:hover {
  background: #DDEEFF;
  outline: 1px solid rgba(0,0,0,0.05);
  outline-offset: -1px;
}
nav a img {
  vertical-align: bottom;
  margin-right: 10px;
}
nav a svg {
  fill: #666;
  stroke: #666;
  vertical-align: bottom;
  margin-right: 6px;
}
header {
  background: #FFF;
  border-bottom: 1px solid #e0e0e0;
  padding: 30px 30px 20px;
  margin: -30px -30px 25px;
}
h2 {
  margin: 0 0 20px 0;
  font-weight: 300;
}
header > p {
  line-height: 24px;
  margin: 0;
}
header > p svg {
  vertical-align: bottom;
  padding: 4px 0;
}
header > p span {
  color: #C3C3D4;
}
header > p a {
  color: #88A;
  text-decoration: none;
}
header > p a:hover {
  text-decoration: underline;
}
</style>
</head>
<body>
$SVGs
  <article>
    <header>
      <h2>Local Web Server</h2>
      <p>Directory: &nbsp; <span>$uriT</span></p>
    </header>
    <nav>
";

  if ($uri != '/')
    echo "<a href='..'>{$BACK_ICON}..</a>";

  $g = array_map (function ($path) {
    if (is_dir ($path)) {
      $path .= '/';
    }
    return str_replace ('//', '/', $path);
  }, glob ($path . '/*'));

  usort ($g, function ($a, $b) {
    if (is_dir ($a) == is_dir ($b))
      return strnatcasecmp ($a, $b);
    else
      return is_dir ($a) ? -1 : 1;
  });

  echo implode ("\n", array_map (function ($a) use ($FILE_ICON, $FOLDER_ICON) {
    $url  = str_replace ($_SERVER['DOCUMENT_ROOT'], '', $a);
    $icon = is_file ($a) ? $FILE_ICON : $FOLDER_ICON;
    return sprintf ('<a href="%s">%s%s</a>', $url, $icon, basename ($url));
  }, $g));

  echo '
    </nav>
  </article>
</body>
</html>';
});
