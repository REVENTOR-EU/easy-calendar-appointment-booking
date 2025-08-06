<?php
/**
 * Database functionality for Easy Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EAB_Database {
    
    public function __construct() {
        // Constructor can be used for future database operations
    }
    

    
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20),
            appointment_type varchar(100) NOT NULL,
            appointment_date date NOT NULL,
            appointment_time time NOT NULL,
            notes text,
            status varchar(20) DEFAULT 'confirmed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY appointment_date (appointment_date),
            KEY appointment_time (appointment_time),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create appointments view table for admin
        $view_table_name = $wpdb->prefix . 'eab_appointment_views';
        
        $view_sql = "CREATE TABLE $view_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            appointment_id mediumint(9) NOT NULL,
            view_date datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id)
        ) $charset_collate;";
        
        dbDelta($view_sql);
    }
    
    public static function get_appointments($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => '',
            'date_to' => '',
            'status' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'appointment_date',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'appointment_date >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'appointment_date <= %s';
            $where_values[] = $args['date_to'];
        }
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'appointment_date ASC';
        }
        
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        
        if (!empty($where_values)) {
            $where_values[] = $limit;
            $where_values[] = $offset;
            $sql = "SELECT * FROM `{$wpdb->prefix}eab_appointments` WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->get_results($wpdb->prepare($sql, $where_values));
        } else {
            $sql = "SELECT * FROM `{$wpdb->prefix}eab_appointments` WHERE 1=1 ORDER BY {$orderby} LIMIT %d OFFSET %d";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
        }
        
        return $result;
    }
    
    public static function get_appointment($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        
        $sql = "SELECT * FROM `{$wpdb->prefix}eab_appointments` WHERE id = %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_row($wpdb->prepare($sql, $id));
    }
    
    public static function update_appointment($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        
        $allowed_fields = array(
            'name', 'email', 'phone', 'appointment_type',
            'appointment_date', 'appointment_time', 'notes', 'status'
        );
        
        $update_data = array();
        $update_format = array();
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $update_data[$key] = $value;
                $update_format[] = '%s';
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        // Get the old appointment data for CalDAV update
        $old_appointment = self::get_appointment($id);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $id),
            $update_format,
            array('%d')
        );
        
        if ($result !== false) {
            // Update CalDAV event if CalDAV is configured
            $caldav_url = get_option('eab_caldav_url', '');
            $caldav_username = get_option('eab_caldav_username', '');
            $caldav_password = get_option('eab_caldav_password', '');
            
            if (!empty($caldav_url) && !empty($caldav_username) && !empty($caldav_password)) {
                $caldav = new EAB_CalDAV();
                
                // Get the updated appointment data
                $updated_appointment = self::get_appointment($id);
                
                // Parse appointment type to handle both old JSON data and new clean data (backward compatibility)
                $appointment_type_name = $updated_appointment->appointment_type;
                $appointment_data_parsed = json_decode($updated_appointment->appointment_type, true);
                if (is_array($appointment_data_parsed) && isset($appointment_data_parsed['name'])) {
                    $appointment_type_name = $appointment_data_parsed['name'];
                }
                
                $new_appointment_data = array(
                    'name' => $updated_appointment->name,
                    'email' => $updated_appointment->email,
                    'phone' => $updated_appointment->phone,
                    'appointment_type' => $appointment_type_name,
                    'appointment_date' => $updated_appointment->appointment_date,
                    'appointment_time' => $updated_appointment->appointment_time,
                    'notes' => $updated_appointment->notes
                );
                
                // Parse old appointment type as well
                $old_appointment_type_name = $old_appointment->appointment_type;
                $old_appointment_data_parsed = json_decode($old_appointment->appointment_type, true);
                if (is_array($old_appointment_data_parsed) && isset($old_appointment_data_parsed['name'])) {
                    $old_appointment_type_name = $old_appointment_data_parsed['name'];
                }
                
                $old_appointment_data = array(
                    'name' => $old_appointment->name,
                    'email' => $old_appointment->email,
                    'phone' => $old_appointment->phone,
                    'appointment_type' => $old_appointment_type_name,
                    'appointment_date' => $old_appointment->appointment_date,
                    'appointment_time' => $old_appointment->appointment_time,
                    'notes' => $old_appointment->notes
                );
                
                $caldav_result = $caldav->update_event($new_appointment_data, $old_appointment_data);
                
                if (!$caldav_result) {
                    // Failed to update CalDAV event
                }
            }
            
            // Fire action hook for other plugins/themes to use
            do_action('eab_appointment_updated', $id, $update_data, $old_appointment);
            
            // Send update notification email with updated ICS attachment
            if (function_exists('eab_send_appointment_confirmation_email')) {
                $email_sent = eab_send_appointment_confirmation_email($id, $new_appointment_data, true);
                if (!$email_sent) {
                    // Log the email error but don't fail the update
                    // Failed to send update confirmation email
                }
            }
        }
        
        return $result;
    }
    
    public static function delete_appointment($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        
        // Get the appointment data before deletion for CalDAV
        $appointment = self::get_appointment($id);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result !== false && $appointment) {
            // Delete CalDAV event if CalDAV is configured
            $caldav_url = get_option('eab_caldav_url', '');
            $caldav_username = get_option('eab_caldav_username', '');
            $caldav_password = get_option('eab_caldav_password', '');
            
            if (!empty($caldav_url) && !empty($caldav_username) && !empty($caldav_password)) {
                $caldav = new EAB_CalDAV();
                
                // Parse appointment type to handle both old JSON data and new clean data
                $appointment_type_name = $appointment->appointment_type;
                $appointment_data_parsed = json_decode($appointment->appointment_type, true);
                if (is_array($appointment_data_parsed) && isset($appointment_data_parsed['name'])) {
                    $appointment_type_name = $appointment_data_parsed['name'];
                }
                
                $appointment_data = array(
                    'name' => $appointment->name,
                    'email' => $appointment->email,
                    'phone' => $appointment->phone,
                    'appointment_type' => $appointment_type_name,
                    'appointment_date' => $appointment->appointment_date,
                    'appointment_time' => $appointment->appointment_time,
                    'notes' => $appointment->notes
                );
                
                $caldav_result = $caldav->delete_event($appointment_data);
                
                if (!$caldav_result) {
                    // Failed to delete CalDAV event
                }
            }
            
            // Fire action hook for other plugins/themes to use
            do_action('eab_appointment_deleted', $id, $appointment);
        }
        
        return $result;
    }
    
    public static function get_appointments_count($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => '',
            'date_to' => '',
            'status' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'appointment_date >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'appointment_date <= %s';
            $where_values[] = $args['date_to'];
        }
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        
        if (!empty($where_values)) {
            $sql = "SELECT COUNT(*) FROM `{$wpdb->prefix}eab_appointments` WHERE {$where_clause}";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->get_var($wpdb->prepare($sql, $where_values));
        } else {
            $sql = "SELECT COUNT(*) FROM `{$wpdb->prefix}eab_appointments` WHERE 1=1";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->get_var($sql);
        }
        
        return $result;
    }
    
    public static function get_daily_stats($date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'eab_appointments';
        
        $stats = array(
            'total' => 0,
            'confirmed' => 0,
            'cancelled' => 0
        );
        
        $sql = "SELECT status, COUNT(*) as count FROM `{$wpdb->prefix}eab_appointments` WHERE appointment_date = %s GROUP BY status";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($wpdb->prepare($sql, $date));
        
        foreach ($results as $result) {
            $stats['total'] += $result->count;
            $stats[$result->status] = $result->count;
        }
        
        return $stats;
    }
}