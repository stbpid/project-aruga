/**
 * UAT Document Generator for Project Aruga
 * Generates UAT_ProjectAruga.xlsx with comprehensive test cases
 */

const XLSX = require('xlsx');

// ─── STYLES HELPER ────────────────────────────────────────────────────────────
// xlsx community edition has limited styling; we use html table approach for
// richer output, but since we need .xlsx we'll write raw workbook with borders.

// Column widths (in characters)
const COL_WIDTHS = [18, 52, 52, 16, 22, 22];
const COL_HEADERS = ['Module', 'Procedure', 'Requirements / Expected Output', 'Test Result', 'Remarks', 'Status\n(To be Filled Out By the Developer)'];

// ─── TEST CASE DATA ────────────────────────────────────────────────────────────

const modules = [];

// ══════════════════════════════════════════════════════════════════
// MODULE 0: COVER / SUMMARY  (handled as separate sheet)
// ══════════════════════════════════════════════════════════════════

// ══════════════════════════════════════════════════════════════════
// MODULE 1: PRIVACY AGREEMENT & ENTRY (Public Landing Page)
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '01 - Entry & Privacy',
  cases: [
    // Happy Path
    {
      module: 'Entry & Privacy Agreement',
      procedure: '1. Navigate to the application root URL (/).\n2. Review the privacy agreement text.\n3. Check the "I agree to the Privacy Policy" checkbox.\n4. Enter a valid interviewer code (e.g., "INT-001").\n5. Click "Proceed" or "Start Assessment".',
      expected: 'Expected Output: The system accepts the agreement and valid interviewer code. The page redirects to /profiling with the interviewer session initialized. The multi-step assessment form (Step 1) is displayed.',
    },
    // Validation: Privacy not accepted
    {
      module: 'Entry & Privacy Agreement',
      procedure: '1. Navigate to /.\n2. Do NOT check the privacy agreement checkbox.\n3. Enter a valid interviewer code.\n4. Click "Proceed".',
      expected: 'Expected Output: The system prevents navigation. An error or prompt is displayed indicating the user must accept the privacy policy before proceeding. No redirect occurs.',
    },
    // Validation: Empty interviewer code
    {
      module: 'Entry & Privacy Agreement',
      procedure: '1. Navigate to /.\n2. Check the privacy agreement checkbox.\n3. Leave the interviewer code field EMPTY.\n4. Click "Proceed".',
      expected: 'Expected Output: The system displays a validation error. The interviewer code field is highlighted. No redirect to /profiling occurs.',
    },
    // Validation: Invalid interviewer code (not found)
    {
      module: 'Entry & Privacy Agreement',
      procedure: '1. Navigate to /.\n2. Check the privacy agreement checkbox.\n3. Enter a non-existent interviewer code (e.g., "INVALID999").\n4. Click "Proceed".',
      expected: 'Expected Output: The API call to /api/validate-interviewer.php returns an error. The system displays "Interviewer code not found" or equivalent. No redirect occurs.',
    },
    // Validation: Inactive interviewer code
    {
      module: 'Entry & Privacy Agreement',
      procedure: '1. Navigate to /.\n2. Check the privacy agreement checkbox.\n3. Enter an INACTIVE interviewer code.\n4. Click "Proceed".',
      expected: 'Expected Output: The API returns an error indicating the interviewer account is inactive. The system blocks entry and displays an appropriate message.',
    },
    // Direct URL access to /profiling without valid session
    {
      module: 'Entry & Privacy Agreement',
      procedure: '1. Without going through the entry page, directly navigate to /profiling in the browser.',
      expected: 'Expected Output: The system detects no active session. The user is redirected back to / (entry page). Access to the profiling form is denied without valid session.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 2: PROFILING FORM – STEP 1 (Pre-Qualification)
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '02 - Profiling Step 1',
  cases: [
    {
      module: 'Profiling – Step 1: Pre-Qualification',
      procedure: '1. Complete entry with a valid interviewer code.\n2. On Step 1, select "Yes" for "Is the child a 4Ps member?".\n3. Enter a valid Household ID (e.g., "HH-2024-0001").\n4. Click "Next".',
      expected: 'Expected Output: Step 1 data is saved. The form advances to Step 2 (Respondent Information). The progress bar updates to reflect Step 2.',
    },
    {
      module: 'Profiling – Step 1: Pre-Qualification',
      procedure: '1. On Step 1, select "No" for "Is the child a 4Ps member?".\n2. Leave Household ID empty.\n3. Click "Next".',
      expected: 'Expected Output: Step 1 accepts the data without requiring Household ID (it is optional). The form advances to Step 2.',
    },
    {
      module: 'Profiling – Step 1: Pre-Qualification',
      procedure: '1. On Step 1, leave "Is the child a 4Ps member?" unselected (no answer).\n2. Click "Next".',
      expected: 'Expected Output: The system flags the required boolean field. An error message is shown. The form does NOT advance to Step 2.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 3: PROFILING FORM – STEP 2 (Respondent Information)
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '03 - Profiling Step 2',
  cases: [
    // Happy path
    {
      module: 'Profiling – Step 2: Respondent Info',
      procedure: '1. On Step 2, enter Full Name: "Maria Dela Cruz".\n2. Select Relationship to Child: "Parent".\n3. Enter Email: "maria@example.com".\n4. Enter Contact Number: "09171234567".\n5. Click "Next".',
      expected: 'Expected Output: Step 2 data is accepted. The form advances to Step 3 (Child Demographics).',
    },
    // Required: full_name empty
    {
      module: 'Profiling – Step 2: Respondent Info',
      procedure: '1. On Step 2, leave Full Name EMPTY.\n2. Select Relationship: "Guardian".\n3. Click "Next".',
      expected: 'Expected Output: Validation error is shown on the Full Name field. The form does not advance.',
    },
    // Required: relationship not selected
    {
      module: 'Profiling – Step 2: Respondent Info',
      procedure: '1. On Step 2, enter Full Name: "Juan Reyes".\n2. Leave Relationship to Child unselected.\n3. Click "Next".',
      expected: 'Expected Output: Validation error on the Relationship field. The form does not advance.',
    },
    // Invalid email format
    {
      module: 'Profiling – Step 2: Respondent Info',
      procedure: '1. On Step 2, fill all required fields.\n2. Enter Email: "notanemail" (invalid format).\n3. Click "Next".',
      expected: 'Expected Output: Email format validation fails. An error message such as "Please enter a valid email address" is shown. The form does not advance.',
    },
    // Valid without optional fields
    {
      module: 'Profiling – Step 2: Respondent Info',
      procedure: '1. On Step 2, enter Full Name: "Pedro Santos".\n2. Select Relationship: "Social Worker".\n3. Leave Email and Contact Number EMPTY.\n4. Click "Next".',
      expected: 'Expected Output: Since Email and Contact Number are optional, the form accepts the input and advances to Step 3.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 4: PROFILING FORM – STEP 3A (Child Demographics)
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '04 - Profiling Step 3A',
  cases: [
    {
      module: 'Profiling – Step 3A: Child Demographics',
      procedure: '1. On Step 3A, enter First Name: "Juan".\n2. Enter Last Name: "Santos".\n3. Enter Middle Name: "Cruz".\n4. Select Name Extension: "Jr.".\n5. Enter Date of Birth: "2015-06-15".\n6. Select Sex: "Male".\n7. Select Region: "NCR (National Capital Region)".\n8. Enter Province: "Metro Manila".\n9. Enter City/Municipality: "Quezon City".\n10. Enter Barangay: "Batasan Hills".\n11. Select Religion: "Roman Catholic".\n12. Select IP Membership: "Not a member".\n13. Click "Next".',
      expected: 'Expected Output: All required child demographic data is accepted. The form advances to Step 3B.',
    },
    // Missing first name
    {
      module: 'Profiling – Step 3A: Child Demographics',
      procedure: '1. On Step 3A, leave First Name EMPTY.\n2. Fill all other required fields.\n3. Click "Next".',
      expected: 'Expected Output: Validation error on First Name field. Form does not advance.',
    },
    // Missing last name
    {
      module: 'Profiling – Step 3A: Child Demographics',
      procedure: '1. On Step 3A, enter First Name but leave Last Name EMPTY.\n2. Fill all other required fields.\n3. Click "Next".',
      expected: 'Expected Output: Validation error on Last Name field. Form does not advance.',
    },
    // Missing date of birth
    {
      module: 'Profiling – Step 3A: Child Demographics',
      procedure: '1. On Step 3A, fill all fields except Date of Birth.\n2. Click "Next".',
      expected: 'Expected Output: Validation error on Date of Birth field. Form does not advance.',
    },
    // Invalid date format
    {
      module: 'Profiling – Step 3A: Child Demographics',
      procedure: '1. On Step 3A, enter Date of Birth as "32/13/2020" (invalid date).\n2. Fill all other required fields.\n3. Click "Next".',
      expected: 'Expected Output: Invalid date is rejected. An appropriate error message is shown. Form does not advance.',
    },
    // Religion = Others → religion_other required
    {
      module: 'Profiling – Step 3A: Child Demographics',
      procedure: '1. On Step 3A, fill all required fields.\n2. Select Religion: "Others".\n3. Leave the "Please specify religion" field EMPTY.\n4. Click "Next".',
      expected: 'Expected Output: When "Others" is selected for Religion, the specification field becomes required. A validation error is shown. Form does not advance.',
    },
    // IP Membership = Others → ip_membership_other required
    {
      module: 'Profiling – Step 3A: Child Demographics',
      procedure: '1. On Step 3A, fill all required fields.\n2. Select IP Membership: "Others".\n3. Leave the "Please specify IP group" field EMPTY.\n4. Click "Next".',
      expected: 'Expected Output: When "Others" is selected for IP Membership, the specification field is required. A validation error is shown. Form does not advance.',
    },
    // Region not selected
    {
      module: 'Profiling – Step 3A: Child Demographics',
      procedure: '1. On Step 3A, fill all fields except Region.\n2. Click "Next".',
      expected: 'Expected Output: Validation error on Region field. Form does not advance.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 5: PROFILING FORM – STEP 3B (Education & Health)
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '05 - Profiling Step 3B',
  cases: [
    {
      module: 'Profiling – Step 3B: Education & Health',
      procedure: '1. On Step 3B, select Highest Education: "Elementary Undergraduate".\n2. Select at least one Disability from the list: "Visual Disability".\n3. Select at least one Critical Illness: "None".\n4. Click "Next".',
      expected: 'Expected Output: Education and health data accepted. Form advances to Step 4.',
    },
    {
      module: 'Profiling – Step 3B: Education & Health',
      procedure: '1. On Step 3B, leave Highest Education unselected.\n2. Click "Next".',
      expected: 'Expected Output: Validation error on Highest Education. Form does not advance.',
    },
    {
      module: 'Profiling – Step 3B: Education & Health',
      procedure: '1. On Step 3B, select no Disability (leave empty).\n2. Click "Next".',
      expected: 'Expected Output: At least one disability option must be selected (including "None" if applicable). Validation error shown if completely empty.',
    },
    {
      module: 'Profiling – Step 3B: Education & Health',
      procedure: '1. On Step 3B, select Disability: "Other (specify)".\n2. Leave the "Please specify other disability" field EMPTY.\n3. Click "Next".',
      expected: 'Expected Output: When "Other (specify)" is selected, the specification text field is required. Validation error shown. Form does not advance.',
    },
    {
      module: 'Profiling – Step 3B: Education & Health',
      procedure: '1. On Step 3B, select an invalid/non-whitelisted disability value by manipulating the form (e.g., via browser console).\n2. Submit the step.',
      expected: 'Expected Output: Server-side sanitizeArray() rejects the invalid value. The API returns an error. The assessment is not saved.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 6: PROFILING FORM – STEP 4 (Family Members)
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '06 - Profiling Step 4',
  cases: [
    {
      module: 'Profiling – Step 4: Family Members',
      procedure: '1. On Step 4, click "Add Family Member".\n2. Enter Full Name: "Rosa Santos".\n3. Select Relationship to Head: "Mother".\n4. Enter Age: "38".\n5. Select Sex: "Female".\n6. Select Civil Status: "Married".\n7. Select Occupation: "Housewife/Homemaker".\n8. Select Disability: "None".\n9. Select Critical Illness: "None".\n10. Click "Next".',
      expected: 'Expected Output: At least one family member is recorded. Form advances to Step 5.',
    },
    {
      module: 'Profiling – Step 4: Family Members',
      procedure: '1. On Step 4, click "Add Family Member".\n2. Leave Full Name EMPTY.\n3. Click "Next".',
      expected: 'Expected Output: Validation error on the family member Full Name field. Form does not advance.',
    },
    {
      module: 'Profiling – Step 4: Family Members',
      procedure: '1. On Step 4, add a family member.\n2. Enter Age: "abc" (non-numeric).\n3. Click "Next".',
      expected: 'Expected Output: Age field rejects non-numeric input or the server casts to integer (resulting in 0). Validation error is shown for an invalid age.',
    },
    {
      module: 'Profiling – Step 4: Family Members',
      procedure: '1. On Step 4, add 3 family members with all required fields filled correctly.\n2. Click "Next".',
      expected: 'Expected Output: All three family members are accepted. The form advances to Step 5. Each member is stored with sequential member_number.',
    },
    {
      module: 'Profiling – Step 4: Family Members',
      procedure: '1. On Step 4, add a family member.\n2. Toggle "Is Solo Parent?" to Yes.\n3. Complete all other fields.\n4. Click "Next".',
      expected: 'Expected Output: The solo parent flag is accepted. Form advances to Step 5.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 7: PROFILING FORM – STEPS 5–9 (Socio-Economic to Service)
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '07 - Profiling Steps 5-9',
  cases: [
    // Step 5 happy path
    {
      module: 'Profiling – Step 5: Socio-Economic',
      procedure: '1. On Step 5, select Housing Materials: "Strong materials".\n2. Select Tenure Status: "Own house and lot".\n3. Select Yes for Accessibility Modifications.\n4. Enter Modification Details: "Wheelchair ramp installed".\n5. Select Electricity Source: "Distribution company (MERALCO, etc.)".\n6. Select Water Source: "Level III (Piped water system)".\n7. Select Toilet Type: "Water-sealed (own use)".\n8. Select Garbage Disposal: "Regular garbage collection".\n9. Click "Next".',
      expected: 'Expected Output: Socio-economic data is accepted. Form advances to Step 6.',
    },
    // Step 5 - Housing = Others
    {
      module: 'Profiling – Step 5: Socio-Economic',
      procedure: '1. On Step 5, select Housing Materials: "Others".\n2. Leave the specify field EMPTY.\n3. Click "Next".',
      expected: 'Expected Output: When "Others" is selected for Housing Materials, the specification field is required. Validation error shown.',
    },
    // Step 6 happy path
    {
      module: 'Profiling – Step 6: Health Information',
      procedure: '1. On Step 6, select "Yes" for Has All Vaccinations.\n2. Select "No" for Has Ongoing Health Conditions.\n3. Enter monthly expenses: Food: 3000, Medication: 500, Therapy: 200, Hygiene: 150, Assistive Device: 0, Other: 0.\n4. Select "No" for Availed Services in Last 6 Months.\n5. Select "Yes" for Is Facility Accessible.\n6. Select "No" for Has Barriers to Healthcare.\n7. Click "Next".',
      expected: 'Expected Output: Health information data accepted. Form advances to Step 7.',
    },
    // Step 6 - negative expense
    {
      module: 'Profiling – Step 6: Health Information',
      procedure: '1. On Step 6, enter expense_food: "-500" (negative value).\n2. Fill other required fields.\n3. Click "Next".',
      expected: 'Expected Output: Negative expense values should be rejected or treated as 0 by server-side validation (float cast). An error or automatic correction is displayed.',
    },
    // Step 7 happy path
    {
      module: 'Profiling – Step 7: Education Info',
      procedure: '1. On Step 7, select "Yes" for Is Currently Enrolled.\n2. Enter Grade/Year Level: "Grade 3".\n3. Select "Yes" for Has Accessibility Features.\n4. Enter details: "Ramps and wide doorways".\n5. Select "No" for Has SPED Programs.\n6. Select "Yes" for Receives Learning Support.\n7. Enter support details: "Special Education teacher".\n8. Click "Next".',
      expected: 'Expected Output: Education information accepted. Form advances to Step 8.',
    },
    // Step 7 - not enrolled, missing reason
    {
      module: 'Profiling – Step 7: Education Info',
      procedure: '1. On Step 7, select "No" for Is Currently Enrolled.\n2. Leave Not Enrolled Reason EMPTY.\n3. Click "Next".',
      expected: 'Expected Output: When not enrolled is selected, the reason field becomes required. Validation error shown if empty.',
    },
    // Step 8 happy path
    {
      module: 'Profiling – Step 8: Economic Capacity',
      procedure: '1. On Step 8, select Primary Income Source: "Employment/Salary".\n2. Enter Monthly Income: "15000".\n3. Select Income Classification: "Below Poverty Line".\n4. Select "Yes" for Are Parents Employed.\n5. Enter Employment Details: "Father works as laborer".\n6. Click "Next".',
      expected: 'Expected Output: Economic data accepted. Form advances to Step 9.',
    },
    // Step 8 - zero income
    {
      module: 'Profiling – Step 8: Economic Capacity',
      procedure: '1. On Step 8, enter Monthly Income: "0".\n2. Fill all other required fields.\n3. Click "Next".',
      expected: 'Expected Output: Monthly income must be greater than 0. Server-side validation rejects 0 or null income. Validation error is shown.',
    },
    // Step 8 - non-numeric income
    {
      module: 'Profiling – Step 8: Economic Capacity',
      procedure: '1. On Step 8, enter Monthly Income: "fifteen thousand" (text).\n2. Fill all other required fields.\n3. Click "Next".',
      expected: 'Expected Output: Non-numeric income value rejected. Validation error shown on Monthly Income field.',
    },
    // Step 9 happy path
    {
      module: 'Profiling – Step 9: Service Availment',
      procedure: '1. On Step 9, select "Yes" for Receives Financial Assistance.\n2. Enter details: "4Ps monthly cash grant".\n3. Select "Yes" for Is Aware of Social Services.\n4. Select "Yes" for Has Availed Services.\n5. Enter availed details: "PWD ID, PhilHealth".\n6. Select Service Challenges: "None".\n7. Click "Next".',
      expected: 'Expected Output: Service availment data accepted. Form advances to Step 10.',
    },
    // Step 9 - service_challenges = Others
    {
      module: 'Profiling – Step 9: Service Availment',
      procedure: '1. On Step 9, select Service Challenges: "Others".\n2. Leave the specify field EMPTY.\n3. Click "Next".',
      expected: 'Expected Output: When "Others" is selected for Service Challenges, the specification field is required. Validation error shown.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 8: PROFILING FORM – STEP 10 & SUBMISSION
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '08 - Profiling Step 10 & Submit',
  cases: [
    // Happy path submission
    {
      module: 'Profiling – Step 10 & Final Submission',
      procedure: '1. On Step 10, enter Strengths: "Child shows resilience and has supportive family".\n2. Enter Assessment Details: "Child assessed during home visit; cooperative respondent".\n3. Enter Recommended Actions: "Refer to DSWD social worker; enroll in SPED program".\n4. Select Readiness Score: "Moderate".\n5. Click "Submit Assessment".',
      expected: 'Expected Output: The system posts to /api/submit-assessment.php. A unique ARUGA ID is generated (format: ARUGA-YYYY-RR-NNNN). The page redirects to /success. A confirmation message is displayed with the generated ARUGA ID.',
    },
    // Missing strengths
    {
      module: 'Profiling – Step 10 & Final Submission',
      procedure: '1. On Step 10, leave Strengths EMPTY.\n2. Enter Assessment Details and Recommended Actions.\n3. Click "Submit Assessment".',
      expected: 'Expected Output: Validation error on Strengths field. Submission does not proceed.',
    },
    // Missing assessment details
    {
      module: 'Profiling – Step 10 & Final Submission',
      procedure: '1. On Step 10, enter Strengths but leave Assessment Details EMPTY.\n2. Click "Submit Assessment".',
      expected: 'Expected Output: Validation error on Assessment Details field. Submission does not proceed.',
    },
    // Missing recommended actions
    {
      module: 'Profiling – Step 10 & Final Submission',
      procedure: '1. On Step 10, fill Strengths and Assessment Details, but leave Recommended Actions EMPTY.\n2. Click "Submit Assessment".',
      expected: 'Expected Output: Validation error on Recommended Actions field. Submission does not proceed.',
    },
    // ARUGA ID format verification
    {
      module: 'Profiling – Step 10 & Final Submission',
      procedure: '1. Complete all 10 steps with valid data for a child from Region I.\n2. Submit the assessment.\n3. Note the ARUGA ID shown on the /success page.',
      expected: 'Expected Output: The ARUGA ID follows the format "ARUGA-YYYY-RR-NNNN" where YYYY is the current year, RR is the region code (e.g., "01" for Region I), and NNNN is a zero-padded sequence number.',
    },
    // Duplicate submission (same session)
    {
      module: 'Profiling – Step 10 & Final Submission',
      procedure: '1. Complete and successfully submit an assessment.\n2. Navigate back to the profiling form without starting a new session.\n3. Attempt to submit again.',
      expected: 'Expected Output: The system detects the session has already been used for a completed submission. Duplicate submission is prevented, and the user is shown a message or redirect.',
    },
    // SQL injection in text field
    {
      module: 'Profiling – Step 10 & Final Submission',
      procedure: '1. In Strengths field, enter: "test\'; DROP TABLE assessments; --".\n2. Submit the assessment.',
      expected: 'Expected Output: The input is sanitized by sanitizeInput() (htmlspecialchars). The text is stored safely without executing as SQL. The application uses Supabase parameterized queries preventing SQL injection.',
    },
    // XSS in text field
    {
      module: 'Profiling – Step 10 & Final Submission',
      procedure: '1. In Assessment Details field, enter: "<script>alert(\'XSS\')</script>".\n2. Submit the assessment.\n3. Open the beneficiary detail view.',
      expected: 'Expected Output: The input is sanitized by htmlspecialchars(ENT_QUOTES). The script tags are escaped and displayed as plain text, not executed. No alert dialog appears.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 9: DASHBOARD LOGIN
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '09 - Dashboard Login',
  cases: [
    // Happy path - admin
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Enter a valid admin email (e.g., "admin@dswd.gov.ph").\n3. Enter the correct password.\n4. Click "Login".',
      expected: 'Expected Output: POST to /api/dashboard-login.php returns success. Session is created. User is redirected to /dashboard-admin. The admin dashboard home page is displayed.',
    },
    // Happy path - field officer
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Enter a valid field_officer email.\n3. Enter the correct password.\n4. Click "Login".',
      expected: 'Expected Output: Login succeeds. Session created. User is redirected to /dashboard-field-officer. Field officer dashboard is displayed with personal statistics.',
    },
    // Happy path - stu_head
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Enter a valid stu_head email.\n3. Enter the correct password.\n4. Click "Login".',
      expected: 'Expected Output: Login succeeds. Session created. User is redirected to /dashboard-stu-head. STU Head dashboard with regional data is displayed.',
    },
    // Happy path - central
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Enter a valid central office email.\n3. Enter the correct password.\n4. Click "Login".',
      expected: 'Expected Output: Login succeeds. Session created. User is redirected to /dashboard-central. National analytics dashboard is displayed.',
    },
    // Empty email
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Leave the Email field EMPTY.\n3. Enter a password.\n4. Click "Login".',
      expected: 'Expected Output: The API returns HTTP 400. Response: "Email and password are required". No session is created. User stays on the login page.',
    },
    // Empty password
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Enter a valid email.\n3. Leave the Password field EMPTY.\n4. Click "Login".',
      expected: 'Expected Output: The API returns HTTP 400. Response: "Email and password are required". No session is created.',
    },
    // Invalid email format
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Enter Email: "notavalidemail" (no @ symbol).\n3. Enter any password.\n4. Click "Login".',
      expected: 'Expected Output: The API returns HTTP 400. Response: "Invalid email address". No session is created.',
    },
    // Wrong password
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Enter a valid existing email.\n3. Enter WRONG password: "WrongPass123!".\n4. Click "Login".',
      expected: 'Expected Output: The API returns HTTP 401. Response indicating invalid credentials. The failed attempt is logged. User stays on login page.',
    },
    // Non-existent email
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Enter a non-existent email: "ghost@dswd.gov.ph".\n3. Enter any password.\n4. Click "Login".',
      expected: 'Expected Output: Login fails. The system returns an error indicating invalid credentials (without revealing whether the email exists). User stays on login page.',
    },
    // Rate limiting - 5 failed attempts
    {
      module: 'Dashboard Login',
      procedure: '1. Navigate to /dashboard.\n2. Attempt login with a valid email and wrong password 5 consecutive times.\n3. Observe the response on the 5th failed attempt.\n4. Attempt a 6th login (even with correct credentials).',
      expected: 'Expected Output: On the 5th and subsequent attempts within 15 minutes, the API returns HTTP 429. Rate limit message is displayed. Even correct credentials are blocked during the lockout period.',
    },
    // Session expiry
    {
      module: 'Dashboard Login',
      procedure: '1. Log in successfully to any dashboard.\n2. Do not interact with the system for 30+ minutes (session idle timeout).\n3. Attempt to navigate to a protected API endpoint or refresh the dashboard.',
      expected: 'Expected Output: The session is expired. The API returns HTTP 401. The user is redirected to the login page with a session expired message.',
    },
    // Direct URL access without login (unauthorized)
    {
      module: 'Dashboard Login',
      procedure: '1. Without logging in, directly navigate to /dashboard-admin in the browser.',
      expected: 'Expected Output: The page detects no valid session token. The user is redirected to /dashboard (login page). Access to the admin dashboard is denied.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 10: FIELD OFFICER DASHBOARD
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '10 - Field Officer Dashboard',
  cases: [
    // Overview stats
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as a field officer.\n2. Navigate to the Overview/Home page.\n3. Observe the dashboard statistics.',
      expected: 'Expected Output: The dashboard displays personal stats: Total Submitted, Accepted, Under Review, and Needs Correction counts. Stats are fetched from /api/field-officer/stats.php.',
    },
    // My Beneficiaries list
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as a field officer.\n2. Navigate to "My Beneficiaries".\n3. Observe the list of beneficiaries.',
      expected: 'Expected Output: A paginated list of beneficiaries assessed by this field officer is shown. Each entry shows ARUGA ID, child name, assessment date, and status. Data is fetched from /api/beneficiaries.php.',
    },
    // Empty beneficiaries state
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as a NEW field officer with no submitted assessments.\n2. Navigate to "My Beneficiaries".',
      expected: 'Expected Output: An empty state is shown (e.g., "No beneficiaries found" or similar message). No error or blank page is displayed.',
    },
    // Beneficiary detail view
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as a field officer.\n2. Navigate to "My Beneficiaries".\n3. Click on any beneficiary record to view details.',
      expected: 'Expected Output: The full beneficiary profile is displayed, including all 10 assessment steps. Data is fetched from /api/get-beneficiary-detail.php with the ARUGA ID.',
    },
    // Performance metrics
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as a field officer.\n2. Navigate to "My Performance".',
      expected: 'Expected Output: Performance metrics are displayed including workload indicators (overloaded/balanced/underutilized), monthly progress chart, and submission history. Data from /api/field-officer/performance.php.',
    },
    // Action Required
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as a field officer.\n2. Navigate to "Action Required".\n3. Observe any items needing correction.',
      expected: 'Expected Output: List of beneficiaries returned by /api/field-officer/action-required.php with status "correction" or similar. Clicking an item should allow the field officer to submit an edit request.',
    },
    // Submit edit request
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as a field officer.\n2. Find a beneficiary record that requires correction.\n3. Click "Submit Edit Request".\n4. Modify relevant fields.\n5. Add a note explaining the change.\n6. Click "Submit".',
      expected: 'Expected Output: POST to /api/field-officer/submit-edit-request.php. Edit request created with status "pending". Confirmation is shown. The record now appears under the STU Head\'s pending reviews.',
    },
    // QR lookup
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as a field officer.\n2. Use the QR code lookup feature.\n3. Scan or manually enter an ARUGA ID.',
      expected: 'Expected Output: /api/field-officer/qr-lookup.php is called. The corresponding beneficiary profile is retrieved and displayed.',
    },
    // Unauthorized access to another FO beneficiary
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as Field Officer A.\n2. Manually modify the ARUGA ID parameter in the URL or API request to access a beneficiary submitted by Field Officer B.',
      expected: 'Expected Output: The system either denies access or only returns data that the field officer is authorized to view. Field Officer A cannot view Field Officer B\'s restricted data.',
    },
    // Admin accessing field officer dashboard
    {
      module: 'Field Officer Dashboard',
      procedure: '1. Log in as an admin user.\n2. Navigate directly to /dashboard-field-officer.',
      expected: 'Expected Output: Access is denied or the system redirects to the admin dashboard. Role mismatch is handled gracefully.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 11: STU HEAD DASHBOARD & EDIT REQUESTS
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '11 - STU Head Dashboard',
  cases: [
    // Overview
    {
      module: 'STU Head Dashboard',
      procedure: '1. Log in as an STU Head user.\n2. Navigate to the Overview page.\n3. Observe regional statistics.',
      expected: 'Expected Output: Regional summary stats are displayed: total beneficiaries in region, assessments, completion rate. Data from /api/stu-head/stats.php.',
    },
    // Regional beneficiary list
    {
      module: 'STU Head Dashboard',
      procedure: '1. Log in as STU Head.\n2. Navigate to "Beneficiaries".\n3. View the list.',
      expected: 'Expected Output: Only beneficiaries from the STU Head\'s assigned region are shown. No beneficiaries from other regions appear in the list.',
    },
    // Field officers list
    {
      module: 'STU Head Dashboard',
      procedure: '1. Log in as STU Head.\n2. Navigate to "User Management".\n3. View the list of field officers.',
      expected: 'Expected Output: Only field officers assigned to the STU Head\'s region are listed. Data from /api/stu-head/interviewers.php with region filter.',
    },
    // Pending edit requests badge
    {
      module: 'STU Head Dashboard',
      procedure: '1. Log in as STU Head.\n2. Observe the sidebar "Pending Reviews" item.\n3. Check for badge counter.',
      expected: 'Expected Output: The sidebar item shows a badge with the count of pending edit requests for the region. Badge updates dynamically.',
    },
    // View pending edit request
    {
      module: 'STU Head Dashboard',
      procedure: '1. Log in as STU Head.\n2. Navigate to "Pending Reviews".\n3. Click on a pending edit request.',
      expected: 'Expected Output: The edit request details are shown including the current data, proposed changes, and the field officer\'s notes. Data from /api/stu-head/edit-requests.php.',
    },
    // Approve edit request
    {
      module: 'STU Head Dashboard – Edit Request Review',
      procedure: '1. Log in as STU Head.\n2. Navigate to a pending edit request.\n3. Select Action: "Approved".\n4. Click "Submit Review".',
      expected: 'Expected Output: POST to /api/stu-head/review-edit-request.php with action="approved". The beneficiary record is updated with the payload changes. Request status changes to "approved". Audit log is created.',
    },
    // Decline edit request
    {
      module: 'STU Head Dashboard – Edit Request Review',
      procedure: '1. Log in as STU Head.\n2. Navigate to a pending edit request.\n3. Select Action: "Declined".\n4. Click "Submit Review".',
      expected: 'Expected Output: Request status changes to "declined". Beneficiary record is NOT modified. Audit log is created.',
    },
    // For Update (requires note)
    {
      module: 'STU Head Dashboard – Edit Request Review',
      procedure: '1. Log in as STU Head.\n2. Navigate to a pending edit request.\n3. Select Action: "For Update".\n4. Leave Reviewer Note EMPTY.\n5. Click "Submit Review".',
      expected: 'Expected Output: The API validates that reviewer_note is required for "for_update" action. Returns HTTP 400. Error message displayed. Request remains pending.',
    },
    // For Update with note
    {
      module: 'STU Head Dashboard – Edit Request Review',
      procedure: '1. Log in as STU Head.\n2. Navigate to a pending edit request.\n3. Select Action: "For Update".\n4. Enter Reviewer Note: "Please correct the date of birth and resubmit".\n5. Click "Submit Review".',
      expected: 'Expected Output: Request status changes to "for_update". Field officer is notified. Reviewer note is saved. Beneficiary record is NOT modified until approved.',
    },
    // Review already-reviewed request
    {
      module: 'STU Head Dashboard – Edit Request Review',
      procedure: '1. Log in as STU Head.\n2. Find an edit request with status "approved" or "declined".\n3. Attempt to change the action and resubmit.',
      expected: 'Expected Output: The API rejects the action since request status is not "pending". Returns error: request is not in pending status. No changes are made.',
    },
    // Invalid action value
    {
      module: 'STU Head Dashboard – Edit Request Review',
      procedure: '1. Log in as STU Head.\n2. Attempt to submit an edit request review with an invalid action value (e.g., "maybe") via API manipulation.',
      expected: 'Expected Output: Server-side validation rejects invalid action. Returns HTTP 400 with error indicating valid actions are: approved, for_update, declined.',
    },
    // Cross-region access attempt
    {
      module: 'STU Head Dashboard',
      procedure: '1. Log in as STU Head of Region I.\n2. Attempt to access edit requests of Region III by modifying the region parameter in the API request.',
      expected: 'Expected Output: The API enforces region-based access control. Requests from other regions are not returned or an authorization error is returned.',
    },
    // QR Code scan
    {
      module: 'STU Head Dashboard',
      procedure: '1. Log in as STU Head.\n2. Navigate to "Scan QR Code".\n3. Scan a valid ARUGA QR code or enter the ARUGA ID manually.',
      expected: 'Expected Output: The beneficiary profile corresponding to the scanned ARUGA ID is retrieved and displayed.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 12: ADMIN DASHBOARD – USER MANAGEMENT
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '12 - Admin User Mgmt',
  cases: [
    // View interviewers list
    {
      module: 'Admin – User Management',
      procedure: '1. Log in as admin.\n2. Navigate to "Users" or "Interviewers".\n3. View the list of interviewers.',
      expected: 'Expected Output: A paginated list of all interviewers/field officers is shown including their name, code, region, role, and status. Data from /api/get-interviewers.php.',
    },
    // Add interviewer - happy path
    {
      module: 'Admin – Add Interviewer',
      procedure: '1. Log in as admin.\n2. Navigate to "Add Interviewer".\n3. Enter Full Name: "Ana Reyes".\n4. Enter Interviewer Code: "FO-R1-0042".\n5. Select Region: "Region I".\n6. Enter Email: "ana.reyes@dswd.gov.ph".\n7. Enter Password: "SecurePass1!".\n8. Select Dashboard Role: "field_officer".\n9. Set Status: "active".\n10. Click "Save".',
      expected: 'Expected Output: POST to /api/add-interviewer.php. New interviewer record created. Password stored as bcrypt hash. Success message displayed. New interviewer appears in the list.',
    },
    // Add interviewer - missing full name
    {
      module: 'Admin – Add Interviewer',
      procedure: '1. Log in as admin.\n2. Navigate to "Add Interviewer".\n3. Leave Full Name EMPTY.\n4. Fill all other required fields.\n5. Click "Save".',
      expected: 'Expected Output: Validation error on Full Name field. Interviewer is NOT created.',
    },
    // Add interviewer - missing code
    {
      module: 'Admin – Add Interviewer',
      procedure: '1. Log in as admin.\n2. Navigate to "Add Interviewer".\n3. Leave Interviewer Code EMPTY.\n4. Fill other fields.\n5. Click "Save".',
      expected: 'Expected Output: Validation error on Interviewer Code field. Interviewer is NOT created.',
    },
    // Add interviewer - missing region
    {
      module: 'Admin – Add Interviewer',
      procedure: '1. Log in as admin.\n2. Navigate to "Add Interviewer".\n3. Do not select a Region.\n4. Fill other fields.\n5. Click "Save".',
      expected: 'Expected Output: Validation error on Region field. Interviewer is NOT created.',
    },
    // Add interviewer - invalid email
    {
      module: 'Admin – Add Interviewer',
      procedure: '1. Log in as admin.\n2. Navigate to "Add Interviewer".\n3. Enter Email: "invalidemail" (no @ symbol).\n4. Fill other required fields.\n5. Click "Save".',
      expected: 'Expected Output: API returns validation error: "Invalid email address". Interviewer is NOT created.',
    },
    // Add interviewer - duplicate email
    {
      module: 'Admin – Add Interviewer',
      procedure: '1. Log in as admin.\n2. Navigate to "Add Interviewer".\n3. Enter an Email that already exists in the system.\n4. Fill other required fields.\n5. Click "Save".',
      expected: 'Expected Output: The system checks email uniqueness. Returns error indicating the email is already registered. Interviewer is NOT created.',
    },
    // Add interviewer - password too short
    {
      module: 'Admin – Add Interviewer',
      procedure: '1. Log in as admin.\n2. Navigate to "Add Interviewer".\n3. Enter valid email and password of only 5 characters: "Ab1!x".\n4. Fill other required fields.\n5. Click "Save".',
      expected: 'Expected Output: Password is too short. The API enforces minimum 8 characters for passwords. Error shown: password must be at least 8 characters. Interviewer is NOT created.',
    },
    // Update interviewer - happy path
    {
      module: 'Admin – Update Interviewer',
      procedure: '1. Log in as admin.\n2. Navigate to the interviewer list.\n3. Click "Edit" on an interviewer.\n4. Update Full Name to "Ana Marie Reyes".\n5. Change Status to "inactive".\n6. Click "Save".',
      expected: 'Expected Output: POST to /api/update-interviewer.php. Interviewer record is updated. Success message displayed. Updated values reflected in the list.',
    },
    // Deactivate interviewer
    {
      module: 'Admin – Update Interviewer',
      procedure: '1. Log in as admin.\n2. Find an active field officer with submitted assessments.\n3. Change their status to "inactive".\n4. Click "Save".',
      expected: 'Expected Output: Interviewer status updated to inactive. The interviewer can no longer log in to the dashboard. Existing assessment records are preserved.',
    },
    // Non-admin trying to add interviewer
    {
      module: 'Admin – User Management',
      procedure: '1. Log in as a field_officer role user.\n2. Attempt to call /api/add-interviewer.php directly via API.',
      expected: 'Expected Output: The API denies access. HTTP 403 or 401 returned. Only admin/stu_head roles are authorized to add interviewers.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 13: CENTRAL/ADMIN – ANALYTICS & REPORTING
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '13 - Analytics & Reports',
  cases: [
    // Central analytics default view
    {
      module: 'Analytics – Central Dashboard',
      procedure: '1. Log in as a central office user.\n2. Navigate to "Analytics".\n3. Observe the default view.',
      expected: 'Expected Output: National analytics are displayed from /api/get-analytics.php. Summary includes: total beneficiaries, completion rate, regional breakdown, disability distribution, and interviewer workload categories.',
    },
    // Filter by region
    {
      module: 'Analytics – Central Dashboard',
      procedure: '1. Log in as central office.\n2. Navigate to "Analytics".\n3. Apply Region filter: "Region III".\n4. Observe results.',
      expected: 'Expected Output: Analytics data is filtered to show only Region III data. Charts and counts update accordingly.',
    },
    // Filter by date range - 30 days
    {
      module: 'Analytics – Central Dashboard',
      procedure: '1. Log in as central office.\n2. Navigate to "Analytics".\n3. Select date range: "Last 30 days".\n4. Observe results.',
      expected: 'Expected Output: Trends and counts are filtered to assessments submitted in the last 30 days. Older records are excluded.',
    },
    // Filter by readiness score
    {
      module: 'Analytics – Central Dashboard',
      procedure: '1. Log in as central office.\n2. Navigate to "Analytics".\n3. Filter by Readiness Score: "Severe".\n4. Observe results.',
      expected: 'Expected Output: Only beneficiaries with readiness_score = "severe" are counted in the filtered results.',
    },
    // Filter by disability type
    {
      module: 'Analytics – Central Dashboard',
      procedure: '1. Log in as central office.\n2. Navigate to "Analytics".\n3. Filter by Disability: "Visual Disability".\n4. Observe results.',
      expected: 'Expected Output: Analytics data is filtered to show only assessments where the child has "Visual Disability" in their disabilities array.',
    },
    // Dashboard stats
    {
      module: 'Analytics – Dashboard Stats',
      procedure: '1. Log in as admin or central.\n2. Navigate to Overview/Dashboard.\n3. Observe summary statistics.',
      expected: 'Expected Output: Summary stats from /api/get-dashboard-stats.php show: total beneficiaries, completion rate (%), active regions, and active interviewers count.',
    },
    // Interviewer workload categories
    {
      module: 'Analytics – Central Dashboard',
      procedure: '1. Log in as central office.\n2. Navigate to "Analytics".\n3. View the Interviewer Workload section.',
      expected: 'Expected Output: Interviewers are categorized as: Overloaded (>80 assessments), Balanced (20-80 assessments), Underutilized (<20 assessments). Counts and percentages are displayed.',
    },
    // Data quality metrics
    {
      module: 'Analytics – Central Dashboard',
      procedure: '1. Log in as central office.\n2. Navigate to Analytics extended view.\n3. View Data Quality metrics.',
      expected: 'Expected Output: /api/get-analytics-extended.php returns data completeness percentage and missing field counts by category.',
    },
    // Field officer analytics access restriction
    {
      module: 'Analytics – Central Dashboard',
      procedure: '1. Log in as field_officer.\n2. Attempt to call /api/get-analytics-extended.php directly.',
      expected: 'Expected Output: Access denied. HTTP 401 or 403 returned. Only central/admin roles can access extended analytics.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 14: ADMIN – AUDIT LOGS
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '14 - Audit Logs',
  cases: [
    // View audit logs
    {
      module: 'Admin – Audit Logs',
      procedure: '1. Log in as admin.\n2. Navigate to "Audit Logs".\n3. View the log entries.',
      expected: 'Expected Output: Audit logs from /api/get-audit-logs.php are displayed. Each entry shows: action (create/update/view/delete), table_name, record_id, interviewer_id, IP address, timestamp.',
    },
    // Filter audit logs by action
    {
      module: 'Admin – Audit Logs',
      procedure: '1. Log in as admin.\n2. Navigate to "Audit Logs".\n3. Filter by Action: "update".',
      expected: 'Expected Output: Only update actions are shown. Create, view, and delete logs are filtered out.',
    },
    // Filter audit logs by date range
    {
      module: 'Admin – Audit Logs',
      procedure: '1. Log in as admin.\n2. Navigate to "Audit Logs".\n3. Set date range filter to the past 7 days.',
      expected: 'Expected Output: Only audit log entries from the past 7 days are displayed.',
    },
    // Unauthorized access to audit logs
    {
      module: 'Admin – Audit Logs',
      procedure: '1. Log in as field_officer.\n2. Attempt to access /api/get-audit-logs.php directly.',
      expected: 'Expected Output: Access denied. HTTP 401 or 403 returned. Audit logs are restricted to admin and central roles.',
    },
    // Audit log created on assessment submission
    {
      module: 'Admin – Audit Logs',
      procedure: '1. Log in as admin.\n2. Submit a test assessment as a field officer.\n3. Return to admin and view Audit Logs.\n4. Search for the new assessment record.',
      expected: 'Expected Output: A new audit log entry is created with action="create", table_name="assessments", and the new assessment record ID. IP address and user agent are recorded.',
    },
    // Audit log on edit request approval
    {
      module: 'Admin – Audit Logs',
      procedure: '1. Have an STU Head approve an edit request.\n2. Log in as admin.\n3. View Audit Logs and find the approval event.',
      expected: 'Expected Output: An audit log entry exists with action="update", showing old_values and new_values for the modified assessment fields. reviewed_by is recorded.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 15: DATA EXPORT & BACKUP
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '15 - Export & Backup',
  cases: [
    // Admin export
    {
      module: 'Admin – Data Export',
      procedure: '1. Log in as admin.\n2. Navigate to "Backup/Export".\n3. Click "Export Data" or "Download Backup".',
      expected: 'Expected Output: GET /api/export-backup.php is called. A downloadable file (CSV or JSON) of beneficiary data is returned. File downloads successfully.',
    },
    // Unauthorized export attempt
    {
      module: 'Admin – Data Export',
      procedure: '1. Log in as field_officer.\n2. Attempt to access /api/export-backup.php directly.',
      expected: 'Expected Output: Access denied. HTTP 401 or 403 returned. Export is restricted to admin role only.',
    },
    // Empty export (no data)
    {
      module: 'Admin – Data Export',
      procedure: '1. Log in as admin.\n2. Apply filters that result in zero records.\n3. Trigger an export.',
      expected: 'Expected Output: The system handles empty export gracefully. Either an empty file is downloaded with headers, or a message "No records found for export" is displayed.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 16: BENEFICIARY MANAGEMENT (View, Update)
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '16 - Beneficiary Management',
  cases: [
    // View beneficiary detail by ARUGA ID
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as any authorized role.\n2. Navigate to Beneficiaries.\n3. Search or click on a record.\n4. View the full beneficiary profile.',
      expected: 'Expected Output: GET /api/get-beneficiary-detail.php?aruga_id=... returns complete profile with all 12 related tables. All assessment steps are displayed in the profile view.',
    },
    // Search by ARUGA ID (exact)
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as any authorized role.\n2. Navigate to Beneficiaries or search bar.\n3. Enter exact ARUGA ID: "ARUGA-2024-01-0001".\n4. Click Search.',
      expected: 'Expected Output: The matching beneficiary record is found and displayed.',
    },
    // Search with no results
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as any authorized role.\n2. Search for ARUGA ID: "ARUGA-9999-99-9999" (non-existent).',
      expected: 'Expected Output: No records found message is displayed. Empty state UI is shown without an error or crash.',
    },
    // Update beneficiary - valid data
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as an authorized role (field officer or admin).\n2. Navigate to a beneficiary\'s detail page.\n3. Click "Edit" or "Update".\n4. Modify the child\'s contact number to "09181234567".\n5. Click "Save".',
      expected: 'Expected Output: POST /api/update-beneficiary.php patches only the changed section. Success message displayed. Updated value is reflected in the profile view.',
    },
    // Update beneficiary - invalid email
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as an authorized role.\n2. Open a beneficiary record for editing.\n3. Change respondent email to "invalidemail" (no @ symbol).\n4. Click "Save".',
      expected: 'Expected Output: Email validation fails server-side (FILTER_VALIDATE_EMAIL). Returns HTTP 400 with error message. Record is NOT updated.',
    },
    // Partial update (only one section)
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as authorized role.\n2. Open a beneficiary detail.\n3. Modify ONLY the Assessment Notes section (Step 10).\n4. Save.',
      expected: 'Expected Output: Only assessment_notes and the readiness_score on the parent assessments table are updated. Other sections remain unchanged.',
    },
    // Filter beneficiaries by region (central/admin)
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as central office.\n2. Navigate to all beneficiaries.\n3. Apply filter: Region = "CAR".\n4. Observe filtered results.',
      expected: 'Expected Output: /api/get-all-beneficiaries.php applies the region filter. Only beneficiaries from CAR are displayed.',
    },
    // Filter by readiness score
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as central or admin.\n2. Navigate to all beneficiaries.\n3. Apply Readiness Score filter: "stable".',
      expected: 'Expected Output: Only beneficiaries with readiness_score = "stable" are displayed in the list.',
    },
    // Pagination - first page
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as central office.\n2. Navigate to all beneficiaries list.\n3. If more than one page of records exists, observe the pagination.',
      expected: 'Expected Output: Pagination controls are visible. Current page is highlighted. "Previous" is disabled on page 1.',
    },
    // Pagination - last page
    {
      module: 'Beneficiary Management',
      procedure: '1. Log in as central office.\n2. Navigate to the last page of beneficiaries using pagination.',
      expected: 'Expected Output: The last page shows remaining records. "Next" button is disabled on the last page.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 17: SESSION & SECURITY
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '17 - Session & Security',
  cases: [
    // Session validation - missing headers
    {
      module: 'Security – Session Validation',
      procedure: '1. Make an API request to a protected endpoint (e.g., /api/beneficiaries.php) WITHOUT including X-Session-ID and X-Interviewer-ID headers.',
      expected: 'Expected Output: The API returns HTTP 401 Unauthorized. Access is denied. Error message indicates missing authentication credentials.',
    },
    // Session validation - invalid UUID format
    {
      module: 'Security – Session Validation',
      procedure: '1. Make an API request to a protected endpoint.\n2. Include X-Session-ID: "not-a-valid-uuid" (invalid format).\n3. Include X-Interviewer-ID: "also-invalid".',
      expected: 'Expected Output: UUID format validation fails (regex: /^[0-9a-f]{8}-[0-9a-f]{4}-...). The API returns HTTP 400 or 401. Invalid session headers are rejected.',
    },
    // Tampered session ID
    {
      module: 'Security – Session Validation',
      procedure: '1. Log in as a field officer and capture the valid session ID.\n2. Modify one character of the session ID to create an invalid token.\n3. Make an API request with the tampered session ID.',
      expected: 'Expected Output: The tampered session is not found in the sessions table or has expired status. API returns HTTP 401 Unauthorized.',
    },
    // CORS header check
    {
      module: 'Security – CORS',
      procedure: '1. Make a cross-origin API request from a domain NOT listed in the allowed origins (e.g., from localhost or a different domain).\n2. Observe the CORS response headers.',
      expected: 'Expected Output: The API returns the appropriate CORS headers restricting access to projectaruga.com domain. Cross-origin requests from unauthorized domains are blocked or do not receive CORS allow headers.',
    },
    // HTTPS / HSTS
    {
      module: 'Security – HTTPS',
      procedure: '1. Navigate to the application over HTTP (not HTTPS).\n2. Observe if a redirect or security enforcement occurs.',
      expected: 'Expected Output: The application enforces HTTPS. HTTP requests are redirected to HTTPS. Strict-Transport-Security (HSTS) header is present in the response.',
    },
    // Logout
    {
      module: 'Security – Logout',
      procedure: '1. Log in as any dashboard user.\n2. Click "Logout" in the navigation.\n3. Attempt to use the browser Back button or navigate to a protected page.',
      expected: 'Expected Output: Session is invalidated. Subsequent requests return HTTP 401. Browser back button does not restore the authenticated session. User is shown the login page.',
    },
    // Password hashing verification
    {
      module: 'Security – Password Storage',
      procedure: '1. Log in as admin.\n2. Create a new interviewer with password: "Test1234!".\n3. Inspect the interviewers table in Supabase dashboard or admin panel.',
      expected: 'Expected Output: The stored value in the password_hash field is a bcrypt hash (starts with "$2y$" or "$2b$"), NOT the plaintext password.',
    },
  ]
});

// ══════════════════════════════════════════════════════════════════
// MODULE 18: PUBLIC PAGES (Contact, Privacy, User Manual)
// ══════════════════════════════════════════════════════════════════
modules.push({
  name: '18 - Public Pages',
  cases: [
    {
      module: 'Public – Contact Page',
      procedure: '1. Navigate to /contact.\n2. Observe the page content.',
      expected: 'Expected Output: The contact page loads successfully with contact information or a contact form. No console errors.',
    },
    {
      module: 'Public – Privacy Policy',
      procedure: '1. Navigate to /privacy.\n2. Read through the privacy policy content.',
      expected: 'Expected Output: The privacy policy page loads with full Data Privacy Act (RA 10173) compliance information. Page is accessible without authentication.',
    },
    {
      module: 'Public – User Manual',
      procedure: '1. Navigate to /user-manual or click the documentation link.\n2. Observe the documentation content.',
      expected: 'Expected Output: The user manual page loads with step-by-step instructions for field officers. Page is accessible without authentication.',
    },
    {
      module: 'Public – Success Page',
      procedure: '1. Complete a full assessment submission.\n2. Observe the /success page.',
      expected: 'Expected Output: The success page displays the generated ARUGA ID, confirmation of submission, and instructions for next steps. No sensitive data other than ARUGA ID is exposed.',
    },
    {
      module: 'Public – Success Page (Direct Access)',
      procedure: '1. Without completing an assessment, directly navigate to /success.',
      expected: 'Expected Output: The system handles direct access gracefully. Either an empty state message is shown or the user is redirected to the entry page.',
    },
  ]
});

// ─── BUILD EXCEL WORKBOOK ─────────────────────────────────────────────────────

const wb = XLSX.utils.book_new();

// ── Cover Sheet ──────────────────────────────────────────────────
const coverData = [
  ['USER ACCEPTANCE TEST (UAT) DOCUMENT'],
  [],
  ['Project Name:', 'Project Aruga – DSWD Child Disability Profiling System'],
  ['Version:', '1.0'],
  ['Date:', '2026-05-22'],
  ['Prepared By:', 'System – Auto-generated'],
  ['Tester Name:', ''],
  ['Approved By:', ''],
  [],
  ['LEGEND'],
  ['Status', 'Description'],
  ['PASS', 'Test case executed and expected output matches actual output'],
  ['FAIL', 'Test case executed but actual output does NOT match expected'],
  ['BLOCKED', 'Test case could not be executed due to a dependency or environment issue'],
  ['SKIP', 'Test case intentionally skipped for this test cycle'],
  [],
  ['MODULE SUMMARY'],
  ['Module Sheet', 'Description'],
  ...modules.map(m => [m.name, `${m.cases.length} test cases`]),
];

const wsCount = [];
modules.forEach(m => wsCount.push(m.cases.length));
const totalCases = wsCount.reduce((a, b) => a + b, 0);
coverData.push([]);
coverData.push(['TOTAL TEST CASES:', totalCases]);

const wsCover = XLSX.utils.aoa_to_sheet(coverData);
wsCover['!cols'] = [{ wch: 25 }, { wch: 55 }];
XLSX.utils.book_append_sheet(wb, wsCover, 'Cover');

// ── Module Sheets ────────────────────────────────────────────────
modules.forEach(mod => {
  const rows = [COL_HEADERS];
  mod.cases.forEach(tc => {
    rows.push([tc.module, tc.procedure, tc.expected, '', '', '']);
  });

  const ws = XLSX.utils.aoa_to_sheet(rows);

  // Column widths
  ws['!cols'] = [
    { wch: 22 },
    { wch: 55 },
    { wch: 55 },
    { wch: 16 },
    { wch: 22 },
    { wch: 22 },
  ];

  // Auto-filter on header row
  ws['!autofilter'] = { ref: `A1:F1` };

  XLSX.utils.book_append_sheet(wb, ws, mod.name);
});

// ── Write File ───────────────────────────────────────────────────
const outputPath = 'UAT_ProjectAruga.xlsx';
XLSX.writeFile(wb, outputPath);

console.log(`\n✓ UAT document generated: ${outputPath}`);
console.log(`\nTEST CASE SUMMARY:`);
modules.forEach((m, i) => {
  console.log(`  ${m.name.padEnd(35)} ${m.cases.length} test cases`);
});
console.log(`\n  ${'TOTAL'.padEnd(35)} ${totalCases} test cases`);
console.log(`\nSheets: 1 Cover + ${modules.length} module sheets = ${modules.length + 1} total sheets`);
