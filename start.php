<?php !session_id() && session_start();

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('html_errors', 1);

define('D', DIRECTORY_SEPARATOR);
define('DEV', false);

define('MIN_VERSION_APACHE', 'v2.4.0');
define('MIN_VERSION_PHP', 'v7.3.0');

define('STABLE_VERSION', 'v3.0.1');
define('STABLE_VERSION_ALERT', 'v3.0.0');
define('STABLE_VERSION_FORM', 'v2.0.0');
define('STABLE_VERSION_PANEL', 'v3.0.1');
define('STABLE_VERSION_USER', 'v2.0.1');

function ping(string $link) {
    try {
        $h = get_headers($link);
        if (!$h || !isset($h[0])) {
            return false;
        }
        $status = (int) (explode(' ', $h[0])[1] ?? 404);
        return $status >= 200 && $status < 300;
    } catch (Throwable $e) {}
    return false;
}

function pull(string $from, string $to) {
    if (is_file($to)) {
        unlink($to);
    }
    $status = file_put_contents($to, fopen($from, 'r', false, stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ])));
    if (is_int($status) && $status > 0) {
        $zip = new ZipArchive;
        if (true === $zip->open($to)) {
            $zip->extractTo(dirname($to));
            $zip->close();
            unlink($to);
            return true;
        }
        $zip->close();
    }
    return false;
}

$content = ($prev = $_SESSION['prev'] ?? "");
$error = $prev && false !== strpos($prev, '&#x2718;') ? 1 : 0;
$sub = "";

unset($_SESSION['prev']); // Clear the flash alert(s)

if (!is_file(__DIR__ . D . 'index.php')) {
    ob_start();
    phpinfo();
    $info = ob_get_clean();
    if (false !== stripos($info, '</body>') && preg_match('/<body(\s[^>]*)?>([\s\S]*?)<\/body>/i', $info, $m)) {
        $info = $m[2];
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
                $content .= '<p role="alert">&#x2718; Apache <a href="https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html" target="_blank"><code>mod_rewrite</code></a> extension is disabled or is not available.</p>';
                ++$error;
            } else {
                $content .= '<p aria-live="polite" role="alert">&#x2714; Apache <a href="https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html" target="_blank"><code>mod_rewrite</code></a> extension is enabled.</p>';
            }
        }
    }
    if (version_compare($version_php, $version = preg_replace('/^v/', "", MIN_VERSION_PHP), '<')) {
        $content .= '<p role="alert">&#x2718; Mecha requires at least PHP version ' . $version . '. Your current PHP version is ' . $version_php . '.</p>';
        ++$error;
    } else {
        $content .= '<p aria-live="polite" role="alert">&#x2714; Minimum PHP version required is ' . $version . '. Your current PHP version is ' . $version_php . '.</p>';
        if (!extension_loaded('json')) {
            $content .= '<p role="alert">&#x2718; PHP <a href="https://www.php.net/book.json" target="_blank"><code>json</code></a> extension is disabled or is not available.</p>';
            ++$error;
        } else {
            $content .= '<p aria-live="polite" role="alert">&#x2714; PHP <a href="https://www.php.net/book.json" target="_blank"><code>json</code></a> extension is enabled.</p>';
        }
        if (!extension_loaded('curl') && !filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $content .= '<p role="alert">&#x2718; The <code>allow_url_fopen</code> configuration must be enabled to allow PHP functions to retrieve data from remote locations over <abbr title="File Transfer Protocol">FTP</abbr> or <abbr title="Hyper Text Transfer Protocol">HTTP</abbr>.</p>';
            ++$error;
        }
        if ('GET' === $_SERVER['REQUEST_METHOD'] && !ping($link = 'https://mecha-cms.com')) {
            $content .= '<p role="alert">&#x2718; Could not connect to <code>' . $link . '</code>. The site may be down right now or there may be some problem with your internet connection.</p>';
            ++$error;
        }
    }
}

