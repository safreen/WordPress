<?php

/**
 * SignUp List Table class.
 *
 * @package SignUp
 * @subpackage List_Table
 * @since 1.0
 * @access private
 */
class WP_SignUp_List_Table extends WP_List_Table
{

    function __construct ($args = array())
    {
        parent::__construct(array(
                'singular' => 'SignUp',
                'plural' => 'SignUps',
                'screen' => isset($args['screen']) ? $args['screen'] : null
        ));

        add_filter("manage_{$this->screen->id}_columns", array(
                &$this,
                'get_columns'
        ), 0);
    }

    function no_items ()
    {
        _e('没有团队报名.');
    }

    function display ()
    {
        ?>
<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>" cellspacing="0">
	<thead>
	<tr>
		<?php $this->print_column_headers(); ?>
	</tr>
	</thead>

	<tfoot>
	<tr>
		<?php $this->print_column_headers( false ); ?>
	</tr>
	</tfoot>

	<tbody id="the-list">
		<?php $this->display_rows_or_placeholder(); ?>
	</tbody>
</table>
<?php
    }

    function get_columns ()
    {
        $c = array(
                'cb' => '<input type="checkbox" />',
                'TeamName' => __('参赛队名称'),
                'WorkName' => __('作品名称'),
                'SchoolName' => __('学校名称')
        );

        return $c;
    }
}
?>