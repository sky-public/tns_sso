// Function to add SSO button
function addSSOButton() {
    // Check if the login form row exists
    const loginFormRow = document.querySelector('div.login-form-row');
    
    if (loginFormRow) {
        // Find the original login button
        const originalButton = document.querySelector('button.tns-button.lt-full-width[type="submit"]');
        
        if (originalButton) {
            // Check if SSO button already exists to prevent duplicates
            if (document.querySelector('.sso-button')) {
                console.log('SSO button already exists');
                return;
            }
            
            // Clone the button
            const ssoButton = originalButton.cloneNode(true);
            
            // Create Microsoft logo (4 squares)
            const msLogo = document.createElement('span');
            msLogo.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 8px; vertical-align: middle;">
                    <rect x="0" y="0" width="7" height="7" fill="#F25022"/>
                    <rect x="8" y="0" width="7" height="7" fill="#7FBA00"/>
                    <rect x="0" y="8" width="7" height="7" fill="#00A4EF"/>
                    <rect x="8" y="8" width="7" height="7" fill="#FFB900"/>
                </svg>
            `;
            
            // Modify the cloned button
            ssoButton.innerHTML = ''; // Clear existing content
            ssoButton.appendChild(msLogo);
            ssoButton.appendChild(document.createTextNode('Anmelden mit SSO'));
            ssoButton.type = 'button'; // Change type to prevent form submission
            ssoButton.classList.add('sso-button'); // Add identifier class
            
            // Add click event to redirect to SSO
            ssoButton.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = '/sso';
            });
            
            // Create a spacer div
            const spacer = document.createElement('div');
            spacer.style.height = '10px'; // Adjust height as needed
            spacer.classList.add('sso-spacer');
            
            // Insert the spacer after the original button
            originalButton.parentNode.insertBefore(spacer, originalButton.nextSibling);
            
            // Insert the SSO button after the spacer
            spacer.parentNode.insertBefore(ssoButton, spacer.nextSibling);
            console.log('SSO button added successfully');
        } else {
            console.log('Original button not found');
        }
    } else {
        console.log('Login form row not found');
    }
}

// Try multiple timing strategies to ensure the button gets added
// Strategy 1: If DOM is already loaded, run immediately
if (document.readyState === 'loading') {
    // DOM is still loading
    document.addEventListener('DOMContentLoaded', addSSOButton);
} else {
    // DOM is already ready
    addSSOButton();
}

// Strategy 2: Also try after a short delay to catch dynamically loaded content
setTimeout(addSSOButton, 100);
setTimeout(addSSOButton, 500);