/**
 * Custom Theme Builder Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        CTB_Admin.init();
    });

    /**
     * Main admin object
     */
    var CTB_Admin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.initConditions();
            this.initTemplateActions();
            this.initBulkActions();
            this.initPreview();
            this.initTooltips();
            this.initAjaxHandlers();
        },

        /**
         * Initialize conditions interface
         */
        initConditions: function() {
            // Add condition button
            $(document).on('click', '#ctb-add-condition', function(e) {
                e.preventDefault();
                CTB_Admin.addCondition();
            });

            // Remove condition button
            $(document).on('click', '.ctb-remove-condition', function(e) {
                e.preventDefault();
                CTB_Admin.removeCondition($(this));
            });

            // Condition type change
            $(document).on('change', '.ctb-condition-type', function() {
                CTB_Admin.updateConditionValue($(this));
            });

            // Add condition group button
            $(document).on('click', '#ctb-add-condition-group', function(e) {
                e.preventDefault();
                CTB_Admin.addConditionGroup();
            });
        },

        /**
         * Initialize template actions
         */
        initTemplateActions: function() {
            // Duplicate template
            $(document).on('click', '.ctb-duplicate-template', function(e) {
                e.preventDefault();
                var templateId = $(this).data('template-id');
                CTB_Admin.duplicateTemplate(templateId);
            });



            // Toggle template status
            $(document).on('click', '.ctb-toggle-status', function(e) {
                e.preventDefault();
                var templateId = $(this).data('template-id');
                CTB_Admin.toggleTemplateStatus(templateId);
            });
        },

        /**
         * Initialize bulk actions
         */
        initBulkActions: function() {
            // Handle bulk actions form
            $(document).on('submit', '#ctb-templates-form', function(e) {
                e.preventDefault();
                CTB_Admin.handleBulkAction();
            });

            // Select all checkbox
            $(document).on('change', '#cb-select-all-1', function() {
                var checked = $(this).prop('checked');
                $('input[name="template_ids[]"]').prop('checked', checked);
            });
        },



        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to help icons
            $('.ctb-help-tooltip').each(function() {
                var $tooltip = $(this);
                var content = $tooltip.data('tooltip');
                
                if (content) {
                    $tooltip.append('<span class="tooltip-content">' + content + '</span>');
                }
            });
        },

        /**
         * Initialize AJAX handlers
         */
        initAjaxHandlers: function() {
            // Global AJAX error handler
            $(document).ajaxError(function(event, xhr, settings, error) {
                if (settings.url && settings.url.indexOf('ctb_') !== -1) {
                    CTB_Admin.showNotice(ctb_admin.strings.error, 'error');
                }
            });
        },

        /**
         * Add condition row
         */
        addCondition: function() {
            var $list = $('#ctb-conditions-list');
            var index = $list.find('.ctb-condition-row').length;
            
            // Remove no conditions message
            $list.find('.ctb-no-conditions').remove();
            
            // Get template
            var template = wp.template('ctb-condition-row');
            var html = template({ index: index });
            
            // Add to list with animation
            var $row = $(html).hide();
            $list.append($row);
            $row.slideDown(300);
            
            // Focus on condition type select
            $row.find('.ctb-condition-type').focus();
        },

        /**
         * Remove condition row
         */
        removeCondition: function($button) {
            var $row = $button.closest('.ctb-condition-row');
            
            $row.addClass('removing').slideUp(300, function() {
                $row.remove();
                
                // Show no conditions message if empty
                var $list = $('#ctb-conditions-list');
                if ($list.find('.ctb-condition-row').length === 0) {
                    $list.append('<div class="ctb-no-conditions"><p>' + 
                        'No conditions set. This template will not be displayed anywhere.' + 
                        '</p></div>');
                }
            });
        },

        /**
         * Update condition value field
         */
        updateConditionValue: function($select) {
            var conditionType = $select.val();
            var $row = $select.closest('.ctb-condition-row');
            var index = $row.data('index');
            
            if (!conditionType) {
                return;
            }
            
            // Show loading
            var $valueContainer = $row.find('.ctb-condition-value');
            $valueContainer.addClass('ctb-loading');
            
            // AJAX request to get condition options
            $.ajax({
                url: ctb_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ctb_get_condition_options',
                    nonce: ctb_admin.nonce,
                    condition_type: conditionType,
                    index: index
                },
                success: function(response) {
                    if (response.success) {
                        $valueContainer.html(response.data.html);
                    } else {
                        CTB_Admin.showNotice(response.data.message || ctb_admin.strings.error, 'error');
                    }
                },
                error: function() {
                    CTB_Admin.showNotice(ctb_admin.strings.error, 'error');
                },
                complete: function() {
                    $valueContainer.removeClass('ctb-loading');
                }
            });
        },

        /**
         * Add condition group
         */
        addConditionGroup: function() {
            // Add separator
            var $list = $('#ctb-conditions-list');
            var $separator = $('<div class="ctb-condition-group-separator">OR</div>');
            $list.append($separator);
            
            // Add new condition
            this.addCondition();
        },

        /**
         * Duplicate template
         */
        duplicateTemplate: function(templateId) {
            if (!confirm(ctb_admin.strings.confirm_duplicate || 'Are you sure you want to duplicate this template?')) {
                return;
            }
            
            // Show loading
            this.showLoading();
            
            $.ajax({
                url: ctb_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ctb_duplicate_template',
                    nonce: ctb_admin.nonce,
                    template_id: templateId
                },
                success: function(response) {
                    if (response.success) {
                        CTB_Admin.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        CTB_Admin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    CTB_Admin.showNotice(ctb_admin.strings.error, 'error');
                },
                complete: function() {
                    CTB_Admin.hideLoading();
                }
            });
        },



        /**
         * Toggle template status
         */
        toggleTemplateStatus: function(templateId) {
            // Show loading
            this.showLoading();
            
            $.ajax({
                url: ctb_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ctb_toggle_template_status',
                    nonce: ctb_admin.nonce,
                    template_id: templateId
                },
                success: function(response) {
                    if (response.success) {
                        CTB_Admin.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        CTB_Admin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    CTB_Admin.showNotice(ctb_admin.strings.error, 'error');
                },
                complete: function() {
                    CTB_Admin.hideLoading();
                }
            });
        },

        /**
         * Handle bulk actions
         */
        handleBulkAction: function() {
            var action = $('#bulk-action-selector-top').val();
            var templateIds = [];
            
            $('input[name="template_ids[]"]:checked').each(function() {
                templateIds.push($(this).val());
            });
            
            if (action === '-1') {
                CTB_Admin.showNotice('Please select an action', 'error');
                return;
            }
            
            if (templateIds.length === 0) {
                CTB_Admin.showNotice('Please select at least one template', 'error');
                return;
            }
            
            // Confirm destructive actions
            if (action === 'delete') {
                if (!confirm('Are you sure you want to delete the selected templates?')) {
                    return;
                }
            }
            
            // Show loading
            this.showLoading();
            
            $.ajax({
                url: ctb_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ctb_bulk_action',
                    nonce: ctb_admin.nonce,
                    bulk_action: action,
                    template_ids: templateIds
                },
                success: function(response) {
                    if (response.success) {
                        CTB_Admin.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        CTB_Admin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    CTB_Admin.showNotice(ctb_admin.strings.error, 'error');
                },
                complete: function() {
                    CTB_Admin.hideLoading();
                }
            });
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap').find('h1').after($notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            if ($('#ctb-loading-overlay').length === 0) {
                $('body').append('<div id="ctb-loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; display: flex; align-items: center; justify-content: center;"><div style="background: white; padding: 20px; border-radius: 4px; text-align: center;"><div class="spinner is-active" style="float: none; margin: 0 auto 10px;"></div><p>' + (ctb_admin.strings.saving || 'Saving...') + '</p></div></div>');
            }
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('#ctb-loading-overlay').remove();
        },

        /**
         * Validate form before submit
         */
        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // Validate template type
            var templateType = $form.find('#ctb_template_type').val();
            if (!templateType) {
                errors.push('Please select a template type');
                isValid = false;
            }
            
            // Validate conditions
            var hasValidConditions = false;
            $form.find('.ctb-condition-row').each(function() {
                var $row = $(this);
                var type = $row.find('.ctb-condition-type').val();
                var value = $row.find('.ctb-condition-value input, .ctb-condition-value select').val();
                
                if (type && value) {
                    hasValidConditions = true;
                }
            });
            
            if (!hasValidConditions && templateType) {
                errors.push('Please add at least one condition to determine where this template should be displayed');
                isValid = false;
            }
            
            // Show errors
            if (!isValid) {
                this.showNotice(errors.join('<br>'), 'error');
            }
            
            return isValid;
        },

        /**
         * Auto-save functionality
         */
        initAutoSave: function() {
            var saveTimeout;
            
            $(document).on('change input', '#post input, #post select, #post textarea', function() {
                clearTimeout(saveTimeout);
                
                saveTimeout = setTimeout(function() {
                    CTB_Admin.autoSave();
                }, 2000);
            });
        },

        /**
         * Auto-save template data
         */
        autoSave: function() {
            var templateId = $('#post_ID').val();
            
            if (!templateId) {
                return;
            }
            
            var data = {
                action: 'ctb_auto_save_template',
                nonce: ctb_admin.nonce,
                template_id: templateId,
                template_data: this.serializeTemplateData()
            };
            
            $.ajax({
                url: ctb_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        // Show auto-save indicator
                        CTB_Admin.showAutoSaveIndicator();
                    }
                }
            });
        },

        /**
         * Serialize template data
         */
        serializeTemplateData: function() {
            var data = {};
            
            // Get basic template data
            data.title = $('#title').val();
            data.content = $('#content').val();
            data.type = $('#ctb_template_type').val();
            data.status = $('#ctb_template_status').val();
            
            // Get conditions
            data.conditions = [];
            $('.ctb-condition-row').each(function() {
                var $row = $(this);
                var condition = {
                    type: $row.find('.ctb-condition-type').val(),
                    operator: $row.find('.ctb-condition-operator').val(),
                    value: $row.find('.ctb-condition-value input, .ctb-condition-value select').val()
                };
                
                if (condition.type && condition.value) {
                    data.conditions.push(condition);
                }
            });
            
            return data;
        },

        /**
         * Show auto-save indicator
         */
        showAutoSaveIndicator: function() {
            var $indicator = $('#ctb-autosave-indicator');
            
            if ($indicator.length === 0) {
                $indicator = $('<div id="ctb-autosave-indicator" style="position: fixed; top: 32px; right: 20px; background: #46b450; color: white; padding: 8px 12px; border-radius: 3px; z-index: 1000; font-size: 12px;">' + (ctb_admin.strings.saved || 'Saved!') + '</div>');
                $('body').append($indicator);
            }
            
            $indicator.fadeIn().delay(2000).fadeOut();
        }
    };

    // Export to global scope
    window.CTB_Admin = CTB_Admin;

})(jQuery);
