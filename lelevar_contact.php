<?php

// Ensure WP_List_Table is available
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Recipient_List_Table extends WP_List_Table
{
    function __construct()
    {
        parent::__construct(array(
            'singular' => 'contact',
            'plural' => 'contacts',
            'ajax' => true // Enable AJAX
        ));
    }

    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'name':
            case 'mobile':
            case 'status':
                return $item[$column_name];
            default:
                return print_r($item, true); // Show the whole array for troubleshooting purposes
        }
    }

    function column_name($item)
    {
        $actions = array(
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Delete', 'cltd_example')),
        );
        return sprintf(
            '%s %s',
            $item['name'],
            $this->row_actions($actions)
        );
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item['id']
        );
    }

    function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />', // Render a checkbox instead of text
            'name' => __('Name', 'cltd_example'),
            'mobile' => __('Mobile', 'cltd_example'),
            'status' => __('Status', 'cltd_example'),
        );
        return $columns;
    }

    function get_sortable_columns()
    {
        $sortable_columns = array(
            'name' => array('name', false),
            'mobile' => array('mobile', false),
            'status' => array('status', false),
        );
        return $sortable_columns;
    }

    function get_bulk_actions()
    {
        return array(
            'delete' => 'Delete',
        );
    }

    function get_current_action()
    {
        if (isset($_REQUEST['action']) && $_REQUEST['action'] != '-1') {
            return $_REQUEST['action'];
        }
        if (isset($_REQUEST['action2']) && $_REQUEST['action2'] != '-1') {
            return $_REQUEST['action2'];
        }
        return null;
    }

    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lelevar_recipient_table';

        // Determine the action from the request
        $action = $this->get_current_action();

        // Get the IDs of the items to be processed
        $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();

        // Ensure $ids is an array (handles single and multiple IDs)
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        // Sanitize the IDs
        $sanitized_ids = array_map('intval', $ids);
        $ids_list = implode(',', $sanitized_ids);

        // Process the delete action
        if ($action === 'delete' && !empty($ids_list)) {
            $wpdb->query("DELETE FROM $table_name WHERE id IN($ids_list)");
        }

        // After processing, clean up the URL to remove conflicting parameters and reload the page
        if (!empty($action)) {
            // Remove the search query and other irrelevant parameters before redirecting
            $redirect_url = remove_query_arg(array('s', 'action', 'action2', '_wpnonce', '_wp_http_referer', 'paged', 'id'));
            wp_redirect($redirect_url);
            exit;
        }
    }

    function prepare_items()
    {
        global $wpdb;

        $per_page = 10; // Items per page
        $current_page = $this->get_pagenum();
        $search = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';

        $sql = "SELECT * FROM {$wpdb->prefix}lelevar_recipient_table WHERE 1=1";

        if (!empty($search)) {
            $sql .= $wpdb->prepare(" AND (name LIKE %s OR mobile LIKE %s OR status LIKE %s)", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
        }

        // Sorting
        $orderby = (!empty($_REQUEST['orderby'])) ? esc_sql($_REQUEST['orderby']) : 'name';
        $order = (!empty($_REQUEST['order'])) ? esc_sql($_REQUEST['order']) : 'ASC';
        $sql .= " ORDER BY $orderby $order";

        // Pagination
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM ({$sql}) AS total"); // Get total items
        $sql .= " LIMIT " . ($current_page - 1) * $per_page . ", $per_page";

        $this->items = $wpdb->get_results($sql, ARRAY_A);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);
    }
}

// Include this where you need to display the contact table
global $wpdb;
$table = new Recipient_List_Table();
$message = '';
$table->process_bulk_action(); // Make sure this is called to process actions
$table->prepare_items();
if ('delete' === $table->get_current_action()) {
    $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items deleted: %d', 'cltd_example'), count($_REQUEST['id'])) . '</p></div>';
}
?>

<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2>Contacts</h2>
    <?php echo $message; ?>

    <form id="contacts-table" method="GET">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        <?php $table->search_box('Search Contacts', 'search_id'); ?>
        <?php $table->display(); ?>

        <div>
            <select name="action">
                <option value="-1">Bulk Actions</option>
                <option value="delete">Delete</option>
            </select>
            <input type="submit" name="doaction" id="doaction" class="button action" value="Apply">
        </div>
    </form>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#doaction').click(function(e) {
            var action = $('select[name="action"]').val();
            if (action == 'delete') {
                if (confirm('Are you sure you want to ' + action + ' selected items?')) {
                    return true;
                } else {
                    e.preventDefault();
                }
            }
        });

        // Show progress loader for AJAX actions
        $(document).ajaxStart(function() {
            $('#contacts-table').css("opacity", "0.5");
        }).ajaxStop(function() {
            $('#contacts-table').css("opacity", "1");
        });
    });
</script>