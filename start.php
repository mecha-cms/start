<?php

session_start();

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('html_errors', 1);

define('DS', DIRECTORY_SEPARATOR);

$dir = dirname(__FILE__);

$title = 'Error';
$content = '<p>No such step.</p>';

// Needed to increase the default access rate limit
// Scopes: repo(repo:status,public_repo),read:packages
define('GITHUB_API_KEY', is_file($f = $dir . DS . 'key') ? file_get_contents($f) : null);

define('MIN_APACHE_VERSION', '2.4.0');
define('MIN_PHP_VERSION', '7.1.0');

define('THE_MECHA_VERSION', '2.5.3');
define('THE_PANEL_VERSION', '2.5.1');
define('THE_USER_VERSION', '1.12.1');

function fetch($url, $lot = null, $type = 'GET') {
    $headers = array('x-requested-with' => 'x-requested-with: CURL');
    $chops = explode('?', $url, 2);
    $type = strtoupper($type);
    // `fetch('/', array('X-Foo' => 'Bar'))`
    if (is_array($lot)) {
        foreach ($lot as $k => $v) {
            $headers[$k] = $k . ': ' . $v;
        }
    } else if (is_string($lot)) {
        $headers['user-agent'] = 'user-agent: ' . $lot;
    }
    if (!isset($headers['user-agent'])) {
        // <https://tools.ietf.org/html/rfc7231#section-5.5.3>
        $port = (int) $_SERVER['SERVER_PORT'];
        $v = 'Mecha/' . THE_MECHA_VERSION . ' (+http' . (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS'] || 443 === $port ? 's' : "") . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : "")) . ')';
        $headers['user-agent'] = 'user-agent: ' . $v;
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
            $_SESSION['alert_0'] = '<p class="error">' . curl_error($curl) . ' (' . $url . ')</p>';
        }
        curl_close($curl);
    } else {
        $context = array('http' => array('method' => $type));
        if ('POST' === $type) {
            $headers['content-type'] = 'content-type: application/x-www-form-urlencoded';
            $context['http']['content'] = isset($chops[1]) ? $chops[1] : "";
        }
        $context['http']['header'] = implode("\r\n", array_values($headers));
        $out = file_get_contents($target, false, stream_context_create($context));
    }
    return false !== $out ? $out : null;
}

$alert = isset($_SESSION['alert_0']) ? $_SESSION['alert_0'] : "";

$root = strtr(__DIR__, array($_SERVER['DOCUMENT_ROOT'] => '.'));

$step = (int) (isset($_GET['step']) ? $_GET['step'] : 0);
$step = $step < 0 ? 0 : $step;

if ('POST' === $_SERVER['REQUEST_METHOD']) {

    if (0 === $step) {
        file_put_contents($dir . DS . 'key', $_POST['key']);
        header('Location: start.php?step=1');
        exit;
    }

    if (4 === $step) {
        if (!is_dir($d = __DIR__ . DS . 'lot' . DS . 'user')) {
            mkdir($d, 0775, true);
        }
        unlink(__FILE__);
        unlink($dir . DS . 'key');
        header('Location: user');
        exit;
    }

    // Else...

    $step = (int) (isset($_POST['step']) ? $_POST['step'] : 0);
    $d = explode('|', $_POST['d']);
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
        $_SESSION['alert_0'] = '<p class="success">Successfully installed <code>' . $repo . '@' . $tag . '</code></p>';
    }

    if (isset($_SESSION['alert_0']) && false !== strpos($_SESSION['alert_0'], ' class="error"')) {
        --$step;
    }

    header('Location: start.php?step=' . ($step + 1));
    exit;

}

