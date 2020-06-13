<?php

session_start();

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('html_errors', 1);

define('DS', DIRECTORY_SEPARATOR);

$dir = dirname(__FILE__);

// Needed to increase the default access rate limit
// Scopes: repo(repo:status,public_repo),read:packages
define('GITHUB_API_KEY', is_file($f = $dir . DS . 'key') ? file_get_contents($f) : null);

define('MIN_PHP_VERSION', '7.1.0');

define('THE_MECHA_VERSION', '2.3.0');
define('THE_PHP_VERSION', PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION);

function fetch($url, $lot = null, $type = 'GET') {
    $headers = array('X-Requested-With' => 'X-Requested-With: CURL');
    $chops = explode('?', $url, 2);
    $type = strtoupper($type);
    // `fetch('/', array('X-Foo' => 'Bar'))`
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
        $v = 'Mecha/' . THE_MECHA_VERSION . ' (+http' . (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS'] || 443 === $port ? 's' : "") . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : "")) . ')';
        $headers['User-Agent'] = 'User-Agent: ' . $v;
    }
    $target = 'GET' === $type ? $url : $chops[0];
    if (extension_loaded('curl')) {
        $curl = curl_init($target);
        curl_setopt_array($curl, array(
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => $type,
            CURLOPT_HTTPHEADER => array_values($headers),
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15
        ));
        if ('POST' === $type) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, isset($chops[1]) ? $chops[1] : "");
        }
        $out = curl_exec($curl);
        if (false === $out) {
            $_SESSION['alert'] = '<p class="error">' . curl_error($curl) . ' (' . $url . ')</p>';
        }
        curl_close($curl);
    } else {
        $context = array('http' => array('method' => $type));
        if ('POST' === $type) {
            $headers['Content-Type'] = 'Content-Type: application/x-www-form-urlencoded';
            $context['http']['content'] = isset($chops[1]) ? $chops[1] : "";
        }
        $context['http']['header'] = implode("\r\n", array_values($headers));
        $out = file_get_contents($target, false, stream_context_create($context));
    }
    return false !== $out ? $out : null;
}

$alert = isset($_SESSION['alert']) ? $_SESSION['alert'] : "";

$root = strtr(__DIR__, array($_SERVER['DOCUMENT_ROOT'] => '.'));

$step = (int) (isset($_GET['step']) ? $_GET['step'] : 0);
$step = $step < 0 ? 0 : $step;

if ('POST' === $_SERVER['REQUEST_METHOD']) {

    if (0 === $step) {
        file_put_contents($dir . DS . 'key', $_POST['key']);
        header('Location: start.php?step=1');
        exit;
    }

    // Else...

    $step = (int) (isset($_POST['step']) ? $_POST['step'] : 0);
    $d = explode(';', $_POST['d']);
    $repo = $d[0];
    $store = __DIR__ . (isset($d[1]) ? DS . $d[1] : "");
    $remove = array(
        'composer.json',
        'README.md' => 1
    );
    $headers = array('User-Agent' => 'Mecha/' . THE_MECHA_VERSION . ' (+https://mecha-cms.com)');
    if (GITHUB_API_KEY) {
        $headers['Authorization'] = 'token ' . GITHUB_API_KEY;
    }
    foreach (explode(',', isset($_POST['l']) ? $_POST['l'] : "") as $v) {
        $remove[$v] = 1;
    }
    $tag = basename(isset($_POST['tag']) ? $_POST['tag'] : 'master');
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
        $_SESSION['alert'] = '<p class="success">Successfully installed <code>' . $repo . '@' . $tag . '</code></p>';
    }

    header('Location: start.php?step=' . ($step + 1));
    exit;

}

if (0 === $step) {
    $title = 'Add Your GitHub Personal Access Token';
    $content = '<p>Make sure you have an internet connection. This token is needed to increase the rate limit of the GitHub API.</p><p>Go to <a href="https://github.com/settings/tokens" target="_blank">https://github.com/settings/tokens</a> to get your own personal access token. Make sure to check <strong>repo:status</strong>, <strong>public_repo</strong> and <strong>read:packages</strong> options only, just to be safe. Then generate the token.</p><p>Do not share your token with anyone!</p><p class="p"><label for="f:0">Token</label><br><span><input id="f:0" name="key" placeholder="' . md5($dir) . '" type="text" value="' . GITHUB_API_KEY . '"></span></p><p class="p"><label></label><span><button type="submit">Save</button></span></p>';
} else if (1 === $step) {
    $title = 'Let&rsquo;s Start the Installation Process!';
    $content = '<p>Everything looks good. You are currently in the <code>' . $root . '</code> folder. Please note that your application will be installed in the <code>' . $root . '</code> folder. Make sure that there are no files in it to ensure that no files will be replaced by the files from this application when they have the same name or directory structure as this application.</p><p>To begin the installation, please click the button below:</p><p><button name="d" onclick="this.disabled=true;this.innerHTML=&quot;Installing&hellip;&quot;;" type="submit" value="mecha-cms/mecha">Install</button><input name="tag" type="hidden" value="v' . THE_MECHA_VERSION . '"></p>';
} else if (2 === $step) {
    $title = 'Adding the Control Panel Feature';
    $content = '<p>I consider users who decide to use this tool as users who are unable to install the external parts of Mecha manually. This inability is a sign that you will most likely need a control panel feature, even though this feature is actually optional which you can remove at any time.</p><p>Please follow these steps to install the feature!</p><h2>Step 1: Install the User Extension</h2><p>This extension is needed to activate the generic user&rsquo;s log-in and log-out feature.</p><p><button name="d" type="submit" value="mecha-cms/x.user;lot/x">Install</button><input name="tag" type="hidden" value="master"></p>';
}

// TODO
if (is_file($dir . DS . 'index.php')) {
    //$content = '<p>Installed.</p>';
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
label {
  cursor: pointer;
}
.p {
  display: flex;
}
.p label {
  text-align: right;
  width: 6em;
  padding: .25em 1em 0 0;
}
.p label + br {
  display: none;
}
.p label + br + span {
  flex: 1;
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
  <input name="step" type="hidden" value="<?= isset($_GET['step']) ? $_GET['step'] : ""; ?>">
</form>

  </body>
</html>
<?php

unset($_SESSION['alert']);
