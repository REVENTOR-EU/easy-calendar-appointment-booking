<?php
/**
 * Email functions for Easy Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
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
    
    // Set email subject and heading based on whether this is a new booking or an update
    if ($is_update) {
        // translators: %s is the site name
        $subject = sprintf(__('Your appointment with %s has been updated', 'easy-calendar-appointment-booking'), $site_name);
        $heading = __('Appointment Update', 'easy-calendar-appointment-booking');
        $intro_text = __('Your appointment has been updated with the following details:', 'easy-calendar-appointment-booking');
    } else {
        // translators: %s is the site name
        $subject = sprintf(__('Your appointment with %s has been confirmed', 'easy-calendar-appointment-booking'), $site_name);
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
    $body .= "<p><strong>Appointment details:</strong></p>";
    $body .= "<p><strong>" . __('Service:', 'easy-calendar-appointment-booking') . "</strong> " . esc_html($appointment_data['appointment_type']) . "</p>";
    $body .= "<p><strong>" . __('Date:', 'easy-calendar-appointment-booking') . "</strong> " . esc_html($formatted_date) . "</p>";
    $body .= "<p><strong>" . __('Time:', 'easy-calendar-appointment-booking') . "</strong> " . esc_html(gmdate('H:i', strtotime($appointment_data['appointment_time']))) . "</p>";
    $duration_minutes = isset($appointment_data['appointment_duration']) ? $appointment_data['appointment_duration'] : get_option('eab_timeslot_duration', 30);
    $body .= "<p><strong>" . __('Duration:', 'easy-calendar-appointment-booking') . "</strong> " . $duration_minutes . " " . __('minutes', 'easy-calendar-appointment-booking') . "</p>";
    $body .= "<p><strong>" . __('Name:', 'easy-calendar-appointment-booking') . "</strong> " . esc_html($appointment_data['name']) . "</p>";
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
    
    // Format date and time for ICS
    $appointment_date = $appointment_data['appointment_date'];
    $appointment_time = $appointment_data['appointment_time'];
    
    // Calculate start and end times
    $start_datetime = new DateTime($appointment_date . ' ' . $appointment_time);
    $duration_minutes = isset($appointment_data['appointment_duration']) ? $appointment_data['appointment_duration'] : get_option('eab_timeslot_duration', 30);
    $end_datetime = clone $start_datetime;
    $end_datetime->add(new DateInterval('PT' . $duration_minutes . 'M'));
    
    // Format times for ICS
    $start_time_utc = gmdate('Ymd\THis\Z', $start_datetime->getTimestamp());
    $end_time_utc = gmdate('Ymd\THis\Z', $end_datetime->getTimestamp());
    
    // Create unique identifier
    $uid = 'eab-' . md5($appointment_data['appointment_date'] . $appointment_data['appointment_time'] . $appointment_data['appointment_type']);
    
    // Create timestamp
    $timestamp = gmdate('Ymd\THis\Z');
    
    // appointment_type is now always clean - no JSON parsing needed
    $appointment_type_name = $appointment_data['appointment_type'];
    
    // Build summary and description
    $summary = $site_name . ' - ' . $appointment_type_name . ' - ' . $duration_minutes . ' min';
    $description = "Appointment details:\n";
    $description .= "Service: " . $appointment_type_name . "\n";
    $description .= "Date: " . eab_format_date($appointment_data['appointment_date']) . "\n";
    $description .= "Time: " . gmdate('H:i', strtotime($appointment_data['appointment_time'])) . "\n";
    $description .= "Duration: " . $duration_minutes . " minutes\n";
    $description .= "Name: " . $appointment_data['name'] . "\n";
    if (!empty($appointment_data['notes'])) {
        $description .= "Notes: " . $appointment_data['notes'] . "\n";
    }
    
    // Build ICS content
    $ics_content = "BEGIN:VCALENDAR\r\n";
    $ics_content .= "VERSION:2.0\r\n";
    $ics_content .= "PRODID:-//" . $site_name . "//Easy Calendar Appointment Booking//EN\r\n";
    $ics_content .= "CALSCALE:GREGORIAN\r\n";
    $ics_content .= "METHOD:PUBLISH\r\n";
    $ics_content .= "BEGIN:VEVENT\r\n";
    $ics_content .= "UID:" . $uid . "@" . wp_parse_url($site_url, PHP_URL_HOST) . "\r\n";
    $ics_content .= "DTSTAMP:" . $timestamp . "\r\n";
    $ics_content .= "DTSTART:" . $start_time_utc . "\r\n";
    $ics_content .= "DTEND:" . $end_time_utc . "\r\n";
    $ics_content .= "SUMMARY:" . eab_ical_escape($summary) . "\r\n";
    $ics_content .= "DESCRIPTION:" . eab_ical_escape($description) . "\r\n";
    $ics_content .= "LOCATION:" . eab_ical_escape("Online Meeting") . "\r\n";
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