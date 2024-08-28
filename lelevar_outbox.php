<?php

use Lelevar\Sms\SmsService;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Outbox_List_Table extends WP_List_Table
{
    function __construct()
    {
        parent::__construct(array(
            'singular' => 'outbox',
            'plural' => 'outboxes',
            'ajax' => true // Enable AJAX
        ));
    }

    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'name':
            case 'mobile':
            case 'message':
            case 'date':
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
            'cancel' => sprintf('<a href="?page=%s&action=cancel&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Cancel', 'cltd_example')),
            're-send' => sprintf('<a href="?page=%s&action=re-send&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Re-Send', 'cltd_example')),
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
            'message' => __('Message', 'cltd_example'),
            'date' => __('Date', 'cltd_example'),
            'status' => __('Status', 'cltd_example'),
        );
        return $columns;
    }

    function get_sortable_columns()
    {
        $sortable_columns = array(
            'name' => array('name', false),
            'mobile' => array('mobile', false),
            'message' => array('message', false),
            'date' => array('date', true),
            'status' => array('status', false),
        );
        return $sortable_columns;
    }

    function get_bulk_actions()
    {
        return array(
            'delete' => 'Delete',
            'cancel' => 'Cancel',
            're-send' => 'Re-Send',
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

    private function send_sms($mobile, $message)
    {
        $apikey = get_option('lelevar_sms_apikey', '');
        $sender_name = get_option('lelevar_sms_sender_name', '');
        if ($apikey && $sender_name) {
            // Initialize the SMS service with your API key
            $smsService = new SmsService($apikey); // Replace with your actual API key
            // Define the parameters for the SMS
            $params = [
                'mobile' => $mobile, // Recipient's mobile number
                'content' => $message, // SMS content
                'sender_name' => $sender_name // Optional: Define sender name if needed
            ];
            // Send the SMS
            $response = $smsService->sendSms($params);

            try {
                if ($response->success && isset($response->data['status']) && !$response->data['status']) {
                    if ($response->data['message'] === "Insufficient Unit Balance") {
                        add_action('admin_notices', function () {
                        ?>
                            <div class="notice notice-error is-dismissible">
                                <p><?php _e('Error: Insufficient Unit Balance for sending SMS.', 'your-text-domain'); ?></p>
                            </div>
                        <?php
                        });
                    }
                }

                // Log the response object for debugging purposes
                error_log(print_r($response, true));

                if ($response->success && isset($response->data['status']) && !$response->data['status']) {
                    if ($response->data['message'] === "Insufficient Unit Balance") {
                        // Set a transient to store the alert message (useful to persist across page reloads)
                        set_transient('lelevar_sms_insufficient_balance_notice', true, 30);
                    }
                }
            } catch (\Throwable $th) {
                //throw $th;
            }

            // Handle the response
            if ($response->success) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lelevar_outbox_table';

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

        // Process the cancel action
        if ($action === 'cancel' && !empty($ids_list)) {
            $wpdb->query("UPDATE $table_name SET status = 'cancelled' WHERE id IN($ids_list)");
        }

        // Process the re-send action
        if ($action === 're-send' && !empty($ids_list)) {
            // Retrieve the messages to be re-sent
            $messages = $wpdb->get_results("SELECT * FROM $table_name WHERE id IN($ids_list)", ARRAY_A);

            foreach ($messages as $message) {
                // Send the SMS using your existing logic
                $sent = $this->send_sms($message['mobile'], $message['message']);

                if ($sent) {
                    // Update the status to 'sent'
                    $wpdb->update(
                        $table_name,
                        array('status' => 'sent'),
                        array('id' => $message['id'])
                    );
                } else {
                    // Optionally, you can log an error or update the status differently if the sending fails
                }
            }
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

        $sql = "SELECT * FROM {$wpdb->prefix}lelevar_outbox_table WHERE 1=1";

        if (!empty($search)) {
            $sql .= $wpdb->prepare(" AND (name LIKE %s OR mobile LIKE %s OR message LIKE %s)", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
        }

        // Sorting
        $orderby = (!empty($_REQUEST['orderby'])) ? esc_sql($_REQUEST['orderby']) : 'date';
        $order = (!empty($_REQUEST['order'])) ? esc_sql($_REQUEST['order']) : 'DESC';
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

global $wpdb;
$table = new Outbox_List_Table();
$message = '';
$table->process_bulk_action(); // Make sure this is called to process actions
$table->prepare_items();
if ('delete' === $table->current_action()) {
    $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items deleted: %d', 'cltd_example'), count($_REQUEST['id'])) . '</p></div>';
} elseif ('cancel' === $table->current_action()) {
    $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items cancelled: %d', 'cltd_example'), count($_REQUEST['id'])) . '</p></div>';
}
?>

<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2>Outbox</h2>
    <?php echo $message; ?>

    <form id="outbox-table" method="GET">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        <?php $table->search_box('Search Outbox', 'search_id'); ?>
        <?php $table->display(); ?>
    </form>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#doaction').click(function(e) {
            var action = $('select[name="action"]').val();
            if (action == 'delete' || action == 'cancel') {
                if (confirm('Are you sure you want to ' + action + ' selected items?')) {
                    return true;
                } else {
                    e.preventDefault();
                }
            }
        });

        // Show progress loader for AJAX actions
        $(document).ajaxStart(function() {
            $('#outbox-table').css("opacity", "0.5");
        }).ajaxStop(function() {
            $('#outbox-table').css("opacity", "1");
        });
    });
</script>