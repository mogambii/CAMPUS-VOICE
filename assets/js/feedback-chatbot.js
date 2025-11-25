/**
 * Feedback Chatbot
 * 
 * Provides a chat interface for users to check for similar feedback
 * before submitting new feedback.
 */

class FeedbackChatbot {
    constructor() {
        this.isOpen = false;
        this.isTyping = false;
        this.typingTimer = null;
        this.typingDelay = 500; // ms
        
        this.initElements();
        this.setupEventListeners();
        this.render();
    }
    
    initElements() {
        // Create main container
        this.container = document.createElement('div');
        this.container.className = 'feedback-chatbot-container';
        this.container.innerHTML = `
            <div class="feedback-chatbot-toggle">
                <i class="fas fa-comment-dots"></i>
            </div>
            <div class="feedback-chatbot-window">
                <div class="feedback-chatbot-header">
                    <h6>Feedback Assistant</h6>
                    <div class="chatbot-header-actions">
                        <button class="chatbot-clear-btn" title="Clear Chat">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <button class="feedback-chatbot-close" title="Close Chat">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="feedback-chatbot-messages">
                    <div class="chat-message bot">
                        <div class="message-content">
                            <p>Hi there! I can help you check if your feedback is similar to existing ones before you submit it. Type your feedback below.</p>
                        </div>
                    </div>
                </div>
                <div class="feedback-chatbot-input-container">
                    <div class="typing-indicator" style="display: none;">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="similar-feedback-container"></div>
                    <div class="input-group">
                        <textarea 
                            id="feedbackInput" 
                            class="form-control" 
                            placeholder="Type your feedback here..."
                            rows="3"
                        ></textarea>
                        <button class="btn btn-primary" id="sendFeedback">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="checkDuplicates" checked>
                        <label class="form-check-label small" for="checkDuplicates">
                            Check for similar feedback
                        </label>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.container);
        
        // Cache frequently used elements
        this.toggleButton = this.container.querySelector('.feedback-chatbot-toggle');
        this.chatWindow = this.container.querySelector('.feedback-chatbot-window');
        this.messagesContainer = this.container.querySelector('.feedback-chatbot-messages');
        this.inputField = this.container.querySelector('#feedbackInput');
        this.sendButton = this.container.querySelector('#sendFeedback');
        this.typingIndicator = this.container.querySelector('.typing-indicator');
        this.similarFeedbackContainer = this.container.querySelector('.similar-feedback-container');
        this.checkDuplicates = this.container.querySelector('#checkDuplicates');
    }
    
    setupEventListeners() {
        // Toggle chat window
        this.toggleButton.addEventListener('click', () => this.toggleChat());
        this.container.querySelector('.feedback-chatbot-close').addEventListener('click', () => this.toggleChat(false));
        
        // Send message on button click or Enter key (with Shift+Enter for new line)
        this.sendButton.addEventListener('click', () => this.handleSendMessage());
        this.inputField.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.handleSendMessage();
            }
            
            // Check for duplicates while typing (with debounce)
            if (this.checkDuplicates.checked) {
                clearTimeout(this.typingTimer);
                
                if (this.inputField.value.trim().length > 10) {
                    this.typingTimer = setTimeout(() => {
                        this.checkForDuplicates(this.inputField.value.trim());
                    }, this.typingDelay);
                } else {
                    this.clearSimilarFeedback();
                }
            }
        });
        
        // Toggle duplicate checking
        this.checkDuplicates.addEventListener('change', () => {
            if (!this.checkDuplicates.checked) {
                this.clearSimilarFeedback();
            } else if (this.inputField.value.trim().length > 10) {
                this.checkForDuplicates(this.inputField.value.trim());
            }
        });
    }
    
    toggleChat(forceState) {
        this.isOpen = forceState !== undefined ? forceState : !this.isOpen;
        this.chatWindow.style.display = this.isOpen ? 'flex' : 'none';
        this.toggleButton.style.display = this.isOpen ? 'none' : 'flex';
        
        if (this.isOpen) {
            this.inputField.focus();
        }
    }
    
    /**
     * Get API configuration with base URL and endpoints
     * @returns {Object} API configuration object
     */
    getApiConfig() {
        const currentPath = window.location.pathname;
        let apiBase = '';
        
        // Decode the path first to handle any existing encoding
        const decodedPath = decodeURIComponent(currentPath);
        
        // Check if we're in a subdirectory
        if (decodedPath.includes('CAMPUS VOICE')) {
            apiBase = '/CAMPUS VOICE';  // Don't encode here - let the browser handle it
        }
        
        return {
            baseUrl: apiBase,
            endpoints: {
                checkDuplicates: `${apiBase}/api/check-duplicates.php`,
                testConnection: `${apiBase}/api/test-connection.php`
            },
            timeout: 10000 // 10 seconds
        };
    }
    
    /**
     * Log errors consistently to console
     * @param {string} message - Error message
     * @param {Error|null} error - Error object (optional)
     * @param {Object} context - Additional context (optional)
     */
    logError(message, error = null, context = {}) {
        const timestamp = new Date().toISOString();
        const errorDetails = error ? {
            name: error.name,
            message: error.message,
            stack: error.stack,
            ...(error.response && { 
                status: error.response.status,
                statusText: error.response.statusText,
                response: error.response.data
            })
        } : null;
        
        const logEntry = {
            timestamp,
            message,
            context: {
                ...context,
                location: window.location.href,
                userAgent: navigator.userAgent
            },
            error: errorDetails
        };
        
        console.error(`[${timestamp}] ${message}`, logEntry);
        
        // Optionally send to server log
        // this.logToServer(logEntry);
    }
    
    /**
     * Test API connection and return result
     * @returns {Promise<Object>} API test result
     * @throws {Error} If connection fails
     */
    async testApiConnection() {
        const { endpoints } = this.getApiConfig();
        
        try {
            console.log('Testing API connection to:', endpoints.testConnection);
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            
            const response = await fetch(endpoints.testConnection, {
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('API Test Response:', data);
            return data;
            
        } catch (error) {
            this.logError('API connection test failed', error, {
                endpoint: endpoints.testConnection
            });
            throw error;
        }
    }
    
    /**
     * Get user-friendly error message from error object
     * @param {Error|Object} error - Error object
     * @returns {string} User-friendly error message
     */
    getErrorMessage(error) {
        if (!error) return 'An unknown error occurred';
        
        // Handle network errors
        if (error.name === 'AbortError') {
            return 'Request timed out. Please check your connection and try again.';
        }
        
        if (error.message && error.message.includes('Failed to fetch')) {
            return 'Unable to connect to the server. Please check your internet connection.';
        }
        
        // Handle HTTP errors
        if (error.response) {
            const { status, data } = error.response;
            
            switch (status) {
                case 400:
                    return data.message || 'Invalid request. Please check your input.';
                case 401:
                    return 'Authentication required. Please log in again.';
                case 403:
                    return 'You do not have permission to perform this action.';
                case 404:
                    return 'The requested resource was not found.';
                case 500:
                    return 'A server error occurred. Please try again later.';
                default:
                    return data.message || `Error: ${status} - ${error.response.statusText || 'Unknown error'}`;
            }
        }
        
        // Fallback to generic error message
        return error.message || 'An error occurred. Please try again.';
    }
    
    /**
     * Check for duplicate feedback
     * @param {string} text - Feedback text to check
     * @returns {Promise<void>}
     */
    async checkForDuplicates(text) {
        if (!text || text.length < 10) {
            this.clearSimilarFeedback();
            return;
        }
        
        this.showTypingIndicator(true);
        this.clearSimilarFeedback();
        
        const { endpoints } = this.getApiConfig();
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);
        
        try {
            console.log('Checking for duplicates with text:', text.substring(0, 50) + '...');
            
            const response = await fetch(endpoints.checkDuplicates, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    description: text,
                    limit: 3
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.similar_feedback && data.similar_feedback.length > 0) {
                this.showSimilarFeedback(data.similar_feedback);
            } else {
                this.clearSimilarFeedback();
            }
        } catch (error) {
            console.error('Error checking for duplicates:', error);
            this.addBotMessage("I'm having trouble checking for similar feedback. You can still submit your feedback.");
        } finally {
            this.showTypingIndicator(false);
        }
    }
    
    showSimilarFeedback(similarItems) {
        this.similarFeedbackContainer.innerHTML = '';
        
        if (!similarItems || similarItems.length === 0) {
            return;
        }
        
        const feedbackList = document.createElement('div');
        feedbackList.className = 'similar-feedback-list';
        
        // Add header
        const header = document.createElement('div');
        header.className = 'similar-feedback-header';
        header.innerHTML = `
            <i class="fas fa-info-circle me-2"></i>
            <span>We found ${similarItems.length} similar feedback items</span>
        `;
        feedbackList.appendChild(header);
        
        // Add each similar feedback item
        similarItems.forEach(item => {
            const feedbackItem = document.createElement('div');
            feedbackItem.className = 'similar-feedback-item';
            feedbackItem.innerHTML = `
                <div class="similar-feedback-content">
                    <div class="similar-feedback-title">
                        <strong>${this.escapeHtml(item.title || 'Feedback #' + item.id)}</strong>
                        <span class="similarity-badge">${Math.round(item.similarity_score * 100)}% match</span>
                    </div>
                    <div class="similar-feedback-description">
                        ${this.truncateText(this.escapeHtml(item.description), 120)}
                    </div>
                    <div class="similar-feedback-meta">
                        <span class="badge bg-${this.getStatusBadgeClass(item.status)}">
                            ${this.formatStatus(item.status)}
                        </span>
                        <small class="text-muted ms-2">
                            ${new Date(item.submitted_date).toLocaleDateString()}
                        </small>
                    </div>
                    ${this.renderResponses(item.responses)}
                </div>
                <div class="similar-feedback-actions">
                    <a href="view-feedback.php?id=${item.id}" class="btn btn-sm btn-outline-primary" target="_blank">
                        View
                    </a>
                </div>
            `;
            feedbackList.appendChild(feedbackItem);
        });
        
        // Add submit button
        const submitContainer = document.createElement('div');
        submitContainer.className = 'similar-feedback-submit';
        submitContainer.innerHTML = `
            <button class="btn btn-success btn-sm" id="submitAnyway">
                <i class="fas fa-paper-plane me-1"></i> Submit Anyway
            </button>
            <small class="text-muted ms-2">
                Your feedback is unique? Go ahead and submit it.
            </small>
        `;
        
        feedbackList.appendChild(submitContainer);
        this.similarFeedbackContainer.appendChild(feedbackList);
        
        // Add event listener for submit button
        const submitBtn = this.similarFeedbackContainer.querySelector('#submitAnyway');
        if (submitBtn) {
            submitBtn.addEventListener('click', () => this.handleSubmitFeedback());
        }
    }
    
    renderResponses(responses) {
        if (!responses || responses.length === 0) {
            return '';
        }
        
        const latestResponse = responses[0]; // Show only the latest response
        return `
            <div class="similar-feedback-response">
                <div class="response-header">
                    <strong>${this.escapeHtml(latestResponse.admin_name || 'Admin')}</strong>
                    <small class="text-muted">
                        ${new Date(latestResponse.created_at).toLocaleDateString()}
                    </small>
                </div>
                <div class="response-content">
                    ${this.truncateText(this.escapeHtml(latestResponse.response), 100)}
                </div>
            </div>
        `;
    }
    
    clearSimilarFeedback() {
        this.similarFeedbackContainer.innerHTML = '';
    }
    
    async handleSendMessage() {
        const message = this.inputField.value.trim();
        if (!message) return;
        
        // Add user message to chat
        this.addUserMessage(message);
        this.inputField.value = '';
        
        // Show typing indicator
        this.showTypingIndicator(true);
        
        try {
            // Get the current path and construct the correct API URL
            const currentPath = window.location.pathname;
            let apiBase = '';
            
            // Check if we're in a subdirectory
            if (currentPath.includes('CAMPUS VOICE')) {
                apiBase = '/CAMPUS VOICE';
            } else if (currentPath.includes('CAMPUS%20VOICE')) {
                apiBase = '/CAMPUS%20VOICE';
            }
            
            // Encode the base path to handle spaces
            const encodedBase = apiBase ? encodeURI(apiBase) : '';
            const apiUrl = `${encodedBase}/api/check-duplicates.php`;
            
            console.log('API URL:', apiUrl); // Debug log
            
            // Make POST request with proper headers
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    description: message,
                    limit: 3
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.similar_feedback && data.similar_feedback.length > 0) {
                this.addBotMessage("I found some similar feedback that might help you:");
                
                // Add similar feedback as bot messages
                data.similar_feedback.forEach((item, index) => {
                    const responseText = item.responses && item.responses.length > 0 
                        ? `<br><br><strong>Response:</strong> ${this.truncateText(item.responses[0].response, 120)}` 
                        : '';
                        
                    this.addBotMessage(
                        `<strong>${this.escapeHtml(item.title || 'Feedback #' + item.id)}</strong> ` +
                        `<span class="badge bg-${this.getStatusBadgeClass(item.status)}">${this.formatStatus(item.status)}</span>` +
                        `<br>${this.truncateText(item.description, 150)}` +
                        responseText +
                        `<br><a href="view-feedback.php?id=${item.id}" target="_blank" class="small">View details</a>`,
                        'similar-feedback-message'
                    );
                });
                
                this.addBotMessage("Would you like to submit your feedback anyway?");
                
                // Add action buttons
                const actions = document.createElement('div');
                actions.className = 'chat-actions mt-2';
                actions.innerHTML = `
                    <button class="btn btn-sm btn-success me-2" id="submitFeedback">
                        <i class="fas fa-paper-plane me-1"></i> Submit Anyway
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="editFeedback">
                        <i class="fas fa-edit me-1"></i> Edit Feedback
                    </button>
                `;
                
                this.messagesContainer.appendChild(actions);
                this.scrollToBottom();
                
                // Add event listeners
                document.getElementById('submitFeedback').addEventListener('click', () => this.handleSubmitFeedback());
                document.getElementById('editFeedback').addEventListener('click', () => {
                    this.inputField.focus();
                    this.inputField.value = message; // Restore the message for editing
                });
                
            } else {
                this.addBotMessage("I couldn't find any similar feedback. You can submit your feedback now.");
                this.handleSubmitFeedback(message);
            }
            
        } catch (error) {
            console.error('Error:', error);
            this.addBotMessage("Sorry, I encountered an error. Please try again later.");
        } finally {
            this.showTypingIndicator(false);
        }
    }
    
    handleSubmitFeedback(message = null) {
        const feedback = message || this.inputField.value.trim();
        if (!feedback) return;
        
        // In a real implementation, you would submit the feedback to your server here
        this.addBotMessage("Thank you for your feedback! It has been submitted successfully.");
        
        // Clear input and hide similar feedback
        this.inputField.value = '';
        this.clearSimilarFeedback();
        
        // Add a small delay before showing the success message
        setTimeout(() => {
            this.addBotMessage("Is there anything else I can help you with?");
        }, 500);
    }
    
    addUserMessage(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'chat-message user';
        messageDiv.innerHTML = `
            <div class="message-content">
                <p>${this.escapeHtml(message).replace(/\n/g, '<br>')}</p>
            </div>
        `;
        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }
    
    addBotMessage(message, className = '') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message bot ${className}`.trim();
        messageDiv.innerHTML = `
            <div class="message-content">
                <p>${message}</p>
            </div>
        `;
        this.messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }
    