if (!extension_loaded('zip')) {
    $content .= '<p role="alert">&#x2718; PHP <a href="https://www.php.net/book.zip" target="_blank"><code>zip</code></a> extension is disabled or is not available. The core application does not require this extension to be enabled, but it is needed to extract the package during the installation process.</p>';
    ++$error;
}

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $folder = rtrim(strtr($_POST['folder'] ?? __DIR__, '/', D), D) ?: __DIR__;
    if (!empty($_POST['sub'])) {
        $sub = strtr(substr($folder, strlen(__DIR__ . D)), D, '/');
        if ("" === $sub || !preg_match('/^[a-z\d]+(-[a-z\d]+)*(\/[a-z\d]+(-[a-z\d]+)*)*$/', $sub)) {
            $_SESSION['prev'] = '<p role="alert">&#x2718; Folder name <code>' . $sub . '</code> must follow the <code>^[a-z\d]+(-[a-z\d]+)*(/[a-z\d]+(-[a-z\d]+)*)*$</code> pattern.</p>';
            header('location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        if (!is_dir($folder) && !mkdir($folder, 0777, true)) {
            $_SESSION['prev'] = '<p role="alert">&#x2718; Could not create folder <code>' . $folder . '</code> due to file system error.</p>';
            header('location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    // Check if folder does not exist
    if (!is_dir($folder)) {
        $_SESSION['prev'] = '<p role="alert">&#x2718; Folder <code>' . $folder . '</code> does not exist.</p>';
        header('location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    // Check if folder not write-able
    if (!is_writable($folder)) {
        $_SESSION['prev'] = '<p role="alert">&#x2718; Folder <code>' . $folder . '</code> is not write-able.</p>';
        header('location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    // Check if folder is not empty
    foreach (glob($folder . D . '*', GLOB_NOSORT) as $v) {
        if (__FILE__ === $v) {
            continue;
        }
        $_SESSION['prev'] = '<p role="alert">&#x2718; Folder <code>' . $folder . '</code> is not empty.</p>';
        header('location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $minify = (int) ($_POST['minify'] ?? 1);
    $panel = (int) ($_POST['panel'] ?? 1);
    $status = (int) ($_POST['status'] ?? 1);
    if (!pull('https://' . (DEV ? 'dev.' : "") . 'mecha-cms.com/git/zip/mecha-cms/mecha?minify=' . ($minify ? '1' : '0') . '&target=' . PHP_VERSION . (0 !== $status ? '&version=' . STABLE_VERSION : ""), $folder . D . 'mecha.zip')) {
        $_SESSION['prev'] = '<p role="alert">&#x2718; Could not pull <code>mecha-cms/mecha</code> due to network error.</p>';
        header('location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    if (0 !== $panel) {
        foreach (['alert', 'form', 'user', 'panel'] as $v) {
            if (!is_dir($d = $folder . D . 'lot' . D . 'x' . D . $v) && !mkdir($d, 0777, true)) {
                $_SESSION['prev'] = '<p role="alert">&#x2718; Could not create folder <code>' . $d . '</code> due to file system error.</p>';
                header('location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
            if (!pull('https://' . (DEV ? 'dev.' : "") . 'mecha-cms.com/git/zip/mecha-cms/x.' . $v . '?minify=' . ($minify ? '1' : '0') . '&target=' . PHP_VERSION . (0 !== $status ? '&version=' . constant('STABLE_VERSION_' . strtoupper($v)) : ""), $d . D . $v . '.zip')) {
                $_SESSION['prev'] = '<p role="alert">&#x2718; Could not pull <code>mecha-cms/x.alert</code> due to network error.</p>';
                header('location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
    unlink(__FILE__); // Done!
    header('location: ' . dirname($_SERVER['PHP_SELF']) . ("" !== $sub ? '/' . $sub : ""));
    exit;
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
echo '*{box-sizing:border-box}a{color:#00f;text-decoration:none}button,label,select{cursor:pointer}hr{margin:1em 0}[role=alert]{color:#900;margin:0}[role=alert][aria-live=polite]{color:#060}';
echo '</style>';
echo '</head>';
echo '<body style="max-width: 600px; margin-right: auto; margin-left: auto;">';
echo '<h1>Start</h1>';
echo '<p>This simple interface will help you carry out the installation process based on your goals. Before starting the installation process, I need to make sure that all application requirements are met:</p>';
echo $content;
if (!$prev) {
    echo '<p>Your system is ' . ($error > 0 ? 'not ready' : 'ready') . ' to perform the installation!</p>';
}
if (0 === $error) {
    echo '<hr>';
    echo '<form method="post">';
    echo '<p>Specify the installation directory (make sure that this folder exists and is empty):</p>';
    echo '<p>';
    echo '<input autofocus list="folders" name="folder" placeholder="' . __DIR__ . '" style="display: block; width: 100%;" type="text" value="' . __DIR__ . '">';
    echo '<datalist id="folders">';
    foreach (glob(__DIR__ . D . '*', GLOB_ONLYDIR) as $v) {
        echo '<option>' . $v . '</option>';
    }
    echo '</datalist>';
    echo '</p>';
    echo '<p>';
    echo '<label>';
    echo '<input name="sub" type="checkbox" value="1">';
    echo ' ';
    echo 'Create the folder if it does not exist';
    echo ' ';
    echo '(current installation directory is <code>' . __DIR__ . '</code>. Adding a sub-folder path will instruct the installer to create that sub-folder and will install the application there).';
    echo '</label>';
    echo ' ';
    echo '</p>';
    echo '<p>What is your goal after the installation is complete?</p>';
    echo '<p>';
    echo '<label style="display: block;">';
    echo '<input checked name="status" type="radio" value="1">';
    echo ' ';
    echo 'I want to use this application to create a stable web site.';
    echo '</label>';
    echo '<label style="display: block;">';
    echo '<input name="status" type="radio" value="0">';
    echo ' ';
    echo 'I want to try the latest features of this application before the stable version is released.';
    echo '</label>';
    echo '</p>';
    echo '<p>Would you like to install the control panel feature?</p>';
    echo '<p>';
    echo '<label style="display: block;">';
    echo '<input checked name="panel" type="radio" value="1">';
    echo ' ';
    echo 'Yes, I want to manage my web site content with a control panel.';
    echo '</label>';
    echo '<label style="display: block;">';
    echo '<input name="panel" type="radio" value="0">';
    echo ' ';
    echo 'No, I am fine with a source code editor to manage my web site content.';
    echo '</label>';
    echo '</p>';
    echo '<p>Would you like to optimize the source code for production? This action will reduce the file size, but will make the source code unreadable.</p>';
    echo '<p>';
    echo '<label style="display: block;">';
    echo '<input checked name="minify" type="radio" value="1">';
    echo ' ';
    echo 'Yes, optimize the source code. I don&rsquo;t care.';
    echo '</label>';
    echo '<label style="display: block;">';
    echo '<input name="minify" type="radio" value="0">';
    echo ' ';
    echo 'No, keep the source code as it is.';
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