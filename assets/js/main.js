// Main JavaScript for Campus Voice
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for navigation links (only for # links that don't have .no-smooth-scroll class)
    document.querySelectorAll('a[href^="#"]:not(.no-smooth-scroll)').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href === '#') {
                e.preventDefault();
                return;
            }
            
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                // Update URL without triggering page reload
                if (history.pushState) {
                    history.pushState(null, null, href);
                } else {
                    window.location.hash = href;
                }
            }
        });
    });

    // Add animation on scroll
    const animateOnScroll = () => {
        const elements = document.querySelectorAll('.feature-card, .step-card, .stat-card');
        
        elements.forEach(element => {
            const elementPosition = element.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (elementPosition < windowHeight - 100) {
                element.classList.add('animate-fadeInUp');
            }
        });
    };

    // Initial check
    animateOnScroll();
    window.addEventListener('scroll', animateOnScroll);

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// AI Duplicate Detection Function
async function checkForDuplicateFeedback(feedbackText) {
    try {
        const response = await fetch('api/check_duplicate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ feedback: feedbackText })
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error checking for duplicate feedback:', error);
        return { isDuplicate: false, error: 'Failed to check for duplicates' };
    }
}

// Handle Feedback Submission
async function handleFeedbackSubmission(event) {
    event.preventDefault();
    
    const form = event.target;
    const feedbackText = form.querySelector('#feedbackText').value;
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;
    
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Checking...';
    
    try {
        const duplicateCheck = await checkForDuplicateFeedback(feedbackText);
        
        if (duplicateCheck.isDuplicate && duplicateCheck.similarPosts.length > 0) {
            showDuplicateWarning(duplicateCheck.similarPosts);
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
            return;
        }
        
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
        
        const formData = new FormData(form);
        const response = await fetch('api/submit_feedback.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', 'Feedback submitted successfully!');
            form.reset();
        } else {
            showAlert('danger', result.message || 'Failed to submit feedback');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred. Please try again.');
    } finally {
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    }
}

// Show duplicate warning
function showDuplicateWarning(similarPosts) {
    const modalHTML = `
        <div class="modal fade" id="duplicateModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Similar Feedback Found</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>We found similar feedback that has already been submitted:</p>
                        <div class="list-group">
                            ${similarPosts.map(post => `
                                <a href="view-feedback.php?id=${post.id}" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">${post.title}</h6>
                                        <small>${post.date}</small>
                                    </div>
                                    <p class="mb-1">${post.description.substring(0, 100)}...</p>
                                </a>
                            `).join('')}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="submitAnyway()">Submit Anyway</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modal = new bootstrap.Modal(document.getElementById('duplicateModal'));
    modal.show();
}

// Show alert message
function showAlert(type, message) {
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.alert-container') || document.querySelector('.container');
    if (container) {
        container.insertAdjacentHTML('afterbegin', alertHTML);
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) alert.remove();
        }, 5000);
    }
}

// Fetch social media posts
async function fetchSocialMediaPosts() {
    try {
        const response = await fetch('api/social_media.php');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching social media posts:', error);
        return [];
    }
}
