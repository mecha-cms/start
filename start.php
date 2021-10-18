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

define('MIN_APACHE_VERSION', 'v2.4.0');
define('MIN_PHP_VERSION', 'v7.1.0');

define('THE_MECHA_VERSION', 'v2.6.2');
define('THE_PANEL_VERSION', 'v2.7.2');
define('THE_USER_VERSION', 'v1.13.0');

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
            $_SESSION['flash'] = '<p class="error">' . curl_error($curl) . ' (' . $url . ')</p>';
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

$alert = isset($_SESSION['flash']) ? $_SESSION['flash'] : "";

$root = strtr(__DIR__, "\\", '/');
$root = strtr($root, array(strtr($_SERVER['DOCUMENT_ROOT'], "\\", '/') => '.'));

$step = (int) (isset($_GET['step']) ? $_GET['step'] : 0);
$step = $step < 0 ? 0 : $step;

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    if (3 === $step) { // The last step!
        if (!is_dir($d = __DIR__ . DS . 'lot' . DS . 'user')) {
            mkdir($d, 0775, true);
        }
        unlink(__FILE__);
        header('Location: user');
        exit;
    }
    $step = (int) (isset($_POST['step']) ? $_POST['step'] : 0);
    $d = explode('|', $_POST['d']);
    $repo = $d[0];
    $store = __DIR__ . (isset($d[1]) ? DS . $d[1] : "");
    $remove = array('README.md' => 1);
    $headers = array('User-Agent' => 'Mecha/' . THE_MECHA_VERSION . ' (+https://mecha-cms.com)');
    foreach (explode(',', isset($_POST['l']) ? $_POST['l'] : "") as $v) {
        $remove[$v] = 1;
    }
    $tag = basename(isset($_POST['tag']) ? $_POST['tag'] : 'main');
    $content = fetch('https://mecha-cms.com/pack/' . $repo . '?tag=' . $tag, $headers);
    $name = basename($repo) . '@' . $tag;
    $alert = "";
    if (null !== $content && file_put_contents($store . DS . $name . '.zip', $content)) {
        $zip = new ZipArchive;
        if (true === $zip->open($store . DS . $name . '.zip')) {
            $zip->extractTo($store);
            $zip->close();
            unlink($store . DS . $name . '.zip');
            $alert .= '<p class="success">Successfully installed <code>' . $repo . '@' . $tag . '</code></p>';
        } else {
            $alert .= '<p class="error">Error reading file <code>' . $name . '.zip</code></p>';
        }
    }
    $_SESSION['flash'] = $alert;
    if (false !== strpos($_SESSION['flash'], ' class="error"')) {
        --$step; // Back!
    }
    header('Location: start.php?step=' . ($step + 1));
    exit;
}

