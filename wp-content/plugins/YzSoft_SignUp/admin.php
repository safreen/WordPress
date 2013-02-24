<?php
add_action('admin_menu', 'SignUp_admin_menu');

// 注册菜单
function SignUp_admin_menu ()
{
    add_submenu_page('index.php', __('报名管理'), __('报名管理'), 'manage_options', 'SignUp_admin_manage', 'SignUp_admin_manage');
}

// 管理界面
function SignUp_admin_manage ()
{
    $url = '';
    require_once SIGNUP . '/includes/class-wp-signup-list-table.php';
    $args['screen'] = get_current_screen();
    $wp_list_table = new WP_SignUp_List_Table($args);

    ?>
<div class="wrap">
    <?php screen_icon(); ?>
    <h2><?php _e('报名管理'); ?></h2>

    <form id="signUp-filter" action="" method="get">

    <?php $wp_list_table->display(); ?>

    </form>
</div>
<?php
}

?>