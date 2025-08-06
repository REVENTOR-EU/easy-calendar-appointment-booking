<?php
/**
 * Booking form template for Easy Calendar Appointment Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$settings = eab_get_settings();
$appointment_types = $settings['appointment_types'];
$available_dates = eab_get_available_dates();

// Handle backward compatibility for appointment types
if (!empty($appointment_types) && is_string($appointment_types[0])) {
    // Convert old format (array of strings) to new format (array of arrays)
    $converted_types = array();
    foreach ($appointment_types as $type) {
        $converted_types[] = array(
            'name' => $type,
            'duration' => 30 // Default duration
        );
    }
    $appointment_types = $converted_types;
}
?>

<div class="eab-booking-form" id="eab-booking-form">
    <div class="eab-form-header">
        <h3><?php esc_html_e('Book Your Appointment', 'easy-calendar-appointment-booking'); ?></h3>
        <div class="eab-progress-container">
            <div class="eab-progress-bar" id="eab-progress-bar"></div>
        </div>
    </div>
    
    <div class="eab-steps-indicator">
        <div class="eab-step active" data-step="1">
            <div class="eab-step-number">1</div>
            <div class="eab-step-label"><?php esc_html_e('Service', 'easy-calendar-appointment-booking'); ?></div>
        </div>
        <div class="eab-step" data-step="2">
            <div class="eab-step-number">2</div>
            <div class="eab-step-label"><?php esc_html_e('Date & Time', 'easy-calendar-appointment-booking'); ?></div>
        </div>
        <div class="eab-step" data-step="3">
            <div class="eab-step-number">3</div>
            <div class="eab-step-label"><?php esc_html_e('Your Details', 'easy-calendar-appointment-booking'); ?></div>
        </div>
    </div>
    
    <form id="eab-appointment-form" class="eab-form">
        <?php wp_nonce_field('eab_frontend_nonce', 'eab_frontend_nonce'); ?>
        <input type="hidden" name="appointment_duration" id="appointment_duration" value="<?php echo esc_attr($settings['timeslot_duration']); ?>" />
        
        <!-- Step 1: Service Selection -->
        <div class="eab-form-step active" id="step-1">
            <div class="eab-step-content">
                <h4><?php esc_html_e('Select Appointment Type', 'easy-calendar-appointment-booking'); ?></h4>
                <div class="eab-service-grid">
                    <?php foreach ($appointment_types as $index => $type): ?>
                        <?php 
                        $type_name = is_array($type) ? $type['name'] : $type;
                        $type_duration = is_array($type) ? $type['duration'] : $settings['timeslot_duration'];
                        // Store only the clean name, not JSON
                        $type_value = $type_name;
                        ?>
                    <label class="eab-service-option">
                        <input type="radio" name="appointment_type" value="<?php echo esc_attr($type_value); ?>" data-duration="<?php echo esc_attr($type_duration); ?>" <?php echo $index === 0 ? 'checked' : ''; ?> />
                        <div class="eab-service-card">
                            <div class="eab-service-icon">
                                <span class="dashicons dashicons-calendar-alt"></span>
                            </div>
                            <div class="eab-service-name"><?php echo esc_html($type_name); ?></div>
                            <div class="eab-service-duration"><?php echo esc_html($type_duration); ?> <?php esc_html_e('minutes', 'easy-calendar-appointment-booking'); ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="eab-step-navigation">
                <button type="button" class="eab-btn eab-btn-primary eab-next-step">
                    <?php esc_html_e('Next', 'easy-calendar-appointment-booking'); ?>
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                </button>
            </div>
        </div>
        
        <!-- Step 2: Date & Time Selection -->
        <div class="eab-form-step" id="step-2">
            <div class="eab-step-content">
                <h4><?php esc_html_e('Select Date & Time', 'easy-calendar-appointment-booking'); ?></h4>
                
                <div class="eab-datetime-container">
                    <div class="eab-date-selection">
                        <label for="appointment_date"><?php esc_html_e('Choose Date', 'easy-calendar-appointment-booking'); ?></label>
                        <select id="appointment_date" name="appointment_date" required>
                            <?php foreach ($available_dates as $index => $date): ?>
                            <option value="<?php echo esc_attr($date['value']); ?>" <?php echo $index === 0 ? 'selected' : ''; ?>><?php echo esc_html($date['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="eab-time-selection">
                        <label><?php esc_html_e('Available Times', 'easy-calendar-appointment-booking'); ?></label>
                        <div id="time-slots-container" class="eab-time-slots">
                            <div class="eab-no-date-selected">
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e('Please select a date first', 'easy-calendar-appointment-booking'); ?>
                            </div>
                        </div>
                        <input type="hidden" id="appointment_time" name="appointment_time" required />
                    </div>
                </div>
            </div>
            <div class="eab-step-navigation">
                <button type="button" class="eab-btn eab-btn-secondary eab-prev-step">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php esc_html_e('Previous', 'easy-calendar-appointment-booking'); ?>
                </button>
                <button type="button" class="eab-btn eab-btn-primary eab-next-step" disabled>
                    <?php esc_html_e('Next', 'easy-calendar-appointment-booking'); ?>
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                </button>
            </div>
        </div>
        
        <!-- Step 3: Personal Information -->
        <div class="eab-form-step" id="step-3">
            <div class="eab-step-content">
                <h4><?php esc_html_e('Your Information', 'easy-calendar-appointment-booking'); ?></h4>
                
                <div class="eab-form-grid">
                    <div class="eab-form-group">
                        <label for="customer_name"><?php esc_html_e('Full Name', 'easy-calendar-appointment-booking'); ?> <span class="required">*</span></label>
                        <input type="text" id="customer_name" name="name" required />
                    </div>
                    
                    <div class="eab-form-group">
                        <label for="customer_email"><?php esc_html_e('Email Address', 'easy-calendar-appointment-booking'); ?> <span class="required">*</span></label>
                        <input type="email" id="customer_email" name="email" required />
                    </div>
                    
                    <div class="eab-form-group">
                        <label for="customer_phone"><?php esc_html_e('Phone Number', 'easy-calendar-appointment-booking'); ?></label>
                        <input type="tel" id="customer_phone" name="phone" />
                    </div>
                    
                    <div class="eab-form-group eab-form-group-full">
                        <label for="customer_notes"><?php esc_html_e('Additional Notes', 'easy-calendar-appointment-booking'); ?></label>
					<textarea id="customer_notes" name="notes" rows="4" placeholder="<?php esc_attr_e('Any additional information or special requests...', 'easy-calendar-appointment-booking'); ?>"></textarea>
                    </div>
                </div>
            </div>
            <div class="eab-step-navigation">
                <button type="button" class="eab-btn eab-btn-secondary eab-prev-step">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php esc_html_e('Previous', 'easy-calendar-appointment-booking'); ?>
                </button>
                <button type="submit" class="eab-btn eab-btn-primary eab-submit-btn">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Book Appointment', 'easy-calendar-appointment-booking'); ?>
                </button>
            </div>
        </div>
        

        
        <!-- Success Message -->
        <div class="eab-form-step" id="step-success" style="display: none;">
            <div class="eab-success-content">
                <div class="eab-success-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h4><?php esc_html_e('Appointment Booked Successfully!', 'easy-calendar-appointment-booking'); ?></h4>
				<p><?php esc_html_e('Thank you for booking with us. You will receive a confirmation email shortly.', 'easy-calendar-appointment-booking'); ?></p>
                <button type="button" class="eab-btn eab-btn-primary" onclick="location.reload();">
                    <?php esc_html_e('Book Another Appointment', 'easy-calendar-appointment-booking'); ?>
                </button>
            </div>
        </div>
    </form>
    
    <!-- Loading Overlay -->
    <div class="eab-loading-overlay" id="eab-loading-overlay" style="display: none;">
        <div class="eab-loading-spinner">
            <div class="eab-spinner"></div>
            <p><?php esc_html_e('Processing your appointment...', 'easy-calendar-appointment-booking'); ?></p>
        </div>
    </div>
    
    <!-- Branding -->
    <div class="eab-branding">
        <p><?php esc_html_e('Powered by', 'easy-calendar-appointment-booking'); ?> <a href="https://kutt.it/wp-plugin" target="_blank" rel="noopener noreferrer" class="eab-branding-link"><?php esc_html_e('Easy Calendar Appointment Booking', 'easy-calendar-appointment-booking'); ?></a> - <?php esc_html_e('Get your own booking system!', 'easy-calendar-appointment-booking'); ?></p>
    </div>
</div>