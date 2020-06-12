<?php

session_start();

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('html_errors', 1);

define('DS', DIRECTORY_SEPARATOR);

// Needed to increase the default access rate limit
// Scopes: repo(repo:status,public_repo), read:packages
define('GITHUB_API_KEY', is_file($f = __DIR__ . DS . 'key') ? file_get_contents($f) : null);

define('MIN_PHP_VERSION', '7.1.0');

define('THE_MECHA_VERSION', '2.3.0');
define('THE_PHP_VERSION', PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION);

function fetch(string $url, $lot = null, $type = 'GET') {
    $headers = ['X-Requested-With' => 'X-Requested-With: CURL'];
    $chops = explode('?', $url, 2);
    $type = strtoupper($type);
    // `fetch('/', ['X-Foo' => 'Bar'])`
    if (is_array($lot)) {
        foreach ($lot as $k => $v) {
            $headers[$k] = $k . ': ' . $v;
        }
    } else if (is_string($lot)) {
        $headers['User-Agent'] = 'User-Agent: ' . $lot;
    }
    if (!isset($headers['User-Agent'])) {
        // <https://tools.ietf.org/html/rfc7231#section-5.5.3>
        $port = (int) $_SERVER['SERVER_PORT'];
        $v = 'Mecha/' . THE_MECHA_VERSION . ' (+http' . (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS'] || 443 === $port ? 's' : "") . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? "") . ')';
        $headers['User-Agent'] = 'User-Agent: ' . $v;
    }
    $target = 'GET' === $type ? $url : $chops[0];
    if (extension_loaded('curl')) {
        $curl = curl_init($target);
        curl_setopt_array($curl, [
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => $type,
            CURLOPT_HTTPHEADER => array_values($headers),
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15
        ]);
        if ('POST' === $type) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $chops[1] ?? "");
        }
        $out = curl_exec($curl);
        if (false === $out) {
            $_SESSION['alert'] = '<p class="error">' . curl_error($curl) . ' (' . $url . ')</p>';
        }
        curl_close($curl);
    } else {
        $context = ['http' => ['method' => $type]];
        if ('POST' === $type) {
            $headers['Content-Type'] = 'Content-Type: application/x-www-form-urlencoded';
            $context['http']['content'] = $chops[1] ?? "";
        }
        $context['http']['header'] = implode("\r\n", array_values($headers));
        $out = file_get_contents($target, false, stream_context_create($context));
    }
    return false !== $out ? $out : null;
}

$root = strtr(__DIR__, [$_SERVER['DOCUMENT_ROOT'] => '.']);

if ('POST' === $_SERVER['REQUEST_METHOD']) {

    $d = explode(';', $_POST['d']);
    $repo = $d[0];
    $store = __DIR__ . (isset($d[1]) ? DS . $d[1] : "");
    $remove = [
        'composer.json',
        'README.md' => 1
    ];
    $headers = ['User-Agent' => 'Mecha/' . THE_MECHA_VERSION . ' (+https://mecha-cms.com)'];
    if (GITHUB_API_KEY) {
        $headers['Authorization'] = 'token ' . GITHUB_API_KEY;
    }
    foreach (explode(',', $_POST['l'] ?? "") as $v) {
        $remove[$v] = 1;
    }
    $tag = basename($_POST['tag'] ?? 'master');
    $tree = fetch('https://api.github.com/repos/' . $repo . '/git/trees/' . $tag . '?recursive=true', $headers);
    if ($tree && ($tree = json_decode($tree, true)) && !empty($tree['tree'])) {
        foreach ($tree['tree'] as $v) {
            if (isset($v['path']) && isset($remove[$v['path']])) {
                continue;
            }
            if ('blob' !== $v['type']) {
                continue;
            }
            $blob = fetch($v['url'], $headers);
            $blob = json_decode($blob, true);
            if (isset($blob['content']) && isset($blob['encoding']) && 'base64' === $blob['encoding']) {
                if (!is_dir($d = dirname($f = $store . DS . $v['path']))) {
                    mkdir($d, 0775, true);
                }
                file_put_contents($f, base64_decode($blob['content']));
            }
        }
        $_SESSION['alert'] = '<p class="success">Installed Mecha <code>' . $tag . '</code></p>';
        header('Location: start.php');
        exit;
    }

}

$title = 'Installation Wizard';
$alert = $_SESSION['alert'] ?? "";
$content = '<p>You are currently in the <code>' . $root . '</code> folder. Please note that your application will be installed in the <code>' . $root . '</code> folder. Make sure that there are no files in it to ensure that no files will be replaced by the files from this application when they have the same name or directory structure as this application.</p>
<p>To begin the installation, please click the button below:</p>
<p><button name="d" onclick="this.innerHTML=&quot;Installing&hellip;&quot;;" type="submit" value="mecha-cms/mecha">Install</button><input name="tag" type="hidden" value="v' . THE_MECHA_VERSION . '"></p>';

if (is_file(__DIR__ . DS . 'index.php')) {
    $content = '<p>Installed.</p>';
}

if (version_compare(THE_PHP_VERSION, MIN_PHP_VERSION, '<')) {
    $title = 'Please Check the Requirements';
    $alert .= '<p class="error">Mecha requires at least PHP version <code>' . MIN_PHP_VERSION . '</code>. Your current PHP version is <code>' . THE_PHP_VERSION . '</code>.</p>';
} else {
    $alert .= '<p class="success">Current PHP version is <code>' . THE_PHP_VERSION . '</code>.</p>';
}

?>
<!DOCTYPE html>
<html dir="ltr">
  <head>
    <meta content="width=device-width" name="viewport">
    <meta charset="utf-8">
    <link href="favicon.ico" rel="icon">
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
.error {
  color: #f00;
}
.info {
  color: #00f;
}
.success {
  color: #0b0;
}

    </style>
  </head>
  <body>

<form action="" method="post">
  <?= $alert ? '<div class="alert">' . $alert . '</div>' : ""; ?>
  <h1><?= $title; ?></h1>
  <?= $content; ?>
</form>

  </body>
</html>
<?php

unset($_SESSION['alert']);
