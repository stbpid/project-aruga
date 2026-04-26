// ============================================================================
// PROJECT ARUGA - PROFILING TOOL JAVASCRIPT
// ============================================================================

// Global Variables
let memberCount = 1;
let globalData = {};
let locationData = {};
let totalSeconds = 0;
const MAX_SECONDS = 20 * 60; // 20 minutes
let timerInterval;

// ============================================================================
// INITIALIZATION
// ============================================================================

document.addEventListener('DOMContentLoaded', async function() {
  // Check if user came from index page
  checkAuthentication();

  // Initialize timer
  startSessionTimer();
  
  // Load data FIRST before rendering steps
  await Promise.all([
    loadDropdowns(),
    loadLocations()
  ]);
  
  // Wait a bit for data to be available
  await new Promise(resolve => setTimeout(resolve, 500));
  
  // NOW load the steps
  loadAllSteps();
  updateProgress(1);
});

// ============================================================================
// AUTHENTICATION CHECK
// ============================================================================

function checkAuthentication() {
  const sessionId = sessionStorage.getItem('session_id');
  const interviewerCode = sessionStorage.getItem('interviewer_code');
  const privacyAccepted = sessionStorage.getItem('privacyAccepted');

  if (!sessionId || !interviewerCode || !privacyAccepted) {
    window.location.href = '/';
    return;
  }

  updateHeaderInfo();
}

// Update header with interviewer info
function updateHeaderInfo() {
  const interviewerCode = sessionStorage.getItem('interviewer_code');
  const interviewerName = sessionStorage.getItem('interviewer_name');

  const nameElement = document.getElementById('interviewer-code');
  if (nameElement) {
    nameElement.textContent = interviewerName || interviewerCode || '---';
    nameElement.title = interviewerCode || '';
  }
}

// ============================================================================
// SESSION TIMER
// ============================================================================

function startSessionTimer() {
  const timerEl = document.getElementById('session-timer');
  
  timerInterval = setInterval(() => {
    if (totalSeconds >= MAX_SECONDS) {
      clearInterval(timerInterval);
      showLogoutModal();
      return;
    }

    totalSeconds++;
    
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    
    const formattedTime = 
      String(minutes).padStart(2, '0') + ":" + 
      String(seconds).padStart(2, '0');

    if (timerEl) {
      timerEl.innerText = formattedTime;
    }
  }, 1000);
}

// ============================================================================
// LOGOUT MODAL
// ============================================================================

function showLogoutModal() {
  document.getElementById('logout-modal').classList.remove('hidden');
}

function hideLogoutModal() {
  document.getElementById('logout-modal').classList.add('hidden');
}

function confirmLogout() {
  sessionStorage.clear();
  window.location.href = '/';
}

// ============================================================================
// DATA LOADING
// ============================================================================

async function loadDropdowns() {
  try {
    const response = await fetch('/api/get-options.php');

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    globalData = data;
    return data;
  } catch (error) {
    // Set empty defaults so app doesn't crash
    globalData = {
      List_Religion: [],
      List_IP: [],
      List_Education: [],
      List_Relationship: [],
      List_Disability: [],
      List_Illness: [],
      List_Extension: [],
      List_Occupation: [],
      List_Occupation_Class: [],
      List_Materials: [],
      List_Tenure: [],
      List_Electricity: [],
      List_Water: [],
      List_Toilet: [],
      List_Garbage: []
    };
    return globalData;
  }
}

async function loadLocations() {
  try {
    const response = await fetch('/api/get-locations.php');

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    locationData = data;
    return data;
  } catch (error) {
    locationData = {};
    return locationData;
  }
}

// ============================================================================
// PROGRESS BAR
// ============================================================================

function updateProgress(step) {
  const bar = document.getElementById('progress-bar');
  const indicator = document.getElementById('step-indicator');
  const label = document.getElementById('step-label');
  
  const stepLabels = {
    1: 'Pre-Qualification',
    2: 'Respondent Profile',
    3: 'Child Profile',
    4: 'Family Profile',
    5: 'Socio Economic',
    6: 'Health',
    7: 'Education',
    8: 'Economic Capacity',
    9: 'Service Availment',
    10: 'Assessment',
    11: 'Review'
  };
  
  let percent = (step / 11) * 100;
  if (step === 11) percent = 100;
  
  bar.style.width = percent + '%';
  indicator.innerText = `STEP ${step === 11 ? 'REVIEW' : step} OF 10`;
  label.innerText = stepLabels[step] || '';
}

// ============================================================================
// NAVIGATION
// ============================================================================

