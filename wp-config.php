<?php
/**
 * WordPress 基础配置文件。
 *
 * 本文件包含以下配置选项：MySQL 设置、数据库表名前缀、密钥、
 * WordPress 语言设定以及 ABSPATH。如需更多信息，请访问
 * {@link http://codex.wordpress.org/zh-cn:%E7%BC%96%E8%BE%91_wp-config.php
 * 编辑 wp-config.php} Codex 页面。MySQL 设置具体信息请咨询您的空间提供商。
 *
 * 这个文件用在于安装程序自动生成 wp-config.php 配置文件，
 * 您可以手动复制这个文件，并重命名为“wp-config.php”，然后输入相关信息。
 *
 * @package WordPress
 */

 define('WP_ALLOW_REPAIR', true);

// ** MySQL 设置 - 具体信息来自您正在使用的主机 ** //
/** WordPress 数据库的名称 */
define('WP_CACHE', true); //Added by WP-Cache Manager
define( 'WPCACHEHOME', 'D:\Develop\PHP\Demo\wordpress\wp-content\plugins\wp-super-cache/' ); //Added by WP-Cache Manager
define('DB_NAME', 'wordpress');

/** MySQL 数据库用户名 */
define('DB_USER', 'WordPress');

/** MySQL 数据库密码 */
define('DB_PASSWORD', 'M9N4Xws5LQbQhFhR');

/** MySQL 主机 */
define('DB_HOST', '127.0.0.1');

/** 创建数据表时默认的文字编码 */
define('DB_CHARSET', 'utf8');

/** 数据库整理类型。如不确定请勿更改 */
define('DB_COLLATE', '');

/**#@+
 * 身份认证密匙设定。
 *
 * 您可以随意写一些字符
 * 或者直接访问 {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org 私钥生成服务}，
 * 任何修改都会导致 cookie 失效，所有用户必须重新登录。
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'Jm:&fuDOL.EWqjB9:`PL2C~3D~Lpm]55?vyHn]XA$750oX>;/OMQO;Bax;~+$M`O');
define('SECURE_AUTH_KEY',  'Ta97.w|-7i6>$.0}XaVWx6;$Rr~CjNct{=~cNBsECIqiFa=5D-_*>~y-j(_l[:YU');
define('LOGGED_IN_KEY',    ')%Y46I59$ YgSD-T:z):]?@uDrFfO.18&Mt]tv?Uw|)6*ORlY5g3`VEF6q3j]?m`');
define('NONCE_KEY',        'c9bu^qKqNNdo#aDtY|m$AhqV3C*F_rSV#@yIp3Sf.}bP2^JK$kVWsZ rSW(9Jvs:');
define('AUTH_SALT',        'TK9mcGve:azGr2m5^POrL^s WRjl|!!Fb(4N?qx09hEkV#)A_OuJ(~I%TaRA+OYq');
define('SECURE_AUTH_SALT', '.Au:]L32(_3A2#}N:{,j5s|.Us2#F+u>zn55Gdv&E{Q&ozaUr8M_f4]UkcvR_[YG');
define('LOGGED_IN_SALT',   '2)V`e^%Q55.B-K/9KdfN`Q=~i}v* ,P:Dkc=CN`&fjfD lN-$izL]pM{>*m+[fN}');
define('NONCE_SALT',       'qK]i(h&{xFZ}|:v6nH3C`[4LWhDAG_4I+_Hvv0BgrNL}xxHY)jC&95!]W~;lJQ{o');

/**#@-*/

/**
 * WordPress 数据表前缀。
 *
 * 如果您有在同一数据库内安装多个 WordPress 的需求，请为每个 WordPress 设置不同的数据表前缀。
 * 前缀名只能为数字、字母加下划线。
 */
$table_prefix  = 'wp_';

/**
 * WordPress 语言设置，中文版本默认为中文。
 *
 * 本项设定能够让 WordPress 显示您需要的语言。
 * wp-content/languages 内应放置同名的 .mo 语言文件。
 * 要使用 WordPress 简体中文界面，只需填入 zh_CN。
 */
define('WPLANG', 'zh_CN');

/**
 * 开发者专用：WordPress 调试模式。
 *
 * 将这个值改为“true”，WordPress 将显示所有用于开发的提示。
 * 强烈建议插件开发者在开发环境中启用本功能。
 */
define('WP_DEBUG', true);

/* 好了！请不要再继续编辑。请保存本文件。使用愉快！ */

/** WordPress 目录的绝对路径。 */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** 设置 WordPress 变量和包含文件。 */
require_once(ABSPATH . 'wp-settings.php');
