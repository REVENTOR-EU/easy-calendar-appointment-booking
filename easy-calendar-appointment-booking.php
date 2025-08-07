<?php
/**
 * Plugin Name: Easy Calendar Appointment Booking
 * Description: An easy calendar appointment booking system with CalDAV integration for seamless scheduling and calendar synchronization.
 * Plugin URI: https://wordpress.org/plugins/easy-calendar-appointment-booking/
 * Version: 1.0.0
 * Author: REVENTOR
 * Author URI: https://reventor.eu
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: easy-calendar-appointment-booking
 * Domain Path: /languages
 * Requires at least: 6.3
 * Tested up to: 6.8.2
 * Requires PHP: 8.1
 * Tags: appointments, booking, calendar, caldav, scheduling
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check PHP version
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo sprintf(
            /* translators: %1$s is the current PHP version, %2$s is the required PHP version */
            esc_html__('Easy Calendar Appointment Booking requires PHP %2$s or higher. You are running PHP %1$s. Please upgrade your PHP version.', 'easy-calendar-appointment-booking'),
            esc_html(PHP_VERSION),
            esc_html('8.1')
        );
        echo '</p></div>';
    });
    return;
}

// Define plugin constants
define('EAB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EAB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('EAB_VERSION', '1.0.0');

// Main plugin class
class EasyAppointmentBooking {
    
    public function __construct() {
        // Include required files first
        $this->include_files();
        
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    public function init() {
        // Load text domain for translations
        $this->load_textdomain();
        
        // Check if plugin was properly activated
        if (is_admin() && !get_option('eab_plugin_activated')) {
            add_action('admin_notices', array($this, 'activation_notice'));
        }
        
        // Initialize components
        if (class_exists('EAB_Admin')) {
            new EAB_Admin();
        }
        if (class_exists('EAB_Frontend')) {
            new EAB_Frontend();
        }
        if (class_exists('EAB_Database')) {
            new EAB_Database();
        }
        if (class_exists('EAB_CalDAV')) {
            new EAB_CalDAV();
        }
        
        // Register shortcode
        add_shortcode('easy_calendar_appointment_booking', array($this, 'booking_form_shortcode'));
    }
    
    private function include_files() {
        $files = array(
            'includes/class-admin.php',
            'includes/class-frontend.php', 
            'includes/class-database.php',
            'includes/class-caldav.php',
            'includes/functions.php',
            'includes/email-functions.php'
        );
        
        foreach ($files as $file) {
            $file_path = EAB_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    public function booking_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'default'
        ), $atts);
        
        ob_start();
        include EAB_PLUGIN_PATH . 'templates/booking-form.php';
        return ob_get_clean();
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=easy-calendar-appointment-booking') . '">' . __('Settings', 'easy-calendar-appointment-booking') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function activate(): void {
        try {
            // Create database tables
            if (class_exists('EAB_Database')) {
                EAB_Database::create_tables();
            }
            
            // Set default options
            $default_options = [
                'timeslot_duration' => 30,
                'booking_days_ahead' => 30,
                'theme_color' => '#007cba',
                'appointment_types' => [['name' => __('General Consultation', 'easy-calendar-appointment-booking'), 'duration' => 30]],
                'caldav_url' => '',
                'caldav_username' => '',
                'caldav_password' => ''
            ];
            
            foreach ($default_options as $key => $value) {
                if (!get_option('eab_' . $key)) {
                    update_option('eab_' . $key, $value);
                }
            }
            
            // Set activation flag
            update_option('eab_plugin_activated', true);
            
        } catch (Exception $e) {
            // Don't prevent activation, just continue silently
        }
    }
    
    public function load_textdomain() {
        // WordPress automatically loads translations for plugins hosted on WordPress.org
        // since WordPress 4.6 using just-in-time loading. No manual load_plugin_textdomain() call needed.
        // The Text Domain header in the plugin file is sufficient for WordPress to locate translations.
    }
    
    public function activation_notice() {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Easy Calendar Appointment Booking:</strong> Plugin activation may not have completed successfully. Please check your error logs or try deactivating and reactivating the plugin.</p>';
        echo '</div>';
    }
    
    public function deactivate() {
        // Clean up activation flag
        delete_option('eab_plugin_activated');
    }
}

// Initialize the plugin
new EasyAppointmentBooking();