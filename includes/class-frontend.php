<?php
/**
 * Frontend functionality for Easy Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EAB_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('wp_ajax_eab_get_available_slots', [$this, 'get_available_slots']);
        add_action('wp_ajax_nopriv_eab_get_available_slots', [$this, 'get_available_slots']);
        add_action('wp_ajax_eab_book_appointment', [$this, 'book_appointment']);
        add_action('wp_ajax_nopriv_eab_book_appointment', [$this, 'book_appointment']);
        add_action('wp_ajax_eab_sync_calendar', [$this, 'sync_calendar']);
        add_action('wp_ajax_nopriv_eab_sync_calendar', [$this, 'sync_calendar']);
    }
    
    public function enqueue_frontend_scripts(): void {
        if ($this->has_booking_shortcode()) {
            wp_enqueue_style('eab-frontend-style', EAB_PLUGIN_URL . 'assets/css/frontend.css', [], EAB_VERSION);
            wp_enqueue_script('eab-frontend-script', EAB_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], EAB_VERSION, true);
            
            $theme_color = get_option('eab_theme_color', '#007cba');
            
            // Add inline CSS to apply theme color to success message
            $custom_css = "
                .eab-success-icon {
                    color: {$theme_color} !important;
                }
                .eab-success-content h4 {
                    color: {$theme_color} !important;
                }
                .eab-step.completed .eab-step-number {
                    background: {$theme_color} !important;
                }
            ";
            wp_add_inline_style('eab-frontend-style', $custom_css);
            
            wp_localize_script('eab-frontend-script', 'eab_frontend', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eab_frontend_nonce'),
                'theme_color' => $theme_color,
                'settings' => [
                    'working_days' => get_option('eab_working_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                    'min_booking_advance' => get_option('eab_min_booking_advance', '2h'),
                    'working_hours_start' => get_option('eab_working_hours_start', '09:00'),
                    'working_hours_end' => get_option('eab_working_hours_end', '17:00'),
                    'time_format' => get_option('eab_time_format', '24h')
                ],
                'strings' => [
                    'loading' => __('Loading...', 'easy-calendar-appointment-booking'),
            'no_slots' => __('No available time slots for this date.', 'easy-calendar-appointment-booking'),
            'booking_success' => __('Appointment booked successfully!', 'easy-calendar-appointment-booking'),
            'booking_error' => __('Error booking appointment. Please try again.', 'easy-calendar-appointment-booking'),
            'required_fields' => __('Please fill in all required fields.', 'easy-calendar-appointment-booking'),
            'invalid_email' => __('Please enter a valid email address.', 'easy-calendar-appointment-booking'),
            'syncing_calendar' => __('Syncing with calendar...', 'easy-calendar-appointment-booking'),
            'outside_working_hours' => __('This date is outside working hours.', 'easy-calendar-appointment-booking'),
            'minimum_advance_required' => __('Please select a date that meets the minimum advance time requirement.', 'easy-calendar-appointment-booking')
                ]
            ]);
            
            // Add dynamic CSS for theme color
            wp_add_inline_style('eab-frontend-style', $this->get_dynamic_css($theme_color));
        }
    }
    
    private function has_booking_shortcode(): bool {
        global $post;
        return is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'easy_calendar_appointment_booking');
    }
    
    private function get_dynamic_css(string $theme_color): string {
        $theme_color_rgb = $this->hex_to_rgb($theme_color);
        $theme_color_rgba_05 = "rgba({$theme_color_rgb['r']}, {$theme_color_rgb['g']}, {$theme_color_rgb['b']}, 0.05)";
        $theme_color_rgba_10 = "rgba({$theme_color_rgb['r']}, {$theme_color_rgb['g']}, {$theme_color_rgb['b']}, 0.1)";
        $theme_color_rgba_15 = "rgba({$theme_color_rgb['r']}, {$theme_color_rgb['g']}, {$theme_color_rgb['b']}, 0.15)";
        $theme_color_rgba_30 = "rgba({$theme_color_rgb['r']}, {$theme_color_rgb['g']}, {$theme_color_rgb['b']}, 0.3)";
        $darker_theme = $this->darken_color($theme_color, 15);
        $text_color = $this->get_contrast_text_color($theme_color);
        
        return "
            /* Form Header */
            .eab-booking-form .eab-form-header {
                background: linear-gradient(135deg, {$theme_color} 0%, {$darker_theme} 100%);
            }
            
            /* Progress Bar */
            .eab-booking-form .eab-progress-bar {
                background-color: #fff;
            }
            
            /* Steps Indicator */
            .eab-booking-form .eab-step.active:not(:last-child)::after,
            .eab-booking-form .eab-step.completed:not(:last-child)::after {
                background: {$theme_color};
            }
            
            .eab-booking-form .eab-step.active .eab-step-number {
                background-color: {$theme_color};
            }
            
            .eab-booking-form .eab-step.completed .eab-step-number {
                background-color: {$theme_color};
            }
            
            .eab-booking-form .eab-step.active .eab-step-label {
                color: {$theme_color};
            }
            
            /* Service Selection */
            .eab-booking-form .eab-service-card::before {
                background: {$theme_color};
            }
            
            .eab-booking-form .eab-service-option:hover .eab-service-card {
                border-color: {$theme_color};
                box-shadow: 0 8px 25px {$theme_color_rgba_15};
            }
            
            .eab-booking-form .eab-service-option input:checked + .eab-service-card {
                border-color: {$theme_color};
                background: {$theme_color_rgba_05};
            }
            
            .eab-booking-form .eab-service-icon {
                color: {$theme_color};
            }
            
            /* Date & Time Selection */
            .eab-booking-form .eab-date-selection select:focus {
                border-color: {$theme_color};
                box-shadow: 0 0 0 3px {$theme_color_rgba_10};
            }
            
            .eab-booking-form .eab-time-slot:hover {
                border-color: {$theme_color};
                background: {$theme_color_rgba_05};
            }
            
            .eab-booking-form .eab-time-slot.selected {
                background-color: {$theme_color};
                border-color: {$theme_color};
            }
            
            /* Form Inputs */
            .eab-booking-form .eab-form-group input:focus,
            .eab-booking-form .eab-form-group textarea:focus {
                border-color: {$theme_color};
                box-shadow: 0 0 0 3px {$theme_color_rgba_10};
            }
            
            /* Confirmation */
            .eab-booking-form .eab-confirmation-header .dashicons {
                color: {$theme_color};
            }
            
            /* Buttons */
            .eab-booking-form .eab-btn-primary {
                background-color: {$theme_color};
                border-color: {$theme_color};
                color: {$text_color};
            }
            
            .eab-booking-form .eab-btn-primary:hover:not(:disabled) {
                background-color: {$darker_theme};
                box-shadow: 0 4px 12px {$theme_color_rgba_30};
                color: {$text_color};
            }
            
            /* Loading Spinner */
            .eab-booking-form .eab-spinner {
                border-top-color: {$theme_color};
            }
            
            /* Branding Link */
            .eab-branding-link {
                color: {$theme_color} !important;
            }
            
            .eab-branding-link:hover {
                color: {$darker_theme} !important;
            }
            
            .eab-branding-link:hover::after {
                background: linear-gradient(90deg, {$theme_color}, {$darker_theme}) !important;
            }
        ";
    }
    
    private function darken_color($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    private function hex_to_rgb(string $hex): array {
        $hex = str_replace('#', '', $hex);
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    private function get_contrast_text_color(string $hex): string {
        $rgb = $this->hex_to_rgb($hex);
        
        // Calculate relative luminance using WCAG formula
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;
        
        // Apply gamma correction
        $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        
        // Calculate luminance
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        
        // Return white for dark colors, black for light colors
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
    
    public function get_available_slots(): void {
        // AJAX handler for getting available slots
        
        try {
            check_ajax_referer('eab_frontend_nonce', 'nonce');
        } catch (Exception $e) {
            // Nonce verification failed
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }
        
        try {
            $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
            $appointment_type = isset($_POST['appointment_type']) ? sanitize_text_field(wp_unslash($_POST['appointment_type'])) : '';
        
        // Get duration from appointment type or use default
        $timeslot_duration = get_option('eab_timeslot_duration', 30);
        if (isset($_POST['duration'])) {
            $timeslot_duration = intval(wp_unslash($_POST['duration']));
        } else {
            // Try to parse appointment type for duration (backward compatibility)
            $appointment_data = json_decode($appointment_type, true);
            if (is_array($appointment_data) && isset($appointment_data['duration'])) {
                $timeslot_duration = intval($appointment_data['duration']);
            }
        }
        
        // Debug logging
        // Debug information prepared
        
        // Check if date is within working days
        if (!$this->is_working_day($date)) {
            // Not a working day
            wp_send_json_success(['slots' => [], 'debug' => 'not_working_day']);
            return;
        }
        
        // Check minimum booking advance time (skip for admin preview)
        // Only consider it admin preview if explicitly set via admin_preview parameter
        $is_admin_preview = isset($_POST['admin_preview']) && sanitize_text_field(wp_unslash($_POST['admin_preview'])) === 'true';
        if (!$is_admin_preview && !$this->meets_minimum_advance($date)) {
            wp_send_json_success(['slots' => [], 'debug' => 'min_advance_not_met']);
            return;
        }
        
        $all_slots = $this->generate_time_slots($date, $timeslot_duration, $is_admin_preview);
        
        // Get booked slots and CalDAV conflicts
        $booked_slots = $this->get_booked_slots($date);
        
        // Get CalDAV conflicts directly from server (no local storage)
        $caldav_conflicts = $this->get_caldav_conflicts($date, $all_slots, $timeslot_duration);
        
        // Debug logging for admin preview
        if ($is_admin_preview && defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('=== Admin Preview CalDAV Debug ===');
            $this->debug_log('Date: ' . $date);
            $this->debug_log('All slots: ' . implode(', ', $all_slots));
            $this->debug_log('Booked slots: ' . implode(', ', $booked_slots));
            $this->debug_log('CalDAV conflicts: ' . implode(', ', $caldav_conflicts));
        }
        
        // Calculate available slots
        $available_slots = array_diff($all_slots, $booked_slots, $caldav_conflicts);
        
        // For admin preview, return both available and unavailable slots with status
        if ($is_admin_preview) {
            $slot_data = [];
            
            // Get timezone-aware current time for admin preview
            try {
                $timezone_string = $this->get_timezone_string();
                $timezone = new DateTimeZone($timezone_string);
                $now_datetime = new DateTime('now', $timezone);
                $now = $now_datetime->getTimestamp();
            } catch (Exception $e) {
                $now = current_time('timestamp');
            }
            $today_date = gmdate('Y-m-d', $now);
            
            foreach ($all_slots as $slot) {
                $status = 'available';
                $reason = '';
                
                // Get timezone and working hours once
                try {
                    $timezone_string = $this->get_timezone_string();
                    $timezone = new DateTimeZone($timezone_string);
                } catch (Exception $e) {
                    $timezone = new DateTimeZone('UTC');
                }
                $working_hours_start = get_option('eab_working_hours_start', '09:00');
                $working_hours_end = get_option('eab_working_hours_end', '17:00');
                
                $slot_datetime = new DateTime($date . ' ' . $slot . ':00', $timezone);
                $slot_timestamp = $slot_datetime->getTimestamp();
                
                // Check if slot is in the past - this has highest priority
                if ($slot_timestamp <= $now) {
                    $status = 'past';
                    $reason = 'Past time';
                } else {
                    // Only check other statuses if not in the past
                    
                    // Check if slot is outside working hours
                    $working_start_datetime = new DateTime($date . ' ' . $working_hours_start . ':00', $timezone);
                    $working_end_datetime = new DateTime($date . ' ' . $working_hours_end . ':00', $timezone);
                    
                    if ($slot_timestamp < $working_start_datetime->getTimestamp() || $slot_timestamp >= $working_end_datetime->getTimestamp()) {
                        $status = 'outside_hours';
                        $reason = __('Outside working hours.', 'easy-calendar-appointment-booking');
                    }
                    
                    // Override with booked status if applicable (includes CalDAV conflicts)
                    if (in_array($slot, $booked_slots) || in_array($slot, $caldav_conflicts)) {
                        $status = 'booked';
                        if (in_array($slot, $booked_slots)) {
                            $reason = __('Booked', 'easy-calendar-appointment-booking');
                        } else {
                            $reason = 'Booked (CalDAV conflict)';
                        }
                    }
                }
                
                $slot_data[] = [
                    'time' => $slot,
                    'status' => $status,
                    'reason' => $reason
                ];
            }
            
            wp_send_json_success([
                'slots' => array_values($available_slots),
                'all_slots' => $slot_data,
                'admin_preview' => true
            ]);
        } else {
            wp_send_json_success(['slots' => array_values($available_slots)]);
        }
        
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'An error occurred while loading time slots.']);
        }
    }
    
    private function generate_time_slots(string $date, int $duration, bool $is_admin_preview = false): array {
        $slots = [];
        $working_hours_start = get_option('eab_working_hours_start', '09:00');
        $working_hours_end = get_option('eab_working_hours_end', '17:00');
        
        // Get timezone setting using the plugin's timezone function
        try {
            $timezone_string = $this->get_timezone_string();
            $timezone = new DateTimeZone($timezone_string);
        } catch (Exception $e) {
            $timezone = new DateTimeZone('UTC');
        }
        
        // For admin preview, generate all slots regardless of time
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        if ($is_admin_preview) {
            // For admin preview, generate slots within working hours but show all statuses
            try {
                $timezone_string = $this->get_timezone_string();
                $timezone = new DateTimeZone($timezone_string);
            } catch (Exception $e) {
                $timezone = new DateTimeZone('UTC');
            }
            
            $start_datetime = new DateTime($date . ' ' . $working_hours_start . ':00', $timezone);
            $end_datetime = new DateTime($date . ' ' . $working_hours_end . ':00', $timezone);
            
            $start_time = $start_datetime->getTimestamp();
            $end_time = $end_datetime->getTimestamp();
            
            // Generate slots every duration minutes within working hours
            for ($time = $start_time; $time < $end_time; $time += ($duration * 60)) {
                $slot_datetime = new DateTime('@' . $time);
                $slot_datetime->setTimezone($timezone);
                $slots[] = $slot_datetime->format('H:i');
            }
        } else {
            // Create timezone-aware datetime objects for working hours
            $start_datetime = new DateTime($date . ' ' . $working_hours_start . ':00', $timezone);
            $end_datetime = new DateTime($date . ' ' . $working_hours_end . ':00', $timezone);
            
            $start_time = $start_datetime->getTimestamp();
            $end_time = $end_datetime->getTimestamp();
            
            // For frontend booking, filter out past times and minimum advance
            $min_advance_time = $this->get_minimum_advance_timestamp();
            
            // Get current time in the same timezone
            $now_datetime = new DateTime('now', $timezone);
            $current_time = $now_datetime->getTimestamp();
            
            // Debug logging for time calculations
            $this->debug_log("=== Time Slot Generation Debug ===");
            $this->debug_log("Date: {$date}");
            $this->debug_log("Current time: " . $now_datetime->format('Y-m-d H:i:s T'));
            $this->debug_log("Current timestamp: {$current_time}");
            $this->debug_log("Min advance timestamp: {$min_advance_time}");
            $this->debug_log("Min advance setting: " . get_option('eab_min_booking_advance', '2h'));
            $this->debug_log("Working hours: {$working_hours_start} - {$working_hours_end}");
            $this->debug_log("Timezone: " . $timezone->getName());
            
            for ($time = $start_time; $time < $end_time; $time += ($duration * 60)) {
                // Use timezone-aware formatting
                $slot_datetime = new DateTime('@' . $time);
                $slot_datetime->setTimezone($timezone);
                $slot_time = $slot_datetime->format('H:i');
                
                // Debug logging for time slot filtering
                $this->debug_log("Checking slot: {$slot_time}");
                $this->debug_log("Slot timestamp: {$time}, Current time: {$current_time}, Min advance time: {$min_advance_time}");
                $this->debug_log("Time > current: " . ($time > $current_time ? 'true' : 'false'));
                $this->debug_log("Time >= min advance: " . ($time >= $min_advance_time ? 'true' : 'false'));
                
                // Skip slots that are in the past or don't meet minimum advance time
                if ($time > $current_time && $time >= $min_advance_time) {
                    $slots[] = $slot_time;
                    $this->debug_log("Slot {$slot_time} added to available slots");
                } else {
                    $this->debug_log("Slot {$slot_time} filtered out");
                }
            }
        }
        
        return $slots;
    }
    
    private function get_booked_slots($date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT appointment_time FROM `{$wpdb->prefix}eab_appointments` WHERE appointment_date = %s AND status = 'confirmed'",
            $date
        ));
        
        return $results;
    }
    
    private function get_caldav_conflicts(string $date, array $available_slots, int $duration): array {
        $conflicts = [];
        
        // Check if CalDAV is configured
        $caldav_url = get_option('eab_caldav_url', '');
        $caldav_username = get_option('eab_caldav_username', '');
        $caldav_password = get_option('eab_caldav_password', '');
        
        if (empty($caldav_url) || empty($caldav_username) || empty($caldav_password)) {
            return $conflicts; // CalDAV not configured
        }
        
        // Always fetch fresh data directly from CalDAV server
        $caldav = new EAB_CalDAV();
        $caldav_conflicts_direct = $caldav->get_conflicts($date, $available_slots, $duration);
        return $caldav_conflicts_direct;
    }
    

    
    private function is_working_day($date) {
        $working_days = get_option('eab_working_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'));
        
        // Get timezone setting and calculate day of week in that timezone
        $timezone_string = $this->get_timezone_string();
        $timezone = new DateTimeZone($timezone_string);
        $date_obj = new DateTime($date, $timezone);
        $day_of_week = strtolower($date_obj->format('l'));
        
        $result = in_array($day_of_week, $working_days);
        
        return $result;
    }
    
    private function meets_minimum_advance($date) {
        $min_advance_time = $this->get_minimum_advance_timestamp();
        
        // For same-day bookings, check if any time slots in the day could meet the minimum advance
        // Instead of checking midnight (00:00:00), check the end of the day (23:59:59)
        $date_end_timestamp = strtotime($date . ' 23:59:59');
        
        return $date_end_timestamp >= $min_advance_time;
    }
    
    private function get_minimum_advance_timestamp() {
        $min_booking_advance = get_option('eab_min_booking_advance', '2h');
        
        // TEMPORARY DEBUG: Override to test if advance time is the issue
        // $min_booking_advance = '5min'; // Uncomment to test with 5 minutes advance
        
        // Get timezone setting and current time in that timezone
        $timezone_string = $this->get_timezone_string();
        $timezone = new DateTimeZone($timezone_string);
        $now_datetime = new DateTime('now', $timezone);
        $now = $now_datetime->getTimestamp();
        
        // Debug logging for minimum advance calculation
        $this->debug_log("=== Minimum Advance Calculation ===");
        $this->debug_log("Min booking advance setting: {$min_booking_advance}");
        $this->debug_log("Current time: " . $now_datetime->format('Y-m-d H:i:s T'));
        $this->debug_log("Current timestamp: {$now}");
        
        switch ($min_booking_advance) {
            case '5min':
                $result = $now + (5 * 60); // 5 minutes for testing
                break;
            case '1h':
                $result = $now + (1 * 60 * 60);
                break;
            case '2h':
                $result = $now + (2 * 60 * 60);
                break;
            case '4h':
                $result = $now + (4 * 60 * 60);
                break;
            case 'next_day':
                $result = strtotime('tomorrow', $now);
                break;
            default:
                $result = $now + (2 * 60 * 60); // Default to 2 hours
                break;
        }
        
        $this->debug_log("Min advance timestamp: {$result}");
        $min_advance_datetime = new DateTime('@' . $result);
        $min_advance_datetime->setTimezone($timezone);
        $this->debug_log("Min advance time: " . $min_advance_datetime->format('Y-m-d H:i:s T'));
        
        return $result;
    }
    
    public function book_appointment(): void {
        check_ajax_referer('eab_frontend_nonce', 'nonce');
        
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $appointment_type = isset($_POST['appointment_type']) ? sanitize_text_field(wp_unslash($_POST['appointment_type'])) : ''; // Now clean name only
        $appointment_duration = isset($_POST['appointment_duration']) ? intval(wp_unslash($_POST['appointment_duration'])) : get_option('eab_timeslot_duration', 30);
        
        // Debug logging for appointment duration removed
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
        $time = isset($_POST['time']) ? sanitize_text_field(wp_unslash($_POST['time'])) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
        
        // No need to parse JSON anymore - appointment_type is now clean
        $appointment_type_name = $appointment_type;
        
        // Validate required fields
        if (empty($name) || empty($email) || empty($appointment_type) || empty($date) || empty($time)) {
            wp_send_json_error(['message' => __('Please fill in all required fields.', 'easy-calendar-appointment-booking')]);
        }
        
        // Validate email
        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'easy-calendar-appointment-booking')]);
        }
        
        // Check if slot is still available - check both database and CalDAV conflicts
        $booked_slots = $this->get_booked_slots($date);
        if (in_array($time, $booked_slots)) {
            wp_send_json_error(['message' => __('This time slot is no longer available.', 'easy-calendar-appointment-booking')]);
        }
        
        // Also check for CalDAV conflicts
        $all_slots = [$time]; // We only need to check this specific time slot
        $caldav_conflicts = $this->get_caldav_conflicts($date, $all_slots, $appointment_duration);
        if (in_array($time, $caldav_conflicts)) {
            wp_send_json_error(['message' => __('This time slot conflicts with an existing calendar event.', 'easy-calendar-appointment-booking')]);
        }
        
        // Save appointment
        global $wpdb;
        $table_name = $wpdb->prefix . 'eab_appointments';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert(
            $table_name,
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'appointment_type' => $appointment_type_name,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'notes' => $notes,
                'status' => 'confirmed',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result) {
            // Get the appointment ID
            $appointment_id = $wpdb->insert_id;
            
            // Prepare appointment data for CalDAV and action hook
            $appointment_data = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'appointment_type' => $appointment_type_name,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'appointment_duration' => $appointment_duration,
                'notes' => $notes
            ];
            
            // Create CalDAV event if CalDAV is configured
            $caldav_url = get_option('eab_caldav_url', '');
            $caldav_username = get_option('eab_caldav_username', '');
            $caldav_password = get_option('eab_caldav_password', '');
            
            if (!empty($caldav_url) && !empty($caldav_username) && !empty($caldav_password)) {
                $caldav = new EAB_CalDAV();
                
                $caldav_result = $caldav->create_event($appointment_data);
                
                if (!$caldav_result) {
                    // CalDAV event creation failed but don't fail the booking
                }
            }
            
            // Fire action hook for other plugins/themes to use
            do_action('eab_appointment_booked', $appointment_id, $appointment_data);
            
            // Send confirmation email with ICS attachment
            if (function_exists('eab_send_appointment_confirmation_email')) {
                $email_sent = eab_send_appointment_confirmation_email($appointment_id, $appointment_data);
                if (!$email_sent) {
                    // Email sending failed but don't fail the booking
                }
            }
            
            wp_send_json_success(['message' => __('Appointment booked successfully!', 'easy-calendar-appointment-booking')]);
        } else {
            wp_send_json_error(['message' => __('Error booking appointment. Please try again.', 'easy-calendar-appointment-booking')]);
        }
    }
    
    public function sync_calendar(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'eab_frontend_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'easy-calendar-appointment-booking')]);
            return;
        }
        
        // Check if CalDAV is configured
        $caldav_url = get_option('eab_caldav_url', '');
        $caldav_username = get_option('eab_caldav_username', '');
        $caldav_password = get_option('eab_caldav_password', '');
        
        if (empty($caldav_url) || empty($caldav_username) || empty($caldav_password)) {
            wp_send_json_success(['message' => __('CalDAV not configured, no sync needed.', 'easy-calendar-appointment-booking')]);
            return;
        }
        
        // Initialize CalDAV class
        $caldav = new EAB_CalDAV();
        
        try {
            // Test connection
            if (!$caldav->test_connection()) {
                wp_send_json_error(['message' => __('Failed to connect to CalDAV server.', 'easy-calendar-appointment-booking')]);
                return;
            }
            
            // Since we fetch directly from CalDAV, no sync storage is needed
            wp_send_json_success(['message' => __('CalDAV connection verified. Real-time fetching is active.', 'easy-calendar-appointment-booking')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('CalDAV connection test failed: ', 'easy-calendar-appointment-booking') . $e->getMessage()]);
        }
    }
    
    private function debug_log($message) {
        eab_log($message);
    }
    
    /**
     * Get timezone string with fallback for older WordPress versions
     */
    private function get_timezone_string() {
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
}