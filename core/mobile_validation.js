/**
 * Mobile number validation
 * Format: 10 digits, starts with 07
 * Example: 0712345678
 */

function validateMobileNumber(mobile) {
    // Remove any spaces or special characters
    const cleaned = mobile.replace(/\D/g, '');
    
    // Check if it's exactly 10 digits and starts with 07
    const regex = /^07\d{8}$/;
    return regex.test(cleaned);
}

function formatMobileNumber(mobile) {
    // Remove any non-digit characters
    const cleaned = mobile.replace(/\D/g, '');
    
    // If it's valid, return it; otherwise return the original
    if (validateMobileNumber(cleaned)) {
        return cleaned;
    }
    return mobile;
}

// Add validation to mobile input fields
document.addEventListener('DOMContentLoaded', function() {
    const mobileInputs = document.querySelectorAll('input[name="mobile"], input[name="mobile2"], input[type="tel"]');
    
    mobileInputs.forEach(input => {
        // Add input event listener for real-time validation
        input.addEventListener('blur', function() {
            if (this.value && !validateMobileNumber(this.value)) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
                
                // Show error message if it doesn't exist
                let errorMsg = this.parentElement.querySelector('.invalid-feedback');
                if (!errorMsg) {
                    errorMsg = document.createElement('div');
                    errorMsg.className = 'invalid-feedback d-block';
                    errorMsg.textContent = 'Mobile number must be 10 digits and start with 07 (e.g., 0712345678)';
                    this.parentElement.appendChild(errorMsg);
                }
            } else if (this.value && validateMobileNumber(this.value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                
                // Remove error message if it exists
                const errorMsg = this.parentElement.querySelector('.invalid-feedback');
                if (errorMsg) {
                    errorMsg.remove();
                }
            }
        });
        
        // Add input event for live feedback
        input.addEventListener('input', function() {
            if (this.value.length === 10) {
                if (validateMobileNumber(this.value)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            }
        });
    });
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { validateMobileNumber, formatMobileNumber };
}
