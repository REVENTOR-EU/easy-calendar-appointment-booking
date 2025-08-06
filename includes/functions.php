<?php
/**
 * Helper functions for Easy Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get formatted appointment types for display
 */
function eab_get_appointment_types(): array {
    $types = get_option('eab_appointment_types', [__('General Consultation', 'easy-calendar-appointment-booking')]);
    return is_array($types) ? $types : [__('General Consultation', 'easy-calendar-appointment-booking')];
}

/**
 * Get plugin settings
 */
function eab_get_settings(): array {
    return [
        'timeslot_duration' => get_option('eab_timeslot_duration', 30),
        'booking_days_ahead' => get_option('eab_booking_days_ahead', 7),
        'theme_color' => get_option('eab_theme_color', '#007cba'),
        'appointment_types' => eab_get_appointment_types(),
        'caldav_url' => get_option('eab_caldav_url', ''),
        'caldav_username' => get_option('eab_caldav_username', ''),
        'caldav_password' => get_option('eab_caldav_password', '')
    ];
}

/**
 * Get the current time format setting
 */
function eab_get_time_format(): string {
    return get_option('eab_time_format', '24h');
}

/**
 * Get the current date format setting
 */
function eab_get_date_format(): string {
    return get_option('eab_date_format', 'DD.MM.YYYY');
}

/**
 * Format date according to the selected date format setting
 */
function eab_format_date(string|int $date, ?string $format = null): string {
    $format ??= get_option('eab_date_format', 'DD.MM.YYYY');
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    
    return match($format) {
        'MM/DD/YYYY' => gmdate('m/d/Y', $timestamp),
        'YYYY-MM-DD' => gmdate('Y-m-d', $timestamp),
        'DD/MM/YYYY' => gmdate('d/m/Y', $timestamp),
        'DD.MM.YYYY' => gmdate('d.m.Y', $timestamp),
        default => gmdate('d.m.Y', $timestamp)
    };
}

/**
 * Format time according to the selected time format setting
 */
function eab_format_time(string $time, ?string $format = null): string {
    $format ??= get_option('eab_time_format', '24h');
    
    return match($format) {
        '12h' => gmdate('g:i A', strtotime($time)),
        default => gmdate('H:i', strtotime($time))
    };
}

/**
 * Get available booking dates
 */
function eab_get_available_dates() {
    $booking_days_ahead = get_option('eab_booking_days_ahead', 7);
    $working_days = get_option('eab_working_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'));
    $min_booking_advance = get_option('eab_min_booking_advance', '2h');
    
    // Calculate minimum advance timestamp
    $now = current_time('timestamp');
    switch ($min_booking_advance) {
        case '1h':
            $min_advance_time = $now + (1 * 60 * 60);
            break;
        case '2h':
            $min_advance_time = $now + (2 * 60 * 60);
            break;
        case '4h':
            $min_advance_time = $now + (4 * 60 * 60);
            break;
        case 'next_day':
            $min_advance_time = strtotime('tomorrow', $now);
            break;
        default:
            $min_advance_time = $now + (2 * 60 * 60);
            break;
    }
    
    $dates = array();
    
    // Check only within the booking_days_ahead period
    for ($i = 0; $i < $booking_days_ahead; $i++) {
        $date = gmdate('Y-m-d', strtotime("+{$i} days"));
        $date_timestamp = strtotime($date);
        $day_of_week = strtolower(gmdate('l', strtotime($date)));
        
        // Check if any time slots in the day could meet the minimum advance time
        // Use consistent logic for all dates
        $date_end_timestamp = strtotime($date . ' 23:59:59');
        if ($date_end_timestamp < $min_advance_time) {
            continue; // Skip this date if no time slots would be available
        }
        
        // Check if date is a working day and has available time slots
        if (in_array($day_of_week, $working_days) && eab_date_has_available_slots($date)) {
            $dates[] = array(
                'value' => $date,
                'label' => eab_format_date($date)
            );
        }
    }
    
    return $dates;
}

/**
 * Validate appointment data
 */
