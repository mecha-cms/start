<?php !session_id() && session_start();

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('html_errors', 1);

define('MIN_VERSION_APACHE', 'v2.4.0');
define('MIN_VERSION_PHP', 'v7.3.0');

define('STABLE_VERSION', 'v2.6.4');
define('STABLE_VERSION_PANEL', 'v2.8.1');
define('STABLE_VERSION_USER', 'v1.13.0');

define('D', DIRECTORY_SEPARATOR);

$content = "";

if (!is_file(__DIR__ . D . 'index.php')) {
    $error = 0;
    ob_start();
    phpinfo();
    $info = ob_get_clean();
    if (false !== stripos($info, '</body>') && preg_match('/<body>([\s\S]*?)<\/body>/i', $info, $m)) {
        $info = $m[1];
    }
    $info = strip_tags($info);
    $apache_can_rewrite = false;
    $version_apache = $version_lite = $version_php = null;
    if (false !== stripos($info, 'apache version ') && preg_match('/apache version \s*(?:apache\/)?(v?\S+)/i', $info, $m)) {
        $version_apache = $m[1];
    }
    if (false !== stripos($info, 'litespeed')) {
        $version_lite = true;
    }
    if (false !== stripos($info, 'php version ') && preg_match('/php version \s*(?:php\/)?(v?\S+)/i', $info, $m)) {
        $version_php = $m[1];
    }
    $loaded_modules = stripos($info, 'loaded modules ');
    if (false !== $loaded_modules) {
        if (false !== stripos(explode("\n", substr($info, $loaded_modules))[0], 'mod_rewrite')) {
            $apache_can_rewrite = true;
        }
    }
    if (!$version_apache && $version_lite) {
        $content .= '<p aria-live="polite" role="alert">&#x2714; It looks like you are using a LiteSpeed web server. This web server is usually compatible with Apache web server configuration.</p>';
    } else if (!$version_apache) {
        $content .= '<p role="alert">&#x2718; Your PHP application does not seem to be running on Apache web server.</p>';
        ++$error;
    } else {
        if (version_compare($version_apache, $version = preg_replace('/^v/', "", MIN_VERSION_APACHE), '<')) {
            $content .= '<p role="alert">&#x2718; Mecha requires at least Apache version ' . $version . '. Your current Apache version is ' . $version_apache . '.</p>';
            ++$error;
        } else {
            $content .= '<p aria-live="polite" role="alert">&#x2714; Minimum Apache version is ' . $version . '. Your current Apache version is ' . $version_apache . '.</p>';
            if (!$apache_can_rewrite) {
                $content .= '<p role="alert">&#x2718; Apache <a href="" target="_blank"><code>mod_rewrite</code></a> extension is disabled or is not available.</p>';
                ++$error;
            } else {
                $content .= '<p aria-live="polite" role="alert">&#x2714; Apache <a href="" target="_blank"><code>mod_rewrite</code></a> extension is enabled.</p>';
            }
        }
    }
    if (version_compare($version_php, $version = preg_replace('/^v/', "", MIN_VERSION_PHP), '<')) {
        $content .= '<p role="alert">&#x2718; Mecha requires at least PHP version ' . $version . '. Your current PHP version is ' . $version_php . '.</p>';
        ++$error;
    } else {
        $content .= '<p aria-live="polite" role="alert">&#x2714; Minimum PHP version required is ' . $version . '. Your current PHP version is ' . $version_php . '.</p>';
        if (!extension_loaded('dom')) {
            $content .= '<p role="alert">&#x2718; PHP <a href="" target="_blank"><code>dom</code></a> extension is disabled or is not available.</p>';
            ++$error;
        } else {
            $content .= '<p aria-live="polite" role="alert">&#x2714; PHP <a href="" target="_blank"><code>dom</code></a> extension is enabled.</p>';
        }
        if (!extension_loaded('json')) {
            $content .= '<p role="alert">&#x2718; PHP <a href="" target="_blank"><code>json</code></a> extension is disabled or is not available.</p>';
            ++$error;
        } else {
            $content .= '<p aria-live="polite" role="alert">&#x2714; PHP <a href="" target="_blank"><code>json</code></a> extension is enabled.</p>';
        }
        if (!extension_loaded('mbstring')) {
            $content .= '<p role="alert">&#x2718; PHP <a href="" target="_blank"><code>mbstring</code></a> extension is disabled or is not available.</p>';
            ++$error;
        } else {
            $content .= '<p aria-live="polite" role="alert">&#x2714; PHP <a href="" target="_blank"><code>mbstring</code></a> extension is enabled.</p>';
        }
    }
}

