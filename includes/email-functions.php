<?php
/**
 * Email functions for Easy Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate a random Jitsi Meet room ID
 *
 * @return string A random 10-character alphanumeric string
 */
function eab_generate_jitsi_room_id() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $room_id = '';
    for ($i = 0; $i < 10; $i++) {
        $room_id .= $characters[wp_rand(0, strlen($characters) - 1)];
    }
    return $room_id;
}

/**
 * Send appointment confirmation email with ICS attachment
 *
 * @param int $appointment_id The appointment ID
 * @param array $appointment_data The appointment data
 * @param bool $is_update Whether this is an update notification
 * @return bool Whether the email was sent successfully
 */
function eab_send_appointment_confirmation_email($appointment_id, $appointment_data, $is_update = false) {
    
    // Get recipient email
    $to = $appointment_data['email'];
    
    // Get site info
    $site_name = get_bloginfo('name');
    $admin_email = get_option('admin_email');
    
    // Format date and time for display
    $formatted_date = eab_format_date($appointment_data['appointment_date']);
    $formatted_time = eab_format_time($appointment_data['appointment_time']);
    
    // Use the Jitsi Meet URL that was already generated and passed in appointment data
    // No need to generate a new one here - it should be consistent across all components
    
    // Set email subject and heading based on whether this is a new booking or an update
    if ($is_update) {
        $subject = __('Your appointment has been updated', 'easy-calendar-appointment-booking');
        $heading = __('Appointment Update', 'easy-calendar-appointment-booking');
        $intro_text = __('Your appointment has been updated with the following details:', 'easy-calendar-appointment-booking');
    } else {
        $subject = __('Your appointment has been confirmed', 'easy-calendar-appointment-booking');
        $heading = __('Appointment Confirmation', 'easy-calendar-appointment-booking');
        $intro_text = __('Your appointment has been confirmed with the following details:', 'easy-calendar-appointment-booking');
    }
    
    // Build email body
    $body = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>";
    $body .= "<div style='background-color: #f8f8f8; padding: 20px; border-bottom: 3px solid " . get_option('eab_theme_color', '#007cba') . ";'>";
    $body .= "<h2 style='color: " . get_option('eab_theme_color', '#007cba') . ";'>" . $heading . "</h2>";
    $body .= "</div>";
    $body .= "<div style='padding: 20px;'>";
    // translators: %s is the customer's name
    $body .= "<p>" . sprintf(__('Dear %s,', 'easy-calendar-appointment-booking'), esc_html($appointment_data['name'])) . "</p>";
    $body .= "<p>" . $intro_text . "</p>";
    $body .= "<div style='background-color: #f8f8f8; padding: 15px; margin: 15px 0; border-left: 4px solid " . get_option('eab_theme_color', '#007cba') . ";'>";
    $body .= "<p><strong>" . __('Appointment details:', 'easy-calendar-appointment-booking') . "</strong></p>";
    $body .= "<p><strong>" . __('Service:', 'easy-calendar-appointment-booking') . "</strong> " . esc_html($appointment_data['appointment_type']) . "</p>";
    $body .= "<p><strong>" . __('Date:', 'easy-calendar-appointment-booking') . "</strong> " . esc_html($formatted_date) . "</p>";
    $body .= "<p><strong>" . __('Time:', 'easy-calendar-appointment-booking') . "</strong> " . esc_html(gmdate('H:i', strtotime($appointment_data['appointment_time']))) . "</p>";
    $duration_minutes = isset($appointment_data['appointment_duration']) ? $appointment_data['appointment_duration'] : get_option('eab_timeslot_duration', 30);
    $body .= "<p><strong>" . __('Duration:', 'easy-calendar-appointment-booking') . "</strong> " . $duration_minutes . " " . __('minutes', 'easy-calendar-appointment-booking') . "</p>";
    $body .= "<p><strong>" . __('Name:', 'easy-calendar-appointment-booking') . "</strong> " . esc_html($appointment_data['name']) . "</p>";
    if (!empty($appointment_data['jitsi_url'])) {
        $body .= "<p><strong>" . __('Video Meeting:', 'easy-calendar-appointment-booking') . "</strong> <a href='" . esc_url($appointment_data['jitsi_url']) . "' target='_blank'>" . esc_html($appointment_data['jitsi_url']) . "</a></p>";
    }
    if (!empty($appointment_data['notes'])) {
        $body .= "<p><strong>" . __('Notes:', 'easy-calendar-appointment-booking') . "</strong> " . esc_html($appointment_data['notes']) . "</p>";
    }
    $body .= "</div>";
    $body .= "<p>" . __('We look forward to seeing you!', 'easy-calendar-appointment-booking') . "</p>";
    $body .= "<p>" . __('You can add this appointment to your calendar using the attached ICS file.', 'easy-calendar-appointment-booking') . "</p>";
    $body .= "<p>" . __('If you need to make any changes to your appointment, please contact us.', 'easy-calendar-appointment-booking') . "</p>";
    $body .= "<p>" . __('Thank you for choosing our services.', 'easy-calendar-appointment-booking') . "</p>";
    $body .= "</div>";
    $body .= "<div style='background-color: #f8f8f8; padding: 15px; font-size: 12px; text-align: center; border-top: 1px solid #ddd;'>";
    // translators: %s is the site name
    $body .= "<p>" . sprintf(__('This is an automated email from %s.', 'easy-calendar-appointment-booking'), $site_name) . "</p>";
    $body .= "</div>";
    $body .= "</div>";
    
    // Get email sender settings
    $sender_name = get_option('eab_email_sender_name', $site_name);
    $sender_email = get_option('eab_email_sender_email', $admin_email);
    
    // Email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $sender_name . ' <' . $sender_email . '>',
        'Reply-To: ' . $admin_email
    );
    
    // Generate ICS file
    $ics_content = eab_generate_ics_file($appointment_data);
    
    // Create temporary file for attachment
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/eab-temp';
    
    // Create directory if it doesn't exist
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    // Create index.php file to prevent directory listing
    if (!file_exists($temp_dir . '/index.php')) {
        file_put_contents($temp_dir . '/index.php', '<?php // Silence is golden');
    }
    
    // Create .htaccess file to prevent direct access
    if (!file_exists($temp_dir . '/.htaccess')) {
        file_put_contents($temp_dir . '/.htaccess', 'deny from all');
    }
    
    // Create temporary ICS file with simple naming
    $filename = 'appointment.ics';
    $filepath = $temp_dir . '/' . $filename;
    file_put_contents($filepath, $ics_content);
    
    // Add attachment
    $attachments = array($filepath);
    
    // Send email
    $sent = wp_mail($to, $subject, $body, $headers, $attachments);
    
    // Delete temporary file
    wp_delete_file($filepath);
    
    // Log email status
    if (!$sent) {
        // Failed to send confirmation email
    }
    
    return $sent;
}

