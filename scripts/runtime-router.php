<?php
/**
 * Local PHP web server router. Apache handles permalinks in Docker.
 */
$path = rawurldecode( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ?: '/' );
if ( preg_match( '~(?:^|/)(?:\.|wp-config\.php|wp-config-sample\.php)(?:[^/]*)(?:/|$)|[\x00-\x1f\\\\]~i', $path ) ) {
    http_response_code( 404 );
    return;
}

$root = rtrim( $_SERVER['DOCUMENT_ROOT'], '/' );
$file = $root . $path;
if ( is_dir( $file ) && str_starts_with( $path, '/wp-admin' ) ) {
    return false;
}
if ( is_file( $file ) ) {
    $extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
    if ( $extension === 'php' ) {
        if ( str_starts_with( $path, '/wp-content/' ) || $path === '/xmlrpc.php' ) {
            http_response_code( 403 );
            return;
        }
        return false;
    }
    $public = [ 'css', 'js', 'mjs', 'map', 'json', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'svg', 'woff2', 'woff', 'ttf', 'otf', 'mp4', 'webm', 'txt', 'html' ];
    if ( ! in_array( $extension, $public, true ) ) {
        http_response_code( 404 );
        return;
    }
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
