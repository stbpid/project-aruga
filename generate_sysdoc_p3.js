/**
 * System Documentation Generator — Part 3
 * Builds: Sections 12-14 (Security, Maintenance, Document Control)
 */
'use strict';
const {
  h1, h2, h3, para, blankLine, bullet, makeTable, pageBreak,
} = require('./generate_sysdoc_p1');

function buildSection12to14() {
  return [
    // ─── SECTION 12: SECURITY AND DATA PRIVACY ───────────────
    h1('Section 12: Security and Data Privacy'),

    h2('12.1 Password Storage'),
    para('All dashboard user passwords are stored as bcrypt hashes. The system uses PHP\'s PASSWORD_BCRYPT algorithm with a cost factor of 12. This means the password is never stored in plain text. A cost factor of 12 means the hashing computation takes a significant amount of time, which slows down brute-force attacks.'),
    blankLine(),
    para('When a user logs in, the system calls a Supabase RPC function called verify_password (which uses the pgcrypto extension) to compare the submitted password against the stored hash. If the RPC call fails, the system falls back to PHP\'s built-in password_verify() function. Both methods compare securely without exposing the stored hash.'),
    blankLine(),
    para('Password requirements enforced when changing a password through the profile settings page: minimum 8 characters, at least one uppercase letter, at least one number, and at least one special character. These rules are checked in api/settings/profile.php.'),

    h2('12.2 Session Management'),
    para('When a user successfully logs in, the server creates a session record in the sessions database table. The session record includes the interviewer ID, IP address, user agent, and creation timestamp. The session ID (a UUID) is returned to the browser and stored in the browser\'s sessionStorage (not localStorage or cookies).'),
    blankLine(),
    para('Every protected API endpoint calls the requireAuth() function in api/auth.php. This function reads the X-Session-ID and X-Interviewer-ID headers from the request and validates them in the following order:'),
    bullet('Check that both headers are present. If either is missing, return HTTP 401 with "Authentication required."'),
    bullet('Validate that both values match the UUID format using a regular expression. If not, return HTTP 401 with "Invalid session."'),
    bullet('Look up the session in the sessions table, filtering for status = active. If not found, return HTTP 401 with "Session expired or invalid. Please log in again."'),
    bullet('Look up the interviewer record, filtering for status = active. If not found, return HTTP 401 with "Account not found or inactive."'),
    blankLine(),
    para('Session timeout: The dashboard JavaScript (dashboard-auth.js) monitors user activity. If no activity is detected for 30 minutes (the default idleTimeout setting), a warning modal appears with a 2-minute countdown. If the user clicks "Stay Logged In", the timer resets. If the user does not respond, the system clears sessionStorage and redirects to the login page.'),
    blankLine(),
    para('Known gap: Logging out clears the browser\'s sessionStorage but does not update the session record in the database to status = expired. A captured session token would remain valid in the database until the database record is cleaned up by an external process. The system does not currently have a scheduled cleanup job for expired sessions.'),

    h2('12.3 Transport Security'),
    para('The system enforces HTTPS through two mechanisms:'),
    bullet('The Strict-Transport-Security header is set on every response via vercel.json with the value "max-age=31536000; includeSubDomains". This tells browsers to only use HTTPS for this domain for one year, even if the user types "http://" in the address bar.'),
    bullet('Vercel automatically provisions and renews TLS certificates for the projectaruga.com domain. HTTP connections are redirected to HTTPS by the Vercel platform.'),

    h2('12.4 CORS (Cross-Origin Resource Sharing)'),
    para('The API only accepts requests from approved origins. In api/config.php, the allowed origins list contains only two entries: https://projectaruga.com and https://www.projectaruga.com. If a request arrives from any other origin, the server does not include the Access-Control-Allow-Origin response header, and the browser blocks the response. The Vary: Origin header is always sent to prevent caching issues.'),

    h2('12.5 Content Security Policy'),
    para('The Content-Security-Policy header is set in vercel.json for all routes. The policy restricts:'),
    bullet('Scripts: Only from the same origin (self) and from https://cdn.jsdelivr.net (for the QR code library). Inline scripts are allowed (unsafe-inline is enabled, which is a known trade-off for compatibility with the application\'s inline event handlers).'),
    bullet('Styles: Only from self and https://fonts.googleapis.com. Inline styles are allowed.'),
    bullet('Fonts: Only from self and https://fonts.gstatic.com.'),
    bullet('Images: From self and data: URIs (for QR codes). Also allows https://lh3.googleusercontent.com.'),
    bullet('Frames: Only from https://maps.google.com.'),
    bullet('Connections (fetch/XHR): Only to self.'),
    bullet('Objects: None (blocks plugins).'),

    h2('12.6 Input Sanitization'),
    para('The api/config.php file provides the sanitizeInput() helper function. It runs trim() to remove surrounding whitespace, stripslashes() to remove escape characters, and htmlspecialchars() with ENT_QUOTES and UTF-8 encoding to escape HTML special characters. This is used to sanitize GET parameter values via getStr() and getInt() helpers.'),
    blankLine(),
    para('Note: The assessment submission endpoint (submit-assessment.php) reads data from the JSON request body using json_decode() and accesses fields directly without calling sanitizeInput(). SQL injection is prevented by Supabase\'s REST API, which uses parameterized JSON queries. However, cross-site scripting (XSS) risks in free-text fields (such as assessment notes) depend on the dashboard rendering code using safe output methods (textContent rather than innerHTML). This has not been confirmed from the available code and should be reviewed.'),

    h2('12.7 Rate Limiting'),
    para('The system limits failed login attempts to 5 per IP address and identifier (email or interviewer code) within a 15-minute window. This applies to:'),
    bullet('Dashboard login (api/dashboard-login.php): limits by IP and email address.'),
    bullet('Interviewer code validation (api/validate-interviewer.php): limits by IP address.'),
    blankLine(),
    para('Rate limiting is implemented by querying the audit_logs table for recent failed login events from the same IP. If 5 or more failed attempts are found within the past 15 minutes, the server returns HTTP 429 with the message: "Too many failed login attempts. Please try again in 15 minutes."'),
    blankLine(),
    para('Note: This rate-limiting approach depends on the audit_logs table being writable and queryable. It is not an in-memory counter. In very high-volume scenarios, this query could be slow. It also does not currently lock the account record itself; it only blocks requests from the same IP during the window.'),

    h2('12.8 Data Privacy Compliance'),
    para('The system collects sensitive personal information about children with disabilities, including names, dates of birth, addresses, medical conditions, and family financial data. The application references the Data Privacy Act of the Philippines (Republic Act No. 10173) in the privacy agreement presented to field officers on the entry page. Users must accept this agreement before beginning any assessment.'),
    blankLine(),
    para('Personal data is:'),
    bullet('Collected only by authorized DSWD field officers with valid interviewer codes.'),
    bullet('Stored in a cloud database (Supabase) with access restricted by a service-role key that is not exposed in the application code.'),
    bullet('Transmitted only over HTTPS.'),
    bullet('Access-controlled through session-based authentication for all data retrieval endpoints.'),
    blankLine(),
    para('The public beneficiary profile page (/profile) exposes a limited view (ARUGA ID, child name, registration date, and readiness score) without requiring authentication. This is by design to support QR code lookups. If the privacy requirements change, this page should be reviewed.'),

    h2('12.9 Audit Logging'),
    para('The system logs all significant actions to the audit_logs table using the logAudit() function in api/config.php. Events logged include:'),
    makeTable(
      ['Event Type', 'Trigger', 'Data Recorded'],
      [
        ['login', 'Successful dashboard login or profiling tool login', 'Interviewer code, full name, region, email, role, IP address, user agent'],
        ['login_failed', 'Failed login attempt (wrong password, invalid code, user not found)', 'Email or code attempted, reason for failure, IP address, user agent'],
        ['create (assessments)', 'New assessment submitted', 'ARUGA ID, child name, readiness score, status'],
        ['create (interviewers)', 'New interviewer account created', 'Full name, code, region, email, role, status'],
        ['update (assessments)', 'Assessment data updated or edit request approved', 'ARUGA ID and update method (direct or via edit request)'],
        ['update (interviewers)', 'Interviewer account updated', 'Fields changed, old and new values'],
        ['update (system_settings)', 'Security settings changed', 'List of fields that were changed'],
        ['create (beneficiary_edit_requests)', 'Edit request submitted by field officer', 'ARUGA ID, interviewer code'],
        ['update (profile)', 'User updates their own profile', 'Fields changed (password is logged as "password", not the hash)'],
      ],
      [22, 28, 50]
    ),
    blankLine(),
    para('Known gap: The audit_logs endpoint (api/get-audit-logs.php) does not restrict access by role. Any authenticated user with a valid session can retrieve all audit log entries. This should be restricted to admin and central roles only.'),

    h2('12.10 Backup Strategy'),
    para('The system provides a manual data export feature through api/export-backup.php. An administrator can POST to this endpoint with a valid X-Admin-Token header to download all 15 data tables as JSON or CSV files. This creates a point-in-time snapshot of the database.'),
    blankLine(),
    para('Supabase (the database platform) also performs automated daily backups of all database data as part of its managed service. The retention period and restore procedures depend on the Supabase plan selected for the project.'),
    blankLine(),
    para('There is no scheduled automated export within the application code itself. Regular backups should be performed manually using the export endpoint or through the Supabase dashboard.'),
    pageBreak(),

    // ─── SECTION 13: SYSTEM MAINTENANCE ─────────────────────
    h1('Section 13: System Maintenance'),

    h2('13.1 Deploying Updates'),
    para('The application is hosted on Vercel. Deployment is managed through the Vercel platform and the project\'s Git repository.'),
    blankLine(),
    para('To deploy an update:'),
    para('1. Make the changes to the code files in the local development environment.'),
    para('2. If any HTML or JavaScript files were modified, rebuild the CSS by running the following command in the project root: npm run build:css'),
    para('3. Commit the changes to the Git repository and push to the connected branch. Vercel automatically detects the push and deploys the update.'),
    para('4. Alternatively, deploy manually using the Vercel CLI command: vercel --prod'),
    para('5. After deployment, open the application in a browser and verify the change works correctly.'),
    blankLine(),
    para('No server restart is needed. Vercel serverless functions are stateless and each request runs fresh. There is no persistent PHP process to restart.'),
    blankLine(),
    para('Environment variables (SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY, ADMIN_EXPORT_TOKEN) are set in the Vercel project dashboard under Settings > Environment Variables. Changing them in Vercel does not require a code change.'),

    h2('13.2 Modifying the CSS'),
    para('The application uses Tailwind CSS. The compiled CSS file is at public/css/tailwind.css. Do not edit this file directly. Instead:'),
    para('1. Edit the source file at public/css/input.css or modify tailwind.config.js to add new colours or fonts.'),
    para('2. Run npm run build:css to regenerate the compiled CSS.'),
    para('3. Deploy the updated tailwind.css file.'),
    blankLine(),
    para('To watch for changes automatically during development: npm run watch:css'),

    h2('13.3 Database Maintenance'),
    para('The database is managed through the Supabase dashboard (app.supabase.com). The following tasks may be needed:'),
    blankLine(),
    para('Running a migration: If a new migration SQL file is added to the repository (such as supabase-migration-dashboard-auth.sql), run it by opening the Supabase SQL Editor for the project and pasting the file contents.'),
    blankLine(),
    para('Reviewing data: Use the Supabase Table Editor or the SQL Editor to browse and query tables directly.'),
    blankLine(),
    para('Backing up data: Use the export endpoint described in Section 12.10, or download a backup from the Supabase dashboard under Settings > Backups.'),
    blankLine(),
    para('Monitoring storage usage: Check the Supabase dashboard for table sizes, row counts, and storage consumption. The audit_logs table will grow the fastest as it records every system action.'),
    blankLine(),
    para('Cleaning up old sessions: The sessions table accumulates records with every login. Rows that are old or marked as active but unused can be cleaned up with an SQL statement run in the Supabase SQL Editor. Example: DELETE FROM sessions WHERE created_at < NOW() - INTERVAL \'90 days\'; (adjust the interval as appropriate for the data retention policy).'),

    h2('13.4 Checking Application Logs'),
    para('Vercel provides function logs for serverless PHP functions. To view logs:'),
    para('1. Open the Vercel dashboard (vercel.com) and navigate to the project.'),
    para('2. Click on a deployment and select the "Functions" tab or "Logs" tab.'),
    para('3. Filter by function name (e.g., api/submit-assessment.php) to see requests and any PHP error messages.'),
    blankLine(),
    para('The application writes PHP error logs using error_log() in some files (for example, api/submit-assessment.php line 110 and api/add-interviewer.php line 65). These appear in the Vercel function logs.'),
    blankLine(),
    para('Application-level audit events are stored in the audit_logs database table and can be viewed through the admin dashboard or queried directly in Supabase.'),

    h2('13.5 Adding a New Interviewer'),
    para('1. Log in to the admin dashboard.'),
    para('2. Navigate to User Management and click "Add Interviewer".'),
    para('3. Fill in the required fields: full name, interviewer code, and region.'),
    para('4. If the user needs dashboard access, also provide an email, password, and dashboard role.'),
    para('5. Set status to active.'),
    para('6. Click Save.'),
    para('7. Share the interviewer code with the field officer for use on the profiling tool, and share the email and password for dashboard access if applicable.'),

    h2('13.6 Deactivating a User Account'),
    para('1. Log in to the admin dashboard.'),
    para('2. Find the interviewer record in the user management list.'),
    para('3. Change the status field from active to inactive.'),
    para('4. Save the change.'),
    para('5. The user will no longer be able to log in. Existing assessment records created by this user are preserved and are not affected by deactivation.'),

    h2('13.7 Troubleshooting Common Issues'),
    makeTable(
      ['Symptom', 'Likely Cause', 'Resolution'],
      [
        ['All API requests return 500 error', 'SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY environment variable is missing or incorrect in Vercel', 'Check Vercel project Settings > Environment Variables. Verify the variable names and values match what Supabase shows in the project API settings.'],
        ['Login returns "Invalid email or password" for correct credentials', 'The interviewer account may be inactive, the dashboard_role field may be null, or the password_hash may be null', 'Check the interviewers table in Supabase for the email. Verify status = active, dashboard_role is set, and password_hash is not null.'],
        ['Profiling form redirects back to / immediately', 'Session data is missing from sessionStorage. This happens if the user navigates directly to /profiling without going through the entry page.', 'Instruct the user to start from the home page (/) and enter their interviewer code first.'],
        ['ARUGA ID region code shows XX', 'The child\'s region name did not match any pattern in the getRegionCode() function in submit-assessment.php', 'Check that the region value submitted matches one of the expected patterns. Update the region mapping in submit-assessment.php if needed.'],
        ['"Too many failed login attempts" error', 'Rate limit triggered. 5 or more failed login attempts from the same IP within 15 minutes.', 'Wait 15 minutes or query the audit_logs table to confirm and delete the failed entries if the lockout was triggered by accident.'],
        ['Edit request stuck in "pending" status', 'The STU Head has not reviewed it, or a review action failed silently', 'Check the beneficiary_edit_requests table in Supabase for the row. Verify the status and reviewed_at fields.'],
        ['CSS styles are broken or missing after deployment', 'The Tailwind CSS build was not run before deploying, or the tailwind.css file was not committed', 'Run npm run build:css locally and commit the updated public/css/tailwind.css before deploying.'],
      ],
      [22, 30, 48]
    ),
    pageBreak(),

    // ─── SECTION 14: DOCUMENT CONTROL ────────────────────────
    h1('Section 14: Document Control'),

    h2('14.1 Document Information'),
    makeTable(
      ['Field', 'Details'],
      [
        ['Document Title', 'System Documentation'],
        ['System Name', 'Project Aruga — Child Disability Profiling and Assessment System'],
        ['Issuing Office', 'Social Technology Bureau, Department of Social Welfare and Development'],
        ['Version', '1.0.0'],
        ['Date Created', '2026-05-23'],
        ['Last Updated', '2026-05-23'],
        ['Classification', 'Confidential — For Official Use Only'],
        ['File Name', 'System_Documentation_ProjectAruga_v1.0.0.docx'],
      ],
      [30, 70]
    ),
    blankLine(),

    h2('14.2 Document History'),
    makeTable(
      ['Version', 'Date', 'Author', 'Changes Made'],
      [
        ['1.0.0', '2026-05-23', '[To be filled in]', 'Initial release of the system documentation. Covers all 14 sections including introduction, system profile, inputs, outputs, processes, access control, server requirements, database dictionary, ERD, security, and maintenance.'],
      ],
      [10, 15, 20, 55]
    ),
    blankLine(),

    h2('14.3 Document Approval'),
    makeTable(
      ['Role', 'Name and Designation', 'Signature', 'Date'],
      [
        ['Prepared By', '[To be filled in]', '', ''],
        ['Reviewed By', '[To be filled in]', '', ''],
        ['Recommended By', '[To be filled in]', '', ''],
        ['Approved By', '[To be filled in]', '', ''],
      ],
      [18, 42, 20, 20]
    ),
    blankLine(),
    para('All "To be filled in" entries must be completed and the document must be signed before it is issued as an official reference document.'),
  ];
}

module.exports = { buildSection12to14 };
