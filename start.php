<?php

$root = $_SERVER['DOCUMENT_ROOT'];
$root_str = strtr(__DIR__, [$root => '.']);

if ('POST' === $_SERVER['REQUEST_METHOD']) {

}

$title = 'Installation Wizard';
$content = '<p>You are currently in the <code>' . $root_str . '</code> folder. Please note that your application will be installed in the <code>' . $root_str . '</code> folder. Make sure that there are no files in it to ensure that no files will be replaced by the files from this application when they have the same name or directory structure as this application.</p>
<p>To begin the installation, please click the button below:</p>
<p><button name="d" type="submit" value="mecha-cms/mecha">Install</button></p>';

?>
<!DOCTYPE html>
<html dir="ltr">
  <head>
    <meta content="width=device-width" name="viewport">
    <meta charset="utf-8">
    <title>Start</title>
    <style>

* {
  box-sizing: border-box;
}
body, html {
  margin: 0;
  padding: 0;
}
html {
  background: #fff;
  font: normal normal 13px/1.4 sans-serif;
  color: #000;
}
h1, h2, h3, h4, h5, h6 {
  font-weight: normal;
}
code {
  font: inherit;
  font-family: monospace;
  font-size: 90%;
}
form {
  max-width: 40rem;
  margin: 0 auto;
}
button::-moz-focus-inner {
  margin: 0;
  padding: 0;
  border: 0;
  outline: 0;
}
button,
input,
select,
textarea {
  background: #ffa;
  font: inherit;
  color: inherit;
  border: 1px solid;
  padding: .25em .5em;
  width: 100%;
  text-align: left;
  box-shadow: inset 0 1px 1px rgba(0, 0, 0, .25);
}
button {
  width: auto;
  text-align: center;
  background: #00a;
  color: #fff;
  border: 0;
  padding-right: .75em;
  padding-left: .75em;
  box-shadow: 0 1px 1px rgba(0, 0, 0, .25);
  cursor: pointer;
}

    </style>
  </head>
  <body>

<form action="" method="post">
  <h1><?= $title; ?></h1>
  <?= $content; ?>
</form>

  </body>
</html>