function eab_validate_appointment_data(array $data): array {
    $errors = [];
    
    // Required fields
    $required_fields = ['name', 'email', 'appointment_type', 'appointment_date', 'appointment_time'];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            // translators: %s is the field name
            $errors[] = sprintf(__('The %s field is required.', 'easy-calendar-appointment-booking'), $field);
        }
    }
    
    // Email validation
    if (!empty($data['email']) && !is_email($data['email'])) {
        $errors[] = __('Please enter a valid email address.', 'easy-calendar-appointment-booking');
    }
    
    // Date validation
    if (!empty($data['appointment_date'])) {
        $date = strtotime($data['appointment_date']);
        $today = strtotime('today');
        $max_date = strtotime('+' . get_option('eab_booking_days_ahead', 30) . ' days');
        
        if ($date <= $today) {
            $errors[] = __('Appointment date must be in the future.', 'easy-calendar-appointment-booking');
        }
        
        if ($date > $max_date) {
            $errors[] = __('Appointment date is too far in the future.', 'easy-calendar-appointment-booking');
        }
    }
    
    // Time validation
    if (!empty($data['appointment_time'])) {
        $time_parts = explode(':', $data['appointment_time']);
        if (count($time_parts) !== 2 || !is_numeric($time_parts[0]) || !is_numeric($time_parts[1])) {
            $errors[] = __('Invalid appointment time format.', 'easy-calendar-appointment-booking');
        }
    }
    
    return $errors;
}

/**
 * Sanitize appointment data
 */
function eab_sanitize_appointment_data($data) {
    $sanitized = array();
    
    $sanitized['name'] = sanitize_text_field($data['name'] ?? '');
    $sanitized['email'] = sanitize_email($data['email'] ?? '');
    $sanitized['phone'] = sanitize_text_field($data['phone'] ?? '');
    $sanitized['appointment_type'] = sanitize_text_field($data['appointment_type'] ?? '');
    $sanitized['appointment_date'] = sanitize_text_field($data['appointment_date'] ?? '');
    $sanitized['appointment_time'] = sanitize_text_field($data['appointment_time'] ?? '');
    $sanitized['notes'] = sanitize_textarea_field($data['notes'] ?? '');
    
    return $sanitized;
}

/**
 * Get appointment status options
 */
function eab_get_status_options(): array {
    return [
        'confirmed' => __('Confirmed', 'easy-calendar-appointment-booking'),
        'pending' => __('Pending', 'easy-calendar-appointment-booking'),
        'cancelled' => __('Cancelled', 'easy-calendar-appointment-booking'),
        'completed' => __('Completed', 'easy-calendar-appointment-booking')
    ];
}

/**
 * Generate unique appointment ID
 */
function eab_generate_appointment_id(): string {
    return 'EAB-' . strtoupper(wp_generate_password(8, false));
}

/**
 * Check if time slot is available
 */
