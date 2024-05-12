<?php
// Function to set a cookie
function set_notice_cookie() {
    $cookie_name = "notice_closed";
    $cookie_value = "true";
    $cookie_expire = time() + (86400 * 30); // 30 days

    // Set the cookie
    setcookie($cookie_name, $cookie_value, $cookie_expire, "/");
}

// Function to unset a cookie
function unset_notice_cookie() {
    $cookie_name = "notice_closed";
    $cookie_expire = time() - 3600; // Expire cookie (1 hour ago)

    // Unset the cookie by setting its expiration time in the past
    setcookie($cookie_name, "", $cookie_expire, "/");
}

// Check if close button is clicked
if (isset($_GET['action']) && $_GET['action'] == 'close') {
    set_notice_cookie();
    // Optionally, you can redirect the user back to the page they were on
    // header("Location: " . $_SERVER['HTTP_REFERER']);
}

// Check if reopen button is clicked
if (isset($_GET['action']) && $_GET['action'] == 'reopen') {
    unset_notice_cookie();
    // Optionally, you can redirect the user back to the page they were on
    // header("Location: " . $_SERVER['HTTP_REFERER']);
}
?>