if (0 === $step) {
    ob_start();
    phpinfo();
    $info = ob_get_clean();
    if (false !== stripos($info, '</body>') && preg_match('/<body>([\s\S]*?)<\/body>/i', $info, $m)) {
        $info = $m[1];
    }
    $info = strip_tags($info);
    $apache_version = $litespeed_version = $php_version = null;
    $has_mod_rewrite = false;
    if (false !== stripos($info, 'apache version ') && preg_match('/apache version \s*(?:apache\/)?(v?\S+)/i', $info, $m)) {
        $apache_version = $m[1];
    }
    if (false !== stripos($info, 'litespeed')) {
        $litespeed_version = true;
    }
    if (false !== stripos($info, 'php version ') && preg_match('/php version \s*(?:php\/)?(v?\S+)/i', $info, $m)) {
        $php_version = $m[1];
    }
    $loaded_modules = stripos($info, 'loaded modules ');
    if (false !== $loaded_modules) {
        if (false !== stripos(explode("\n", substr($info, $loaded_modules))[0], 'mod_rewrite')) {
            $has_mod_rewrite = true;
        }
    }
    if (version_compare($php_version, $version = preg_replace('/^v/', "", MIN_PHP_VERSION), '<')) {
        $alert .= '<p class="error">Mecha requires at least PHP version ' . $version . '. Your current PHP version is ' . $php_version . '.</p>';
    } else {
        $alert .= '<p class="success">Your current PHP version is ' . $php_version . '.</p>';
    }
    if (!$apache_version && $litespeed_version) {
        $alert .= '<p class="info">It looks like you are using a LiteSpeed web server. This web server is usually compatible with Apache web server configuration.</p>';
    } else if (!$apache_version) {
        $alert .= '<p class="error">Your PHP application doesn&rsquo;t seem to be running on Apache web server.</p>';
    } else {
        if (version_compare($apache_version, $version = preg_replace('/^v/', "", MIN_APACHE_VERSION), '<')) {
            $alert .= '<p class="error">Mecha requires at least Apache version ' . $version . '. Your current Apache version is ' . $apache_version . '.</p>';
        } else {
            $alert .= '<p class="success">Your current Apache version is ' . $apache_version . '.</p>';
            if (!$has_mod_rewrite) {
                $alert .= '<p class="error">Apache module <code>mod_rewrite</code> is disabled or is not yet available.</p>';
            } else {
                $alert .= '<p class="success">Apache module <code>mod_rewrite</code> is enabled.</p>';
            }
        }
    }
    if (!extension_loaded('zip')) {
        $alert .= '<p class="error">Extension <a href="https://www.php.net/manual/en/book.zip.php" rel="nofollow" target="_blank"><code>zip</code></a> is not installed on your web server.</p>';
    }
    if (false === strpos($alert, ' class="error"')) {
        $title = 'Let&rsquo;s Start the Installation Process!';
        $content = '<p>Everything looks good. You are currently in the <code>' . $root . '</code> folder. Please note that your application will be installed in the <code>' . $root . '</code> folder. Make sure that there are no files in that folder to ensure that no files will be replaced by the files from this application when they have the same name or directory structure as this application.</p><p>To begin the installation, please click the button below!</p><p><button class="button" type="submit">Install</button><input name="d" type="hidden" value="mecha-cms/mecha"><input name="tag" type="hidden" value="' . THE_MECHA_VERSION . '"></p>';
    } else {
        $title = 'Please Check the Requirements!';
        $content = '<p>You can install this application after fixing all the errors.</p>';
    }
} else if (1 === $step) {
    $title = 'Adding the Control Panel Feature';
    $content = '<p>I consider users who decide to use this tool as users who are unable to install the external parts of Mecha manually. This inability is a sign that you will most likely need a control panel feature, even though this feature is actually optional which you can remove at any time.</p><p>Please follow these steps to install the feature!</p><h2>Step 1: Install the User Extension</h2><p>This extension is needed to activate the generic user&rsquo;s log-in and log-out feature.</p><p><button class="button" type="submit">Install</button><input name="d" type="hidden" value="mecha-cms/x.user|lot/x"><input name="tag" type="hidden" value="' . THE_USER_VERSION . '"></p>';
} else if (2 === $step) {
    $title = 'Adding the Control Panel Feature';
    $content = '<p>I consider users who decide to use this tool as users who are unable to install the external parts of Mecha manually. This inability is a sign that you will most likely need a control panel feature, even though this feature is actually optional which you can remove at any time.</p><p>Please follow these steps to install the feature!</p><h2>Step 2: Install the Panel Extension</h2><p>After the user extension has been successfully installed, you can now install the control panel extension.</p><p><button class="button" type="submit">Install</button><input name="d" type="hidden" value="mecha-cms/x.panel|lot/x"><input name="tag" type="hidden" value="' . THE_PANEL_VERSION . '"></p>';
} else if (3 === $step) {
    $title = 'The Last Step&hellip;';
    $content = '<p><strong>Congratulations!</strong></p><p>Your site has been successfully installed and published to the world-wide-web. After clicking the button below, you will be directed to the first time user registration page. Clicking the button below will also delete the installer file, so your site will be safe.</p><p><button class="button" type="submit">Finish</button></p><p>After your user account is created, you can see the front page of your site through <a href="//' . rtrim($_SERVER['HTTP_HOST'] . strtr($root, array("\\" => '/', '.' => "")), '/') . '" target="_blank">this link</a>.</p>';
}

?>
<!DOCTYPE html>
<html dir="ltr">
  <head>
    <meta content="width=device-width" name="viewport">
    <meta charset="utf-8">
    <link href="favicon.ico" rel="icon">
    <title>Start</title>
    <link href="https://taufik-nurrohman.js.org/layout/index.min.css" rel="stylesheet">
    <style>
      :root {
        border-top: 5px solid;
      }
      main {
        max-width: 50rem;
        margin: 3rem auto;
      }
      .alert a {
        color: inherit;
        text-decoration: underline;
      }
      .alert p {
        padding: .5rem 1rem;
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
    <main>
      <form action="" method="post">
        <?php echo $alert ? '<div class="alert">' . $alert . '</div>' : ""; ?>
        <h1><?php echo $title; ?></h1>
        <?php echo $content; ?>
        <input name="step" type="hidden" value="<?php echo isset($_GET['step']) ? $_GET['step'] : ""; ?>">
      </form>
    </main>
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

unset($_SESSION['flash']);