function eab_is_time_slot_available(string $date, string $time): bool {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'eab_appointments';
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->prefix}eab_appointments` WHERE appointment_date = %s AND appointment_time = %s AND status = 'confirmed'",
        $date,
        $time
    ));
    
    return $count == 0;
}

/**
 * Get business hours
 */
function eab_get_business_hours(): array {
    return apply_filters('eab_business_hours', [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [1, 2, 3, 4, 5] // Monday to Friday
    ]);
}

/**
 * Check if date is a business day
 */
function eab_is_business_day($date) {
    $business_hours = eab_get_business_hours();
    $day_of_week = gmdate('w', strtotime($date));
    
    return in_array($day_of_week, $business_hours['days']);
}

/**
 * Get plugin version
 */
function eab_get_version() {
    return EAB_VERSION;
}

/**
 * Log debug information
 */
function eab_log($message, $level = 'info') {
    // TEMPORARY: Force logging regardless of WP_DEBUG for testing
    $log_file = plugin_dir_path(__FILE__) . '../caldav-debug.log';
    $timestamp = gmdate('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [EAB " . strtoupper($level) . "] {$message}" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Get timezone string
 */
function eab_get_timezone_string() {
    // First check if we have a custom timezone setting
    $custom_timezone = get_option('eab_timezone');
    if (!empty($custom_timezone)) {
        return $custom_timezone;
    }
    
    // Try wp_timezone_string() first (WordPress 5.3+)
    if (function_exists('wp_timezone_string')) {
        try {
            return wp_timezone_string();
        } catch (Exception $e) {
            // Fall through to manual method
        }
    }
    
    $timezone_string = get_option('timezone_string');
    
    if ($timezone_string) {
        return $timezone_string;
    }
    
    $offset = get_option('gmt_offset');
    if ($offset !== false) {
        $timezone_string = timezone_name_from_abbr('', $offset * 3600, 0);
        if ($timezone_string !== false) {
            return $timezone_string;
        }
    }
    
    return 'UTC';
}

/**
 * Convert time to user timezone
 */
function eab_convert_to_user_timezone($datetime, $format = 'Y-m-d H:i:s') {
    $timezone = new DateTimeZone(eab_get_timezone_string());
    $date = new DateTime($datetime, new DateTimeZone('UTC'));
    $date->setTimezone($timezone);
    
    return $date->format($format);
}

/**
 * Get localized month names
 */
function eab_get_month_names() {
    global $wp_locale;
    
    $months = array();
    for ($i = 1; $i <= 12; $i++) {
        $months[$i] = $wp_locale->get_month($i);
    }
    
    return $months;
}

/**
 * Get localized day names
 */
function eab_get_day_names() {
    global $wp_locale;
    
    $days = array();
    for ($i = 0; $i <= 6; $i++) {
        $days[$i] = $wp_locale->get_weekday($i);
    }
    
    return $days;
}

/**
 * Check if a date has available time slots
 */
function eab_date_has_available_slots($date) {
    // Get default appointment type duration
    $appointment_types = eab_get_appointment_types();
    $duration = 30; // Default duration
    if (!empty($appointment_types) && isset($appointment_types[0]['duration'])) {
        $duration = intval($appointment_types[0]['duration']);
    }
    
    // Check if it's a working day (using timezone-aware calculation)
    $working_days = get_option('eab_working_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
    
    // Get timezone setting
    try {
        $timezone_string = wp_timezone_string();
        $timezone = new DateTimeZone($timezone_string);
    } catch (Exception $e) {
        $timezone = new DateTimeZone('UTC');
    }
    
    $date_obj = new DateTime($date, $timezone);
    $day_of_week = strtolower($date_obj->format('l'));
    
    if (!in_array($day_of_week, $working_days)) {
        return false;
    }
    
    // Check minimum advance time
    $min_advance = get_option('eab_min_booking_advance', '2h');
    $now = current_time('timestamp');
    
    switch ($min_advance) {
        case '1h':
            $min_advance_time = $now + (1 * 60 * 60);
            break;
        case '4h':
            $min_advance_time = $now + (4 * 60 * 60);
            break;
        case 'next_day':
            $min_advance_time = strtotime('tomorrow', $now);
            break;
        case '2h':
        default:
            $min_advance_time = $now + (2 * 60 * 60);
            break;
    }
    
    // For today, check if there's enough time left based on minimum advance
    $date_timestamp = strtotime($date);
    $today = strtotime(gmdate('Y-m-d'));
    
    // Check if any time slots in the day could meet the minimum advance time
    // Use the same logic as the AJAX handler for consistency
    $date_end_timestamp = strtotime($date . ' 23:59:59');
    if ($date_end_timestamp < $min_advance_time) {
        return false; // No time slots would be available on this date
    }
    
    // Generate time slots for this date (using same logic as AJAX handler)
    $working_hours_start = get_option('eab_working_hours_start', '09:00');
    $working_hours_end = get_option('eab_working_hours_end', '17:00');
    
    // Create timezone-aware datetime objects for working hours
    $start_datetime = new DateTime($date . ' ' . $working_hours_start . ':00', $timezone);
    $end_datetime = new DateTime($date . ' ' . $working_hours_end . ':00', $timezone);
    
    $start_time = $start_datetime->getTimestamp();
    $end_time = $end_datetime->getTimestamp();
    
    // Get current time in the same timezone
    $now_datetime = new DateTime('now', $timezone);
    $current_time = $now_datetime->getTimestamp();
    
    $available_slots = [];
    
    // Generate time slots (matching AJAX handler logic exactly)
    for ($time = $start_time; $time < $end_time; $time += ($duration * 60)) {
        // Use timezone-aware formatting
        $slot_datetime = new DateTime('@' . $time);
        $slot_datetime->setTimezone($timezone);
        $slot_time = $slot_datetime->format('H:i');
        
        // Skip slots that are in the past or don't meet minimum advance time
        if ($time > $current_time && $time >= $min_advance_time) {
            $available_slots[] = $slot_time;
        }
    }
    
    if (empty($available_slots)) {
        return false;
    }
    
    // Check for booked slots
    global $wpdb;
    $table_name = $wpdb->prefix . 'eab_appointments';
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query required
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Real-time booking data needed
    $booked_slots = $wpdb->get_col($wpdb->prepare(
        "SELECT appointment_time FROM `{$wpdb->prefix}eab_appointments` WHERE appointment_date = %s AND status = 'confirmed'",
        $date
    ));
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
    
    // Check for CalDAV conflicts if configured
    $caldav_conflicts = [];
    $caldav_url = get_option('eab_caldav_url', '');
    $caldav_username = get_option('eab_caldav_username', '');
    $caldav_password = get_option('eab_caldav_password', '');
    
    if (!empty($caldav_url) && !empty($caldav_username) && !empty($caldav_password)) {
        // Always fetch fresh data directly from CalDAV server
        if (class_exists('EAB_CalDAV')) {
            $caldav = new EAB_CalDAV();
            $caldav_conflicts = $caldav->get_conflicts($date, $available_slots, $duration);
        }
    }
    
    // Calculate final available slots
    $final_available_slots = array_diff($available_slots, $booked_slots, $caldav_conflicts);
    
    return !empty($final_available_slots);
}