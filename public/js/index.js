// ===========================
// INDEX PAGE - LOGIN LOGIC
// ===========================

document.addEventListener('DOMContentLoaded', function() {
  initializePage();
  setupFormValidation();
});

// Initialize page
function initializePage() {
  // Images are now hardcoded in HTML with /images/ paths
  // No API call needed anymore - images load directly
  console.log('Page initialized - images loading from /images/ directory');
}

// Form validation logic
function setupFormValidation() {
  const codeInput = document.getElementById('code');
  const agreeCheckbox = document.getElementById('agree');
  const submitButton = document.getElementById('btn-submit');

  function validateForm() {
    const codeValid = codeInput.value.trim().length > 0;
    const agreeValid = agreeCheckbox.checked;
    submitButton.disabled = !(codeValid && agreeValid);
  }

  codeInput.addEventListener('input', validateForm);
  agreeCheckbox.addEventListener('change', validateForm);

  // Initial validation
  validateForm();
}

// Submit form
function submitForm() {
  const codeInput = document.getElementById('code');
  const submitButton = document.getElementById('btn-submit');
  const interviewerCode = codeInput.value.trim();

  // Validate code exists
  if (!interviewerCode) {
    alert('Please enter your interviewer code');
    return;
  }

  // Store in sessionStorage
  sessionStorage.setItem('interviewerCode', interviewerCode);
  sessionStorage.setItem('privacyAccepted', 'true');
  sessionStorage.setItem('loginTime', new Date().toISOString());

  // Show processing state
  submitButton.innerHTML = 'Processing...';
  submitButton.disabled = true;

  // Redirect to profiling page
  setTimeout(() => {
    window.location.href = '/profiling';
  }, 500);
}