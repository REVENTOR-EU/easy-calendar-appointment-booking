/**
 * Frontend JavaScript for Easy Calendar Appointment Booking
 */

(function($) {
    'use strict';
    
    let currentStep = 1;
    let totalSteps = 3;
    let selectedTimeSlot = null;
    let formData = {};
    
    $(document).ready(function() {
        initializeBookingForm();
    });
    
    function initializeBookingForm() {
        // Initialize step navigation
        initStepNavigation();
        
        // Initialize date selection
        initDateSelection();
        
        // Initialize time slot selection
        initTimeSlotSelection();
        
        // Initialize form validation
        initFormValidation();
        
        // Initialize form submission
        initFormSubmission();
        
        // Update progress bar
        updateProgressBar();
        
        // Initialize accessibility features
        initAccessibility();
    }
    
    function initStepNavigation() {
        // Handle appointment type selection
        $('input[name="appointment_type"]').on('change', function() {
            var duration = $(this).data('duration');
            $('#appointment_duration').val(duration);
            
            // Show sync indicator and trigger calendar sync when service is selected
            showServiceSyncIndicator();
            performInitialCalendarSync().then(function() {
                hideServiceSyncIndicator();
            });
        });
        
        // Next step buttons
        $('.eab-next-step').on('click', function() {
            if (validateCurrentStep()) {
                nextStep();
            }
        });
        
        // Previous step buttons
        $('.eab-prev-step').on('click', function() {
            prevStep();
        });
        
        // Step indicator clicks
        $('.eab-step').on('click', function() {
            const targetStep = parseInt($(this).data('step'));
            if (targetStep < currentStep) {
                goToStep(targetStep);
            }
        });
    }
    
    function nextStep() {
        if (currentStep < totalSteps) {
            currentStep++;
            updateStepDisplay();
            
            // No confirmation step needed anymore
        }
    }
    
    function prevStep() {
        if (currentStep > 1) {
            currentStep--;
            updateStepDisplay();
        }
    }
    
    function goToStep(step) {
        if (step >= 1 && step <= totalSteps) {
            currentStep = step;
            updateStepDisplay();
        }
    }
    
    function updateStepDisplay() {
        // Hide all steps
        $('.eab-form-step').removeClass('active');
        
        // Show current step
        $('#step-' + currentStep).addClass('active');
        
        // Update step indicators
        $('.eab-step').removeClass('active completed');
        
        $('.eab-step').each(function() {
            const stepNum = parseInt($(this).data('step'));
            if (stepNum < currentStep) {
                $(this).addClass('completed');
            } else if (stepNum === currentStep) {
                $(this).addClass('active');
            }
        });
        
        // Update progress bar
        updateProgressBar();
        
        // Focus management
        $('#step-' + currentStep).find('input, select, button').first().focus();
    }
    
    function updateProgressBar() {
        const progress = (currentStep / totalSteps) * 100;
        $('.eab-progress-bar').css('width', progress + '%');
    }
    
    function validateCurrentStep() {
        switch (currentStep) {
            case 1:
                return validateServiceSelection();
            case 2:
                return validateDateTimeSelection();
            case 3:
                return validatePersonalInfo();
            default:
                return true;
        }
    }
    
    function validateServiceSelection() {
        const selectedServiceInput = $('input[name="appointment_type"]:checked');
        if (selectedServiceInput.length === 0) {
            showError(eab_frontend.strings.required_fields || 'Please select an appointment type.');
            return false;
        }
        
        const selectedService = selectedServiceInput.val();
        const selectedDuration = selectedServiceInput.data('duration') || 30;
        
        formData.appointmentType = selectedService;
        formData.appointmentTypeName = selectedService;
        formData.appointmentDuration = selectedDuration;
        
        return true;
    }
    
    function validateDateTimeSelection() {
        const selectedDate = $('#appointment_date').val();
        const selectedTime = $('#appointment_time').val();
        
        if (!selectedDate) {
            showError('Please select a date.');
            return false;
        }
        
        if (!selectedTime) {
            showError('Please select a time slot.');
            return false;
        }
        
        formData.appointmentDate = selectedDate;
        formData.appointmentTime = selectedTime;
        return true;
    }
    
    function validatePersonalInfo() {
        const name = $('#customer_name').val().trim();
        const email = $('#customer_email').val().trim();
        
        if (!name) {
            showError('Please enter your full name.');
            $('#customer_name').focus();
            return false;
        }
        
        if (!email) {
            showError('Please enter your email address.');
            $('#customer_email').focus();
            return false;
        }
        
        if (!isValidEmail(email)) {
            showError(eab_frontend.strings.invalid_email || 'Please enter a valid email address.');
            $('#customer_email').focus();
            return false;
        }
        
        formData.name = name;
        formData.email = email;
        formData.phone = $('#customer_phone').val().trim();
        formData.notes = $('#customer_notes').val().trim();
        
        return true;
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function initDateSelection() {
        $('#appointment_date').on('change', function() {
            const selectedDate = $(this).val();
            if (selectedDate) {
                loadTimeSlots(selectedDate);
            } else {
                clearTimeSlots();
            }
        });
        
        // Auto-load time slots for the preselected first date (calendar sync already done)
        const preselectedDate = $('#appointment_date').val();
        if (preselectedDate) {
            loadTimeSlots(preselectedDate);
        }
    }
    
    function loadTimeSlots(date) {
        const container = $('#time-slots-container');
        const selectedServiceInput = $('input[name="appointment_type"]:checked');
        const appointmentType = selectedServiceInput.val();
        const appointmentDuration = selectedServiceInput.data('duration') || 30;
        
        // Show loading state
        container.html('<div class="eab-loading-slots"><div class="eab-spinner"></div><p>' + (eab_frontend.strings.loading || 'Loading available times...') + '</p></div>');
        
        $.ajax({
            url: eab_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'eab_get_available_slots',
                nonce: eab_frontend.nonce,
                date: date,
                appointment_type: appointmentType,
                duration: appointmentDuration
            },
            success: function(response) {
                // Debug logging
                console.log('EAB AJAX Response:', response);
                console.log('Response success:', response.success);
                console.log('Response data:', response.data);
                
                if (response.success && response.data && response.data.slots && response.data.slots.length > 0) {
                    console.log('Found', response.data.slots.length, 'time slots:', response.data.slots);
                    displayTimeSlots(response.data.slots);
                } else {
                    console.log('No slots available. Response success:', response.success);
                    if (response.data) {
                        console.log('Response data slots:', response.data.slots);
                        console.log('Response data debug:', response.data.debug);
                    }
                    showNoSlotsMessage();
                }
            },
            error: function(xhr, status, error) {
                console.log('EAB AJAX Error:', status, error);
                console.log('Response text:', xhr.responseText);
                showNoSlotsMessage();
            }
        });
    }
    
    function displayTimeSlots(slots) {
        const container = $('#time-slots-container');
        let html = '';
        
        slots.forEach(function(slot) {
            html += '<div class="eab-time-slot" data-time="' + slot + '">' + formatTime(slot) + '</div>';
        });
        
        container.html(html);
        
        // Reset selected time
        selectedTimeSlot = null;
        $('#appointment_time').val('');
        updateNextButtonState();
    }
    
    function showNoSlotsMessage() {
        const container = $('#time-slots-container');
        container.html('<div class="eab-no-date-selected"><span class="dashicons dashicons-info"></span>' + (eab_frontend.strings.no_slots || 'No available time slots for this date.') + '</div>');
        
        selectedTimeSlot = null;
        $('#appointment_time').val('');
        updateNextButtonState();
    }
    
    function clearTimeSlots() {
        const container = $('#time-slots-container');
        container.html('<div class="eab-no-date-selected"><span class="dashicons dashicons-info"></span>Please select a date first</div>');
        
        selectedTimeSlot = null;
        $('#appointment_time').val('');
        updateNextButtonState();
    }
    
    function formatTime(time) {
        // Get time format setting from backend
        const timeFormat = eab_frontend.settings.time_format || '24h';
        
        if (timeFormat === '12h') {
            // Convert 24-hour format to 12-hour format
            const [hours, minutes] = time.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return displayHour + ':' + minutes + ' ' + ampm;
        } else {
            // Return 24-hour format as-is
            return time;
        }
    }
    
    function initTimeSlotSelection() {
        $(document).on('click', '.eab-time-slot', function() {
            if ($(this).hasClass('unavailable')) {
                return;
            }
            
            // Remove previous selection
            $('.eab-time-slot').removeClass('selected');
            
            // Select current slot
            $(this).addClass('selected');
            
            selectedTimeSlot = $(this).data('time');
            $('#appointment_time').val(selectedTimeSlot);
            
            updateNextButtonState();
        });
    }
    
    function updateNextButtonState() {
        const nextButton = $('#step-2 .eab-next-step');
        const hasDate = $('#appointment_date').val();
        const hasTime = $('#appointment_time').val();
        
        if (hasDate && hasTime) {
            nextButton.prop('disabled', false);
        } else {
            nextButton.prop('disabled', true);
        }
    }
    
    function populateConfirmation() {
        // Service - use the parsed name
        $('#confirm-service').text(formData.appointmentTypeName || formData.appointmentType);
        
        // Duration
        $('#confirm-duration').text((formData.appointmentDuration || 30) + ' minutes');
        
        // Date
        const dateObj = new Date(formData.appointmentDate);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        $('#confirm-date').text(formattedDate);
        
        // Time
        $('#confirm-time').text(formatTime(formData.appointmentTime));
        
        // Personal info
        $('#confirm-name').text(formData.name);
        $('#confirm-email').text(formData.email);
        $('#confirm-phone').text(formData.phone || 'Not provided');
        
        // Notes
        if (formData.notes) {
            $('#confirm-notes').text(formData.notes);
            $('#confirm-notes-row').show();
        } else {
            $('#confirm-notes-row').hide();
        }
    }
    
    function initFormValidation() {
        // Real-time validation
        $('#customer_email').on('blur', function() {
            const email = $(this).val().trim();
            if (email && !isValidEmail(email)) {
                $(this).addClass('error');
                showError(eab_frontend.strings.invalid_email || 'Please enter a valid email address.');
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Remove error class on input
        $('input, select, textarea').on('input change', function() {
            $(this).removeClass('error');
        });
    }
    
    function initFormSubmission() {
        $('#eab-appointment-form').on('submit', function(e) {
            e.preventDefault();
            
            // Validate all steps before submitting
            if (!validateServiceSelection()) {
                goToStep(1);
                return;
            }
            
            if (!validateDateTimeSelection()) {
                goToStep(2);
                return;
            }
            
            if (!validatePersonalInfo()) {
                return;
            }
            
            submitAppointment();
        });
    }
    
    function submitAppointment() {
        showLoadingOverlay();
        
        const submitData = {
            action: 'eab_book_appointment',
            nonce: eab_frontend.nonce,
            name: formData.name,
            email: formData.email,
            phone: formData.phone,
            appointment_type: formData.appointmentType,
            appointment_duration: formData.appointmentDuration,
            date: formData.appointmentDate,
            time: formData.appointmentTime,
            notes: formData.notes
        };
        
        // Debug logging for duration
        console.log('EAB Submit Data:', submitData);
        console.log('Form Data Duration:', formData.appointmentDuration);
        console.log('Selected Service Duration:', $('input[name="appointment_type"]:checked').data('duration'));
        
        $.ajax({
            url: eab_frontend.ajax_url,
            type: 'POST',
            data: submitData,
            success: function(response) {
                hideLoadingOverlay();
                
                if (response.success) {
                    showSuccessStep();
                } else {
                    showError(response.data.message || eab_frontend.strings.booking_error);
                }
            },
            error: function() {
                hideLoadingOverlay();
                showError(eab_frontend.strings.booking_error || 'Error booking appointment. Please try again.');
            }
        });
    }
    
    function showSuccessStep() {
        $('.eab-form-step').removeClass('active');
        $('#step-success').show();
        
        // Update progress to 100%
        $('.eab-progress-bar').css('width', '100%');
        
        // Mark all steps as completed
        $('.eab-step').removeClass('active').addClass('completed');
        
        // Scroll to top
        $('.eab-booking-form')[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    function showLoadingOverlay() {
        $('#eab-loading-overlay').show();
    }
    
    function hideLoadingOverlay() {
        $('#eab-loading-overlay').hide();
    }
    
    function showError(message) {
        // Create or update error message
        let errorDiv = $('.eab-error-message');
        
        if (errorDiv.length === 0) {
            errorDiv = $('<div class="eab-error-message"></div>');
            $('.eab-booking-form').prepend(errorDiv);
        }
        
        errorDiv.html('<span class="dashicons dashicons-warning"></span> ' + message)
               .addClass('show');
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            errorDiv.removeClass('show');
        }, 5000);
        
        // Scroll to error
        errorDiv[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function initAccessibility() {
        // Add ARIA labels
        $('.eab-time-slot').attr('role', 'button').attr('tabindex', '0');
        $('.eab-service-option').attr('role', 'radio');
        
        // Keyboard navigation for time slots
        $(document).on('keydown', '.eab-time-slot', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });
        
        // Keyboard navigation for service options
        $(document).on('keydown', '.eab-service-option', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            }
        });
        
        // Announce step changes to screen readers
        const stepAnnouncer = $('<div class="sr-only" aria-live="polite" aria-atomic="true"></div>');
        $('.eab-booking-form').append(stepAnnouncer);
        
        // Update announcer when step changes
        $(document).on('stepChanged', function() {
            const stepTitle = $('#step-' + currentStep).find('h4').text();
            stepAnnouncer.text('Step ' + currentStep + ' of ' + totalSteps + ': ' + stepTitle);
        });
    }
    
    function showServiceSyncIndicator() {
        const step1 = $('#step-1');
        if (step1.find('.eab-service-sync').length === 0) {
            const syncHtml = '<div class="eab-service-sync" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; text-align: center;"><div class="eab-spinner" style="display: inline-block; margin-right: 8px;"></div><span>' + (eab_frontend.strings.syncing_calendar || 'Syncing with calendar...') + '</span></div>';
            step1.append(syncHtml);
        }
    }
    
    function hideServiceSyncIndicator() {
        $('.eab-service-sync').remove();
    }
    
    function performInitialCalendarSync() {
        return new Promise(function(resolve) {
            $.ajax({
                url: eab_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'eab_sync_calendar',
                    nonce: eab_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Initial calendar sync completed successfully');
                    } else {
                        console.warn('Initial calendar sync failed:', response.data?.message || 'Unknown error');
                    }
                    resolve(); // Always resolve to allow form to continue
                },
                error: function(xhr, status, error) {
                    console.warn('Initial calendar sync request failed:', error);
                    resolve(); // Always resolve to allow form to continue
                },
                timeout: 10000 // 10 second timeout
            });
        });
    }
    
    function syncCalendarData() {
        // Show loading indicator - centered spinner with description
        const loadingHtml = '<div class="eab-loading-slots"><div class="eab-spinner"></div><p>' + (eab_frontend.strings.syncing_calendar || 'Syncing with calendar...') + '</p></div>';
        
        // Add loading overlay to step 2
        const step2 = $('#step-2');
        if (step2.find('.eab-calendar-sync').length === 0) {
            step2.append(loadingHtml);
        }
        
        $.ajax({
            url: eab_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'eab_sync_calendar',
                nonce: eab_frontend.nonce
            },
            success: function(response) {
                // Remove loading indicator
                $('.eab-loading-slots').remove();
                
                if (response.success) {
                    // Calendar sync successful, proceed normally
                    console.log('Calendar sync completed successfully');
                } else {
                    // Calendar sync failed, but don't block the user
                    console.warn('Calendar sync failed:', response.data?.message || 'Unknown error');
                }
                
                // Always allow the user to continue
                // The time slot loading will handle any conflicts
            },
            error: function(xhr, status, error) {
                // Remove loading indicator
                $('.eab-loading-slots').remove();
                
                // Log error but don't block the user
                console.warn('Calendar sync request failed:', error);
            },
            timeout: 10000 // 10 second timeout
        });
    }
    
    // Utility functions
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Auto-save form data to localStorage
    function saveFormData() {
        localStorage.setItem('eab_form_data', JSON.stringify(formData));
    }
    
    function loadFormData() {
        const saved = localStorage.getItem('eab_form_data');
        if (saved) {
            try {
                formData = JSON.parse(saved);
                // Populate form fields if needed
            } catch (e) {
                console.warn('Failed to load saved form data:', e);
            }
        }
    }
    
    function clearFormData() {
        localStorage.removeItem('eab_form_data');
        formData = {};
    }
    
    // Initialize form data management
    loadFormData();
    
    // Save form data on input changes
    const debouncedSave = debounce(saveFormData, 1000);
    $('input, select, textarea').on('input change', debouncedSave);
    
    // Clear form data on successful submission
    $(document).on('appointmentBooked', clearFormData);
    
    // Add CSS for error messages
    if ($('.eab-error-message').length === 0) {
        $('<style>').text(`
            .eab-error-message {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
                border-radius: 8px;
                padding: 12px 16px;
                margin-bottom: 20px;
                display: none;
                align-items: center;
                gap: 8px;
            }
            .eab-error-message.show {
                display: flex;
            }
            .eab-error-message .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }
            input.error, select.error, textarea.error {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1) !important;
            }

        `).appendTo('head');
    }
    
})(jQuery);