/**
 * Generate ICS file content for an appointment
 *
 * @param array $appointment_data The appointment data
 * @return string The ICS file content
 */
function eab_generate_ics_file($appointment_data) {
    // Get site info
    $site_name = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    
    // Use user's timezone if available, otherwise fall back to site timezone
    $user_timezone = isset($appointment_data['user_timezone']) ? $appointment_data['user_timezone'] : null;
    $timezone_string = $user_timezone ? $user_timezone : eab_get_timezone_string();
    
    // Format date and time for ICS
    $appointment_date = $appointment_data['appointment_date'];
    $appointment_time = $appointment_data['appointment_time'];
    
    // Calculate start and end times using the user's timezone
    $duration_minutes = isset($appointment_data['appointment_duration']) ? $appointment_data['appointment_duration'] : get_option('eab_timeslot_duration', 30);
    
    try {
        // Create timezone object
        $timezone = new DateTimeZone($timezone_string);
        
        // Create datetime objects in the user's timezone
        $start_datetime = new DateTime($appointment_date . ' ' . $appointment_time, $timezone);
        $end_datetime = clone $start_datetime;
        $end_datetime->add(new DateInterval('PT' . $duration_minutes . 'M'));
        
        // Format times for ICS with timezone information
        $start_time_ics = $start_datetime->format('Ymd\THis');
        $end_time_ics = $end_datetime->format('Ymd\THis');
        $use_timezone = true;
    } catch (Exception $e) {
        // Fallback to UTC if timezone fails
        $utc_timezone = new DateTimeZone('UTC');
        $start_datetime = new DateTime($appointment_date . ' ' . $appointment_time, $utc_timezone);
        $end_datetime = clone $start_datetime;
        $end_datetime->add(new DateInterval('PT' . $duration_minutes . 'M'));
        
        $start_time_ics = $start_datetime->format('Ymd\THis\Z');
        $end_time_ics = $end_datetime->format('Ymd\THis\Z');
        $timezone_string = 'UTC';
        $use_timezone = false;
    }
    
    // Create unique identifier
    $uid = 'eab-' . md5($appointment_data['appointment_date'] . $appointment_data['appointment_time'] . $appointment_data['appointment_type']);
    
    // Create timestamp
    $timestamp = gmdate('Ymd\THis\Z');
    
    // appointment_type is now always clean - no JSON parsing needed
    $appointment_type_name = $appointment_data['appointment_type'];
    
    // Build summary and description
    $summary = $site_name . ' - ' . $appointment_type_name . ' - ' . $duration_minutes . ' min';
    $description = __('Appointment details:', 'easy-calendar-appointment-booking') . "\n";
    $description .= "Service: " . $appointment_type_name . "\n";
    $description .= "Date: " . eab_format_date($appointment_data['appointment_date']) . "\n";
    $description .= "Time: " . $appointment_data['appointment_time'] . "\n";
    $description .= "Duration: " . $duration_minutes . " minutes\n";
    $description .= "Name: " . $appointment_data['name'] . "\n";
    $phone = !empty($appointment_data['phone']) ? $appointment_data['phone'] : '---';
    $description .= "Phone: " . $phone . "\n";
    if (!empty($appointment_data['jitsi_url'])) {
        $description .= "Video Meeting: " . $appointment_data['jitsi_url'] . "\n";
    }
    if (!empty($appointment_data['notes'])) {
        $description .= "Notes: " . $appointment_data['notes'] . "\n";
    }
    
    // Build ICS content with timezone support
    $ics_content = "BEGIN:VCALENDAR\r\n";
    $ics_content .= "VERSION:2.0\r\n";
    $ics_content .= "PRODID:-//" . $site_name . "//Easy Calendar Appointment Booking//EN\r\n";
    $ics_content .= "CALSCALE:GREGORIAN\r\n";
    $ics_content .= "METHOD:PUBLISH\r\n";
    
    // Add VTIMEZONE component if using a specific timezone
    if (isset($use_timezone) && $use_timezone && $timezone_string !== 'UTC') {
        $ics_content .= eab_generate_vtimezone($timezone_string);
        $dtstart_value = "DTSTART;TZID=" . $timezone_string . ":" . $start_time_ics;
        $dtend_value = "DTEND;TZID=" . $timezone_string . ":" . $end_time_ics;
    } else {
        // Use UTC format for fallback
        $dtstart_value = "DTSTART:" . $start_time_ics;
        $dtend_value = "DTEND:" . $end_time_ics;
    }
    
    $ics_content .= "BEGIN:VEVENT\r\n";
    $ics_content .= "UID:" . $uid . "@" . wp_parse_url($site_url, PHP_URL_HOST) . "\r\n";
    $ics_content .= "DTSTAMP:" . $timestamp . "\r\n";
    $ics_content .= $dtstart_value . "\r\n";
    $ics_content .= $dtend_value . "\r\n";
    $ics_content .= "SUMMARY:" . eab_ical_escape($summary) . "\r\n";
    $ics_content .= "DESCRIPTION:" . eab_ical_escape($description) . "\r\n";
    $location = !empty($appointment_data['jitsi_url']) ? $appointment_data['jitsi_url'] : "Online Meeting";
    $ics_content .= "LOCATION:" . eab_ical_escape($location) . "\r\n";
    if (!empty($appointment_data['jitsi_url'])) {
        $ics_content .= "URL:" . eab_ical_escape($appointment_data['jitsi_url']) . "\r\n";
    }
    $ics_content .= "STATUS:CONFIRMED\r\n";
    $ics_content .= "END:VEVENT\r\n";
    $ics_content .= "END:VCALENDAR\r\n";
    
    return $ics_content;
}

