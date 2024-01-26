<?php
/**
 * Plugin Name: WP Subscriber Plugin
 * Description: A simple subscription plugin.
 * Version: 0.5
 * Author: Tom Kaczocha
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Shortcode for subscription form.
function my_subscriber_form() {
    ob_start();
    ?>
    <form id="subscriber-form">
        <input type="text" name="name" placeholder="Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <button type="submit">Subscribe</button>
    </form>
    <div id="form-message"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('subscriber_form', 'my_subscriber_form');

// AJAX handler for form submission.
function my_save_subscriber() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscribers';

    // Input validation and sanitization.
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);

    // Check for duplicate email.
    $existing_subscriber = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));

    if ($existing_subscriber == 0) {
        // Insert subscriber into the database if not a duplicate.
        my_insert_subscriber($name, $email);
    }

    // Always return success to show the subscription success message.
    wp_send_json_success('Thank you for subscribing!');
}
add_action('wp_ajax_save_subscriber', 'my_save_subscriber');
add_action('wp_ajax_nopriv_save_subscriber', 'my_save_subscriber');

// Enqueue JavaScript for AJAX request.
function my_enqueue_scripts() {
    wp_enqueue_script('my-subscriber-script', plugin_dir_url(__FILE__) . 'js/subscriber.js', ['jquery'], null, true);
    wp_localize_script('my-subscriber-script', 'mySubscriberAjax', ['ajaxurl' => admin_url('admin-ajax.php')]);
}
add_action('wp_enqueue_scripts', 'my_enqueue_scripts');

// Create database table for subscribers.
function my_create_subscribers_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscribers';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        email text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'my_create_subscribers_table');

// Add admin menu for subscribers.
function my_add_admin_menu() {
    add_menu_page('Subscribers', 'Subscribers', 'manage_options', 'my-subscribers', 'my_subscribers_page', 'dashicons-email-alt', 6);
}
add_action('admin_menu', 'my_add_admin_menu');

// Admin page content.
// Updated Admin page content.
function my_subscribers_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscribers';
    $subscribers = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<h1>Subscribers</h1>';
    echo '<table>';
    echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Actions</th></tr>';
    foreach ($subscribers as $subscriber) {
        echo "<tr><td>{$subscriber->id}</td><td>{$subscriber->name}</td><td>{$subscriber->email}</td>";
        echo "<td><a href='" . admin_url('admin.php?page=my-subscribers&edit=' . $subscriber->id) . "'>Edit</a> | ";
        echo "<a href='" . wp_nonce_url(admin_url('admin-post.php?action=delete_subscriber&subscriber_id=' . $subscriber->id), 'delete_subscriber') . "' onclick=\"return confirm('Are you sure?')\">Delete</a></td></tr>";
    }
    echo '</table>';

    // Add to the my_subscribers_page() function
    echo '<h2>Import Subscribers</h2>';
    echo '<form method="post" action="' . admin_url('admin-post.php') . '" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="import_subscribers">';
    echo '<input type="file" name="subscribers_csv" accept=".csv">';
    echo '<input type="submit" value="Import CSV">';
    echo '</form>';

    // Check if the 'edit' action is triggered.
    if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $subscriber_id = $_GET['edit'];
        $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $subscriber_id));

        // Display the edit form.
        if ($subscriber) {
            echo "<form method='post' action='" . admin_url('admin-post.php') . "'>";
            echo "<input type='hidden' name='action' value='update_subscriber'>";
            echo "<input type='hidden' name='subscriber_id' value='{$subscriber->id}'>";
            echo "Name: <input type='text' name='name' value='{$subscriber->name}' required>";
            echo "Email: <input type='email' name='email' value='{$subscriber->email}' required>";
            echo "<input type='submit' value='Update'>";
            echo "</form>";
        }
    }

    // Export button
    echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
    echo '<input type="hidden" name="action" value="export_subscribers">';
    echo '<input type="submit" value="Export as CSV">';
    echo '</form>';
}

// Function to handle CSV import.
// Function to handle CSV import with header check.
function my_import_subscribers() {
    if (isset($_FILES['subscribers_csv']) && current_user_can('manage_options')) {
        $csv_file = $_FILES['subscribers_csv']['tmp_name'];
        if (is_readable($csv_file)) {
            $file = fopen($csv_file, 'r');
            $headers = fgetcsv($file); // Read the first row as headers

            // Validate headers
            if ($headers && in_array('Name', $headers) && in_array('Email', $headers)) {
                $name_index = array_search('Name', $headers);
                $email_index = array_search('Email', $headers);

                while (($line = fgetcsv($file)) !== FALSE) {
                    // Map CSV data to fields based on headers
                    $name = $line[$name_index];
                    $email = $line[$email_index];
                    my_insert_subscriber($name, $email);
                }
            } else {
                // Handle case where headers are not as expected
                wp_redirect(admin_url('admin.php?page=my-subscribers&import_error=1'));
                exit;
            }
            fclose($file);
        }
        wp_redirect(admin_url('admin.php?page=my-subscribers&import_success=1'));
        exit;
    }
}
add_action('admin_post_import_subscribers', 'my_import_subscribers');

// Function to insert subscriber if not exists.
function my_insert_subscriber($name, $email) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscribers';

    // Check for duplicate email.
    $existing_subscriber = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));

    if ($existing_subscriber == 0) {
        // Insert subscriber into the database if not a duplicate.
        $wpdb->insert($table_name, ['name' => sanitize_text_field($name), 'email' => sanitize_email($email)]);
    }
}

// Function to handle subscriber updates.
function my_update_subscriber() {
    if (isset($_POST['subscriber_id'], $_POST['name'], $_POST['email']) && current_user_can('manage_options')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscribers';

        $wpdb->update(
            $table_name,
            ['name' => sanitize_text_field($_POST['name']), 'email' => sanitize_email($_POST['email'])],
            ['id' => intval($_POST['subscriber_id'])]
        );

        wp_redirect(admin_url('admin.php?page=my-subscribers'));
        exit;
    }
}
add_action('admin_post_update_subscriber', 'my_update_subscriber');

// Function to handle subscriber deletions.
function my_delete_subscriber() {
    if (isset($_GET['subscriber_id']) && current_user_can('manage_options') && wp_verify_nonce($_GET['_wpnonce'], 'delete_subscriber')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscribers';

        $wpdb->delete($table_name, ['id' => intval($_GET['subscriber_id'])]);

        wp_redirect(admin_url('admin.php?page=my-subscribers'));
        exit;
    }
}
add_action('admin_post_delete_subscriber', 'my_delete_subscriber');

// Handle CSV export.
function my_export_subscribers() {
    if (current_user_can('manage_options')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscribers';
        $subscribers = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

        if (!empty($subscribers)) {
            $csv_output = fopen('php://output', 'w');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=subscribers.csv');
            fputcsv($csv_output, array('ID', 'Name', 'Email'));

            foreach ($subscribers as $subscriber) {
                fputcsv($csv_output, $subscriber);
            }

            fclose($csv_output);
        }
    }
    exit;
}
add_action('admin_post_export_subscribers', 'my_export_subscribers');
