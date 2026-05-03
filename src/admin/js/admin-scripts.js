/**
 * Admin Scripts for WP EC2 Backoffice Plugin
 *
 * @package WP_EC2_Backoffice
 */

(function($) {
    'use strict';

    /**
     * Plugin state management
     */
    const EC2Admin = {
        pollingInterval: null,
        pollingDelay: 60000, // 60 seconds
        isOperationInProgress: false,
        currentInstanceState: null,
        windowsPassword: null,

        /**
         * Initialize the plugin
         */
        init: function() {
            this.bindEvents();
            this.startPolling();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Instance control buttons
            $('#ec2-start-btn').on('click', this.handleStartInstance.bind(this));
            $('#ec2-stop-btn').on('click', this.handleStopInstance.bind(this));
            
            // RDP download button
            $('#ec2-download-rdp-btn').on('click', this.handleDownloadRDP.bind(this));
            
            // Test connection button
            $('#ec2-test-connection-btn').on('click', this.handleTestConnection.bind(this));
            
            // Password functionality
            $('#ec2-toggle-password-btn').on('click', this.handleTogglePassword.bind(this));
            $('#ec2-copy-password-btn').on('click', this.handleCopyPassword.bind(this));
            
            // Tab switching - preserve state
            $('.nav-tab').on('click', this.handleTabSwitch.bind(this));
        },

        /**
         * Start polling for instance status
         */
        startPolling: function() {
            // Only poll if we're on the access tab
            if ($('.ec2-access-tab').length > 0) {
                this.pollingInterval = setInterval(
                    this.pollInstanceStatus.bind(this),
                    this.pollingDelay
                );
            }
        },

        /**
         * Stop polling
         */
        stopPolling: function() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
        },

        /**
         * Poll instance status
         */
        pollInstanceStatus: function() {
            // Don't poll if an operation is in progress
            if (this.isOperationInProgress) {
                return;
            }

            $.ajax({
                url: wpEc2Backoffice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ec2_get_status',
                    nonce: $('#ec2-ajax-nonce').val()
                },
                success: this.handleStatusResponse.bind(this),
                error: function(xhr, status, error) {
                    console.error('Status polling error:', error);
                }
            });
        },

        /**
         * Handle status response
         */
        handleStatusResponse: function(response) {
            if (response.success && response.data && response.data.data) {
                const instanceData = response.data.data;
                this.updateUI(instanceData);
            }
        },

        /**
         * Update UI with instance data
         */
        updateUI: function(instanceData) {
            const state = instanceData.state;
            const publicIp = instanceData.public_ip || '';
            
            // Store current state
            this.currentInstanceState = state;

            // Update state display
            const $stateElement = $('#ec2-instance-state');
            $stateElement.text(this.capitalizeFirst(state));
            
            // Remove all state classes and add current one
            $stateElement.removeClass(function(index, className) {
                return (className.match(/(^|\s)ec2-state-\S+/g) || []).join(' ');
            });
            $stateElement.addClass('ec2-state-' + state);

            // Update public IP
            if (publicIp) {
                $('#ec2-public-ip').text(publicIp);
                $('#ec2-public-ip').parent().show();
            } else {
                $('#ec2-public-ip').parent().hide();
            }

            // Update button states
            this.updateButtonStates(state);
        },

        /**
         * Update button states based on instance state
         */
        updateButtonStates: function(state) {
            const isTransitional = ['pending', 'stopping'].includes(state);
            const isRunning = state === 'running';
            const isStopped = state === 'stopped';

            // Start button
            $('#ec2-start-btn').prop('disabled', isRunning || isTransitional);

            // Stop button
            $('#ec2-stop-btn').prop('disabled', isStopped || isTransitional);

            // RDP download button
            $('#ec2-download-rdp-btn').prop('disabled', !isRunning);
        },

        /**
         * Handle start instance button click
         */
        handleStartInstance: function(e) {
            e.preventDefault();
            
            if (this.isOperationInProgress) {
                return;
            }

            this.isOperationInProgress = true;
            this.showLoading(true);
            $('#ec2-start-btn').prop('disabled', true);

            $.ajax({
                url: wpEc2Backoffice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ec2_start_instance',
                    nonce: $('#ec2-ajax-nonce').val()
                },
                success: this.handleStartResponse.bind(this),
                error: this.handleAjaxError.bind(this),
                complete: function() {
                    this.isOperationInProgress = false;
                    this.showLoading(false);
                }.bind(this)
            });
        },

        /**
         * Handle start instance response
         */
        handleStartResponse: function(response) {
            if (response.success) {
                this.showNotice(response.data.message, 'success');
                // Immediately poll for updated status
                this.pollInstanceStatus();
            } else {
                this.showNotice(response.data.message, 'error');
                $('#ec2-start-btn').prop('disabled', false);
            }
        },

        /**
         * Handle stop instance button click
         */
        handleStopInstance: function(e) {
            e.preventDefault();
            
            if (this.isOperationInProgress) {
                return;
            }

            this.isOperationInProgress = true;
            this.showLoading(true);
            $('#ec2-stop-btn').prop('disabled', true);

            $.ajax({
                url: wpEc2Backoffice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ec2_stop_instance',
                    nonce: $('#ec2-ajax-nonce').val()
                },
                success: this.handleStopResponse.bind(this),
                error: this.handleAjaxError.bind(this),
                complete: function() {
                    this.isOperationInProgress = false;
                    this.showLoading(false);
                }.bind(this)
            });
        },

        /**
         * Handle stop instance response
         */
        handleStopResponse: function(response) {
            if (response.success) {
                this.showNotice(response.data.message, 'success');
                // Immediately poll for updated status
                this.pollInstanceStatus();
            } else {
                this.showNotice(response.data.message, 'error');
                $('#ec2-stop-btn').prop('disabled', false);
            }
        },

        /**
         * Handle download RDP button click
         */
        handleDownloadRDP: function(e) {
            e.preventDefault();
            
            // Create a form and submit it to trigger download
            const nonce = $('#ec2-ajax-nonce').val();
            const url = wpEc2Backoffice.ajaxUrl + '?action=ec2_download_rdp&nonce=' + nonce;
            
            // Open in new window to trigger download
            window.location.href = url;
        },

        /**
         * Handle test connection button click
         */
        handleTestConnection: function(e) {
            e.preventDefault();
            
            const $button = $('#ec2-test-connection-btn');
            const $loading = $('#ec2-test-loading');
            const $result = $('#ec2-test-result');
            
            $button.prop('disabled', true);
            $loading.addClass('is-active').show();
            $result.empty();

            $.ajax({
                url: wpEc2Backoffice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ec2_test_connection',
                    nonce: $('#ec2-ajax-nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<div class="notice notice-error"><p>Error de conexión: ' + error + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $loading.removeClass('is-active').hide();
                }
            });
        },

        /**
         * Handle toggle password visibility
         */
        handleTogglePassword: function(e) {
            e.preventDefault();
            
            const $display = $('#ec2-password-display');
            const $button = $('#ec2-toggle-password-btn');

            if ($display.hasClass('ec2-password-masked')) {
                this.fetchWindowsPassword().done(function(password) {
                    $display.removeClass('ec2-password-masked').text(password);
                    $button.text('Hide');
                }).fail(function(message) {
                    alert(message);
                });
            } else {
                $display.addClass('ec2-password-masked').text('••••••••••••');
                $button.text('Reveal');
                this.windowsPassword = null;
            }
        },

        /**
         * Handle copy password to clipboard
         */
        handleCopyPassword: function(e) {
            e.preventDefault();
            
            const $feedback = $('#ec2-copy-feedback');

            this.fetchWindowsPassword().done(function(password) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(password).then(
                        function() {
                            $feedback.fadeIn();
                            setTimeout(function() {
                                $feedback.fadeOut();
                            }, 2000);
                        },
                        function(err) {
                            console.error('Failed to copy password:', err);
                            alert('Failed to copy the password');
                        }
                    );
                } else {
                    const $temp = $('<textarea>');
                    $('body').append($temp);
                    $temp.val(password).select();

                    try {
                        document.execCommand('copy');
                        $feedback.fadeIn();
                        setTimeout(function() {
                            $feedback.fadeOut();
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy password:', err);
                        alert('Failed to copy the password');
                    }

                    $temp.remove();
                }
            }).fail(function(message) {
                alert(message);
            });
        },

        /**
         * Fetch the Windows password on demand.
         */
        fetchWindowsPassword: function() {
            const deferred = $.Deferred();

            if (this.windowsPassword) {
                deferred.resolve(this.windowsPassword);
                return deferred.promise();
            }

            $.ajax({
                url: wpEc2Backoffice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ec2_get_windows_password',
                    nonce: $('#ec2-ajax-nonce').val()
                },
                success: function(response) {
                    if (response.success && response.data && response.data.password) {
                        this.windowsPassword = response.data.password;
                        deferred.resolve(response.data.password);
                        return;
                    }

                    deferred.reject((response.data && response.data.message) ? response.data.message : 'Unable to load the Windows password');
                }.bind(this),
                error: function(xhr, status, error) {
                    deferred.reject('Unable to load the Windows password: ' + error);
                }
            });

            return deferred.promise();
        },

        /**
         * Handle tab switching
         */
        handleTabSwitch: function(e) {
            // Let WordPress handle the tab switching via URL
            // The instance state is preserved because we poll on page load
            // No need to prevent default or do anything special
        },

        /**
         * Show loading spinner
         */
        showLoading: function(show) {
            const $spinner = $('#ec2-loading');
            if (show) {
                $spinner.addClass('is-active').show();
            } else {
                $spinner.removeClass('is-active').hide();
            }
        },

        /**
         * Show notice message
         */
        showNotice: function(message, type) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after the h1 title
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Handle AJAX errors
         */
        handleAjaxError: function(xhr, status, error) {
            console.error('AJAX error:', error);
            this.showNotice('Error de comunicación con el servidor: ' + error, 'error');
        },

        /**
         * Capitalize first letter
         */
        capitalizeFirst: function(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        EC2Admin.init();
    });

})(jQuery);