if (!extension_loaded('zip')) {
    $content .= '<p role="alert">&#x2718; PHP <a href="" target="_blank"><code>zip</code></a> extension is disabled or is not available. This extension is needed to perform package extraction during the installation process. The core application does not require this extension to be enabled.</p>';
    ++$error;
}

if ('POST' === $_SERVER['REQUEST_METHOD']) {
}

header('Content-Type: text/html');

http_response_code(200);

echo '<!DOCTYPE html>';
echo '<html dir="ltr">';
echo '<head>';
echo '<meta charset="utf-8">';
echo '<title>' . ($error > 0 ? 'Error (' . $error . ')' : 'Start') . '</title>';
echo '<link href="data:image/x-icon;base64,AAABAAEAEBAAAAEAIABoBAAAFgAAACgAAAAQAAAAIAAAAAEAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAzMzP/MzMz/zMzM/8zMzP/MzMz/////wAzMzP/MzMz/zMzM/8zMzP/////ADMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/////8AMzMz/zMzM/8zMzP/MzMz/////wAzMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/////ADMzM/8zMzP/MzMz/zMzM/////8AMzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/////wAzMzP/MzMz/zMzM/8zMzP/////ADMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/////8AMzMz/zMzM/8zMzP/MzMz/////wAzMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/////ADMzM/8zMzP/MzMz/zMzM/////8AMzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/////wAzMzP/MzMz/zMzM/8zMzP/////ADMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/////8AMzMz/zMzM/8zMzP/MzMz/////wAzMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/////AP///wD///8A////ADMzM/8zMzP/MzMz/zMzM/////8A////AP///wD///8AMzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/MzMz/zMzM/8zMzP/BCAAAAQgAAAEIAAABCAAAAQgAAAEIAAABCAAAAQgAAA8PAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==" rel="icon">';
echo '<style>';
echo 'a{color:#00f;text-decoration:none}button,label,select{cursor:pointer}hr{margin:1em 0}[role=alert]{color:#900;margin:0}[role=alert][aria-live=polite]{color:#060}';
echo '</style>';
echo '</head>';
echo '<body style="max-width: 600px; margin-right: auto; margin-left: auto;">';
echo '<h1>Start</h1>';
echo '<p>This simple interface will help you carry out the installation process based on your goals. Before starting the installation process, I need to make sure that all the requirements are available.</p>';
echo $content;
echo '<hr>';
if ($error > 0) {
    echo '<p>Please fix the missing requirements to be able to start the installation process!</p>';
} else {

    echo '<form method="post">';
    echo '<p>What is your goal after the installation is complete?</p>';
    echo '<p>';
    echo '<label style="display: block;">';
    echo '<input checked name="status" type="radio" value="1">';
    echo ' ';
    echo 'I want to use this application for my own site.';
    echo '</label>';
    echo '<label style="display: block;">';
    echo '<input name="status" type="radio" value="2">';
    echo ' ';
    echo 'I want to use this application for my client site.';
    echo '</label>';
    echo '<label style="display: block;">';
    echo '<input name="status" type="radio" value="0">';
    echo ' ';
    echo 'I want to try the latest features before the stable version is released.';
    echo '</label>';
    echo '</p>';
    echo '<p>Would you like to install the control panel feature?</p>';
    echo '<p>';
    echo '<label style="display: block;">';
    echo '<input checked name="panel" type="radio" value="1">';
    echo ' ';
    echo 'Yes, I want to manage my content with a control panel.';
    echo '</label>';
    echo '<label style="display: block;">';
    echo '<input name="panel" type="radio" value="2">';
    echo ' ';
    echo 'Yes, my client wants to manage his/her content with a control panel.';
    echo '</label>';
    echo '<label style="display: block;">';
    echo '<input name="panel" type="radio" value="0">';
    echo ' ';
    echo 'I am good with a code editor.';
    echo '</label>';
    echo '</p>';
    echo '<p>';
    echo '<button type="submit">Install</button>';
    echo '</p>';
    echo '</form>';
}
echo '</body>';
echo '</html>';

exit;