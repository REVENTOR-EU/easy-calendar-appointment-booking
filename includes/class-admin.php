<?php
/**
 * Admin functionality for Easy Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EAB_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_eab_save_settings', [$this, 'save_settings_ajax']);
        add_action('wp_ajax_eab_save_appointment_types', [$this, 'save_appointment_types_ajax']);
        add_action('wp_ajax_eab_test_caldav', [$this, 'test_caldav_connection']);
        add_action('wp_ajax_eab_test_caldav_conflicts', [$this, 'test_caldav_conflicts']);
        add_action('wp_ajax_nopriv_eab_test_caldav_conflicts', [$this, 'test_caldav_conflicts']);
        add_action('wp_ajax_eab_export_settings', [$this, 'export_settings_ajax']);
        add_action('wp_ajax_eab_import_settings', [$this, 'import_settings_ajax']);
    }
    
    public function add_admin_menu(): void {
        add_options_page(
            page_title: __('Easy Calendar Appointment Booking', 'easy-calendar-appointment-booking'),
            menu_title: __('Easy Calendar<br>Appointment Booking', 'easy-calendar-appointment-booking'),
            capability: 'manage_options',
            menu_slug: 'easy-calendar-appointment-booking',
            callback: [$this, 'admin_page']
        );
    }
    
    public function register_settings(): void {
        register_setting('eab_settings', 'eab_timeslot_duration', ['sanitize_callback' => 'intval']);
        register_setting('eab_settings', 'eab_booking_days_ahead', ['sanitize_callback' => 'intval']);
        register_setting('eab_settings', 'eab_theme_color', ['sanitize_callback' => 'sanitize_hex_color']);
        register_setting('eab_settings', 'eab_timezone', ['sanitize_callback' => [$this, 'sanitize_timezone']]);
        register_setting('eab_settings', 'eab_time_format', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_date_format', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_appointment_types', ['sanitize_callback' => [$this, 'sanitize_appointment_types']]);
        register_setting('eab_settings', 'eab_caldav_url', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('eab_settings', 'eab_caldav_username', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_caldav_password', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_min_booking_advance', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_working_hours_start', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_working_hours_end', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_working_days', ['sanitize_callback' => [$this, 'sanitize_working_days']]);

        register_setting('eab_settings', 'eab_email_sender_name', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_email_sender_email', ['sanitize_callback' => 'sanitize_email']);
        register_setting('eab_settings', 'eab_timezone', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_time_format', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('eab_settings', 'eab_date_format', ['sanitize_callback' => 'sanitize_text_field']);
    }
    
    public function sanitize_working_days($value) {
        if (!is_array($value)) {
            return array();
        }
        
        $valid_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $sanitized = array();
        
        foreach ($value as $day) {
            $day = sanitize_text_field($day);
            if (in_array($day, $valid_days, true)) {
                $sanitized[] = $day;
            }
        }
        
        return $sanitized;
    }
    
    public function sanitize_appointment_types($value) {
        if (!is_array($value)) {
            return array(array('name' => 'General Consultation', 'duration' => 30));
        }
        
        $sanitized = array();
        $valid_durations = array(15, 30, 45, 60, 90, 120);
        
        foreach ($value as $type) {
            if (!is_array($type)) {
                continue;
            }
            
            $name = isset($type['name']) ? sanitize_text_field($type['name']) : '';
            $duration = isset($type['duration']) ? absint($type['duration']) : 30;
            
            // Validate duration
            if (!in_array($duration, $valid_durations, true)) {
                $duration = 30;
            }
            
            // Only add if name is not empty
            if (!empty(trim($name))) {
                $sanitized[] = array(
                    'name' => $name,
                    'duration' => $duration
                );
            }
        }
        
        // Ensure at least one appointment type exists
        if (empty($sanitized)) {
            $sanitized = array(array('name' => 'General Consultation', 'duration' => 30));
        }
        
        return $sanitized;
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_easy-calendar-appointment-booking') {
            return;
        }
        
        wp_enqueue_style('eab-admin-style', EAB_PLUGIN_URL . 'assets/css/admin.css', array(), EAB_VERSION);
        wp_enqueue_script('eab-admin-script', EAB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), EAB_VERSION, true);
        
        wp_localize_script('eab-admin-script', 'eab_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eab_admin_nonce'),
            'frontend_nonce' => wp_create_nonce('eab_frontend_nonce'),
            'settings' => array(
                'time_format' => get_option('eab_time_format', '24h')
            ),
            'strings' => array(
                'saving' => __('Saving...', 'easy-calendar-appointment-booking'),
            'saved' => __('Settings saved successfully!', 'easy-calendar-appointment-booking'),
            'error' => __('Error saving settings. Please try again.', 'easy-calendar-appointment-booking'),
            'testing' => __('Testing connection...', 'easy-calendar-appointment-booking'),
            'connection_success' => __('CalDAV connection successful!', 'easy-calendar-appointment-booking'),
            'connection_failed' => __('CalDAV connection failed. Please check your settings.', 'easy-calendar-appointment-booking')
            )
        ));
    }
    
    public function admin_page() {
        $timeslot_duration = get_option('eab_timeslot_duration', 30);
        $booking_days_ahead = get_option('eab_booking_days_ahead', 7);
        $theme_color = get_option('eab_theme_color', '#007cba');
        $appointment_types = get_option('eab_appointment_types', array(array('name' => 'General Consultation', 'duration' => 30)));
        
        // Convert old format to new format if needed
        if (!empty($appointment_types) && isset($appointment_types[0]) && is_string($appointment_types[0])) {
            $converted_types = array();
            foreach ($appointment_types as $type) {
                $converted_types[] = array(
                    'name' => $type,
                    'duration' => 30
                );
            }
            $appointment_types = $converted_types;
            // Update the option with the new format
            update_option('eab_appointment_types', $appointment_types);
        }
        $caldav_url = get_option('eab_caldav_url', '');
        $caldav_username = get_option('eab_caldav_username', '');
        $caldav_password = get_option('eab_caldav_password', '');
        $min_booking_advance = get_option('eab_min_booking_advance', '2h');
        $working_hours_start = get_option('eab_working_hours_start', '09:00');
        $working_hours_end = get_option('eab_working_hours_end', '17:00');
        $working_days = get_option('eab_working_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'));
        $timezone = get_option('eab_timezone', $this->get_timezone_string());
        $time_format = get_option('eab_time_format', '24h');
        $date_format = get_option('eab_date_format', 'DD.MM.YYYY');
        
        include EAB_PLUGIN_PATH . 'templates/admin-page.php';
    }
    
    public function save_settings_ajax() {
        try {
            check_ajax_referer('eab_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Unauthorized', 'easy-calendar-appointment-booking')));
                return;
            }
            
            $this->save_general_settings();
            $this->save_restriction_settings();
            $this->save_working_hours_settings();
            $this->save_appointment_types_settings();
            $this->save_email_settings();
            $this->save_caldav_settings();
            
            wp_send_json_success(array('message' => __('All settings saved successfully!', 'easy-calendar-appointment-booking')));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error saving settings: ', 'easy-calendar-appointment-booking') . $e->getMessage()));
        }
    }
    
    private function save_general_settings() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in save_settings_ajax()
        $timeslot_duration = isset($_POST['timeslot_duration']) ? intval($_POST['timeslot_duration']) : 30;
        $booking_days_ahead = isset($_POST['booking_days_ahead']) ? intval($_POST['booking_days_ahead']) : 7;
        $theme_color = isset($_POST['theme_color']) ? sanitize_hex_color(wp_unslash($_POST['theme_color'])) : '#0073aa';
        $timezone = isset($_POST['timezone']) ? sanitize_text_field(wp_unslash($_POST['timezone'])) : 'UTC';
        $time_format = isset($_POST['time_format']) ? sanitize_text_field(wp_unslash($_POST['time_format'])) : '24h';
        $date_format = isset($_POST['date_format']) ? sanitize_text_field(wp_unslash($_POST['date_format'])) : 'DD.MM.YYYY';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        update_option('eab_timeslot_duration', $timeslot_duration);
        update_option('eab_booking_days_ahead', $booking_days_ahead);
        update_option('eab_theme_color', $theme_color);
        update_option('eab_timezone', $timezone);
        update_option('eab_time_format', $time_format);
        update_option('eab_date_format', $date_format);
        
        return true;
    }
    
    private function save_restriction_settings() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in save_settings_ajax()
        $min_booking_advance = isset($_POST['min_booking_advance']) ? sanitize_text_field(wp_unslash($_POST['min_booking_advance'])) : '2h';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        update_option('eab_min_booking_advance', $min_booking_advance);
        
        return true;
    }
    
    private function save_working_hours_settings() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in save_settings_ajax()
        $working_hours_start = isset($_POST['working_hours_start']) ? sanitize_text_field(wp_unslash($_POST['working_hours_start'])) : '09:00';
        $working_hours_end = isset($_POST['working_hours_end']) ? sanitize_text_field(wp_unslash($_POST['working_hours_end'])) : '17:00';
        $working_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
        if (isset($_POST['working_days']) && is_array($_POST['working_days'])) {
            $working_days = array_map('sanitize_text_field', wp_unslash($_POST['working_days']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        // Debug logging for working days removed
        
        update_option('eab_working_hours_start', $working_hours_start);
        update_option('eab_working_hours_end', $working_hours_end);
        update_option('eab_working_days', $working_days);
        
        // Verify the save removed
        
        return true;
    }
    
    public function save_appointment_types_ajax() {
        try {
            check_ajax_referer('eab_admin_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Unauthorized access.', 'easy-calendar-appointment-booking')));
                return;
            }
            
            $appointment_types = array();
        
        // Get appointment types from POST data
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $post_data = isset($_POST['appointment_types']) ? $_POST['appointment_types'] : array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $raw_types = wp_unslash($post_data);
        
        if (!is_array($raw_types)) {
            wp_send_json_error(array('message' => __('Invalid appointment types data.', 'easy-calendar-appointment-booking')));
        }
        
        // Process each appointment type with strict PHP 8.1 compatibility
        foreach ($raw_types as $index => $type_data) {
            if (!is_array($type_data)) {
                continue;
            }
            
            $name = array_key_exists('name', $type_data) ? sanitize_text_field($type_data['name']) : '';
            $duration = array_key_exists('duration', $type_data) ? absint($type_data['duration']) : 30;
            
            // Validate duration
            if (!in_array($duration, array(15, 30, 45, 60, 90, 120), true)) {
                $duration = 30;
            }
            
            // Only add if name is not empty
            if (!empty(trim($name))) {
                $appointment_types[] = array(
                    'name' => $name,
                    'duration' => $duration
                );
            }
        }
        
        // Ensure at least one appointment type exists
        if (empty($appointment_types)) {
            $appointment_types = array(
                array(
                    'name' => 'General Consultation',
                    'duration' => 30
                )
            );
        }
        
            // Update the option
            $result = update_option('eab_appointment_types', $appointment_types);
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => __('Appointment types saved successfully!', 'easy-calendar-appointment-booking'),
                    'appointment_types' => $appointment_types
                ));
            } else {
                wp_send_json_error(array('message' => __('Failed to save appointment types.', 'easy-calendar-appointment-booking')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error saving appointment types: ', 'easy-calendar-appointment-booking') . $e->getMessage()));
        }
    }
    
    private function save_appointment_types_settings() {
        // This method is kept for backward compatibility with the main save function
        return $this->save_appointment_types_from_post();
    }
    
    private function save_appointment_types_from_post() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in save_settings_ajax()
        $appointment_types = array();
        
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $post_types = isset($_POST['appointment_types']) ? $_POST['appointment_types'] : array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $post_types = wp_unslash($post_types);
        if (is_array($post_types)) {
            foreach ($post_types as $index => $type_data) {
                if (!is_array($type_data)) {
                    continue;
                }
                
                $name = array_key_exists('name', $type_data) ? sanitize_text_field($type_data['name']) : '';
                $duration = array_key_exists('duration', $type_data) ? absint($type_data['duration']) : 30;
                
                if (!in_array($duration, array(15, 30, 45, 60, 90, 120), true)) {
                    $duration = 30;
                }
                
                if (!empty(trim($name))) {
                    $appointment_types[] = array(
                        'name' => $name,
                        'duration' => $duration
                    );
                }
            }
        }
        
        if (empty($appointment_types)) {
            $appointment_types = array(array('name' => 'General Consultation', 'duration' => 30));
        }
        
        update_option('eab_appointment_types', $appointment_types);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        return true;
    }
    

    private function save_email_settings() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in save_settings_ajax()
        $email_sender_name = isset($_POST['email_sender_name']) ? sanitize_text_field(wp_unslash($_POST['email_sender_name'])) : '';
        $email_sender_email = isset($_POST['email_sender_email']) ? sanitize_email(wp_unslash($_POST['email_sender_email'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        update_option('eab_email_sender_name', $email_sender_name);
        update_option('eab_email_sender_email', $email_sender_email);
        
        return true;
    }
    
    private function save_caldav_settings() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in save_settings_ajax()
        $caldav_url = isset($_POST['caldav_url']) ? esc_url_raw(wp_unslash($_POST['caldav_url'])) : '';
        $caldav_username = isset($_POST['caldav_username']) ? sanitize_text_field(wp_unslash($_POST['caldav_username'])) : '';
        $caldav_password = isset($_POST['caldav_password']) ? sanitize_text_field(wp_unslash($_POST['caldav_password'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        update_option('eab_caldav_url', $caldav_url);
        update_option('eab_caldav_username', $caldav_username);
        update_option('eab_caldav_password', $caldav_password);
        
        return true;
    }
    
    public function test_caldav_connection() {
        check_ajax_referer('eab_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'easy-calendar-appointment-booking'));
        }
        
        $caldav_url = isset($_POST['caldav_url']) ? esc_url_raw(wp_unslash($_POST['caldav_url'])) : '';
        $caldav_username = isset($_POST['caldav_username']) ? sanitize_text_field(wp_unslash($_POST['caldav_username'])) : '';
        $caldav_password = isset($_POST['caldav_password']) ? sanitize_text_field(wp_unslash($_POST['caldav_password'])) : '';
        
        $caldav = new EAB_CalDAV();
        $result = $caldav->test_connection($caldav_url, $caldav_username, $caldav_password);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    public function test_caldav_conflicts() {
        check_ajax_referer('eab_frontend_nonce', 'nonce');
        
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
        $appointment_type = isset($_POST['appointment_type']) ? sanitize_text_field(wp_unslash($_POST['appointment_type'])) : '';
        
        if (empty($date)) {
            wp_send_json_error(array('message' => 'Date is required'));
            return;
        }
        
        // Get CalDAV settings
        $caldav_url = get_option('eab_caldav_url', '');
        $caldav_username = get_option('eab_caldav_username', '');
        $caldav_password = get_option('eab_caldav_password', '');
        
        if (empty($caldav_url) || empty($caldav_username) || empty($caldav_password)) {
            wp_send_json_error(array('message' => 'CalDAV not configured'));
            return;
        }
        
        try {
            $caldav = new EAB_CalDAV();
            
            // Generate test time slots
            $duration = get_option('eab_timeslot_duration', 30);
            $start_time = get_option('eab_working_hours_start', '09:00');
            $end_time = get_option('eab_working_hours_end', '17:00');
            
            $slots = array();
            $current = strtotime($date . ' ' . $start_time);
            $end = strtotime($date . ' ' . $end_time);
            
            while ($current < $end) {
                $slots[] = gmdate('H:i', $current);
                $current += ($duration * 60);
            }
            
            // Get conflicts
            $conflicts = $caldav->get_conflicts($date, $slots, $duration);
            
            wp_send_json_success(array(
                'conflicts' => $conflicts,
                'total_slots' => count($slots),
                'conflict_count' => count($conflicts),
                'message' => 'CalDAV conflicts checked successfully'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error checking conflicts: ' . $e->getMessage()));
        }
    }
    
    public function export_settings_ajax() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'eab_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Get all plugin settings
        $settings = array(
            'eab_timeslot_duration' => get_option('eab_timeslot_duration', 30),
            'eab_booking_days_ahead' => get_option('eab_booking_days_ahead', 30),
            'eab_theme_color' => get_option('eab_theme_color', '#007cba'),
            'eab_appointment_types' => get_option('eab_appointment_types', array(array('name' => 'General Consultation', 'duration' => 30))),
            'eab_caldav_url' => get_option('eab_caldav_url', ''),
            'eab_caldav_username' => get_option('eab_caldav_username', ''),
            'eab_caldav_password' => get_option('eab_caldav_password', ''),
            'eab_min_booking_advance' => get_option('eab_min_booking_advance', '1h'),
            'eab_working_hours_start' => get_option('eab_working_hours_start', '09:00'),
            'eab_working_hours_end' => get_option('eab_working_hours_end', '23:00'),
            'eab_working_days' => get_option('eab_working_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday')),

            'eab_email_sender_name' => get_option('eab_email_sender_name', ''),
            'eab_email_sender_email' => get_option('eab_email_sender_email', ''),
            'eab_timezone' => $this->get_timezone_string(),
            'eab_time_format' => get_option('eab_time_format', 'H:i'),
            'eab_date_format' => get_option('eab_date_format', 'Y-m-d')
        );
        
        // Create export data
        $export_data = array(
            'plugin' => 'Easy Calendar Appointment Booking',
            'version' => '1.0.0',
            'exported_at' => current_time('c'),
            'settings' => $settings
        );
        
        wp_send_json_success($export_data);
    }
    
    public function import_settings_ajax() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'eab_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Get import data
        if (!isset($_POST['import_data'])) {
            wp_send_json_error(array('message' => 'No import data provided'));
            return;
        }
        
        $import_data = json_decode(stripslashes(sanitize_textarea_field(wp_unslash($_POST['import_data']))), true);
        
        if (!$import_data || !isset($import_data['settings'])) {
            wp_send_json_error(array('message' => 'Invalid import data format'));
            return;
        }
        
        $settings = $import_data['settings'];
        $imported_count = 0;
        
        // Define valid settings keys
        $valid_settings = array(
            'eab_timeslot_duration',
            'eab_booking_days_ahead',
            'eab_theme_color',
            'eab_appointment_types',
            'eab_caldav_url',
            'eab_caldav_username',
            'eab_caldav_password',
            'eab_min_booking_advance',
            'eab_working_hours_start',
            'eab_working_hours_end',
            'eab_working_days',

            'eab_email_sender_name',
            'eab_email_sender_email',
            'eab_timezone',
            'eab_time_format',
            'eab_date_format'
        );
        
        // Import settings
        foreach ($settings as $key => $value) {
            if (in_array($key, $valid_settings)) {
                update_option($key, $value);
                $imported_count++;
            }
        }
        
        if ($imported_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf('Successfully imported %d settings', $imported_count),
                'imported_count' => $imported_count
            ));
        } else {
            wp_send_json_error(array('message' => 'No valid settings found to import'));
        }
    }
    
    /**
     * Get timezone string with fallback for older WordPress versions
     */
    private function get_timezone_string() {
        // Try wp_timezone_string() first (WordPress 5.3+)
        if (function_exists('wp_timezone_string')) {
            try {
                return wp_timezone_string();
            } catch (Exception $e) {
                // Fall through to manual method
            }
        }
        
        // Fallback method for older WordPress versions
        $timezone_string = get_option('timezone_string');
        
        if (!empty($timezone_string)) {
            return $timezone_string;
        }
        
        // If no timezone_string, try to get from GMT offset
        $offset = get_option('gmt_offset');
        if ($offset !== false) {
            $timezone_string = timezone_name_from_abbr('', $offset * 3600, 0);
            if ($timezone_string !== false) {
                return $timezone_string;
            }
        }
        
        // Final fallback
        return 'UTC';
    }

    /**
     * Sanitize timezone setting
     */
    public function sanitize_timezone($timezone) {
        // Get list of valid timezones
        $valid_timezones = timezone_identifiers_list();
        
        // Check if the provided timezone is valid
        if (in_array($timezone, $valid_timezones, true)) {
            return $timezone;
        }
        
        // Return default if invalid
        return 'UTC';
    }

}