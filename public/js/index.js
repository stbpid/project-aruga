// Load images on page load
document.addEventListener('DOMContentLoaded', async function() {
  try {
    const response = await fetch('/api/get-assets.php');
    const assets = await response.json();
    setImages(assets);
  } catch (error) {
    console.error('Error loading assets:', error);
    // Use default images if API fails
    setImages({
      navLogo: 'https://lh3.googleusercontent.com/d/1PXQxz3l0uxl0zEo1Lw5R4Qrd41yXbQ6V',
      heroImage: 'https://lh3.googleusercontent.com/d/1m7NYCL9E3bDRR1XKcrPtV4RPLg9BKW2a',
      formLogo: 'https://lh3.googleusercontent.com/d/1q7Qgj0pJp6CtGVbMvOdQTQM3BKAuy4mw'
    });
  }
  
  // Initialize form handlers
  initializeForm();
});

function setImages(assets) {
  if (assets.navLogo) {
    document.getElementById('img-nav-logo').src = assets.navLogo;
  }
  if (assets.heroImage) {
    document.getElementById('img-hero').src = assets.heroImage;
  }
  if (assets.formLogo) {
    document.getElementById('img-form-logo').src = assets.formLogo;
  }
}

function initializeForm() {
  const code = document.getElementById('code');
  const agree = document.getElementById('agree');
  const btn = document.getElementById('btn-submit');

  // Enable/disable submit button based on validation
  function checkFormValidity() {
    const isCodeValid = code.value.trim().length > 0;
    const isAgreed = agree.checked;
    btn.disabled = !(isCodeValid && isAgreed);
  }

  // Event listeners
  code.addEventListener('input', checkFormValidity);
  agree.addEventListener('change', checkFormValidity);

  // Submit handler
  btn.addEventListener('click', function() {
    const originalText = btn.innerHTML;
    btn.innerHTML = "Processing...";
    btn.disabled = true;

    // Store data in sessionStorage
    sessionStorage.setItem('interviewerCode', code.value.trim());
    sessionStorage.setItem('privacyAccepted', 'true');
    sessionStorage.setItem('loginTime', new Date().toISOString());

    // Redirect to profiling page
    setTimeout(() => {
      window.location.href = '/profiling';
    }, 500);
  });

  // Allow Enter key to submit
  code.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !btn.disabled) {
      btn.click();
    }
  });
}