<?php
function set_notice_cookie(): void {
    setcookie('notice_closed', 'true', [
        'expires'  => time() + 86400*30,
        'path'     => '/',
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
    ]);
}

function unset_notice_cookie(): void {
    setcookie('notice_closed', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
    ]);
}

function get_notice_closed(): bool {
    return isset($_COOKIE['notice_closed']) && $_COOKIE['notice_closed'] === 'true';
}

if (!empty($_GET['action'])) {
    $_GET['action'] === 'close' ? set_notice_cookie() : unset_notice_cookie();

    // If it's ajax, just return 204; otherwise redirect
    if (!empty($_GET['ajax'])) {
        http_response_code(204);
        exit;
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
