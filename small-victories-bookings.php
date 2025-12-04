<?php
/**
 * Plugin Name: Small Victories Bookings
 * Plugin URI: https://small-victories.co
 * Description: Allows visitors to submit their contact info and select available options with stock management
 * Version: 1.0.7
 * Author: Small Victories
 * License: GPL v2 or later
 * Text Domain: small-victories-bookings
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TIMESLOT_VERSION', '1.0.7');
define('TIMESLOT_DB_VERSION', '4'); // Database schema version
define('TIMESLOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TIMESLOT_PLUGIN_URL', plugin_dir_url(__FILE__));

class TimeslotBookings {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'check_and_run_migrations'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_timeslot_submit', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_timeslot_submit', array($this, 'handle_submission'));
        add_action('admin_init', array($this, 'handle_csv_export'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('template_redirect', array($this, 'handle_public_share'));
        add_shortcode('timeslot_form', array($this, 'render_shortcode'));
    }

    public function load_textdomain() {
        load_plugin_textdomain('small-victories-bookings', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function activate() {
        $this->create_tables();
        $this->check_and_run_migrations();
    }

    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        // Forms table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timeslot_forms (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            columns int(11) DEFAULT 2,
            email_template LONGTEXT NULL,
            share_key varchar(36) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY share_key (share_key)
        ) $charset_collate;";

        // Options table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timeslot_options (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            availability int(11) NOT NULL DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        // Submissions table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timeslot_submissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        // Submission options (many-to-many)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}timeslot_submission_options (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) NOT NULL,
            option_id bigint(20) NOT NULL,
            PRIMARY KEY (id),
            KEY submission_id (submission_id),
            KEY option_id (option_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Small Victories Bookings',
            'SV Bookings',
            'manage_options',
            'sv-bookings',
            array($this, 'render_forms_list'),
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'sv-bookings',
            'Add New Form',
            'Add New',
            'manage_options',
            'sv-bookings-new',
            array($this, 'render_form_editor')
        );

        add_submenu_page(
            'sv-bookings',
            'Submissions',
            'Submissions',
            'manage_options',
            'sv-bookings-submissions',
            array($this, 'render_submissions')
        );
    }

    public function enqueue_admin_scripts($hook) {
        // Load CSS on dashboard and plugin pages
        if ($hook === 'index.php' || strpos($hook, 'sv-bookings') !== false) {
            wp_enqueue_style('timeslot-admin', TIMESLOT_PLUGIN_URL . 'assets/admin.css', array(), TIMESLOT_VERSION);
        }

        // Load JS only on plugin pages
        if (strpos($hook, 'sv-bookings') !== false) {
            wp_enqueue_script('timeslot-admin', TIMESLOT_PLUGIN_URL . 'assets/admin.js', array('jquery'), TIMESLOT_VERSION, true);
        }
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_style('timeslot-frontend', TIMESLOT_PLUGIN_URL . 'assets/frontend.css', array(), TIMESLOT_VERSION);
        wp_enqueue_script('timeslot-frontend', TIMESLOT_PLUGIN_URL . 'assets/frontend.js', array('jquery'), TIMESLOT_VERSION, true);
        wp_localize_script('timeslot-frontend', 'timeslotAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('timeslot_nonce')
        ));
    }

    public function render_forms_list() {
        require_once TIMESLOT_PLUGIN_DIR . 'includes/admin-forms-list.php';
    }

    public function render_form_editor() {
        require_once TIMESLOT_PLUGIN_DIR . 'includes/admin-form-editor.php';
    }

    public function render_submissions() {
        require_once TIMESLOT_PLUGIN_DIR . 'includes/admin-submissions.php';
    }

    public function handle_public_share() {
        if (!isset($_GET['sv_bookings_share'])) {
            return;
        }

        $share_key = sanitize_text_field($_GET['sv_bookings_share']);

        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}timeslot_forms WHERE share_key = %s",
            $share_key
        ));

        if (!$form) {
            wp_die(__('Invalid share link.', 'small-victories-bookings'));
        }

        require_once TIMESLOT_PLUGIN_DIR . 'includes/public-share-view.php';
        exit;
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts);
        $form_id = intval($atts['id']);

        if (!$form_id) {
            return '<p>' . __('Please specify a form ID.', 'small-victories-bookings') . '</p>';
        }

        ob_start();
        require TIMESLOT_PLUGIN_DIR . 'includes/frontend-form.php';
        return ob_get_clean();
    }

    public function handle_submission() {
        check_ajax_referer('timeslot_nonce', 'nonce');

        global $wpdb;

        $form_id = intval($_POST['form_id']);
        $field_responses = isset($_POST['field_responses']) ? $_POST['field_responses'] : array();
        $options = isset($_POST['options']) ? array_map('intval', $_POST['options']) : array();

        // Sanitize field responses
        $sanitized_responses = array();
        foreach ($field_responses as $label => $value) {
            $sanitized_responses[sanitize_text_field($label)] = sanitize_text_field($value);
        }

        if (empty($sanitized_responses) || empty($options)) {
            wp_send_json_error(array('message' => __('Please fill in all fields and select at least one option.', 'small-victories-bookings')));
            return;
        }

        // Check availability for all selected options
        $wpdb->query('START TRANSACTION');

        foreach ($options as $option_id) {
            $option = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, availability FROM {$wpdb->prefix}timeslot_options WHERE id = %d AND form_id = %d FOR UPDATE",
                $option_id,
                $form_id
            ));

            if (!$option) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => __('Invalid option selected.', 'small-victories-bookings')));
                return;
            }

            $booked = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}timeslot_submission_options WHERE option_id = %d",
                $option_id
            ));

            if ($booked >= $option->availability) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => sprintf(__("Sorry, '%s' is no longer available.", 'small-victories-bookings'), $option->name)));
                return;
            }
        }

        // Insert submission with JSON response
        $wpdb->insert(
            $wpdb->prefix . 'timeslot_submissions',
            array(
                'form_id' => $form_id,
                'response' => json_encode($sanitized_responses),
                'submitted_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s')
        );

        $submission_id = $wpdb->insert_id;

        // Insert selected options
        foreach ($options as $option_id) {
            $wpdb->insert(
                $wpdb->prefix . 'timeslot_submission_options',
                array(
                    'submission_id' => $submission_id,
                    'option_id' => $option_id
                ),
                array('%d', '%d')
            );
        }

        $wpdb->query('COMMIT');

        // Send email notification
        $this->send_booking_email($form_id, $sanitized_responses, $options);

        wp_send_json_success(array('message' => __('Thank you! Your submission has been received.', 'small-victories-bookings')));
    }

    private function send_booking_email($form_id, $field_responses, $selected_option_ids) {
        global $wpdb;

        // Get form details including email template
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT name, email_template FROM {$wpdb->prefix}timeslot_forms WHERE id = %d",
            $form_id
        ));

        if (!$form || empty($form->email_template)) {
            return; // No email template configured
        }

        // Get selected option names
        $option_names = array();
        foreach ($selected_option_ids as $option_id) {
            $option = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}timeslot_options WHERE id = %d",
                $option_id
            ));
            if ($option) {
                $option_names[] = $option->name;
            }
        }

        // Build email content
        $email_content = $form->email_template . "\n\n";
        $email_content .= __('Selected Options:', 'small-victories-bookings') . "\n";
        foreach ($option_names as $option_name) {
            $email_content .= "- " . $option_name . "\n";
        }

        // Try to get email address from field responses
        $to_email = '';
        foreach ($field_responses as $label => $value) {
            if (strtolower($label) === 'email' && is_email($value)) {
                $to_email = $value;
                break;
            }
        }

        if (empty($to_email)) {
            return; // No valid email address found
        }

        // Send email
        $subject = sprintf(__('Booking Confirmation - %s', 'small-victories-bookings'), $form->name);
        wp_mail($to_email, $subject, $email_content);
    }

    public function handle_csv_export() {
        if (!isset($_GET['timeslot_export_csv']) || !isset($_GET['form_id'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('timeslot_export_csv_' . $_GET['form_id']);

        global $wpdb;
        $form_id = intval($_GET['form_id']);

        // Get form name and fields
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT name, fields FROM {$wpdb->prefix}timeslot_forms WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_die('Form not found');
        }

        // Get form fields
        $fields = [];
        if (!empty($form->fields)) {
            $fields = json_decode($form->fields, true);
        }

        // Get all options for this form
        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}timeslot_options WHERE form_id = %d ORDER BY sort_order ASC",
            $form_id
        ));

        // Prepare CSV data
        $csv_data = array();

        foreach ($options as $option) {
            // Get all submissions for this option
            $submissions = $wpdb->get_results($wpdb->prepare(
                "SELECT s.response
                 FROM {$wpdb->prefix}timeslot_submissions s
                 INNER JOIN {$wpdb->prefix}timeslot_submission_options so ON s.id = so.submission_id
                 WHERE so.option_id = %d
                 ORDER BY s.submitted_at ASC",
                $option->id
            ));

            $row = array($option->name);
            foreach ($submissions as $submission) {
                $response = json_decode($submission->response, true);
                $values = [];
                foreach ($fields as $field) {
                    if (isset($response[$field['label']])) {
                        $values[] = $response[$field['label']];
                    }
                }
                $row[] = implode(' - ', $values);
            }
            $csv_data[] = $row;
        }

        // Send CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($form->name) . '-submissions-' . date('Y-m-d') . '.csv"');

        // Output CSV
        $output = fopen('php://output', 'w');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'sv_bookings_recent_submissions',
            __('Recent Bookings', 'small-victories-bookings'),
            array($this, 'render_dashboard_widget')
        );
    }

    public function render_dashboard_widget() {
        global $wpdb;

        // Get the last 5 submissions across all forms
        $submissions = $wpdb->get_results(
            "SELECT s.id, s.response, s.submitted_at, s.form_id, f.name as form_name
             FROM {$wpdb->prefix}timeslot_submissions s
             INNER JOIN {$wpdb->prefix}timeslot_forms f ON s.form_id = f.id
             ORDER BY s.submitted_at DESC
             LIMIT 5"
        );

        if (empty($submissions)) {
            echo '<p>' . __('No submissions yet.', 'small-victories-bookings') . '</p>';
            return;
        }

        echo '<div class="sv-bookings-widget">';
        echo '<ul class="sv-bookings-list">';

        foreach ($submissions as $submission) {
            $response = json_decode($submission->response, true);

            // Try to get a name from the response
            $name = '';
            if (isset($response['Name'])) {
                $name = $response['Name'];
            } elseif (isset($response['name'])) {
                $name = $response['name'];
            } else {
                // Use the first field value as the name
                $name = !empty($response) ? reset($response) : __('Unknown', 'small-victories-bookings');
            }

            $date = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $submission->submitted_at);

            echo '<li class="sv-booking-item">';
            echo '<strong>' . esc_html($name) . '</strong>';
            echo '<br><span class="sv-booking-meta">';
            echo esc_html($submission->form_name) . ' &mdash; ' . esc_html($date);
            echo '</span>';
            echo '</li>';
        }

        echo '</ul>';

        // Add link to view all submissions
        $submissions_url = admin_url('admin.php?page=sv-bookings-submissions');
        echo '<p class="sv-bookings-footer">';
        echo '<a href="' . esc_url($submissions_url) . '">' . __('View all submissions', 'small-victories-bookings') . ' &rarr;</a>';
        echo '</p>';
        echo '</div>';
    }

    public function check_and_run_migrations() {
        $current_db_version = get_option('timeslot_db_version', '1');

        if (version_compare($current_db_version, TIMESLOT_DB_VERSION, '<')) {
            $this->run_migrations($current_db_version);
        }
    }

    private function run_migrations($from_version) {
        global $wpdb;

        // Migration to version 2: Add fields column to forms, convert submissions to JSON
        if (version_compare($from_version, '2', '<')) {
            // Add fields column to forms table
            $wpdb->query("ALTER TABLE {$wpdb->prefix}timeslot_forms ADD COLUMN fields LONGTEXT NULL AFTER columns");

            // Migrate existing forms to have default fields (Name and Email)
            $forms = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}timeslot_forms WHERE fields IS NULL");
            $default_fields = json_encode([
                ['label' => 'Name', 'required' => true, 'type' => 'text'],
                ['label' => 'Email', 'required' => true, 'type' => 'email']
            ]);

            foreach ($forms as $form) {
                $wpdb->update(
                    $wpdb->prefix . 'timeslot_forms',
                    ['fields' => $default_fields],
                    ['id' => $form->id],
                    ['%s'],
                    ['%d']
                );
            }

            // Add response column to submissions table
            $wpdb->query("ALTER TABLE {$wpdb->prefix}timeslot_submissions ADD COLUMN response LONGTEXT NULL AFTER form_id");

            // Migrate existing submissions data to JSON format
            $submissions = $wpdb->get_results("SELECT id, name, email FROM {$wpdb->prefix}timeslot_submissions WHERE response IS NULL");

            foreach ($submissions as $submission) {
                $response = json_encode([
                    'Name' => $submission->name,
                    'Email' => $submission->email
                ]);

                $wpdb->update(
                    $wpdb->prefix . 'timeslot_submissions',
                    ['response' => $response],
                    ['id' => $submission->id],
                    ['%s'],
                    ['%d']
                );
            }

            // Drop old columns from submissions table
            $wpdb->query("ALTER TABLE {$wpdb->prefix}timeslot_submissions DROP COLUMN name");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}timeslot_submissions DROP COLUMN email");

            update_option('timeslot_db_version', '2');
        }

        // Migration to version 3: Add email_template column to forms
        if (version_compare($from_version, '3', '<')) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}timeslot_forms ADD COLUMN email_template LONGTEXT NULL AFTER columns");
            update_option('timeslot_db_version', '3');
        }

        // Migration to version 4: Add share_key column to forms
        if (version_compare($from_version, '4', '<')) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}timeslot_forms ADD COLUMN share_key varchar(36) NULL AFTER email_template");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}timeslot_forms ADD UNIQUE KEY share_key (share_key)");

            // Generate share keys for existing forms
            $forms = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}timeslot_forms WHERE share_key IS NULL");
            foreach ($forms as $form) {
                $share_key = wp_generate_uuid4();
                $wpdb->update(
                    $wpdb->prefix . 'timeslot_forms',
                    array('share_key' => $share_key),
                    array('id' => $form->id),
                    array('%s'),
                    array('%d')
                );
            }

            update_option('timeslot_db_version', '4');
        }
    }
}

// Initialize plugin
TimeslotBookings::get_instance();
