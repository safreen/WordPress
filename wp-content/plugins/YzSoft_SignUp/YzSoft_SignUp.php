<?php
/**
 * @package YzSoft_SignUp
 */
/*
Plugin Name: 报名系统
Description: 暂无描述
Version: 1.0.0
Author: Midnight
Author URI: http://my.oschina.net/Midnight
License: GPLv2 or later
*/

if (! defined('SIGNUP')) {
    define('SIGNUP', WP_PLUGIN_DIR . '/YzSoft_SignUp/');
}

if (is_admin()) {
    require_once SIGNUP . 'admin.php';
}



 ?>