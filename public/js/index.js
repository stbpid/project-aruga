// ===========================
// INDEX PAGE - LOGIN LOGIC
// ===========================

document.addEventListener('DOMContentLoaded', function() {
  initializePage();
  setupFormValidation();
  setupSubmitButton();
});

// Initialize page
function initializePage() {
  console.log('✅ Page initialized - images loading from /images/ directory');
}

// Form validation logic
function setupFormValidation() {
  const codeInput = document.getElementById('code');
  const agreeCheckbox = document.getElementById('agree');
  const submitButton = document.getElementById('btn-submit');

  if (!codeInput || !agreeCheckbox || !submitButton) {
    console.error('❌ Form elements not found!');
    return;
  }

  function validateForm() {
    const codeValid = codeInput.value.trim().length === 8;
    const agreeValid = agreeCheckbox.checked;
    submitButton.disabled = !(codeValid && agreeValid);
  }

  // Convert input to uppercase and limit to 8 characters
  codeInput.addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 8);
    validateForm();
  });

  agreeCheckbox.addEventListener('change', validateForm);

  // Initial validation
  validateForm();
}

// Setup submit button click handler
function setupSubmitButton() {
  const submitButton = document.getElementById('btn-submit');
  
  if (!submitButton) {
    console.error('❌ Submit button not found!');
    return;
  }

  submitButton.addEventListener('click', function(e) {
    e.preventDefault();
    console.log('🔵 Submit button clicked');
    submitForm();
  });
}

// Submit form and validate interviewer
async function submitForm() {
  console.log('🔵 submitForm() called');
  
  const codeInput = document.getElementById('code');
  const submitButton = document.getElementById('btn-submit');
  const agreeCheckbox = document.getElementById('agree');

  if (!codeInput || !submitButton || !agreeCheckbox) {
    console.error('❌ Form elements missing');
    alert('Error: Form elements not found. Please refresh the page.');
    return;
  }

  const interviewerCode = codeInput.value.trim().toUpperCase();

  // Validate
  if (!interviewerCode) {
    alert('Please enter your interviewer code');
    return;
  }

  if (interviewerCode.length !== 8) {
    alert('Interviewer code must be exactly 8 characters');
    return;
  }

  if (!agreeCheckbox.checked) {
    alert('Please accept the Data Privacy terms');
    return;
  }

  console.log('✅ Validation passed');
  console.log('Interviewer Code:', interviewerCode);

  // Show processing state
  const originalText = submitButton.innerHTML;
  submitButton.innerHTML = 'Validating...';
  submitButton.disabled = true;

  try {
    // Call validation API
    console.log('🔵 Calling validation API...');
    
    const response = await fetch('/api/validate-interviewer.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        interviewer_code: interviewerCode
      })
    });

    const result = await response.json();
    console.log('API Response:', result);

    if (result.success && result.data) {
      console.log('✅ Validation successful');
      
      // Store session data in sessionStorage
      sessionStorage.setItem('session_id', result.data.session_id);
      sessionStorage.setItem('interviewer_id', result.data.interviewer_id);
      sessionStorage.setItem('interviewer_code', result.data.interviewer_code);
      sessionStorage.setItem('interviewer_name', result.data.full_name);
      sessionStorage.setItem('interviewer_region', result.data.region);
      sessionStorage.setItem('interviewer_province', result.data.province || '');
      sessionStorage.setItem('interviewer_office', result.data.office || '');
      sessionStorage.setItem('interviewer_position', result.data.position || '');
      sessionStorage.setItem('session_started_at', result.data.started_at);
      sessionStorage.setItem('privacyAccepted', 'true');
      sessionStorage.setItem('loginTime', new Date().toISOString());

      console.log('✅ Session data stored in sessionStorage');

      // Show success message
      submitButton.innerHTML = 'Success! Redirecting...';
      
      // Redirect to profiling page
      setTimeout(() => {
        window.location.href = '/profiling';
      }, 500);

    } else {
      // Validation failed
      console.error('❌ Validation failed:', result.message);
      
      // Show error message
      alert(result.message || 'Invalid interviewer code. Please check and try again.');
      
      // Reset button
      submitButton.innerHTML = originalText;
      submitButton.disabled = false;
      
      // Clear the input
      codeInput.value = '';
      codeInput.focus();
    }

  } catch (error) {
    console.error('❌ Network error:', error);
    
    // Show error message
    alert('Connection error. Please check your internet and try again.');
    
    // Reset button
    submitButton.innerHTML = originalText;
    submitButton.disabled = false;
  }
}

// Make submitForm globally accessible (for onclick in HTML if needed)
window.submitForm = submitForm;