function goToStep(stepNumber) {
  document.querySelectorAll('.step-section').forEach(el => {
    el.classList.add('hidden-step');
    el.style.opacity = 0;
  });
  
  const target = document.getElementById('step-' + stepNumber);
  if (target) {
    target.classList.remove('hidden-step');
    setTimeout(() => target.style.opacity = 1, 50);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  
  const progressSection = document.getElementById('progress-section');
  if (stepNumber === 11) {
    progressSection.style.display = 'none';
  } else {
    progressSection.style.display = 'block';
    updateProgress(stepNumber);
  }
}

// ============================================================================
// LOAD ALL STEPS
// ============================================================================

function loadAllSteps() {
  const container = document.getElementById('steps-container');
  container.innerHTML = `
    ${getStep1HTML()}
    ${getStep2HTML()}
    ${getStep3HTML()}
    ${getStep4HTML()}
    ${getStep5HTML()}
    ${getStep6HTML()}
    ${getStep7HTML()}
    ${getStep8HTML()}
    ${getStep9HTML()}
    ${getStep10HTML()}
    ${getStep11HTML()}
  `;
  
  // Initialize all interactive elements
  initializeAllSteps();
}

// ============================================================================
// STEP 1: PRE-QUALIFICATION
// ============================================================================

function getStep1HTML() {
  return `
    <div id="step-1" class="step-section w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Pre-Qualification</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Confirm 4Ps membership to help us coordinate your benefits.</p>
      </div>
      
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-start gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">assignment_ind</span>
          </div>
          <div>
            <h2 class="font-bold text-brand-dark text-base sm:text-lg leading-tight">4Ps Membership</h2>
            <p class="text-gray-500 text-xs sm:text-sm mt-0.5">Are you a member of the Pantawid Pamilyang Pilipino Program?</p>
          </div>
        </div>
        
        <fieldset class="space-y-3 mb-5">
          <label class="radio-card relative block w-full border border-gray-200 rounded-lg p-1 cursor-pointer hover:border-blue-300 transition-all select-none">
            <input type="radio" name="membership" value="Yes" class="peer sr-only" onchange="toggleId(true)" checked>
            <div class="p-2 flex flex-col border-transparent transition-all">
              <span class="font-bold text-brand-dark block text-xs sm:text-sm">Yes</span>
              <span class="text-[10px] sm:text-xs text-gray-500">I am a member of the 4Ps Program</span>
            </div>
          </label>
          
          <label class="radio-card relative block w-full border border-gray-200 rounded-lg p-1 cursor-pointer hover:border-blue-300 transition-all select-none">
            <input type="radio" name="membership" value="No" class="peer sr-only" onchange="toggleId(false)">
            <div class="p-2 flex flex-col border-transparent transition-all">
              <span class="font-bold text-brand-dark block text-xs sm:text-sm">No</span>
              <span class="text-[10px] sm:text-xs text-gray-500">I am not a 4Ps member</span>
            </div>
          </label>
        </fieldset>
        
        <div id="id-container" class="transition-opacity duration-300">
          <label class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">Household ID</label>
          <div class="flex items-center gap-2 bg-white rounded border border-gray-300 px-3 py-1.5 h-9 focus-within:ring-1 focus-within:ring-brand-blue transition-all">
            <span class="material-symbols-outlined text-[16px] text-gray-400">badge</span>
            <input id="household-id" type="text" maxlength="18" class="w-full text-xs sm:text-sm outline-none text-gray-800 placeholder-gray-400 bg-transparent" placeholder="Enter 18-digit ID number">
          </div>
          <p class="text-[10px] sm:text-xs text-brand-blue mt-1">Found on your 4Ps ID card.</p>
        </div>
      </section>
      
      <div class="w-full flex justify-end pb-6">
        <button onclick="goToStep(2)" class="w-full sm:w-auto px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover shadow-md transition-all flex items-center justify-center gap-2">
          Next: Respondent Profile 
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 2: RESPONDENT PROFILE
// ============================================================================

function getStep2HTML() {
  return `
    <div id="step-2" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Respondent Profile</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Provide your personal details as the individual completing this assessment.</p>
      </div>
      
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-8 flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">account_circle</span>
          </div>
          <h2 class="font-bold text-brand-dark text-base sm:text-lg">Respondent Profile</h2>
        </div>
        
        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">Name of Respondent</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="material-symbols-outlined text-[16px] text-gray-400">person</span>
                </div>
                <input type="text" id="resp-name" class="w-full h-9 pl-10 pr-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Enter full name">
              </div>
            </div>
            
            <div>
              <label class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">Relationship to the Child</label>
              <div class="relative">
                <select id="dd-relationship" class="google-dropdown-style w-full h-9 pl-3 pr-10 text-xs sm:text-sm outline-none bg-white text-gray-800 invalid:text-gray-400">
                  <option value="" disabled selected>Loading...</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">Email Address</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="material-symbols-outlined text-[16px] text-gray-400">mail</span>
                </div>
                <input type="email" id="resp-email" class="w-full h-9 pl-10 pr-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="name@example.com">
              </div>
            </div>
            
            <div>
              <label class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">Contact Number</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="material-symbols-outlined text-[16px] text-gray-400">phone</span>
                </div>
                <input type="tel" id="resp-contact" class="w-full h-9 pl-10 pr-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="0912 345 6789">
              </div>
            </div>
          </div>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(1)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back
        </button>
        <button onclick="goToStep(3)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          Next: Child Profile 
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 3: CHILD PROFILE (Part 1 of the file - continues below)
// ============================================================================

function getStep3HTML() {
  return `
    <div id="step-3" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Child Profile</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Provide the child's personal details, demographics, and specific health conditions.</p>
      </div>
      
      <!-- Personal Information -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-8 flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">face</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base sm:text-lg">Personal Information</h3>
        </div>
        
        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="sm:col-span-1">
              <label class="block text-xs font-bold text-brand-dark mb-1">First Name</label>
              <input type="text" id="child-fname" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="First Name">
            </div>
            <div class="sm:col-span-1">
              <label class="block text-xs font-bold text-brand-dark mb-1">Middle Name</label>
              <input type="text" id="child-mname" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Middle Name">
            </div>
            <div class="sm:col-span-1">
              <label class="block text-xs font-bold text-brand-dark mb-1">Last Name</label>
              <input type="text" id="child-lname" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Last Name">
            </div>
            <div class="sm:col-span-1">
              <label class="block text-xs font-bold text-brand-dark mb-1">Extension</label>
              <div class="relative">
                <select id="dd-extension" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400">
                  <option value="" disabled selected>Loading...</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Region</label>
              <div class="relative">
                <select id="child-region" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="updateProvinces()">
                  <option value="" disabled selected>Select Region</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Province</label>
              <div class="relative">
                <select id="child-province" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="updateCities()" disabled>
                  <option value="" disabled selected>Select Province</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">City/Municipality</label>
              <div class="relative">
                <select id="child-city" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="updateBarangays()" disabled>
                  <option value="" disabled selected>Select City</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Barangay</label>
              <div class="relative">
                <select id="child-barangay" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" disabled>
                  <option value="" disabled selected>Select Barangay</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Street Address</label>
              <input type="text" id="child-street" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="House No., Street">
            </div>
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Contact Number</label>
              <input type="tel" id="child-contact" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="09XX XXX XXXX">
            </div>
          </div>
        </div>
      </section>
      
      <!-- Demographics -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-8 flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">manage_accounts</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base sm:text-lg">Demographics</h3>
        </div>
        
        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Date of Birth</label>
              <div class="relative">
                <input type="date" id="child-dob" min="2008-01-01" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none text-gray-600">
              </div>
              <p class="text-[10px] text-gray-400 mt-1 ml-1">Must be year 2008 or later.</p>
            </div>
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Sex</label>
              <div class="slide-toggle-container h-9 w-full sm:w-2/3">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this)">
                  <input type="radio" name="sex" value="Male" class="hidden" checked> 
                  <span>Male</span>
                </label>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this)">
                  <input type="radio" name="sex" value="Female" class="hidden"> 
                  <span>Female</span>
                </label>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Religion</label>
              <div class="relative">
                <select id="dd-religion" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'rel-other')">
                  <option value="" disabled selected>Loading...</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
                </div>
              </div>
              <input id="rel-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
            </div>
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">IP Membership</label>
              <div class="relative">
                <select id="dd-ip" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'ip-other')">
                  <option value="" disabled selected>Loading...</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
                </div>
              </div>
              <input id="ip-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
            </div>
          </div>
        </div>
      </section>
      
      <!-- Condition & Education -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-8 flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">school</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base sm:text-lg">Condition & Education</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Highest Educational Attainment</label>
            <div class="relative">
              <select id="dd-education" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'edu-other')">
                <option value="" disabled selected>Loading...</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
            <input id="edu-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
          
          <div class="relative">
            <label class="block text-xs font-bold text-brand-dark mb-1">Disability or Special Needs (Select all that apply)</label>
            <button type="button" onclick="toggleDropdown('dd-disability')" class="w-full h-9 px-3 text-left bg-white border border-gray-300 rounded focus:ring-1 focus:ring-brand-blue outline-none flex justify-between items-center text-xs sm:text-sm">
              <span id="disability-display" class="truncate text-gray-400">Select options...</span>
              <span class="material-symbols-outlined text-[18px] text-gray-500 ml-2 flex-shrink-0">expand_more</span>
            </button>
            <div id="dd-disability" class="hidden absolute z-10 w-full google-menu mt-1 max-h-60 overflow-y-auto dropdown-scroll">
              <!-- Will be populated by JavaScript -->
            </div>
          </div>
          
          <div class="relative">
            <label class="block text-xs font-bold text-brand-dark mb-1">Critical Illness (Select all that apply)</label>
            <button type="button" onclick="toggleDropdown('dd-illness')" class="w-full h-9 px-3 text-left bg-white border border-gray-300 rounded focus:ring-1 focus:ring-brand-blue outline-none flex justify-between items-center text-xs sm:text-sm">
              <span id="illness-display" class="truncate text-gray-400">Select options...</span>
              <span class="material-symbols-outlined text-[18px] text-gray-500 ml-2 flex-shrink-0">expand_more</span>
            </button>
            <div id="dd-illness" class="hidden absolute z-10 w-full google-menu mt-1 max-h-60 overflow-y-auto dropdown-scroll">
              <!-- Will be populated by JavaScript -->
            </div>
            <input id="illness-other-input" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(2)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back
        </button>
        <button onclick="goToStep(4)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          Next: Family Profile 
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 4: FAMILY PROFILE
// ============================================================================

function getStep4HTML() {
  return `
    <div id="step-4" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Family Profile</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Provide details regarding family members living in the household.</p>
      </div>
      
      <section class="w-full bg-blue-50 rounded-xl border border-blue-200 shadow-sm p-5 sm:p-6 flex items-center justify-between">
        <div>
          <h3 class="font-bold text-brand-dark text-base sm:text-lg">Current Family Size</h3>
          <p class="text-xs text-gray-500">Auto-calculated based on listed members.</p>
        </div>
        <div class="w-20">
          <input id="total-family-size" type="number" readonly value="1" class="w-full h-12 text-center text-xl font-bold text-brand-blue bg-white rounded-lg border border-blue-100 outline-none">
        </div>
      </section>
      
      <div id="family-members-container" class="space-y-5">
        <!-- Family member cards will be added here -->
      </div>
      
      <button onclick="addFamilyMember()" class="w-full py-3 border-2 border-dashed border-brand-blue text-brand-blue rounded-xl font-bold text-sm hover:bg-blue-50 transition-colors flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-[20px]">person_add</span>
        Add Another Family Member
      </button>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(3)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back
        </button>
        <button onclick="goToStep(5)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          <span>Next: Socio Economic</span> 
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 5: SOCIO ECONOMIC
// ============================================================================

function getStep5HTML() {
  return `
    <div id="step-5" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Socio Economic</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Detail the household's living conditions, assets, and financial resources.</p>
      </div>
      
      <!-- Housing Condition -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">home</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Housing Condition</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">What type of construction materials are the roofs and outer walls made of?</label>
            <div class="relative">
              <select id="dd-materials" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'mat-other')">
                <option value="" disabled selected>Select Materials</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
            <input id="mat-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
          
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">What is the tenure status of the house and lot does the family have?</label>
            <div class="relative">
              <select id="dd-tenure" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'tenure-other')">
                <option value="" disabled selected>Select Status</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
            <input id="tenure-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
          
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Are there any modifications in the house to accommodate the child's disability?</label>
            <div class="slide-toggle-container h-8 w-full sm:w-1/3 mb-2">
              <div class="slide-toggle-slider"></div>
              <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('mod-specify').classList.remove('hidden')">
                <input type="radio" name="modifications" value="Yes" class="hidden"> 
                <span>Yes</span>
              </label>
              <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('mod-specify').classList.add('hidden')">
                <input type="radio" name="modifications" value="No" class="hidden" checked> 
                <span>No</span>
              </label>
            </div>
            <input id="mod-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
          
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">What is the main source of electricity in the dwelling place?</label>
            <div class="relative">
              <select id="dd-electricity" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'elec-other')">
                <option value="" disabled selected>Select Source</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
            <input id="elec-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
        </div>
      </section>
      
      <!-- Water Supply -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">water_drop</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Water Supply</h3>
        </div>
        
        <div>
          <label class="block text-xs font-bold text-brand-dark mb-1">What is your family's main source of water supply?</label>
          <div class="relative">
            <select id="dd-water" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'water-other')">
              <option value="" disabled selected>Select Water Source</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
              <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
            </div>
          </div>
          <input id="water-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
        </div>
      </section>
      
      <!-- Sanitation -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">recycling</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Sanitation</h3>
        </div>
        
        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Main type of toilet facility</label>
              <div class="relative">
                <select id="dd-toilet" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'toilet-other')">
                  <option value="" disabled selected>Select Toilet Type</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
                </div>
              </div>
              <input id="toilet-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
            </div>
            <div>
              <label class="block text-xs font-bold text-brand-dark mb-1">Is the toilet accessible for the child?</label>
              <div class="slide-toggle-container h-9 w-full">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this)">
                  <input type="radio" name="toilet-access" value="Yes" class="hidden" checked> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this)">
                  <input type="radio" name="toilet-access" value="No" class="hidden"> 
                  <span>No</span>
                </label>
              </div>
            </div>
          </div>
          
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Main system of garbage disposal</label>
            <div class="relative">
              <select id="dd-garbage" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'garbage-other')">
                <option value="" disabled selected>Select System</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
            <input id="garbage-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(4)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back
        </button>
        <button onclick="goToStep(6)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          <span>Next: Health</span> 
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 6: HEALTH
// ============================================================================

function getStep6HTML() {
  return `
    <div id="step-6" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Health</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Provide information on the child's medical condition and healthcare accessibility.</p>
      </div>
      
      <!-- General Health -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">health_and_safety</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">General Health</h3>
        </div>
        
        <div class="space-y-4">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <label class="text-xs font-bold text-brand-dark flex-1">Has the child received all recommended vaccinations?</label>
            <div class="slide-toggle-container h-8 w-32">
              <div class="slide-toggle-slider"></div>
              <label class="slide-toggle-label text-white" onclick="toggleBtn(this)">
                <input type="radio" name="vaccines" value="Yes" class="hidden" checked> 
                <span>Yes</span>
              </label>
              <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this)">
                <input type="radio" name="vaccines" value="No" class="hidden"> 
                <span>No</span>
              </label>
            </div>
          </div>
          
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Does the child have any ongoing health conditions?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('health-cond-specify').classList.remove('hidden')">
                  <input type="radio" name="health_cond" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('health-cond-specify').classList.add('hidden')">
                  <input type="radio" name="health_cond" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="health-cond-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
        </div>
      </section>
      
      <!-- Monthly Health Expenses -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">monetization_on</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Monthly Health Expenses</h3>
        </div>
        
        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-[10px] font-bold text-gray-500 mb-1">Food</label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold pointer-events-none text-xs">₱</span>
                <input type="text" id="exp-food" class="w-full h-9 pl-6 pr-3 rounded border border-gray-300 text-xs focus:ring-1 focus:ring-brand-blue outline-none text-right placeholder-gray-400" placeholder="0" oninput="validateExpense(this)">
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-500 mb-1">Medication</label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold pointer-events-none text-xs">₱</span>
                <input type="text" id="exp-med" class="w-full h-9 pl-6 pr-3 rounded border border-gray-300 text-xs focus:ring-1 focus:ring-brand-blue outline-none text-right placeholder-gray-400" placeholder="0" oninput="validateExpense(this)">
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-500 mb-1">Therapy</label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold pointer-events-none text-xs">₱</span>
                <input type="text" id="exp-therapy" class="w-full h-9 pl-6 pr-3 rounded border border-gray-300 text-xs focus:ring-1 focus:ring-brand-blue outline-none text-right placeholder-gray-400" placeholder="0" oninput="validateExpense(this)">
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-[10px] font-bold text-gray-500 mb-1">Hygiene-related needs</label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold pointer-events-none text-xs">₱</span>
                <input type="text" id="exp-hygiene" class="w-full h-9 pl-6 pr-3 rounded border border-gray-300 text-xs focus:ring-1 focus:ring-brand-blue outline-none text-right placeholder-gray-400" placeholder="0" oninput="validateExpense(this)">
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-500 mb-1">Assistive Device Maint.</label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold pointer-events-none text-xs">₱</span>
                <input type="text" id="exp-assist" class="w-full h-9 pl-6 pr-3 rounded border border-gray-300 text-xs focus:ring-1 focus:ring-brand-blue outline-none text-right placeholder-gray-400" placeholder="0" oninput="validateExpense(this)">
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-gray-500 mb-1">Other health needs</label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold pointer-events-none text-xs">₱</span>
                <input type="text" id="exp-other" class="w-full h-9 pl-6 pr-3 rounded border border-gray-300 text-xs focus:ring-1 focus:ring-brand-blue outline-none text-right placeholder-gray-400" placeholder="0" oninput="validateExpense(this)">
              </div>
            </div>
          </div>
          
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex justify-between items-center">
            <div>
              <h4 class="font-bold text-brand-dark text-sm">Total Health Expense</h4>
              <p class="text-[10px] text-gray-500">Sum of all monthly costs</p>
            </div>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-brand-blue font-bold pointer-events-none text-lg">₱</span>
              <input id="exp-total" type="text" readonly class="bg-transparent text-right font-bold text-brand-blue text-lg outline-none w-32" value="0">
            </div>
          </div>
        </div>
      </section>
      
      <!-- Access to Health Services -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">local_hospital</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Access to Health Services</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Has the child availed health services in the past 6 months?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('avail-specify').classList.remove('hidden')">
                  <input type="radio" name="avail_services" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('avail-specify').classList.add('hidden')">
                  <input type="radio" name="avail_services" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="avail-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
          
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <label class="text-xs font-bold text-brand-dark flex-1">Is the health facility accessible for the child?</label>
            <div class="slide-toggle-container h-8 w-32">
              <div class="slide-toggle-slider"></div>
              <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this)">
                <input type="radio" name="facility_access" value="Yes" class="hidden"> 
                <span>Yes</span>
              </label>
              <label class="slide-toggle-label text-white" onclick="toggleBtn(this)">
                <input type="radio" name="facility_access" value="No" class="hidden" checked> 
                <span>No</span>
              </label>
            </div>
          </div>
          
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Are there any barriers to accessing health care services?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('barrier-specify').classList.remove('hidden')">
                  <input type="radio" name="barriers" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('barrier-specify').classList.add('hidden')">
                  <input type="radio" name="barriers" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="barrier-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(5)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back
        </button>
        <button onclick="goToStep(7)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          <span>Next: Education</span> 
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 7: EDUCATION
// ============================================================================

function getStep7HTML() {
  return `
    <div id="step-7" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Education</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Identify the child's schooling status and the physical accessibility of their learning environment.</p>
      </div>
      
      <!-- Educational Status -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">school</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Educational Status</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Is the child currently enrolled in school?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); toggleEnrollment(true)">
                  <input type="radio" name="enrolled" value="Yes" class="hidden" checked> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); toggleEnrollment(false)">
                  <input type="radio" name="enrolled" value="No" class="hidden"> 
                  <span>No</span>
                </label>
              </div>
            </div>
            
            <div id="enrollment-yes" class="">
              <label class="block text-xs font-bold text-brand-dark mb-1">Grade/Year Level</label>
              <input type="text" id="grade-level" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Enter Grade/Year Level">
            </div>
            <div id="enrollment-no" class="hidden">
              <label class="block text-xs font-bold text-brand-dark mb-1">Why not?</label>
              <input type="text" id="not-enrolled-reason" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
            </div>
          </div>
        </div>
      </section>
      
      <!-- School Accessibility -->
      <section id="form-school-access" class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6 transition-all duration-300">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">accessible</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">School Accessibility</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Is the school equipped with physically accessibility features?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('school-access-specify').classList.remove('hidden')">
                  <input type="radio" name="school_features" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('school-access-specify').classList.add('hidden')">
                  <input type="radio" name="school_features" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="school-access-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
          
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Are there special education programs available?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('sped-specify').classList.remove('hidden')">
                  <input type="radio" name="sped_prog" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('sped-specify').classList.add('hidden')">
                  <input type="radio" name="sped_prog" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="sped-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
          
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Does the child receive any learning support?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('learn-supp-specify').classList.remove('hidden')">
                  <input type="radio" name="learning_support" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('learn-supp-specify').classList.add('hidden')">
                  <input type="radio" name="learning_support" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="learn-supp-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(6)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back
        </button>
        <button onclick="goToStep(8)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          <span>Next: Economic Capacity</span> 
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 8: ECONOMIC CAPACITY
// ============================================================================

function getStep8HTML() {
  return `
    <div id="step-8" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Economic Capacity</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Assess the household's financial resources and the employment status of family members.</p>
      </div>
      
      <!-- Financial Information -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">account_balance_wallet</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Financial Information</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">What is the primary source of income for the family?</label>
            <input type="text" id="income-source" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="e.g., Employment, Business, Remittance">
          </div>
          
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">How much is the approximate monthly income of the family?</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold pointer-events-none text-xs">₱</span>
              <input type="text" id="monthly-income" class="w-full h-9 pl-6 pr-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="0" oninput="calculateIncomeClass(this)">
            </div>
          </div>
          
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <h4 class="font-bold text-brand-dark text-xs mb-1">Income Classification</h4>
            <p id="income-class-display" class="text-sm font-semibold text-brand-blue">Enter income to see classification</p>
          </div>
        </div>
      </section>
      
      <!-- Employment -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">work</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Employment</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Are the parents/guardians employed or have entrepreneurial activities?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('emp-specify').classList.remove('hidden')">
                  <input type="radio" name="employed" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('emp-specify').classList.add('hidden')">
                  <input type="radio" name="employed" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="emp-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(7)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back
        </button>
        <button onclick="goToStep(9)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          <span>Next: Service Availment</span> 
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 9: SERVICE AVAILMENT
// ============================================================================

function getStep9HTML() {
  return `
    <div id="step-9" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Service Availment</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Identify the government or private sector services the child has previously accessed.</p>
      </div>
      
      <!-- Social Services -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">handshake</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Social Services</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Does the family receive any form of financial assistance?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('fin-assist-specify').classList.remove('hidden')">
                  <input type="radio" name="fin_assist" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('fin-assist-specify').classList.add('hidden')">
                  <input type="radio" name="fin_assist" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="fin-assist-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
          
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Is the family aware of available social services for children with disabilities?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('aware-specify').classList.remove('hidden')">
                  <input type="radio" name="aware_services" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('aware-specify').classList.add('hidden')">
                  <input type="radio" name="aware_services" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="aware-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
          
          <div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
              <label class="text-xs font-bold text-brand-dark flex-1">Has the family availed of any services?</label>
              <div class="slide-toggle-container h-8 w-32">
                <div class="slide-toggle-slider"></div>
                <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this); document.getElementById('availed-specify').classList.remove('hidden')">
                  <input type="radio" name="availed_any" value="Yes" class="hidden"> 
                  <span>Yes</span>
                </label>
                <label class="slide-toggle-label text-white" onclick="toggleBtn(this); document.getElementById('availed-specify').classList.add('hidden')">
                  <input type="radio" name="availed_any" value="No" class="hidden" checked> 
                  <span>No</span>
                </label>
              </div>
            </div>
            <input id="availed-specify" type="text" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
        </div>
      </section>
      
      <!-- Barriers -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">warning</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Barriers to Service Availment</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">What are the challenges faced in availing these services?</label>
            <div class="relative">
              <select id="service-challenges" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'barrier-other')">
                <option value="" disabled selected>Select Challenge</option>
                <option>Lack of awareness</option>
                <option>Financial constraints</option>
                <option>Distance/Transportation</option>
                <option>Requirements/Documents</option>
                <option value="Others">Others (Specify)</option>
                <option>None</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
            <input id="barrier-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Please specify">
          </div>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(8)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back
        </button>
        <button onclick="goToStep(10)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          <span>Next: Assessment</span> 
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 10: ASSESSMENT
// ============================================================================

function getStep10HTML() {
  return `
    <div id="step-10" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">General Observations and Recommendations</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Provide a summary of the child's situation and specific steps for intervention.</p>
      </div>
      
      <!-- Assessment Notes -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">edit_note</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Assessment Notes</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Strengths</label>
            <textarea id="strengths" class="w-full p-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none h-24 resize-none placeholder-gray-400" placeholder="Enter key strengths..."></textarea>
          </div>
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Assessment</label>
            <textarea id="assessment" class="w-full p-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none h-24 resize-none placeholder-gray-400" placeholder="Provide assessment details..."></textarea>
          </div>
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Recommended Actions/Interventions</label>
            <textarea id="recommendations" class="w-full p-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none h-24 resize-none placeholder-gray-400" placeholder="Suggest interventions..."></textarea>
          </div>
        </div>
      </section>
      
      <!-- Readiness Score -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3 border-b border-gray-100 pb-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">speed</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base">Readiness Score</h3>
        </div>
        
        <div class="space-y-3">
          <label class="radio-card relative block w-full border border-gray-200 rounded-lg p-3 cursor-pointer hover:border-blue-300 transition-all select-none">
            <input type="radio" name="readiness" value="severe" class="peer sr-only">
            <div class="flex flex-col">
              <h3 class="font-bold text-gray-700 text-sm flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xs font-bold">1</span>
                Severe: Immediate intervention needed
              </h3>
              <p class="text-xs text-gray-500 mt-1 pl-7">The well-being of the child and family is at a critical level, requiring urgent and immediate intervention.</p>
            </div>
          </label>
          
          <label class="radio-card relative block w-full border border-gray-200 rounded-lg p-3 cursor-pointer hover:border-blue-300 transition-all select-none">
            <input type="radio" name="readiness" value="moderate" class="peer sr-only">
            <div class="flex flex-col">
              <h3 class="font-bold text-gray-700 text-sm flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-xs font-bold">2</span>
                Moderate: Address within a short period
              </h3>
              <p class="text-xs text-gray-500 mt-1 pl-7">The well-being shows significant areas of concern that need to be addressed in the short term.</p>
            </div>
          </label>
          
          <label class="radio-card relative block w-full border border-gray-200 rounded-lg p-3 cursor-pointer hover:border-blue-300 transition-all select-none">
            <input type="radio" name="readiness" value="low" class="peer sr-only">
            <div class="flex flex-col">
              <h3 class="font-bold text-gray-700 text-sm flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center text-xs font-bold">3</span>
                Low: No immediate action needed, but regular monitoring
              </h3>
              <p class="text-xs text-gray-500 mt-1 pl-7">The child and family's well-being is generally adequate, with some minor issues requiring attention.</p>
            </div>
          </label>
          
          <label class="radio-card relative block w-full border border-gray-200 rounded-lg p-3 cursor-pointer hover:border-blue-300 transition-all select-none">
            <input type="radio" name="readiness" value="stable" class="peer sr-only">
            <div class="flex flex-col">
              <h3 class="font-bold text-gray-700 text-sm flex items-center gap-2">
                <span class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs font-bold">4</span>
                Stable: Meets all needs effectively
              </h3>
              <p class="text-xs text-gray-500 mt-1 pl-7">The child and family's well-being is satisfactory and functioning as expected.</p>
            </div>
          </label>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(9)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back
        </button>
        <button onclick="generateReview()" class="px-6 h-10 bg-green-600 rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-green-700 flex items-center gap-2 shadow-md transition-all">
          <span class="material-symbols-outlined text-[16px]">check</span> Review Assessment
        </button>
      </div>
    </div>
  `;
}

// ============================================================================
// STEP 11: REVIEW
// ============================================================================

function getStep11HTML() {
  return `
    <div id="step-11" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Review Assessment</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Please verify all information before final submission.</p>
      </div>
      
      <div id="review-content" class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-6 space-y-6">
        <!-- Review content will be generated here -->
      </div>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(10)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">Edit Forms</button>
        <button onclick="submitAssessment()" class="px-6 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">Submit Assessment</button>
      </div>
    </div>
  `;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function initializeAllSteps() {
  // Populate all dropdowns
  populateAllDropdowns();
  
  // Initialize location dropdowns
  initializeLocationDropdowns();
  
  // Initialize toggle buttons
  initToggles();
  
  // Initialize first family member
  addFirstFamilyMember();
  
  // Initialize Google-style selects
  setTimeout(initGoogleSelects, 500);
}

function populateAllDropdowns() {
  if (!globalData || Object.keys(globalData).length === 0) {
    setTimeout(populateAllDropdowns, 1000);
    return;
  }

  // Step 2
  populateSelect('dd-relationship', globalData.List_Relationship, "Select Relationship");
  
  // Step 3
  populateSelect('dd-extension', globalData.List_Extension, "None");
  populateSelect('dd-religion', globalData.List_Religion, "Select Religion");
  populateSelect('dd-ip', globalData.List_IP, "Select IP Group");
  populateSelect('dd-education', globalData.List_Education, "Select Education");
  populateMulti('dd-disability', globalData.List_Disability, 'disability-display');
  populateMulti('dd-illness', globalData.List_Illness, 'illness-display', 'illness-other-input');
  
  // Step 5
  populateSelect('dd-materials', globalData.List_Materials, "Select Materials");
  populateSelect('dd-tenure', globalData.List_Tenure, "Select Status");
  populateSelect('dd-electricity', globalData.List_Electricity, "Select Source");
  populateSelect('dd-water', globalData.List_Water, "Select Water Source");
  populateSelect('dd-toilet', globalData.List_Toilet, "Select Toilet Type");
  populateSelect('dd-garbage', globalData.List_Garbage, "Select System");
}

function populateSelect(id, items, placeholder) {
  const s = document.getElementById(id);
  if (!s) {
    return;
  }

  if (!items || items.length === 0) {
    s.innerHTML = `<option value="" disabled selected>${placeholder} (No data)</option>`;
    return;
  }

  s.innerHTML = `<option value="" disabled selected>${placeholder}</option>`;
  let hasOthers = false;
  
  (items || []).forEach(i => {
    const o = document.createElement('option');
    if (i === "Others" || i === "Other" || i === "Others (Specify)") {
      o.value = "Others";
      o.innerText = "Others (Specify)";
      hasOthers = true;
    } else {
      o.value = i;
      o.innerText = i;
    }
    s.appendChild(o);
  });
  
  if (!hasOthers && id !== 'dd-extension' && id !== 'dd-relationship') {
    const o = document.createElement('option');
    o.value = "Others";
    o.innerText = "Others (Specify)";
    s.appendChild(o);
  }
}

function populateMulti(cid, items, did, oid = null) {
  const c = document.getElementById(cid);
  if (!c) return;
  
  c.innerHTML = "";
  c.className = "hidden absolute z-10 w-full google-menu dropdown-scroll max-h-60 overflow-y-auto";
  let hasOthers = false;
  
  (items || []).forEach(x => {
    if (x === "Others" || x === "Other" || x === "Others (Specify)") {
      hasOthers = true;
      return;
    }
    const l = document.createElement('label');
    l.className = "google-menu-item";
    const cb = document.createElement('input');
    cb.type = "checkbox";
    cb.value = x;
    cb.onchange = function() { updateMultiSelect(cid, did) };
    const s = document.createElement('span');
    s.innerText = x;
    l.appendChild(cb);
    l.appendChild(s);
    c.appendChild(l);
  });
  
  if (oid || hasOthers) {
    const l = document.createElement('label');
    l.className = "google-menu-item";
    const cb = document.createElement('input');
    cb.type = "checkbox";
    cb.value = "Others";
    cb.onchange = function() {
      updateMultiSelect(cid, did);
      if (oid) {
        const inp = document.getElementById(oid);
        if (inp) inp.classList.toggle('hidden', !this.checked);
      }
    };
    const s = document.createElement('span');
    s.innerText = "Others (Specify)";
    l.appendChild(cb);
    l.appendChild(s);
    c.appendChild(l);
  }
}

function initializeLocationDropdowns() {
  const regionSelect = document.getElementById('child-region');
  if (!regionSelect) return;
  
  regionSelect.innerHTML = '<option value="" disabled selected>Select Region</option>';
  Object.keys(locationData).forEach(region => {
    const opt = document.createElement('option');
    opt.value = region;
    opt.innerText = region;
    regionSelect.appendChild(opt);
  });
}

function updateProvinces() {
  const region = document.getElementById('child-region').value;
  const provSelect = document.getElementById('child-province');
  
  provSelect.innerHTML = '<option value="" disabled selected>Select Province</option>';
  document.getElementById('child-city').innerHTML = '<option value="" disabled selected>Select City</option>';
  document.getElementById('child-barangay').innerHTML = '<option value="" disabled selected>Select Barangay</option>';
  provSelect.disabled = true;
  document.getElementById('child-city').disabled = true;
  document.getElementById('child-barangay').disabled = true;
  
  if (region && locationData[region]) {
    const provinces = Object.keys(locationData[region]);
    provinces.forEach(prov => {
      const opt = document.createElement('option');
      opt.value = prov;
      opt.innerText = prov;
      provSelect.appendChild(opt);
    });
    provSelect.disabled = false;
  }
}

function updateCities() {
  const region = document.getElementById('child-region').value;
  const province = document.getElementById('child-province').value;
  const citySelect = document.getElementById('child-city');
  
  citySelect.innerHTML = '<option value="" disabled selected>Select City</option>';
  document.getElementById('child-barangay').innerHTML = '<option value="" disabled selected>Select Barangay</option>';
  citySelect.disabled = true;
  document.getElementById('child-barangay').disabled = true;
  
  if (region && province && locationData[region][province]) {
    const cities = Object.keys(locationData[region][province]);
    cities.forEach(city => {
      const opt = document.createElement('option');
      opt.value = city;
      opt.innerText = city;
      citySelect.appendChild(opt);
    });
    citySelect.disabled = false;
  }
}

function updateBarangays() {
  const region = document.getElementById('child-region').value;
  const province = document.getElementById('child-province').value;
  const city = document.getElementById('child-city').value;
  const brgySelect = document.getElementById('child-barangay');
  
  brgySelect.innerHTML = '<option value="" disabled selected>Select Barangay</option>';
  brgySelect.disabled = true;
  
  if (region && province && city && locationData[region][province][city]) {
    const barangays = locationData[region][province][city];
    barangays.forEach(brgy => {
      const opt = document.createElement('option');
      opt.value = brgy;
      opt.innerText = brgy;
      brgySelect.appendChild(opt);
    });
    brgySelect.disabled = false;
  }
}

function initToggles() {
  document.querySelectorAll('.slide-toggle-container').forEach(container => {
    const checkedInput = container.querySelector('input:checked');
    if (checkedInput) {
      const label = checkedInput.closest('.slide-toggle-label');
      const labels = Array.from(container.querySelectorAll('.slide-toggle-label'));
      const index = labels.indexOf(label);
      const slider = container.querySelector('.slide-toggle-slider');
      
      slider.style.transform = `translateX(${index * 100}%)`;
      labels.forEach(l => l.classList.remove('text-white', 'text-gray-500'));
      labels.forEach(l => l.classList.add('text-gray-500'));
      label.classList.remove('text-gray-500');
      label.classList.add('text-white');
    }
  });
}

function toggleBtn(label) {
  const container = label.closest('.slide-toggle-container');
  const slider = container.querySelector('.slide-toggle-slider');
  const labels = Array.from(container.querySelectorAll('.slide-toggle-label'));
  
  const index = labels.indexOf(label);
  slider.style.transform = `translateX(${index * 100}%)`;
  
  labels.forEach(l => {
    l.classList.remove('text-white');
    l.classList.add('text-gray-500');
  });
  label.classList.remove('text-gray-500');
  label.classList.add('text-white');
  
  label.querySelector('input').checked = true;
}

function toggleId(show) {
  const el = document.getElementById('household-id');
  const box = document.getElementById('id-container');
  if (show) {
    el.removeAttribute('disabled');
    box.style.opacity = '1';
    el.value = "";
    el.focus();
  } else {
    el.setAttribute('disabled', 'true');
    box.style.opacity = '0.5';
    el.value = "N/A";
  }
}

function toggleOther(selectEl, inputId) {
  const input = document.getElementById(inputId);
  if (selectEl.value === 'Others') {
    input.classList.remove('hidden');
    input.focus();
  } else {
    input.classList.add('hidden');
    input.value = "";
  }
}

function toggleDropdown(id) {
  const dropdown = document.getElementById(id);
  dropdown.classList.toggle('hidden');
}

function updateMultiSelect(cid, did) {
  const c = document.getElementById(cid);
  const chk = c.querySelectorAll('input:checked');
  const d = document.getElementById(did);
  
  if (chk.length === 0) {
    d.innerText = "Select options...";
    d.classList.add("text-gray-400");
    d.classList.remove("text-gray-900");
  } else {
    const v = Array.from(chk).map(x => x.value).join(', ');
    d.innerText = v;
    d.classList.remove("text-gray-400");
    d.classList.add("text-gray-900");
  }
}

function toggleEnrollment(isEnrolled) {
  const yesDiv = document.getElementById('enrollment-yes');
  const noDiv = document.getElementById('enrollment-no');
  const form2 = document.getElementById('form-school-access');
  
  if (isEnrolled) {
    yesDiv.classList.remove('hidden');
    noDiv.classList.add('hidden');
    form2.classList.remove('hidden');
  } else {
    yesDiv.classList.add('hidden');
    noDiv.classList.remove('hidden');
    form2.classList.add('hidden');
  }
}

function validateExpense(input) {
  let val = input.value.replace(/[^0-9]/g, '');
  if (val.length > 7) val = val.slice(0, 7);
  input.value = val;
  calculateHealthTotal();
}

function calculateHealthTotal() {
  const ids = ['exp-food', 'exp-med', 'exp-therapy', 'exp-hygiene', 'exp-assist', 'exp-other'];
  let total = 0;
  ids.forEach(id => {
    const val = document.getElementById(id).value;
    total += parseInt(val || 0);
  });
  document.getElementById('exp-total').value = total.toLocaleString();
}

function calculateIncomeClass(input) {
  let val = input.value.replace(/[^0-9]/g, '');
  if (val.length > 7) val = val.slice(0, 7);
  input.value = val;
  
  const numVal = parseInt(val || 0);
  const display = document.getElementById('income-class-display');
  
  if (numVal === 0) {
    display.innerText = "Enter income to see classification";
  } else if (numVal <= 24000) {
    display.innerText = "Below Minimum / Low Income";
  } else if (numVal <= 76000) {
    display.innerText = "Middle Income";
  } else {
    display.innerText = "Above Moderate / Upper Income";
  }
}

// Close dropdowns when clicking outside
window.addEventListener('click', function(e) {
  document.querySelectorAll('.google-menu-custom, .google-menu').forEach(menu => {
    const btn = menu.previousElementSibling;
    if (!menu.contains(e.target) && btn && !btn.contains(e.target)) {
      menu.classList.add('hidden');
    }
  });
});

function initGoogleSelects() {
  document.querySelectorAll('select.google-dropdown-style').forEach(select => {
    if (select.dataset.customized) return;
    select.dataset.customized = "true";
    
    const wrapper = document.createElement('div');
    wrapper.className = 'relative w-full';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add('hidden');
    
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = "w-full h-9 px-3 text-left bg-white border border-gray-300 rounded flex justify-between items-center text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none transition-all";
    
    const textSpan = document.createElement('span');
    textSpan.className = 'truncate text-gray-500';
    
    const icon = document.createElement('span');
    icon.className = 'material-symbols-outlined text-[18px] text-gray-500 ml-2 flex-shrink-0';
    icon.innerText = 'expand_more';
    
    btn.appendChild(textSpan);
    btn.appendChild(icon);
    wrapper.appendChild(btn);
    
    const menu = document.createElement('div');
    menu.className = 'hidden absolute z-10 w-full bg-white border border-gray-200 mt-1 rounded-md shadow-lg max-h-60 overflow-y-auto dropdown-scroll py-1 google-menu-custom';
    wrapper.appendChild(menu);
    
    const syncText = () => {
      const opt = select.options[select.selectedIndex];
      if (opt) {
        textSpan.innerText = opt.text;
        if (opt.disabled || !opt.value) {
          textSpan.classList.replace('text-gray-900', 'text-gray-500');
        } else {
          textSpan.classList.replace('text-gray-500', 'text-gray-900');
        }
      }
    };
    syncText();
    
    btn.onclick = (e) => {
      e.stopPropagation();
      const isHidden = menu.classList.contains('hidden');
      document.querySelectorAll('.google-menu-custom, .google-menu').forEach(m => m.classList.add('hidden'));
      if (isHidden && !select.disabled) {
        menu.classList.remove('hidden');
      }
    };
    
    const syncOptions = () => {
      menu.innerHTML = '';
      Array.from(select.options).forEach(opt => {
        if (opt.disabled) return;
        const item = document.createElement('div');
        item.className = 'px-3 py-2 text-sm text-gray-800 hover:bg-gray-100 cursor-pointer transition-colors';
        item.innerText = opt.text;
        item.onclick = (e) => {
          e.stopPropagation();
          select.value = opt.value;
          syncText();
          menu.classList.add('hidden');
          select.dispatchEvent(new Event('change'));
        };
        menu.appendChild(item);
      });
      syncText();
    };
    syncOptions();
    
    const observer = new MutationObserver(syncOptions);
    observer.observe(select, { childList: true });
    
    const handleDisable = () => {
      if (select.disabled) {
        btn.classList.add('bg-gray-50', 'opacity-70', 'cursor-not-allowed');
      } else {
        btn.classList.remove('bg-gray-50', 'opacity-70', 'cursor-not-allowed');
      }
    };
    handleDisable();
    
    const disableObserver = new MutationObserver(handleDisable);
    disableObserver.observe(select, { attributes: true, attributeFilter: ['disabled'] });
  });
}

// ============================================================================
// FAMILY MEMBER MANAGEMENT
// ============================================================================

function addFirstFamilyMember() {
  const container = document.getElementById('family-members-container');
  if (!container) return;
  
  container.innerHTML = getFamilyMemberCardHTML(1, true);
  updateTotalCount();
  
  // Populate dropdowns for first member
  setTimeout(() => {
    populateSelect('dd-fam-occ-1', globalData.List_Occupation, "Select Occupation");
    populateSelect('dd-fam-class-1', globalData.List_Occupation_Class, "Select Class");
    populateMulti('dd-fam-dis-1', globalData.List_Disability, 'disp-fam-dis-1');
    populateMulti('dd-fam-ill-1', globalData.List_Illness, 'disp-fam-ill-1');
    initToggles();
    initGoogleSelects();
  }, 100);
}

function addFamilyMember() {
  memberCount++;
  const container = document.getElementById('family-members-container');
  
  const newCard = document.createElement('div');
  newCard.innerHTML = getFamilyMemberCardHTML(memberCount, false);
  container.appendChild(newCard.firstElementChild);
  
  updateTotalCount();
  
  // Populate dropdowns for new member
  setTimeout(() => {
    populateSelect(`dd-fam-occ-${memberCount}`, globalData.List_Occupation, "Select Occupation");
    populateSelect(`dd-fam-class-${memberCount}`, globalData.List_Occupation_Class, "Select Class");
    populateMulti(`dd-fam-dis-${memberCount}`, globalData.List_Disability, `disp-fam-dis-${memberCount}`);
    populateMulti(`dd-fam-ill-${memberCount}`, globalData.List_Illness, `disp-fam-ill-${memberCount}`);
    initToggles();
    initGoogleSelects();
  }, 100);
}

function getFamilyMemberCardHTML(num, isHead) {
  const title = isHead ? `Member #${num} (Head of Family)` : `Member #${num}`;
  const removeButton = !isHead ? `
    <button onclick="removeMember(this)" class="text-xs bg-red-50 text-red-600 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-100 transition-colors font-semibold">Remove</button>
  ` : '';
  
  return `
    <section class="member-card w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm relative" id="member-card-${num}">
      <div class="bg-gray-50 px-5 py-3 border-b border-gray-200 flex justify-between items-center rounded-t-xl h-14">
        <h3 class="font-bold text-brand-dark text-sm sm:text-base member-title">${title}</h3>
        <div class="flex items-center gap-2 remove-btn-container">
          <button onclick="toggleMemberVisibility(this)" class="text-[10px] text-gray-400 font-semibold hover:text-brand-blue transition-colors">Hide</button>
          ${removeButton}
        </div>
      </div>
      
      <div class="p-5 sm:p-6 space-y-4 member-content">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Full Name</label>
            <input type="text" data-field="full_name" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Enter Full Name">
          </div>
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Relationship to Head</label>
            <div class="relative">
              <select data-field="relationship_to_head" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400">
                <option>${isHead ? 'Head' : 'Spouse'}</option>
                <option>Spouse</option>
                <option>Child</option>
                <option>Parent</option>
                <option>Sibling</option>
                <option>Grandparent</option>
                <option>Grandchild</option>
                <option>Other Relative</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Solo Parent</label>
            <div class="slide-toggle-container h-9 w-full">
              <div class="slide-toggle-slider"></div>
              <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this)">
                <input type="radio" name="solo-${num}" value="Yes" data-field="is_solo_parent" class="hidden">
                <span>Yes</span>
              </label>
              <label class="slide-toggle-label text-white" onclick="toggleBtn(this)">
                <input type="radio" name="solo-${num}" value="No" data-field="is_solo_parent" class="hidden" checked>
                <span>No</span>
              </label>
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Civil Status</label>
            <div class="relative">
              <select data-field="civil_status" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400">
                <option value="" disabled selected>Select Status</option>
                <option>Single</option>
                <option>Married</option>
                <option>Widowed</option>
                <option>Separated</option>
                <option>Live-in</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Age</label>
            <input type="text" data-field="age" maxlength="3" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="Age" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
          </div>
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Sex</label>
            <div class="slide-toggle-container h-9 w-full">
              <div class="slide-toggle-slider"></div>
              <label class="slide-toggle-label text-white" onclick="toggleBtn(this)">
                <input type="radio" name="sex-${num}" value="Male" data-field="member_sex" class="hidden" checked>
                <span>Male</span>
              </label>
              <label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this)">
                <input type="radio" name="sex-${num}" value="Female" data-field="member_sex" class="hidden">
                <span>Female</span>
              </label>
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Occupation</label>
            <div class="relative">
              <select id="dd-fam-occ-${num}" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400">
                <option value="" disabled selected>Loading...</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-brand-dark mb-1">Occupation Class</label>
            <div class="relative">
              <select id="dd-fam-class-${num}" class="google-dropdown-style w-full h-9 px-3 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400">
                <option value="" disabled selected>Loading...</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-symbols-outlined text-[18px] text-gray-500">expand_more</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="relative">
            <label class="block text-xs font-bold text-brand-dark mb-1">Disability/Special Needs</label>
            <button type="button" onclick="toggleDropdown('dd-fam-dis-${num}')" class="w-full h-9 px-3 text-left bg-white border border-gray-300 rounded focus:ring-1 focus:ring-brand-blue outline-none flex justify-between items-center text-xs sm:text-sm">
              <span id="disp-fam-dis-${num}" class="truncate text-gray-500">Select...</span>
              <span class="material-symbols-outlined text-[18px] text-gray-500 ml-2 flex-shrink-0">expand_more</span>
            </button>
            <div id="dd-fam-dis-${num}" class="hidden absolute z-10 w-full google-menu mt-1 max-h-40 overflow-y-auto dropdown-scroll">
              <div class="p-2 text-gray-400 text-xs">Loading...</div>
            </div>
          </div>
          <div class="relative">
            <label class="block text-xs font-bold text-brand-dark mb-1">Critical Illness</label>
            <button type="button" onclick="toggleDropdown('dd-fam-ill-${num}')" class="w-full h-9 px-3 text-left bg-white border border-gray-300 rounded focus:ring-1 focus:ring-brand-blue outline-none flex justify-between items-center text-xs sm:text-sm">
              <span id="disp-fam-ill-${num}" class="truncate text-gray-500">Select...</span>
              <span class="material-symbols-outlined text-[18px] text-gray-500 ml-2 flex-shrink-0">expand_more</span>
            </button>
            <div id="dd-fam-ill-${num}" class="hidden absolute z-10 w-full google-menu mt-1 max-h-40 overflow-y-auto dropdown-scroll">
              <div class="p-2 text-gray-400 text-xs">Loading...</div>
            </div>
          </div>
        </div>
      </div>
    </section>
  `;
}

function removeMember(btn) {
  const card = btn.closest('.member-card');
  card.remove();
  
  const allCards = document.querySelectorAll('.member-card');
  let currentCount = 0;
  allCards.forEach((c, index) => {
    currentCount = index + 1;
    c.id = `member-card-${currentCount}`;
    const title = c.querySelector('.member-title');
    if (currentCount === 1) {
      title.innerText = "Member #1 (Head of Family)";
    } else {
      title.innerText = `Member #${currentCount}`;
    }
  });
  memberCount = currentCount;
  updateTotalCount();
}

function toggleMemberVisibility(btn) {
  const card = btn.closest('.member-card');
  const content = card.querySelector('.member-content');
  if (content.style.display === "none") {
    content.style.display = "block";
    btn.innerText = "Hide";
  } else {
    content.style.display = "none";
    btn.innerText = "Show";
  }
}

function updateTotalCount() {
  document.getElementById('total-family-size').value = memberCount;
}

// ============================================================================
// REVIEW GENERATION
// ============================================================================

function generateReview() {
  const getVal = (id) => { 
    const el = document.getElementById(id); 
    return (el && el.value.trim() !== "") ? el.value : 'N/A'; 
  };
  
  const getRadio = (name) => { 
    const el = document.querySelector(`input[name="${name}"]:checked`); 
    return el ? el.value : 'N/A'; 
  };
  
  let html = `
    <!-- REVIEW INTRODUCTION -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-xl p-6 mb-6 text-white">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-[28px]">fact_check</span>
        </div>
        <div>
          <h2 class="text-2xl font-extrabold">Assessment Review</h2>
          <p class="text-sm opacity-90 mt-1">Please verify all information before final submission</p>
        </div>
      </div>
    </div>

    <!-- SECTION 1: PRE-QUALIFICATION -->
    <section class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">assignment_ind</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">1. Pre-Qualification</h3>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 ml-12">
        <div>
          <p class="text-xs font-bold text-gray-500 uppercase mb-1">4Ps Member</p>
          <p class="text-sm font-semibold text-gray-900">${getRadio('membership')}</p>
        </div>
        <div>
          <p class="text-xs font-bold text-gray-500 uppercase mb-1">Household ID</p>
          <p class="text-sm font-semibold text-gray-900">${getVal('household-id')}</p>
        </div>
      </div>
    </section>

    <!-- SECTION 2: RESPONDENT PROFILE -->
    <section class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">account_circle</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">2. Respondent Profile</h3>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 ml-12">
        <div>
          <p class="text-xs font-bold text-gray-500 uppercase mb-1">Name</p>
          <p class="text-sm font-semibold text-gray-900">${getVal('resp-name')}</p>
        </div>
        <div>
          <p class="text-xs font-bold text-gray-500 uppercase mb-1">Relationship</p>
          <p class="text-sm font-semibold text-gray-900">${getVal('dd-relationship')}</p>
        </div>
        <div>
          <p class="text-xs font-bold text-gray-500 uppercase mb-1">Email</p>
          <p class="text-sm font-semibold text-gray-900 break-all">${getVal('resp-email')}</p>
        </div>
        <div>
          <p class="text-xs font-bold text-gray-500 uppercase mb-1">Contact</p>
          <p class="text-sm font-semibold text-gray-900">${getVal('resp-contact')}</p>
        </div>
      </div>
    </section>

    <!-- SECTION 3: CHILD PROFILE -->
    <section class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">face</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">3. Child Profile</h3>
      </div>
      <div class="ml-12 space-y-4">
        <!-- Personal Information -->
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Personal Information</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Full Name</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('child-fname')} ${getVal('child-mname')} ${getVal('child-lname')} ${getVal('dd-extension')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Date of Birth / Sex</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('child-dob')} / ${getRadio('sex')}</p>
            </div>
          </div>
        </div>

        <!-- Address -->
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Address</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Street Address</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('child-street')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Barangay</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('child-barangay')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">City/Municipality</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('child-city')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Province</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('child-province')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Region</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('child-region')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Contact Number</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('child-contact')}</p>
            </div>
          </div>
        </div>

        <!-- Demographics -->
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Demographics</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Religion</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('dd-religion') === 'Others' ? getVal('rel-other') : getVal('dd-religion')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">IP Membership</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('dd-ip') === 'Others' ? getVal('ip-other') : getVal('dd-ip')}</p>
            </div>
          </div>
        </div>

        <!-- Condition & Education -->
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Condition & Education</p>
          <div class="grid grid-cols-1 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Highest Educational Attainment</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('dd-education') === 'Others' ? getVal('edu-other') : getVal('dd-education')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Disability or Special Needs</p>
              <p class="text-sm font-semibold text-gray-900">${document.getElementById('disability-display') ? document.getElementById('disability-display').innerText : 'N/A'}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Critical Illness</p>
              <p class="text-sm font-semibold text-gray-900">${document.getElementById('illness-display') ? document.getElementById('illness-display').innerText : 'N/A'}</p>
            </div>
          </div>
        </div>
      </div>
    </section>
    
    <!-- SECTION 4: FAMILY PROFILE -->
    <section class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">family_restroom</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">4. Family Profile</h3>
      </div>
      <div class="ml-12">
        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200 mb-4">
          <div class="flex justify-between items-center">
            <p class="text-sm font-bold text-brand-dark">Total Family Size</p>
            <p class="text-2xl font-extrabold text-brand-blue">${getVal('total-family-size')}</p>
          </div>
        </div>
  `;
  
  const cards = document.querySelectorAll('.member-card');
  if (cards.length === 0) {
    html += `
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 text-center">
          <p class="text-sm text-gray-500">No family members added</p>
        </div>
    `;
  } else {
    html += `<div class="space-y-3">`;
    cards.forEach((card, index) => {
      const inputs = card.querySelectorAll('input[type="text"]');
      let name = "Member " + (index + 1);
      if (inputs.length > 0 && inputs[0].value) {
        name = inputs[0].value;
      }
      html += `
        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-brand-blue text-[18px]">person</span>
            <p class="text-sm font-semibold text-gray-900">${name}</p>
          </div>
        </div>
      `;
    });
    html += `</div>`;
  }
  html += `
      </div>
    </section>

    <!-- SECTION 5: SOCIO ECONOMIC -->
    <section class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">home</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">5. Socio Economic</h3>
      </div>
      <div class="ml-12 space-y-3">
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Housing Condition</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Construction Materials</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('dd-materials') === 'Others' ? getVal('mat-other') : getVal('dd-materials')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Tenure Status</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('dd-tenure') === 'Others' ? getVal('tenure-other') : getVal('dd-tenure')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Electricity Source</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('dd-electricity') === 'Others' ? getVal('elec-other') : getVal('dd-electricity')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Modifications for Child</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('modifications') === 'Yes' ? 'Yes - ' + getVal('mod-specify') : 'No'}</p>
            </div>
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Water & Sanitation</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Water Source</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('dd-water') === 'Others' ? getVal('water-other') : getVal('dd-water')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Toilet Facility</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('dd-toilet') === 'Others' ? getVal('toilet-other') : getVal('dd-toilet')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Toilet Accessible</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('toilet-access')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Garbage Disposal</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('dd-garbage') === 'Others' ? getVal('garbage-other') : getVal('dd-garbage')}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 6: HEALTH -->
    <section class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">health_and_safety</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">6. Health</h3>
      </div>
      <div class="ml-12 space-y-3">
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">General Health</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Vaccinations Complete</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('vaccines')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Ongoing Health Conditions</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('health_cond') === 'Yes' ? 'Yes - ' + getVal('health-cond-specify') : 'No'}</p>
            </div>
          </div>
        </div>

        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-xs font-bold text-gray-700 uppercase mb-1">Total Monthly Health Expense</p>
              <p class="text-xs text-gray-500">Sum of all health-related costs</p>
            </div>
            <p class="text-2xl font-extrabold text-brand-blue">₱${getVal('exp-total')}</p>
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Access to Health Services</p>
          <div class="grid grid-cols-1 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Availed Services (Past 6 Months)</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('avail_services') === 'Yes' ? 'Yes - ' + getVal('avail-specify') : 'No'}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Health Facility Accessible</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('facility_access')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Barriers to Healthcare</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('barriers') === 'Yes' ? 'Yes - ' + getVal('barrier-specify') : 'No'}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 7: EDUCATION -->
    <section class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">school</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">7. Education</h3>
      </div>
      <div class="ml-12 space-y-3">
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Educational Status</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Currently Enrolled</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('enrolled') === 'Yes' ? 'Yes - Grade/Year: ' + getVal('grade-level') : 'No - Reason: ' + getVal('not-enrolled-reason')}</p>
            </div>
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">School Accessibility</p>
          <div class="grid grid-cols-1 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Physical Accessibility Features</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('school_features') === 'Yes' ? 'Yes - ' + getVal('school-access-specify') : 'No'}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Special Education Programs</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('sped_prog') === 'Yes' ? 'Yes - ' + getVal('sped-specify') : 'No'}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Learning Support</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('learning_support') === 'Yes' ? 'Yes - ' + getVal('learn-supp-specify') : 'No'}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 8: ECONOMIC CAPACITY -->
    <section class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">account_balance_wallet</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">8. Economic Capacity</h3>
      </div>
      <div class="ml-12 space-y-3">
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Financial Information</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Primary Income Source</p>
              <p class="text-sm font-semibold text-gray-900">${getVal('income-source')}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Monthly Income</p>
              <p class="text-sm font-semibold text-gray-900">₱${getVal('monthly-income')}</p>
            </div>
          </div>
        </div>

        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
          <div class="flex justify-between items-center">
            <p class="text-xs font-bold text-gray-700 uppercase">Income Classification</p>
            <p class="text-lg font-extrabold text-brand-blue">${document.getElementById('income-class-display').innerText}</p>
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Employment</p>
          <div>
            <p class="text-xs text-gray-500 mb-1">Parents/Guardians Employed</p>
            <p class="text-sm font-semibold text-gray-900">${getRadio('employed') === 'Yes' ? 'Yes - ' + getVal('emp-specify') : 'No'}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 9: SERVICE AVAILMENT -->
    <section class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">handshake</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">9. Service Availment</h3>
      </div>
      <div class="ml-12 space-y-3">
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Social Services</p>
          <div class="grid grid-cols-1 gap-3">
            <div>
              <p class="text-xs text-gray-500 mb-1">Financial Assistance</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('fin_assist') === 'Yes' ? 'Yes - ' + getVal('fin-assist-specify') : 'No'}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Aware of Social Services</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('aware_services') === 'Yes' ? 'Yes - ' + getVal('aware-specify') : 'No'}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Availed Services</p>
              <p class="text-sm font-semibold text-gray-900">${getRadio('availed_any') === 'Yes' ? 'Yes - ' + getVal('availed-specify') : 'No'}</p>
            </div>
          </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-3">Barriers</p>
          <div>
            <p class="text-xs text-gray-500 mb-1">Challenges in Availing Services</p>
            <p class="text-sm font-semibold text-gray-900">${document.getElementById('service-challenges').value === 'Others' ? getVal('barrier-other') : document.getElementById('service-challenges').value}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 10: ASSESSMENT NOTES -->
    <section class="mb-6">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-brand-blue text-[22px]">edit_note</span>
        </div>
        <h3 class="text-lg font-bold text-brand-dark">10. Assessment Notes</h3>
      </div>
      <div class="ml-12 space-y-3">
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-2">Strengths</p>
          <p class="text-sm text-gray-900 whitespace-pre-wrap">${getVal('strengths')}</p>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-2">Assessment</p>
          <p class="text-sm text-gray-900 whitespace-pre-wrap">${getVal('assessment')}</p>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <p class="text-xs font-bold text-gray-500 uppercase mb-2">Recommended Actions/Interventions</p>
          <p class="text-sm text-gray-900 whitespace-pre-wrap">${getVal('recommendations')}</p>
        </div>

        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-lg p-5 text-white">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-xs font-bold opacity-90 uppercase mb-1">Readiness Score</p>
              <p class="text-sm opacity-90">Final assessment classification</p>
            </div>
            <p class="text-3xl font-extrabold uppercase">${getRadio('readiness')}</p>
          </div>
        </div>
      </div>
    </section>
  `;

  document.getElementById('review-content').innerHTML = html;
  goToStep(11);
}

// ============================================================================
// FORM SUBMISSION
// ============================================================================

function collectFormData() {
  const getVal = (id) => {
    const el = document.getElementById(id);
    const v = el ? el.value.trim() : '';
    return v !== '' ? v : null;
  };
  const getRadio = (name) => {
    const el = document.querySelector(`input[name="${name}"]:checked`);
    return el ? el.value : null;
  };
  const getMulti = (containerId) => {
    const container = document.getElementById(containerId);
    if (!container) return [];
    return Array.from(container.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);
  };

  const membershipVal  = getRadio('membership');
  const religionVal    = getVal('dd-religion');
  const ipVal          = getVal('dd-ip');
  const eduVal         = getVal('dd-education');
  const materialsVal   = getVal('dd-materials');
  const tenureVal      = getVal('dd-tenure');
  const elecVal        = getVal('dd-electricity');
  const waterVal       = getVal('dd-water');
  const toiletVal      = getVal('dd-toilet');
  const garbageVal     = getVal('dd-garbage');
  const healthCondVal  = getRadio('health_cond');
  const availServVal   = getRadio('avail_services');
  const barriersVal    = getRadio('barriers');
  const enrolledVal    = getRadio('enrolled');
  const schoolFeatVal  = getRadio('school_features');
  const spedVal        = getRadio('sped_prog');
  const learnSuppVal   = getRadio('learning_support');
  const employedVal    = getRadio('employed');
  const finAssistVal   = getRadio('fin_assist');
  const awareServVal   = getRadio('aware_services');
  const availedAnyVal  = getRadio('availed_any');
  const readinessVal   = getRadio('readiness');

  const serviceChalEl  = document.getElementById('service-challenges');
  const serviceChalVal = serviceChalEl ? (serviceChalEl.value || null) : null;

  const incomeClassEl  = document.getElementById('income-class-display');
  const incomeClassTxt = incomeClassEl ? incomeClassEl.innerText : '';
  const incomeClass    = (incomeClassTxt && incomeClassTxt !== 'Enter income to see classification') ? incomeClassTxt : null;

  // Collect family members by DOM order, using data-field attributes
  const familyMembers = [];
  let memberIndex = 0;
  document.querySelectorAll('.member-card').forEach((card) => {
    memberIndex++;
    const cardId  = card.id || '';
    const origNum = parseInt(cardId.replace('member-card-', '')) || memberIndex;

    const nameEl    = card.querySelector('[data-field="full_name"]');
    const relEl     = card.querySelector('[data-field="relationship_to_head"]');
    const civilEl   = card.querySelector('[data-field="civil_status"]');
    const ageEl     = card.querySelector('[data-field="age"]');
    const soloInput = card.querySelector('input[data-field="is_solo_parent"]:checked');
    const sexInput  = card.querySelector('input[data-field="member_sex"]:checked');
    const occEl     = document.getElementById(`dd-fam-occ-${origNum}`);
    const classEl   = document.getElementById(`dd-fam-class-${origNum}`);
    const disDiv    = document.getElementById(`dd-fam-dis-${origNum}`);
    const illDiv    = document.getElementById(`dd-fam-ill-${origNum}`);

    familyMembers.push({
      member_number:        memberIndex,
      full_name:            nameEl  ? nameEl.value.trim()  : '',
      relationship_to_head: relEl   ? (relEl.value   || null) : null,
      is_solo_parent:       soloInput ? soloInput.value === 'Yes' : false,
      civil_status:         civilEl ? (civilEl.value || null) : null,
      age:                  ageEl && ageEl.value ? (parseInt(ageEl.value) || null) : null,
      sex:                  sexInput ? sexInput.value : null,
      occupation:           occEl   ? (occEl.value   || null) : null,
      occupation_class:     classEl ? (classEl.value || null) : null,
      disabilities:         disDiv  ? Array.from(disDiv.querySelectorAll('input:checked')).map(cb => cb.value) : [],
      critical_illnesses:   illDiv  ? Array.from(illDiv.querySelectorAll('input:checked')).map(cb => cb.value) : [],
    });
  });

  return {
    session_id:       sessionStorage.getItem('session_id'),
    interviewer_id:   sessionStorage.getItem('interviewer_id'),
    interviewer_code: sessionStorage.getItem('interviewer_code'),
    readiness_score:  readinessVal,

    pre_qualification: {
      is_4ps_member: membershipVal === 'Yes',
      household_id:  membershipVal === 'Yes' ? getVal('household-id') : null,
    },

    respondent: {
      full_name:             getVal('resp-name') || '',
      relationship_to_child: getVal('dd-relationship'),
      email:                 getVal('resp-email'),
      contact_number:        getVal('resp-contact'),
    },

    child: {
      first_name:          getVal('child-fname') || '',
      middle_name:         getVal('child-mname'),
      last_name:           getVal('child-lname') || '',
      name_extension:      getVal('dd-extension'),
      region:              getVal('child-region'),
      province:            getVal('child-province'),
      city_municipality:   getVal('child-city'),
      barangay:            getVal('child-barangay'),
      street_address:      getVal('child-street'),
      contact_number:      getVal('child-contact'),
      date_of_birth:       getVal('child-dob'),
      sex:                 getRadio('sex'),
      religion:            religionVal !== 'Others' ? religionVal : null,
      religion_other:      religionVal === 'Others' ? getVal('rel-other') : null,
      ip_membership:       ipVal !== 'Others' ? ipVal : null,
      ip_membership_other: ipVal === 'Others' ? getVal('ip-other') : null,
    },

    child_education_health: {
      highest_education:       eduVal !== 'Others' ? eduVal : null,
      highest_education_other: eduVal === 'Others' ? getVal('edu-other') : null,
      disabilities:            getMulti('dd-disability'),
      critical_illnesses:      getMulti('dd-illness'),
      illness_other:           getVal('illness-other-input'),
    },

    family_members: familyMembers,

    socio_economic: {
      housing_materials:               materialsVal !== 'Others' ? materialsVal : null,
      housing_materials_other:         materialsVal === 'Others' ? getVal('mat-other') : null,
      tenure_status:                   tenureVal !== 'Others' ? tenureVal : null,
      tenure_status_other:             tenureVal === 'Others' ? getVal('tenure-other') : null,
      has_accessibility_modifications: getRadio('modifications') === 'Yes',
      modification_details:            getRadio('modifications') === 'Yes' ? getVal('mod-specify') : null,
      electricity_source:              elecVal !== 'Others' ? elecVal : null,
      electricity_source_other:        elecVal === 'Others' ? getVal('elec-other') : null,
      water_source:                    waterVal !== 'Others' ? waterVal : null,
      water_source_other:              waterVal === 'Others' ? getVal('water-other') : null,
      toilet_type:                     toiletVal !== 'Others' ? toiletVal : null,
      toilet_type_other:               toiletVal === 'Others' ? getVal('toilet-other') : null,
      is_toilet_accessible:            getRadio('toilet-access') === 'Yes',
      garbage_disposal:                garbageVal !== 'Others' ? garbageVal : null,
      garbage_disposal_other:          garbageVal === 'Others' ? getVal('garbage-other') : null,
    },

    health_info: {
      has_all_vaccinations:         getRadio('vaccines') === 'Yes',
      has_ongoing_health_conditions:healthCondVal === 'Yes',
      health_conditions_details:    healthCondVal === 'Yes' ? getVal('health-cond-specify') : null,
      expense_food:                 parseFloat(getVal('exp-food') || '0') || 0,
      expense_medication:           parseFloat(getVal('exp-med') || '0') || 0,
      expense_therapy:              parseFloat(getVal('exp-therapy') || '0') || 0,
      expense_hygiene:              parseFloat(getVal('exp-hygiene') || '0') || 0,
      expense_assistive_device:     parseFloat(getVal('exp-assist') || '0') || 0,
      expense_other:                parseFloat(getVal('exp-other') || '0') || 0,
      availed_services_6months:     availServVal === 'Yes',
      availed_services_details:     availServVal === 'Yes' ? getVal('avail-specify') : null,
      is_facility_accessible:       getRadio('facility_access') === 'Yes',
      has_barriers_to_healthcare:   barriersVal === 'Yes',
      healthcare_barriers_details:  barriersVal === 'Yes' ? getVal('barrier-specify') : null,
    },

    education_info: {
      is_currently_enrolled:          enrolledVal === 'Yes',
      grade_year_level:               enrolledVal === 'Yes' ? getVal('grade-level') : null,
      not_enrolled_reason:            enrolledVal !== 'Yes' ? getVal('not-enrolled-reason') : null,
      has_accessibility_features:     schoolFeatVal === 'Yes',
      accessibility_features_details: schoolFeatVal === 'Yes' ? getVal('school-access-specify') : null,
      has_sped_programs:              spedVal === 'Yes',
      sped_programs_details:          spedVal === 'Yes' ? getVal('sped-specify') : null,
      receives_learning_support:      learnSuppVal === 'Yes',
      learning_support_details:       learnSuppVal === 'Yes' ? getVal('learn-supp-specify') : null,
    },

    economic_capacity: {
      primary_income_source: getVal('income-source'),
      monthly_income:        parseFloat(getVal('monthly-income') || '0') || null,
      income_classification: incomeClass,
      are_parents_employed:  employedVal === 'Yes',
      employment_details:    employedVal === 'Yes' ? getVal('emp-specify') : null,
    },

    service_availment: {
      receives_financial_assistance: finAssistVal === 'Yes',
      financial_assistance_details:  finAssistVal === 'Yes' ? getVal('fin-assist-specify') : null,
      is_aware_of_social_services:   awareServVal === 'Yes',
      awareness_details:             awareServVal === 'Yes' ? getVal('aware-specify') : null,
      has_availed_services:          availedAnyVal === 'Yes',
      availed_services_details:      availedAnyVal === 'Yes' ? getVal('availed-specify') : null,
      service_challenges:            serviceChalVal !== 'Others' ? serviceChalVal : null,
      service_challenges_other:      serviceChalVal === 'Others' ? getVal('barrier-other') : null,
    },

    assessment_notes: {
      strengths:            getVal('strengths'),
      assessment_details:   getVal('assessment'),
      recommended_actions:  getVal('recommendations'),
      readiness_score:      readinessVal,
    },
  };
}

async function submitAssessment() {
  const submitBtn = document.querySelector('#step-11 button[onclick="submitAssessment()"]');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-symbols-outlined text-[16px]" style="animation:spin 1s linear infinite">progress_activity</span> Submitting...';
  }

  try {
    const formData = collectFormData();

    const response = await fetch('/api/submit-assessment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formData),
    });

    const result = await response.json();

    if (result.success) {
      const d = result.data || {};
      const params = new URLSearchParams({
        name:    d.child_name || '',
        arugaId: d.aruga_id   || '',
        email:   d.email      || '',
      });
      sessionStorage.clear();
      window.location.href = '/success.html?' + params.toString();
    } else {
      throw new Error(result.message || 'Submission failed');
    }
  } catch (error) {
    window.toast.error(error.message || 'Could not reach the server. Please try again.', 'Submission Error');
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Submit Assessment';
    }
  }
}

// ============================================================================
// END OF PROFILING TOOL JAVASCRIPT
// ============================================================================