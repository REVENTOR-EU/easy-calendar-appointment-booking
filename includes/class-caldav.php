<?php
/**
 * CalDAV integration for Easy Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EAB_CalDAV {
    
    private $caldav_url = '';
    private $username = '';
    private $password = '';
    
    public function __construct() {
        $this->caldav_url = get_option('eab_caldav_url', '');
        $this->username = get_option('eab_caldav_username', '');
        $this->password = get_option('eab_caldav_password', '');
    }
    
    public function test_connection($url = null, $username = null, $password = null) {
        $test_url = $url ?: $this->caldav_url;
        $test_username = $username ?: $this->username;
        $test_password = $password ?: $this->password;
        
        if (empty($test_url) || empty($test_username) || empty($test_password)) {
            return array('success' => false, 'message' => 'Please fill in all CalDAV fields (URL, username, and password).');
        }
        
        $response = wp_remote_request($test_url, array(
            'method' => 'PROPFIND',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($test_username . ':' . $test_password),
                'Content-Type' => 'application/xml; charset=utf-8',
                'Depth' => '0'
            ),
            'body' => '<?xml version="1.0" encoding="utf-8" ?><D:propfind xmlns:D="DAV:"><D:prop><D:displayname/></D:prop></D:propfind>',
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Connection failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if (in_array($response_code, array(200, 207))) {
            return array('success' => true, 'message' => 'CalDAV connection successful!');
        } elseif ($response_code === 404) {
            return array('success' => true, 'message' => 'CalDAV connection successful! (Calendar endpoint found)');
        } elseif ($response_code === 401) {
            return array('success' => false, 'message' => 'Authentication failed. Please check your username and password.');
        } elseif ($response_code === 403) {
            return array('success' => false, 'message' => 'Access forbidden. Please check your permissions.');
        } else {
            return array('success' => false, 'message' => 'Connection failed with HTTP status: ' . $response_code);
        }
    }
    
    public function get_conflicts($date, $time_slots, $duration) {
        if (empty($this->caldav_url) || empty($this->username) || empty($this->password)) {
            return array();
        }
        
        $conflicts = array();
        $events = $this->get_events_for_date($date);
        
        // Force logging for debugging
        eab_log("CalDAV get_conflicts called for date: $date with " . count($time_slots) . " time slots");
        eab_log("Time slots: " . implode(', ', $time_slots));
        eab_log('Date: ' . $date);
        eab_log('Found ' . count($events) . ' events');
        foreach ($events as $i => $event) {
            eab_log('Event ' . $i . ': ' . gmdate('Y-m-d H:i:s', $event['start']) . ' to ' . gmdate('Y-m-d H:i:s', $event['end']) . ' (' . (isset($event['summary']) ? $event['summary'] : 'No summary') . ')');
        }
        
        if (empty($events)) {
            return $conflicts;
        }
        
        foreach ($time_slots as $slot) {
            $slot_start = strtotime($date . ' ' . $slot);
            $slot_end = $slot_start + ($duration * 60);
            
            // Debug logging for specific slot
            if (defined('WP_DEBUG') && WP_DEBUG && $slot === '16:30') {
                $this->debug_log('Checking slot 16:30: ' . gmdate('Y-m-d H:i:s', $slot_start) . ' to ' . gmdate('Y-m-d H:i:s', $slot_end));
            }
            
            foreach ($events as $event) {
                if ($this->times_overlap($slot_start, $slot_end, $event['start'], $event['end'])) {
                    $conflicts[] = $slot;
                    if (defined('WP_DEBUG') && WP_DEBUG && $slot === '16:30') {
                        $this->debug_log('CONFLICT FOUND for 16:30 with event: ' . gmdate('Y-m-d H:i:s', $event['start']) . ' to ' . gmdate('Y-m-d H:i:s', $event['end']));
                    }
                    break;
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('Total conflicts found: ' . count($conflicts) . ' (' . implode(', ', $conflicts) . ')');
        }
        
        return $conflicts;
    }
    
    private function get_events_for_date($date) {
        $start_date = $date . 'T00:00:00Z';
        $end_date = $date . 'T23:59:59Z';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('Fetching events for date range: ' . $start_date . ' to ' . $end_date);
            $this->debug_log('CalDAV URL: ' . $this->caldav_url);
        }

        $report_body = '<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
  <D:prop>
    <D:getetag />
    <C:calendar-data />
  </D:prop>
  <C:filter>
    <C:comp-filter name="VCALENDAR">
      <C:comp-filter name="VEVENT">
        <C:time-range start="' . $start_date . '" end="' . $end_date . '"/>
      </C:comp-filter>
    </C:comp-filter>
  </C:filter>
</C:calendar-query>';

        $response = wp_remote_request($this->caldav_url, array(
            'method' => 'REPORT',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                'Content-Type' => 'application/xml; charset=utf-8',
                'Depth' => '1'
            ),
            'body' => $report_body,
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->debug_log('Request failed: ' . $response->get_error_message());
            }
            return array();
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('Response code: ' . $response_code);
        }
        
        if ($response_code !== 207) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->debug_log('Unexpected response code: ' . $response_code);
                $body = wp_remote_retrieve_body($response);
                $this->debug_log('Response body length: ' . strlen($body));
                $this->debug_log('Response body preview: ' . substr($body, 0, 500));
            }
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('Response body length: ' . strlen($body));
            $this->debug_log('Response body preview: ' . substr($body, 0, 500));
        }
        
        return $this->parse_calendar_events($body, $date);
    }
    
    private function parse_calendar_events($xml_data, $date) {
        $events = array();
        
        // Simple XML parsing for CalDAV response
        if (empty($xml_data)) {
            return $events;
        }
        
        try {
            // Use DOMDocument for better XML parsing
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            
            // Suppress warnings and check if XML loading was successful
            if (!@$dom->loadXML($xml_data)) {
                $errors = libxml_get_errors();
                // XML parsing failed, continue silently
                libxml_clear_errors();
                return $events;
            }
            
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('D', 'DAV:');
            $xpath->registerNamespace('C', 'urn:ietf:params:xml:ns:caldav');
            
            $calendar_data_nodes = $xpath->query('//C:calendar-data');
            
            if ($calendar_data_nodes === false) {
                // XPath query failed, return empty events
                return $events;
            }
            
            foreach ($calendar_data_nodes as $node) {
                $ical_data = $node->textContent;
                $parsed_events = $this->parse_ical_events($ical_data, $date);
                $events = array_merge($events, $parsed_events);
            }
            
        } catch (Exception $e) {
            // Exception occurred, return current events
            return $events;
        }
        
        return $events;
    }
    
    private function parse_ical_events($ical_data, $date) {
        $events = array();
        
        if (empty($ical_data)) {
            return $events;
        }
        
        try {
            $lines = explode("\n", str_replace("\r\n", "\n", $ical_data));
            $in_event = false;
            $current_event = array();
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                if ($line === 'BEGIN:VEVENT') {
                    $in_event = true;
                    $current_event = array();
                } elseif ($line === 'END:VEVENT' && $in_event) {
                    if (!empty($current_event['start']) && !empty($current_event['end'])) {
                        $events[] = $current_event;
                    }
                    $in_event = false;
                } elseif ($in_event) {
                    if (strpos($line, 'DTSTART') === 0) {
                        $current_event['start'] = $this->parse_ical_datetime($line);
                    } elseif (strpos($line, 'DTEND') === 0) {
                        $current_event['end'] = $this->parse_ical_datetime($line);
                    } elseif (strpos($line, 'SUMMARY') === 0) {
                        $current_event['summary'] = substr($line, 8); // Remove 'SUMMARY:'
                    }
                }
            }
            
        } catch (Exception $e) {
            // Exception occurred, return current events
            return $events;
        }
        
        return $events;
    }
    
    private function parse_ical_datetime($line) {
        try {
            // Extract datetime value from iCal line
            $parts = explode(':', $line, 2);
            if (count($parts) < 2) {
                return null;
            }
            
            $datetime_str = trim($parts[1]);
            
            if (empty($datetime_str)) {
                return null;
            }
            
            $timestamp = null;
            $is_utc = false;
            
            // Handle different datetime formats
            if (strlen($datetime_str) === 8) {
                // Date only format: YYYYMMDD
                $timestamp = strtotime(substr($datetime_str, 0, 4) . '-' . substr($datetime_str, 4, 2) . '-' . substr($datetime_str, 6, 2));
            } elseif (strlen($datetime_str) === 15 && substr($datetime_str, -1) === 'Z') {
                // UTC format: YYYYMMDDTHHMMSSZ
                $date_part = substr($datetime_str, 0, 8);
                $time_part = substr($datetime_str, 9, 6);
                $formatted = substr($date_part, 0, 4) . '-' . substr($date_part, 4, 2) . '-' . substr($date_part, 6, 2) . ' ' .
                            substr($time_part, 0, 2) . ':' . substr($time_part, 2, 2) . ':' . substr($time_part, 4, 2);
                $timestamp = strtotime($formatted . ' UTC');
                $is_utc = true;
            } else {
                // Try to parse as-is
                $timestamp = strtotime($datetime_str);
            }
            
            // Validate the timestamp
            if ($timestamp === false || $timestamp === -1) {
                // Failed to parse datetime, return null
                return null;
            }
            
            // Convert UTC timestamp to local timezone if needed
            if ($is_utc) {
                $local_timezone = $this->get_timezone_string();
                $original_timestamp = $timestamp;
                eab_log('CalDAV timezone conversion - Original UTC timestamp: ' . $timestamp . ' (' . gmdate('Y-m-d H:i:s', $timestamp) . ' UTC)');
                eab_log('CalDAV timezone conversion - Target timezone: ' . $local_timezone);
                try {
                    $utc_datetime = new DateTime('@' . $timestamp);
                    $utc_datetime->setTimezone(new DateTimeZone($local_timezone));
                    $timestamp = $utc_datetime->getTimestamp();
                    eab_log('CalDAV timezone conversion - Converted timestamp: ' . $timestamp . ' (' . $utc_datetime->format('Y-m-d H:i:s T') . ')');
                } catch (Exception $e) {
                    // If timezone conversion fails, keep original timestamp
                    eab_log('CalDAV timezone conversion failed: ' . $e->getMessage());
                }
            }
            
            return $timestamp;
            
        } catch (Exception $e) {
            // Exception occurred, return null
            return null;
        }
    }
    
    private function times_overlap($start1, $end1, $start2, $end2) {
        return ($start1 < $end2) && ($end1 > $start2);
    }
    
    public function create_event($appointment_data) {
        if (empty($this->caldav_url) || empty($this->username) || empty($this->password)) {
            return false;
        }
        
        // Use user's timezone if available, otherwise fall back to site timezone
        $user_timezone = isset($appointment_data['user_timezone']) ? $appointment_data['user_timezone'] : null;
        $timezone_string = $user_timezone ? $user_timezone : $this->get_timezone_string();
        
        try {
            // Create timezone object
            $timezone = new DateTimeZone($timezone_string);
            
            // Create DateTime objects in the user's timezone
            $start_datetime_obj = new DateTime($appointment_data['appointment_date'] . ' ' . $appointment_data['appointment_time'], $timezone);
            $duration = isset($appointment_data['appointment_duration']) ? $appointment_data['appointment_duration'] : get_option('eab_timeslot_duration', 30);
            
            // Calculate end time
            $end_datetime_obj = clone $start_datetime_obj;
            $end_datetime_obj->add(new DateInterval('PT' . $duration . 'M'));
            
            // Format with timezone information
            $start_datetime = $start_datetime_obj->format('Ymd\THis');
            $end_datetime = $end_datetime_obj->format('Ymd\THis');
            $use_timezone = true;
        } catch (Exception $e) {
            // Fallback to UTC if timezone fails
            $utc_timezone = new DateTimeZone('UTC');
            $start_datetime_obj = new DateTime($appointment_data['appointment_date'] . ' ' . $appointment_data['appointment_time'], $utc_timezone);
            $duration = isset($appointment_data['appointment_duration']) ? $appointment_data['appointment_duration'] : get_option('eab_timeslot_duration', 30);
            
            $end_datetime_obj = clone $start_datetime_obj;
            $end_datetime_obj->add(new DateInterval('PT' . $duration . 'M'));
            
            $start_datetime = $start_datetime_obj->format('Ymd\THis\Z');
            $end_datetime = $end_datetime_obj->format('Ymd\THis\Z');
            $timezone_string = 'UTC';
            $use_timezone = false;
        }
        
        // Event creation parameters prepared
        
        $uid = 'eab-' . md5($appointment_data['appointment_date'] . $appointment_data['appointment_time'] . $appointment_data['appointment_type']);
        $summary = $appointment_data['appointment_type'] . ' - ' . $appointment_data['name'];
        
        // Build description with same details as ICS file
        $description = 'Name: ' . $appointment_data['name'] . '\n';
        $description .= 'Email: ' . $appointment_data['email'] . '\n';
        $phone = !empty($appointment_data['phone']) ? $appointment_data['phone'] : '---';
        $description .= 'Phone: ' . $phone . '\n';
        if (!empty($appointment_data['jitsi_url'])) {
            $description .= 'Video Meeting: ' . $appointment_data['jitsi_url'] . '\n';
        }
        $description .= 'Type: ' . $appointment_data['appointment_type'] . '\n';
        $description .= 'Date: ' . $appointment_data['appointment_date'] . '\n';
        $description .= 'Time: ' . $appointment_data['appointment_time'];
        if (!empty($appointment_data['notes'])) {
            $description .= '\nNotes: ' . $appointment_data['notes'];
        }
        
        // Set location to Jitsi URL if available
        $location = !empty($appointment_data['jitsi_url']) ? $appointment_data['jitsi_url'] : 'Online Meeting';
        
        $ical_content = "BEGIN:VCALENDAR\r\n";
        $ical_content .= "VERSION:2.0\r\n";
        $ical_content .= "PRODID:-//REVENTOR.EU//Easy Calendar Appointment Booking//EN\r\n";
        
        // Add VTIMEZONE component if using a specific timezone
        if ($use_timezone && $timezone_string !== 'UTC') {
            $ical_content .= "BEGIN:VTIMEZONE\r\n";
            $ical_content .= "TZID:" . $timezone_string . "\r\n";
            $ical_content .= "END:VTIMEZONE\r\n";
        }
        
        $ical_content .= "BEGIN:VEVENT\r\n";
        $ical_content .= "UID:" . $uid . "\r\n";
        
        // Use timezone-aware DTSTART/DTEND if timezone is specified
        if ($use_timezone && $timezone_string !== 'UTC') {
            $ical_content .= "DTSTART;TZID=" . $timezone_string . ":" . $start_datetime . "\r\n";
            $ical_content .= "DTEND;TZID=" . $timezone_string . ":" . $end_datetime . "\r\n";
        } else {
            $ical_content .= "DTSTART:" . $start_datetime . "\r\n";
            $ical_content .= "DTEND:" . $end_datetime . "\r\n";
        }
        
        $ical_content .= "SUMMARY:" . $summary . "\r\n";
        $ical_content .= "DESCRIPTION:" . $description . "\r\n";
        $ical_content .= "LOCATION:" . $location . "\r\n";
        if (!empty($appointment_data['jitsi_url'])) {
            $ical_content .= "URL:" . $appointment_data['jitsi_url'] . "\r\n";
        }
        $ical_content .= "STATUS:CONFIRMED\r\n";
        $ical_content .= "END:VEVENT\r\n";
        $ical_content .= "END:VCALENDAR\r\n";
        
        $event_url = rtrim($this->caldav_url, '/') . '/' . $uid . '.ics';
        
        $response = wp_remote_request($event_url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                'Content-Type' => 'text/calendar; charset=utf-8'
            ),
            'body' => $ical_content,
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return in_array($response_code, array(200, 201, 204));
    }
    
    public function update_event($appointment_data, $old_appointment_data = null) {
        if (empty($this->caldav_url) || empty($this->username) || empty($this->password)) {
            return false;
        }
        
        // For updates, we need to delete the old event and create a new one
        // since we don't store the original UID
        if ($old_appointment_data) {
            $this->delete_event($old_appointment_data);
        }
        
        return $this->create_event($appointment_data);
    }
    
    public function delete_event($appointment_data) {
        if (empty($this->caldav_url) || empty($this->username) || empty($this->password)) {
            return false;
        }
        
        // Since we don't store the original UID, we need to find and delete the event
        // by searching for events on the appointment date and matching the details
        $events = $this->get_events_for_date($appointment_data['appointment_date']);
        
        foreach ($events as $event) {
            // Check if this event matches our appointment
            $event_time = gmdate('H:i', $event['start']);
            if ($event_time === $appointment_data['appointment_time'] && 
                strpos($event['summary'], $appointment_data['appointment_type']) !== false) {
                
                // Extract UID from the event (this is a simplified approach)
                $uid = 'eab-' . md5($appointment_data['appointment_date'] . $appointment_data['appointment_time'] . $appointment_data['appointment_type']);
                $event_url = rtrim($this->caldav_url, '/') . '/' . $uid . '.ics';
                
                $response = wp_remote_request($event_url, array(
                    'method' => 'DELETE',
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)
                    ),
                    'timeout' => 10
                ));
                
                if (!is_wp_error($response)) {
                    $response_code = wp_remote_retrieve_response_code($response);
                    return in_array($response_code, array(200, 204, 404)); // 404 is OK if already deleted
                }
            }
        }
        
        return false;
    }
    
    public function fetch_events($start_date = null, $end_date = null) {
        if (!$this->test_connection()) {
            return false;
        }
        
        // Default to current month if no dates provided
        if (!$start_date) {
            $start_date = gmdate('Y-m-01');
        }
        if (!$end_date) {
            $end_date = gmdate('Y-m-t', strtotime('+2 months'));
        }
        
        $events = array();
        
        try {
            $ical_data = $this->fetch_calendar_data($start_date, $end_date);
            if ($ical_data) {
                $events = $this->parse_ical_events($ical_data, $start_date);
            }
        } catch (Exception $e) {
            return false;
        }
        
        return $events;
    }
    
    // Note: sync_events method removed - we now fetch directly from CalDAV server
    // This eliminates the need for local storage and ensures real-time conflict checking
    
    /**
     * Custom debug logging to plugin-specific log file
     * Function disabled - no CalDAV logging performed
     */
    private function debug_log($message) {
        // Function disabled to prevent CalDAV debug logging
        return;
    }
    
    /**
     * Get booked time slots for a specific date from CalDAV calendar
     */
    public function get_booked_slots_for_date($date) {
        $booked_slots = array();
        $events = $this->get_events_for_date($date);
        
        foreach ($events as $event) {
            // Convert event start time to local time slot format (H:i)
            $local_start = $this->convert_utc_to_local($event['start']);
            $slot_time = gmdate('H:i', $local_start);
            $booked_slots[] = $slot_time;
        }
        
        return array_unique($booked_slots);
    }
    
    /**
     * Convert UTC timestamp to local timezone
     */
    private function convert_utc_to_local($utc_timestamp) {
        $timezone_string = $this->get_timezone_string();
        
        try {
            $utc_date = new DateTime('@' . $utc_timestamp);
            $local_timezone = new DateTimeZone($timezone_string);
            $utc_date->setTimezone($local_timezone);
            return $utc_date->getTimestamp();
        } catch (Exception $e) {
            // If conversion fails, return original timestamp
            return $utc_timestamp;
        }
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