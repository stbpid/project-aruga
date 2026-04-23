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
  console.log('Page initialized - images loading from /images/ directory');
}

// Form validation logic
function setupFormValidation() {
  const codeInput = document.getElementById('code');
  const agreeCheckbox = document.getElementById('agree');
  const submitButton = document.getElementById('btn-submit');

  if (!codeInput || !agreeCheckbox || !submitButton) {
    console.error('Form elements not found!');
    return;
  }

  function validateForm() {
    const codeValid = codeInput.value.trim().length > 0;
    const agreeValid = agreeCheckbox.checked;
    submitButton.disabled = !(codeValid && agreeValid);
    console.log('Form validation:', { codeValid, agreeValid, buttonDisabled: submitButton.disabled });
  }

  codeInput.addEventListener('input', validateForm);
  agreeCheckbox.addEventListener('change', validateForm);

  // Initial validation
  validateForm();
}

// Setup submit button click handler
function setupSubmitButton() {
  const submitButton = document.getElementById('btn-submit');
  
  if (!submitButton) {
    console.error('Submit button not found!');
    return;
  }

  submitButton.addEventListener('click', function(e) {
    e.preventDefault();
    console.log('Submit button clicked');
    submitForm();
  });
}

// Submit form and redirect
function submitForm() {
  console.log('submitForm() called');
  
  const codeInput = document.getElementById('code');
  const submitButton = document.getElementById('btn-submit');
  const agreeCheckbox = document.getElementById('agree');

  if (!codeInput || !submitButton || !agreeCheckbox) {
    console.error('Form elements missing');
    alert('Error: Form elements not found. Please refresh the page.');
    return;
  }

  const interviewerCode = codeInput.value.trim();

  // Validate
  if (!interviewerCode) {
    alert('Please enter your interviewer code');
    return;
  }

  if (!agreeCheckbox.checked) {
    alert('Please accept the Data Privacy terms');
    return;
  }

  console.log('Validation passed');
  console.log('Interviewer Code:', interviewerCode);

  // Store in sessionStorage
  try {
    sessionStorage.setItem('interviewerCode', interviewerCode);
    sessionStorage.setItem('privacyAccepted', 'true');
    sessionStorage.setItem('loginTime', new Date().toISOString());
    console.log('Data stored in sessionStorage');
  } catch (e) {
    console.error('SessionStorage error:', e);
    alert('Error saving data. Please check browser settings.');
    return;
  }

  // Show processing state
  submitButton.innerHTML = 'Processing...';
  submitButton.disabled = true;

  console.log('Redirecting to /profiling');

  // Redirect to profiling page
  setTimeout(() => {
    window.location.href = '/profiling';
  }, 500);
}

// Make submitForm globally accessible (for onclick in HTML if needed)
window.submitForm = submitForm;