if (0 === $step) {
    $title = 'Add Your GitHub Personal Access Token';
    $content = '<p>Make sure you have an internet connection. This token is needed to increase the rate limit of the GitHub API.</p><p>Go to <a href="https://github.com/settings/tokens" target="_blank">https://github.com/settings/tokens</a> to get your own personal access token. Make sure to check <strong>repo:status</strong>, <strong>public_repo</strong> and <strong>read:packages</strong> options only, just to be safe. Then generate the token.</p><p>Do not share your token with anyone!</p><p><input name="key" placeholder="' . md5($dir) . '" type="text" value="' . GITHUB_API_KEY . '"></p><p><button type="submit">Save</button> <button type="submit">I Don&rsquo;t Have a GitHub Account</button></p>';
    $v = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
    if (version_compare($v, MIN_PHP_VERSION, '<')) {
        $alert .= '<p class="error">Mecha requires at least PHP version ' . MIN_PHP_VERSION . '. Your current PHP version is ' . $v . '.</p>';
    } else {
        $alert .= '<p class="success">Your current PHP version is ' . $v . '.</p>';
    }
    if (!function_exists('apache_get_version')) {
        $alert .= '<p class="error">Your PHP application doesn&rsquo;t seem to be running on Apache web server.</p>';
    } else {
        if (preg_match('/\d+(\.\d+)*/', apache_get_version(), $m)) {
            if (version_compare($v = $m[0], MIN_APACHE_VERSION, '<')) {
                $alert .= '<p class="error">Mecha requires at least Apache version ' . MIN_APACHE_VERSION . '. Your current Apache version is ' . $v . '.</p>';
            } else {
                $alert .= '<p class="success">Your current Apache version is ' . $v . '.</p>';
                if (!in_array('mod_rewrite', apache_get_modules())) {
                    $alert .= '<p class="error">Apache module <code>mod_rewrite</code> is disabled or is not yet available.</p>';
                } else {
                    $alert .= '<p class="success">Apache module <code>mod_rewrite</code> is enabled.</p>';
                }
            }
        }
    }
    if (false !== strpos($alert, ' class="error"')) {
        $title = 'Please Check the Requirements!';
        $content = '<p>You can install this application after fixing all the errors.</p>';
    }
} else if (1 === $step) {
    if (!GITHUB_API_KEY) {
        $alert .= '<p class="info">Personal access token not specified. Your installation process may stop midway if you have reached the maximum access limit of the GitHub API.</p>';
    }
    $title = 'Let&rsquo;s Start the Installation Process!';
    $content = '<p>Everything looks good. You are currently in the <code>' . $root . '</code> folder. Please note that your application will be installed in the <code>' . $root . '</code> folder. Make sure that there are no files in it to ensure that no files will be replaced by the files from this application when they have the same name or directory structure as this application.</p><p>To begin the installation, please click the button below!</p><p><button type="submit">Install</button><input name="d" type="hidden" value="mecha-cms/mecha"><input name="tag" type="hidden" value="v' . THE_MECHA_VERSION . '"></p>';
} else if (2 === $step) {
    $title = 'Adding the Control Panel Feature';
    $content = '<p>I consider users who decide to use this tool as users who are unable to install the external parts of Mecha manually. This inability is a sign that you will most likely need a control panel feature, even though this feature is actually optional which you can remove at any time.</p><p>Please follow these steps to install the feature!</p><h2>Step 1: Install the User Extension</h2><p>This extension is needed to activate the generic user&rsquo;s log-in and log-out feature.</p><p><button type="submit">Install</button><input name="d" type="hidden" value="mecha-cms/x.user|lot/x"><input name="tag" type="hidden" value="v' . THE_USER_VERSION . '"></p>';
} else if (3 === $step) {
    $title = 'Adding the Control Panel Feature';
    $content = '<p>I consider users who decide to use this tool as users who are unable to install the external parts of Mecha manually. This inability is a sign that you will most likely need a control panel feature, even though this feature is actually optional which you can remove at any time.</p><p>Please follow these steps to install the feature!</p><h2>Step 2: Install the Panel Extension</h2><p>After the user extension has been successfully installed, you can now install the control panel extension.</p><p><button type="submit">Install</button><input name="d" type="hidden" value="mecha-cms/x.panel|lot/x"><input name="tag" type="hidden" value="v' . THE_PANEL_VERSION . '"></p>';
} else if (4 === $step) {
    $title = 'The Last Step&hellip;';
    $content = '<p><strong>Congratulations!</strong></p><p>Your site has been successfully installed and published to the world-wide-web. After clicking the button below, you will be directed to the first time user registration page. Clicking the button below will also delete the installer file, so your site will be safe.</p><p><button type="submit">Finish</button></p><p>After your user account is created, you can see the front page of your site through <a href="//' . rtrim($_SERVER['HTTP_HOST'] . strtr($root, array("\\" => '/', '.' => "")), '/') . '" target="_blank">this link</a>.</p>';
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
  font: normal normal 16px/1.2 sans-serif;
  color: #000;
  border-top: 4px solid;
}
h1, h2, h3, h4, h5, h6 {
  font-weight: normal;
}
h1 {
  margin-top: 0;
}
code {
  font: inherit;
  font-family: monospace;
  font-size: 90%;
}
form {
  max-width: 50rem;
  margin: 0 auto;
  padding: 5%;
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
  padding: .5em .75em;
  width: 100%;
  text-align: left;
  box-shadow: inset 0 1px 1px rgba(0, 0, 0, .25);
}
button {
  width: auto;
  text-align: center;
  background: #00a;
  color: #fff;
  padding-right: .85em;
  padding-left: .85em;
  border: 0;
  box-shadow: 0 1px 1px rgba(0, 0, 0, .25);
  cursor: pointer;
}
[disabled] {
  opacity: .6 !important;
  box-shadow: none !important;
}
label {
  cursor: pointer;
}
.alert {
  margin-bottom: 5%;
}
.alert p {
  margin: 0;
  padding: .5em 1em;
}
.alert p + p {
  margin-top: .5em;
}
.error {
  background: #f00;
  color: #fff;
}
.info {
  background: #00f;
  color: #fff;
}
.success {
  background: #0b0;
  color: #fff;
}

    </style>
  </head>
  <body>

<form action="" method="post">
  <?php echo $alert ? '<div class="alert">' . $alert . '</div>' : ""; ?>
  <h1><?php echo $title; ?></h1>
  <?php echo $content; ?>
  <input name="step" type="hidden" value="<?php echo isset($_GET['step']) ? $_GET['step'] : ""; ?>">
</form>

<script>
document.querySelectorAll('[type=submit]').forEach(function(button) {
    !button.disabled && button.addEventListener('click', function() {
        if (button.disabled) {
            return;
        }
        this.disabled = true;
        this.style.cursor = 'wait';
    });
});
</script>

  </body>
</html>
<?php

unset($_SESSION['alert_0']);