/**
 * Escape special characters for iCalendar format
 *
 * @param string $text The text to escape
 * @return string The escaped text
 */
function eab_ical_escape($text) {
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\n", "\\n", $text);
    $text = str_replace(",", "\\,", $text);
    $text = str_replace(";", "\\;", $text);
    return $text;
}

/**
 * Generate VTIMEZONE component for ICS file
 *
 * @param string $timezone_string The timezone identifier
 * @return string The VTIMEZONE component
 */
function eab_generate_vtimezone($timezone_string) {
    // For simplicity, we'll generate a basic VTIMEZONE component
    // This covers most common timezones but may not handle all DST transitions perfectly
    
    try {
        $timezone = new DateTimeZone($timezone_string);
        $now = new DateTime('now', $timezone);
        $transitions = $timezone->getTransitions($now->getTimestamp(), $now->getTimestamp() + (365 * 24 * 3600));
        
        $vtimezone = "BEGIN:VTIMEZONE\r\n";
        $vtimezone .= "TZID:" . $timezone_string . "\r\n";
        
        if (!empty($transitions) && count($transitions) > 1) {
            // Handle DST transitions
            $std_transition = null;
            $dst_transition = null;
            
            foreach ($transitions as $transition) {
                if ($transition['isdst']) {
                    $dst_transition = $transition;
                } else {
                    $std_transition = $transition;
                }
            }
            
            // Add STANDARD time
            if ($std_transition) {
                $vtimezone .= "BEGIN:STANDARD\r\n";
                $vtimezone .= "DTSTART:" . gmdate('Ymd\THis', $std_transition['ts']) . "\r\n";
                $vtimezone .= "TZOFFSETFROM:" . eab_format_timezone_offset($dst_transition ? $dst_transition['offset'] : $std_transition['offset']) . "\r\n";
                $vtimezone .= "TZOFFSETTO:" . eab_format_timezone_offset($std_transition['offset']) . "\r\n";
                $vtimezone .= "TZNAME:" . $std_transition['abbr'] . "\r\n";
                $vtimezone .= "END:STANDARD\r\n";
            }
            
            // Add DAYLIGHT time
            if ($dst_transition) {
                $vtimezone .= "BEGIN:DAYLIGHT\r\n";
                $vtimezone .= "DTSTART:" . gmdate('Ymd\THis', $dst_transition['ts']) . "\r\n";
                $vtimezone .= "TZOFFSETFROM:" . eab_format_timezone_offset($std_transition ? $std_transition['offset'] : $dst_transition['offset']) . "\r\n";
                $vtimezone .= "TZOFFSETTO:" . eab_format_timezone_offset($dst_transition['offset']) . "\r\n";
                $vtimezone .= "TZNAME:" . $dst_transition['abbr'] . "\r\n";
                $vtimezone .= "END:DAYLIGHT\r\n";
            }
        } else {
            // No DST, just standard time
            $offset = $timezone->getOffset($now);
            $vtimezone .= "BEGIN:STANDARD\r\n";
            $vtimezone .= "DTSTART:19700101T000000\r\n";
            $vtimezone .= "TZOFFSETFROM:" . eab_format_timezone_offset($offset) . "\r\n";
            $vtimezone .= "TZOFFSETTO:" . eab_format_timezone_offset($offset) . "\r\n";
            $vtimezone .= "END:STANDARD\r\n";
        }
        
        $vtimezone .= "END:VTIMEZONE\r\n";
        
        return $vtimezone;
    } catch (Exception $e) {
        // Fallback: return empty string if timezone generation fails
        return '';
    }
}

/**
 * Format timezone offset for ICS format
 *
 * @param int $offset_seconds The offset in seconds
 * @return string The formatted offset (e.g., +0200, -0500)
 */
function eab_format_timezone_offset($offset_seconds) {
    $hours = intval($offset_seconds / 3600);
    $minutes = abs(($offset_seconds % 3600) / 60);
    
    return sprintf('%+03d%02d', $hours, $minutes);
}