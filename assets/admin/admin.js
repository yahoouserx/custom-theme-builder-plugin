/**
 * Custom Theme Builder - Modern Admin UI JavaScript
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        CTB_Admin.init();
    });

    // Main admin object
    window.CTB_Admin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initFilters();
            this.initAnimations();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Template card actions
            $(document).on('click', '.ctb-action-btn.duplicate', this.duplicateTemplate);
            $(document).on('click', '.ctb-action-btn.delete', this.deleteTemplate);
            
            // Bulk actions
            $('#ctb-apply-bulk').on('click', this.applyBulkAction);
            
            // Filter handlers
            $('#ctb-filter-type, #ctb-filter-status, #ctb-filter-date').on('change', this.applyFilters);
            $('#ctb-filter-search').on('input', this.debounce(this.applyFilters, 300));
            
            // Stats card interactions
            $('.ctb-stats-card').on('click', this.filterByStats);
            
            // Template card interactions
            $(document).on('click', '.ctb-template-card', this.handleTemplateCardClick);
            
            // Empty state actions
            $('#ctb-show-help').on('click', this.showHelp);
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts);
        },

        /**
         * Initialize filters
         */
        initFilters: function() {
            // Set up filter state
            this.filterState = {
                type: '',
                status: '',
                search: '',
                date: ''
            };
            
            // Apply initial filters from URL parameters
            this.applyFiltersFromURL();
        },

        /**
         * Initialize animations
         */
        initAnimations: function() {
            // Animate stats cards on load
            $('.ctb-stats-card').each(function(index) {
                $(this).delay(index * 100).queue(function(next) {
                    $(this).addClass('ctb-fade-in');
                    next();
                });
            });
            
            // Animate template cards on load
            $('.ctb-template-card').each(function(index) {
                $(this).delay(index * 50).queue(function(next) {
                    $(this).addClass('ctb-slide-in');
                    next();
                });
            });
        },

        /**
         * Apply filters
         */
        applyFilters: function() {
            const self = CTB_Admin;
            
            // Get filter values
            self.filterState.type = $('#ctb-filter-type').val();
            self.filterState.status = $('#ctb-filter-status').val();
            self.filterState.search = $('#ctb-filter-search').val().toLowerCase();
            self.filterState.date = $('#ctb-filter-date').val();
            
            // Show loading state
            $('#ctb-templates-grid').addClass('ctb-loading');
            
            // Filter templates
            $('.ctb-template-card').each(function() {
                const $card = $(this);
                const shouldShow = self.shouldShowTemplate($card);
                
                if (shouldShow) {
                    $card.removeClass('ctb-hidden').addClass('ctb-fade-in');
                } else {
                    $card.addClass('ctb-hidden').removeClass('ctb-fade-in');
                }
            });
            
            // Update results count
            setTimeout(function() {
                self.updateResultsCount();
                $('#ctb-templates-grid').removeClass('ctb-loading');
            }, 300);
            
            // Update URL parameters
            self.updateURL();
        },

        /**
         * Check if template should be shown based on filters
         */
        shouldShowTemplate: function($card) {
            const templateData = this.getTemplateData($card);
            
            // Type filter
            if (this.filterState.type && templateData.type !== this.filterState.type) {
                return false;
            }
            
            // Status filter
            if (this.filterState.status && templateData.status !== this.filterState.status) {
                return false;
            }
            
            // Search filter
            if (this.filterState.search) {
                const searchableText = (templateData.title + ' ' + templateData.conditions).toLowerCase();
                if (searchableText.indexOf(this.filterState.search) === -1) {
                    return false;
                }
            }
            
            // Date filter
            if (this.filterState.date && !this.matchesDateFilter(templateData.date, this.filterState.date)) {
                return false;
            }
            
            return true;
        },

        /**
         * Get template data from card
         */
        getTemplateData: function($card) {
            return {
                id: $card.data('template-id'),
                title: $card.find('.ctb-template-title').text().trim(),
                type: $card.find('.ctb-template-type').text().trim().toLowerCase(),
                status: $card.find('.ctb-template-status').text().trim().toLowerCase(),
                conditions: $card.find('.ctb-template-conditions').text().trim(),
                date: $card.find('.ctb-template-date').text().trim()
            };
        },

        /**
         * Check if date matches filter
         */
        matchesDateFilter: function(dateText, filter) {
            const now = new Date();
            const cardDate = this.parseRelativeDate(dateText);
            
            if (!cardDate) return true;
            
            switch (filter) {
                case 'today':
                    return this.isSameDay(cardDate, now);
                case 'week':
                    const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                    return cardDate >= weekAgo;
                case 'month':
                    const monthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                    return cardDate >= monthAgo;
                default:
                    return true;
            }
        },

        /**
         * Parse relative date string
         */
        parseRelativeDate: function(dateText) {
            // Parse "X days ago", "X hours ago", etc.
            const match = dateText.match(/(\d+)\s+(minute|hour|day|week|month|year)s?\s+ago/);
            if (!match) return null;
            
            const amount = parseInt(match[1]);
            const unit = match[2];
            const now = new Date();
            
            switch (unit) {
                case 'minute':
                    return new Date(now.getTime() - amount * 60 * 1000);
                case 'hour':
                    return new Date(now.getTime() - amount * 60 * 60 * 1000);
                case 'day':
                    return new Date(now.getTime() - amount * 24 * 60 * 60 * 1000);
                case 'week':
                    return new Date(now.getTime() - amount * 7 * 24 * 60 * 60 * 1000);
                case 'month':
                    return new Date(now.getTime() - amount * 30 * 24 * 60 * 60 * 1000);
                case 'year':
                    return new Date(now.getTime() - amount * 365 * 24 * 60 * 60 * 1000);
                default:
                    return null;
            }
        },

        /**
         * Check if two dates are the same day
         */
        isSameDay: function(date1, date2) {
            return date1.getDate() === date2.getDate() &&
                   date1.getMonth() === date2.getMonth() &&
                   date1.getFullYear() === date2.getFullYear();
        },

        /**
         * Update results count
         */
        updateResultsCount: function() {
            const visibleCards = $('.ctb-template-card').not('.ctb-hidden');
            const totalCards = $('.ctb-template-card');
            
            $('.ctb-templates-title').text(`Templates (${visibleCards.length})`);
            
            // Show/hide empty state
            if (visibleCards.length === 0 && totalCards.length > 0) {
                this.showNoResultsMessage();
            } else {
                this.hideNoResultsMessage();
            }
        },

        /**
         * Show no results message
         */
        showNoResultsMessage: function() {
            if ($('.ctb-no-results').length === 0) {
                const message = `
                    <div class="ctb-no-results">
                        <div class="ctb-empty-icon">
                            <span class="dashicons dashicons-search"></span>
                        </div>
                        <h3>No templates found</h3>
                        <p>Try adjusting your filters or search terms.</p>
                        <button class="ctb-btn-outline" onclick="CTB_Admin.clearFilters()">Clear Filters</button>
                    </div>
                `;
                $('#ctb-templates-grid').append(message);
            }
        },

        /**
         * Hide no results message
         */
        hideNoResultsMessage: function() {
            $('.ctb-no-results').remove();
        },

        /**
         * Clear all filters
         */
        clearFilters: function() {
            $('#ctb-filter-type').val('');
            $('#ctb-filter-status').val('');
            $('#ctb-filter-search').val('');
            $('#ctb-filter-date').val('');
            
            this.filterState = {
                type: '',
                status: '',
                search: '',
                date: ''
            };
            
            this.applyFilters();
        },

        /**
         * Filter by stats card
         */
        filterByStats: function(e) {
            e.preventDefault();
            
            const $card = $(this);
            const cardTitle = $card.find('.ctb-stats-card-title').text().toLowerCase();
            
            // Clear existing filters
            CTB_Admin.clearFilters();
            
            // Apply appropriate filter
            if (cardTitle.includes('active')) {
                $('#ctb-filter-status').val('active');
            } else if (cardTitle.includes('inactive')) {
                $('#ctb-filter-status').val('inactive');
            } else if (cardTitle.includes('draft')) {
                $('#ctb-filter-status').val('draft');
            }
            
            CTB_Admin.applyFilters();
            
            // Add visual feedback
            $card.addClass('ctb-stats-card-selected');
            setTimeout(function() {
                $card.removeClass('ctb-stats-card-selected');
            }, 1000);
        },

        /**
         * Handle template card click
         */
        handleTemplateCardClick: function(e) {
            // Don't trigger for action buttons
            if ($(e.target).closest('.ctb-action-btn').length > 0) {
                return;
            }
            
            const $card = $(this);
            const templateId = $card.data('template-id');
            
            // Navigate to edit page
            window.location.href = `post.php?post=${templateId}&action=edit`;
        },

        /**
         * Duplicate template
         */
        duplicateTemplate: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const templateId = $btn.data('template-id');
            
            // Show loading state
            $btn.prop('disabled', true).addClass('ctb-loading');
            
            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ctb_duplicate_template',
                    template_id: templateId,
                    nonce: ctb_admin_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        CTB_Admin.showNotification('Template duplicated successfully!', 'success');
                        // Reload page to show new template
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        CTB_Admin.showNotification(response.data.message || 'Failed to duplicate template', 'error');
                    }
                },
                error: function() {
                    CTB_Admin.showNotification('An error occurred while duplicating the template', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('ctb-loading');
                }
            });
        },

        /**
         * Delete template
         */
        deleteTemplate: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const templateId = $btn.data('template-id');
            const $card = $btn.closest('.ctb-template-card');
            const templateTitle = $card.find('.ctb-template-title').text().trim();
            
            // Confirm deletion
            if (!confirm(`Are you sure you want to delete "${templateTitle}"? This action cannot be undone.`)) {
                return;
            }
            
            // Show loading state
            $btn.prop('disabled', true).addClass('ctb-loading');
            
            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ctb_delete_template',
                    template_id: templateId,
                    nonce: ctb_admin_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        CTB_Admin.showNotification('Template deleted successfully!', 'success');
                        // Animate removal
                        $card.fadeOut(300, function() {
                            $card.remove();
                            CTB_Admin.updateResultsCount();
                        });
                    } else {
                        CTB_Admin.showNotification(response.data.message || 'Failed to delete template', 'error');
                    }
                },
                error: function() {
                    CTB_Admin.showNotification('An error occurred while deleting the template', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('ctb-loading');
                }
            });
        },

        /**
         * Apply bulk action
         */
        applyBulkAction: function(e) {
            e.preventDefault();
            
            const action = $('#ctb-bulk-action').val();
            if (!action) {
                CTB_Admin.showNotification('Please select an action', 'error');
                return;
            }
            
            const selectedTemplates = [];
            $('.ctb-template-card input[type="checkbox"]:checked').each(function() {
                selectedTemplates.push($(this).val());
            });
            
            if (selectedTemplates.length === 0) {
                CTB_Admin.showNotification('Please select at least one template', 'error');
                return;
            }
            
            // Confirm bulk action
            if (!confirm(`Are you sure you want to ${action} ${selectedTemplates.length} template(s)?`)) {
                return;
            }
            
            // Show loading state
            $('#ctb-apply-bulk').prop('disabled', true).addClass('ctb-loading');
            
            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ctb_bulk_action',
                    bulk_action: action,
                    template_ids: selectedTemplates,
                    nonce: ctb_admin_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        CTB_Admin.showNotification(response.data.message || 'Bulk action completed successfully!', 'success');
                        // Reload page to reflect changes
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        CTB_Admin.showNotification(response.data.message || 'Bulk action failed', 'error');
                    }
                },
                error: function() {
                    CTB_Admin.showNotification('An error occurred while performing the bulk action', 'error');
                },
                complete: function() {
                    $('#ctb-apply-bulk').prop('disabled', false).removeClass('ctb-loading');
                }
            });
        },

        /**
         * Show help modal
         */
        showHelp: function(e) {
            e.preventDefault();
            
            const helpContent = `
                <div class="ctb-help-modal">
                    <div class="ctb-help-content">
                        <h2>Getting Started with Custom Templates</h2>
                        <div class="ctb-help-sections">
                            <div class="ctb-help-section">
                                <h3>1. Create a Template</h3>
                                <p>Click "Add New Template" to create your first template. You can use any page builder including Elementor.</p>
                            </div>
                            <div class="ctb-help-section">
                                <h3>2. Set Display Conditions</h3>
                                <p>Add conditions to control where your template appears. Choose from 30+ condition types.</p>
                            </div>
                            <div class="ctb-help-section">
                                <h3>3. Template Types</h3>
                                <ul>
                                    <li><strong>Content:</strong> Replace only the content area (single posts, pages)</li>
                                    <li><strong>Full Page:</strong> Replace the entire page (archives, 404 pages)</li>
                                    <li><strong>Header/Footer:</strong> Replace specific page sections</li>
                                </ul>
                            </div>
                            <div class="ctb-help-section">
                                <h3>4. Manage Templates</h3>
                                <p>Use the filters to find templates quickly. Activate/deactivate templates as needed.</p>
                            </div>
                        </div>
                        <div class="ctb-help-actions">
                            <a href="#" class="ctb-btn-primary" onclick="CTB_Admin.closeHelp()">Get Started</a>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(helpContent);
            $('.ctb-help-modal').fadeIn(300);
        },

        /**
         * Close help modal
         */
        closeHelp: function() {
            $('.ctb-help-modal').fadeOut(300, function() {
                $(this).remove();
            });
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            const notification = `
                <div class="ctb-notification ctb-notification-${type}">
                    <span class="dashicons dashicons-${type === 'success' ? 'yes' : 'warning'}"></span>
                    ${message}
                    <button class="ctb-notification-close">&times;</button>
                </div>
            `;
            
            // Remove existing notifications
            $('.ctb-notification').remove();
            
            // Add new notification
            $('body').append(notification);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.ctb-notification').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Close button handler
            $('.ctb-notification-close').on('click', function() {
                $(this).parent().fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                $('#ctb-filter-search').focus();
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                $('#ctb-filter-search').val('').trigger('input');
            }
        },

        /**
         * Apply filters from URL parameters
         */
        applyFiltersFromURL: function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('filter_type')) {
                $('#ctb-filter-type').val(urlParams.get('filter_type'));
            }
            
            if (urlParams.has('filter_status')) {
                $('#ctb-filter-status').val(urlParams.get('filter_status'));
            }
            
            if (urlParams.has('filter_search')) {
                $('#ctb-filter-search').val(urlParams.get('filter_search'));
            }
            
            if (urlParams.has('filter_date')) {
                $('#ctb-filter-date').val(urlParams.get('filter_date'));
            }
            
            // Apply filters if any are set
            if (urlParams.has('filter_type') || urlParams.has('filter_status') || 
                urlParams.has('filter_search') || urlParams.has('filter_date')) {
                this.applyFilters();
            }
        },

        /**
         * Update URL with current filters
         */
        updateURL: function() {
            const url = new URL(window.location);
            
            // Clear existing filter parameters
            url.searchParams.delete('filter_type');
            url.searchParams.delete('filter_status');
            url.searchParams.delete('filter_search');
            url.searchParams.delete('filter_date');
            
            // Add current filter parameters
            if (this.filterState.type) {
                url.searchParams.set('filter_type', this.filterState.type);
            }
            
            if (this.filterState.status) {
                url.searchParams.set('filter_status', this.filterState.status);
            }
            
            if (this.filterState.search) {
                url.searchParams.set('filter_search', this.filterState.search);
            }
            
            if (this.filterState.date) {
                url.searchParams.set('filter_date', this.filterState.date);
            }
            
            // Update URL without reloading
            window.history.replaceState({}, '', url);
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
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
    };

})(jQuery);

// Additional CSS for notifications and help modal
const additionalCSS = `
    .ctb-notification {
        position: fixed;
        top: 32px;
        right: 20px;
        background: white;
        border-radius: 8px;
        padding: 16px 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        font-size: 14px;
        font-weight: 500;
        border-left: 4px solid;
        animation: slideInRight 0.3s ease-out;
    }
    
    .ctb-notification-success {
        border-left-color: #10b981;
        color: #065f46;
    }
    
    .ctb-notification-error {
        border-left-color: #ef4444;
        color: #dc2626;
    }
    
    .ctb-notification-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        margin-left: auto;
        opacity: 0.7;
    }
    
    .ctb-notification-close:hover {
        opacity: 1;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .ctb-help-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10001;
        display: none;
        align-items: center;
        justify-content: center;
    }
    
    .ctb-help-content {
        background: white;
        border-radius: 16px;
        padding: 40px;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        margin: 20px;
    }
    
    .ctb-help-content h2 {
        margin: 0 0 24px 0;
        font-size: 24px;
        color: #1f2937;
    }
    
    .ctb-help-section {
        margin-bottom: 24px;
    }
    
    .ctb-help-section h3 {
        margin: 0 0 8px 0;
        font-size: 18px;
        color: #374151;
    }
    
    .ctb-help-section p {
        margin: 0 0 12px 0;
        color: #6b7280;
        line-height: 1.5;
    }
    
    .ctb-help-section ul {
        margin: 0;
        padding-left: 20px;
        color: #6b7280;
    }
    
    .ctb-help-actions {
        text-align: center;
        margin-top: 32px;
    }
    
    .ctb-no-results {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
        grid-column: 1 / -1;
    }
    
    .ctb-no-results h3 {
        margin: 16px 0 8px 0;
        font-size: 20px;
        color: #374151;
    }
    
    .ctb-no-results p {
        margin: 0 0 20px 0;
        font-size: 14px;
    }
    
    .ctb-stats-card-selected {
        transform: scale(1.05);
        box-shadow: 0 12px 48px rgba(99, 102, 241, 0.3) !important;
    }
    
    .ctb-hidden {
        display: none !important;
    }
`;

// Inject additional CSS
const style = document.createElement('style');
style.textContent = additionalCSS;
document.head.appendChild(style);