    showTypingIndicator(show) {
        this.isTyping = show;
        this.typingIndicator.style.display = show ? 'flex' : 'none';
        this.scrollToBottom();
    }
    
    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }
    
    // Helper methods
    escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    truncateText(text, maxLength) {
        if (!text) return '';
        if (text.length <= maxLength) return this.escapeHtml(text);
        return this.escapeHtml(text.substring(0, maxLength)) + '...';
    }
    
    formatStatus(status) {
        if (!status) return 'Pending';
        return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
    }
    
    getStatusBadgeClass(status) {
        switch (status?.toLowerCase()) {
            case 'resolved': return 'success';
            case 'in_progress': return 'primary';
            case 'closed': return 'secondary';
            case 'pending': return 'warning';
            default: return 'info';
        }
    }
    
    render() {
        // Initial render
        this.toggleChat(false);
    }
}

// Initialize the chatbot when the page loads
document.addEventListener('DOMContentLoaded', () => {
    try {
        // Only initialize on the submit-feedback page
        if (window.location.pathname.includes('submit-feedback.php')) {
            // Wait a short time to ensure all elements are loaded
            setTimeout(() => {
                try {
                    const chatbot = new FeedbackChatbot();
                    window.FeedbackChatbot = chatbot;
                    console.log('Feedback chatbot initialized successfully');
                } catch (error) {
                    console.error('Error initializing feedback chatbot:', error);
                }
            }, 500);
        }
    } catch (error) {
        console.error('Error in chatbot initialization:', error);
    }
});
