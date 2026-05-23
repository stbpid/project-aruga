/**
 * System Documentation Generator — Part 2
 * Builds: Sections 6-11 (ACL, Server Reqs, DB Dictionary, ERD)
 */
'use strict';
const {
  h1, h2, h3, para, blankLine, bullet, makeTable, pageBreak,
  DARK_BLUE, MID_BLUE, WHITE, BLACK, pt, run, bold,
  cell, tableRow,
} = require('./generate_sysdoc_p1');

const {
  Paragraph, TextRun, AlignmentType, HeadingLevel,
  Table, TableRow, TableCell, WidthType, BorderStyle, ShadingType,
  LineRuleType, convertInchesToTwip,
} = require('docx');

function noteCell(text) {
  return cell(text, { size: 9 });
}

// ═══════════════════════════════════════════════════════════════
// SECTION 6: ACCESS CONTROL LIST
// ═══════════════════════════════════════════════════════════════
function buildSection6() {
  return [
    h1('Section 6: Access Control List'),
    para('The system has four user roles. Each role has access to specific modules and operations. The table below shows each role, the module or feature, and the permitted operations.'),
    blankLine(),
    para('Operation codes used: C = Create, R = Read, U = Update, D = Delete, X = No Access.'),
    blankLine(),
    makeTable(
      ['Role', 'Module / Feature', 'Operations', 'Notes'],
      [
        // field_officer
        ['field_officer', 'Profiling Form (Assessment Submission)', 'C', 'Can create new assessments for children they visit.'],
        ['field_officer', 'Own Beneficiary List', 'R', 'Can view the list of beneficiaries they personally assessed. (Note: the API does not enforce ownership filtering. Any authenticated user can query any record.)'],
        ['field_officer', 'Beneficiary Detail', 'R', 'Can view full assessment data.'],
        ['field_officer', 'Edit Request Submission', 'C', 'Can submit a correction request for a submitted assessment.'],
        ['field_officer', 'Edit Request Status', 'R', 'Can see the status of their submitted edit requests.'],
        ['field_officer', 'Own Performance and Stats', 'R', 'Can view their own submission counts and performance metrics.'],
        ['field_officer', 'QR Code Lookup', 'R', 'Can look up a beneficiary by scanning or entering an ARUGA ID.'],
        ['field_officer', 'Profile Settings', 'R, U', 'Can view and update their own name, position, and password.'],
        ['field_officer', 'Dashboard Login and Logout', 'C, D', 'Can log in and log out.'],
        ['field_officer', 'Add / Update Interviewer', 'X', 'No access. (Note: the API does not enforce this. Any authenticated session can call add-interviewer.php. This is a known security gap.)'],
        ['field_officer', 'Admin Dashboard', 'X', 'Redirect to own dashboard if accessed directly.'],
        ['field_officer', 'Audit Logs', 'X (intended), R (actual)', 'The audit log endpoint does not check role. Any authenticated user can access it. This is a known security gap.'],
        ['field_officer', 'Data Export', 'X', 'Requires a separate admin token. Not available to field officers.'],
        // stu_head
        ['stu_head', 'Regional Beneficiary List', 'R', 'Can view beneficiaries in their assigned region. (Note: the region filter is based on a client-supplied parameter, not enforced from the user\'s account record. Cross-region access is a known gap.)'],
        ['stu_head', 'Edit Request Review', 'R, U', 'Can view pending edit requests and approve, return for update, or decline them.'],
        ['stu_head', 'Regional Field Officers List', 'R', 'Can view field officers assigned to their region with performance stats.'],
        ['stu_head', 'Add Interviewer', 'C', 'Can add new interviewers (same endpoint as admin, no separate role check).'],
        ['stu_head', 'Regional Analytics', 'R', 'Can view regional statistics and analytics.'],
        ['stu_head', 'QR Code Lookup', 'R', 'Can scan or look up beneficiaries by ARUGA ID.'],
        ['stu_head', 'Profile Settings', 'R, U', 'Can update their own name and password.'],
        ['stu_head', 'Admin Dashboard', 'X', 'No access unless they also have admin role.'],
        // central
        ['central', 'All Beneficiaries (National)', 'R', 'Can view all beneficiaries across all regions.'],
        ['central', 'National Analytics', 'R', 'Can view full analytics including extended reports.'],
        ['central', 'All Interviewers', 'R', 'Can view all interviewers across regions.'],
        ['central', 'Audit Logs', 'R', 'Can view audit logs (no role check enforced at present).'],
        ['central', 'Add Interviewer', 'C', 'No specific restriction beyond valid session.'],
        ['central', 'Profile Settings', 'R, U', 'Can update their own name, position, and office.'],
        // admin
        ['admin', 'All Modules', 'C, R, U, D', 'Full access to all modules.'],
        ['admin', 'User Management (Interviewers)', 'C, R, U', 'Can add, view, and update all interviewer accounts.'],
        ['admin', 'Security Settings', 'R, U', 'Can view and update system-wide security settings.'],
        ['admin', 'Audit Logs', 'R', 'Can view full audit trail.'],
        ['admin', 'Data Export and Backup', 'R', 'Can export all data using the admin export token.'],
        ['admin', 'System Settings', 'R, U', 'Can manage system_settings table entries.'],
      ],
      [14, 28, 10, 48]
    ),
    blankLine(),
    para('Known security gaps identified during code review:'),
    bullet('The add-interviewer.php endpoint requires only a valid session. It does not check that the authenticated user has the admin or stu_head role. Any field officer with a valid session can call this endpoint.'),
    bullet('The get-audit-logs.php endpoint requires only a valid session. It does not restrict access to admin or central roles.'),
    bullet('The get-analytics-extended.php endpoint requires only a valid session. It does not restrict access to central or admin roles.'),
    bullet('The region filter on STU Head endpoints (stu-head/edit-requests.php, stu-head/interviewers.php) is based on a client-supplied query parameter. The system does not verify that the requested region matches the authenticated user\'s assigned region.'),
    bullet('The beneficiaries.php listing does not filter by the authenticated user\'s interviewer code. Any authenticated user can retrieve any beneficiary record by knowing the ARUGA ID.'),
    pageBreak(),

    // ─── SECTION 7: SOFTWARE SERVER REQUIREMENTS ─────────────
    h1('Section 7: Software Server Requirements'),
    para('Project Aruga runs on Vercel (serverless hosting) with a Supabase-managed PostgreSQL database. There is no traditional server to configure. The table below describes the software components required to run and maintain the system.'),
    blankLine(),
    makeTable(
      ['Component Type', 'Software / Service', 'Version', 'Purpose'],
      [
        ['Hosting Platform', 'Vercel', 'Current (managed service)', 'Hosts and serves static frontend files (HTML, CSS, JS) and executes PHP backend files as serverless functions. Handles HTTPS, CDN, and auto-scaling automatically.'],
        ['Backend Runtime', 'PHP', '8.x (runtime vercel-php@0.7.1 as declared in vercel.json)', 'Executes backend API logic. Each PHP file is a separate serverless function. PHP handles JSON parsing, input validation, Supabase REST calls, password hashing, and response formatting.'],
        ['Database', 'Supabase (PostgreSQL)', 'PostgreSQL 15+ (managed by Supabase)', 'Stores all application data across 17 tables. Supabase provides the REST API, connection pooling, row-level security, and pgcrypto functions used for password verification.'],
        ['CSS Build Tool', 'Tailwind CSS CLI', 'v3.4.19 (from package.json devDependencies)', 'Compiles the Tailwind CSS utility classes from public/css/input.css into the minified public/css/tailwind.css file. Only needed during development or when modifying the CSS.'],
        ['JavaScript Runtime (build only)', 'Node.js', 'v18+ (recommended for Tailwind CLI)', 'Required locally to run the Tailwind CSS build command (npm run build:css). Not required on the server.'],
        ['Package Manager (build only)', 'npm', 'v9+ (bundled with Node.js)', 'Used to install and run the Tailwind CSS build tool. Not required on the server.'],
        ['Excel Generation (UAT tool only)', 'xlsx', 'v0.18.5 (from package.json devDependencies)', 'Used in the UAT generation scripts included in this repository. Not part of the application runtime.'],
        ['Supabase pgcrypto', 'pgcrypto (PostgreSQL extension)', 'Built into Supabase', 'Used by the verify_password RPC function to compare passwords against bcrypt hashes stored in the database. PHP falls back to password_verify() if the RPC call is unavailable.'],
        ['HTTPS / TLS', 'Managed by Vercel', 'TLS 1.2+', 'Vercel automatically provisions and renews SSL/TLS certificates for the projectaruga.com domain. The HSTS header (Strict-Transport-Security: max-age=31536000; includeSubDomains) is set in vercel.json.'],
        ['Content Delivery Network', 'Vercel Edge Network', 'Managed service', 'Serves static files from the nearest edge location to the user. Reduces page load time.'],
        ['QR Code Library', 'qrcodejs (CDN)', 'Loaded from cdn.jsdelivr.net', 'Client-side JavaScript library that generates QR codes in the browser. Used on the success page.'],
        ['Fonts and Icons', 'Google Fonts + Material Symbols (CDN)', 'Loaded from fonts.googleapis.com and fonts.gstatic.com', 'Provides the Poppins typeface and Material Design icons used across all pages.'],
      ],
      [18, 22, 18, 42]
    ),
    blankLine(),
    para('Environment variables required on Vercel (set in Vercel Project Settings):'),
    makeTable(
      ['Variable Name', 'Description', 'Where Used'],
      [
        ['SUPABASE_URL', 'The base URL of the Supabase project (e.g., https://[project-id].supabase.co)', 'api/config.php — used in every API request to Supabase'],
        ['SUPABASE_SERVICE_ROLE_KEY', 'The Supabase service-role key. This key bypasses row-level security. Keep it secret.', 'api/config.php — used in every API request to Supabase'],
        ['ADMIN_EXPORT_TOKEN', 'A secret token used to authenticate data export requests. Must be sent in the X-Admin-Token header.', 'api/export-backup.php'],
      ],
      [25, 45, 30]
    ),
    pageBreak(),

    // ─── SECTION 8: HARDWARE SERVER REQUIREMENTS ─────────────
    h1('Section 8: Hardware Server Requirements'),
    para('Project Aruga does not run on a dedicated physical server. It uses serverless and managed cloud services (Vercel and Supabase). There is no hardware to procure or maintain for the production system.'),
    blankLine(),
    para('The table below provides hardware recommendations for a local development workstation used by developers to build, test, and maintain the system.'),
    blankLine(),
    makeTable(
      ['Component', 'Minimum', 'Recommended', 'Notes'],
      [
        ['Processor (CPU)', '2-core Intel/AMD x64', '4-core Intel Core i5 / AMD Ryzen 5 or better', 'Used for running the local development environment and building CSS with Tailwind CLI.'],
        ['Memory (RAM)', '4 GB', '8 GB or more', 'Node.js, a code editor (VS Code), and a browser for testing can consume 4 to 6 GB combined.'],
        ['Storage', '20 GB free disk space (SSD preferred)', '50 GB free disk space (SSD)', 'The project itself is small. Storage is needed for Node.js, npm packages, and browser caches.'],
        ['Internet Connection', '5 Mbps or faster', '20 Mbps or faster', 'Required to connect to Supabase for all API calls during development. All data is stored in the cloud.'],
        ['Operating System', 'Windows 10, macOS 12, or Ubuntu 20.04 LTS', 'Windows 11, macOS 13+, or Ubuntu 22.04 LTS', 'Any OS that supports Node.js v18+ and a modern browser.'],
        ['Browser', 'Chrome 110+, Firefox 115+, or Edge 110+', 'Latest version of Chrome or Edge', 'Required for testing the application. See Section 9 for end-user client requirements.'],
      ],
      [16, 22, 28, 34]
    ),
    blankLine(),
    para('For Supabase (cloud database), the free tier or Pro tier of Supabase is sufficient for the current scale of the system. Supabase manages hardware provisioning, backups, and scaling automatically. No hardware decisions are needed for the database.'),
    pageBreak(),

    // ─── SECTION 9: CLIENT REQUIREMENTS ─────────────────────
    h1('Section 9: Client Requirements'),
    para('End users access Project Aruga through a web browser. No software installation is required. The following table lists the requirements for the devices used by field officers, STU heads, central office staff, and administrators.'),
    blankLine(),
    makeTable(
      ['Requirement', 'Minimum', 'Recommended', 'Notes'],
      [
        ['Web Browser', 'Chrome 90+, Firefox 90+, Edge 90+, Safari 14+', 'Latest version of Chrome, Edge, or Firefox', 'The application uses ES2020+ JavaScript features (optional chaining, nullish coalescing, async/await, Array.from). Internet Explorer is not supported. The Tailwind CSS build targets modern browsers.'],
        ['Internet Connection', '3G mobile or 2 Mbps broadband', '4G / LTE mobile or 5 Mbps broadband', 'Required at all times. The system is fully cloud-based and does not work offline. A stable connection is especially important during assessment submission, which writes to 12 database tables in one request.'],
        ['Device Type', 'Any smartphone, tablet, or desktop computer', 'Android or iOS smartphone (for field use), desktop or laptop (for dashboard use)', 'The frontend uses Tailwind CSS responsive classes. The profiling form and dashboards are designed to work on mobile screens.'],
        ['Operating System', 'Android 8+, iOS 13+, Windows 10, macOS 11, or Ubuntu 20.04', 'Android 11+, iOS 15+, Windows 11, macOS 13+', 'Any OS that runs a supported browser.'],
        ['Screen Resolution', '360 x 640 pixels or larger', '768 x 1024 pixels or larger', 'The interface is responsive. The minimum is typical for entry-level Android smartphones used by field officers.'],
        ['JavaScript', 'Must be enabled in browser', 'N/A', 'The application is entirely JavaScript-driven. It will not function with JavaScript disabled.'],
        ['Cookies and Session Storage', 'Must be enabled in browser', 'N/A', 'Session tokens are stored in the browser\'s sessionStorage. Disabling storage will prevent login from working.'],
        ['Pop-up Blocker', 'Should not block the application domain', 'Whitelist projectaruga.com', 'Idle timeout warning and logout confirmation modals are displayed as in-page overlays, not browser pop-ups. Pop-up blockers should not interfere.'],
      ],
      [18, 22, 26, 34]
    ),
    pageBreak(),

    // ─── SECTION 10: DATABASE DICTIONARY ─────────────────────
    h1('Section 10: Database Dictionary'),
    para('This section documents all 17 tables in the Project Aruga database. Column definitions for tables 1 through 15 are reconstructed from API source code (submit-assessment.php, update-beneficiary.php, auth.php, and related files) because no CREATE TABLE SQL files exist in the repository for these tables. They were likely created directly in the Supabase Studio interface. Only beneficiary_edit_requests and the interviewer auth columns (email, password_hash, dashboard_role) have SQL DDL files in the repository.'),
    blankLine(),
    para('Column type abbreviations: UUID = Universally Unique Identifier, TEXT = variable-length string, BOOLEAN = true/false, INTEGER = whole number, NUMERIC = decimal number, DATE = calendar date, TIMESTAMPTZ = timestamp with time zone, JSONB = JSON binary data.'),
    blankLine(),
    para('PK = Primary Key, FK = Foreign Key, UQ = Unique, NN = Not Null.'),

    // Table 1: assessments
    h2('10.1 assessments'),
    para('Purpose: The central record for each child assessment. Every other assessment-related table references this table through the assessment_id foreign key. The ARUGA ID is stored here and is used as the primary public identifier.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique internal identifier for the assessment record.'],
        ['aruga_id', 'TEXT', 'UQ, NN', 'Generated by API', 'Public identifier in format ARUGA-YYYY-RR-NNNN. Generated by submit-assessment.php using year, region code, and a sequence count.'],
        ['session_id', 'UUID', 'FK (sessions.id)', 'NULL', 'References the profiling session during which the assessment was submitted.'],
        ['interviewer_id', 'UUID', 'FK (interviewers.id)', 'NULL', 'References the field officer who conducted the assessment.'],
        ['interviewer_code', 'TEXT', 'NN', 'Empty string', 'Denormalized copy of the interviewer code at the time of submission, for faster lookup.'],
        ['privacy_accepted', 'BOOLEAN', 'NN', 'true', 'Always set to true on submission. The privacy agreement is accepted on the entry page before the profiling session starts.'],
        ['privacy_accepted_at', 'TIMESTAMPTZ', '', 'NOW() at submission', 'Timestamp when privacy was accepted.'],
        ['current_step', 'INTEGER', '', '11', 'Set to 11 (the review step) on submission, indicating all steps were completed.'],
        ['status', 'TEXT', '', 'completed', 'Assessment workflow status. Values observed: completed, accepted, correction, pending.'],
        ['readiness_score', 'TEXT', '', 'NULL', 'Assessed readiness level. Values: severe, moderate, low, stable. Set from Step 10 of the form.'],
        ['completed_at', 'TIMESTAMPTZ', '', 'NOW()', 'Timestamp when the assessment was completed.'],
        ['submitted_at', 'TIMESTAMPTZ', '', 'NOW()', 'Timestamp when the assessment was submitted to the server.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
        ['updated_at', 'TIMESTAMPTZ', '', 'NOW()', 'Last update timestamp.'],
      ],
      [14, 12, 16, 16, 42]
    ),

    // Table 2: children
    h2('10.2 children'),
    para('Purpose: Stores the child\'s personal and demographic information collected in Step 3A of the profiling form.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links this child record to its parent assessment.'],
        ['first_name', 'TEXT', 'NN', "''", 'Child\'s first name.'],
        ['middle_name', 'TEXT', '', 'NULL', 'Child\'s middle name (optional).'],
        ['last_name', 'TEXT', 'NN', "''", 'Child\'s last name.'],
        ['name_extension', 'TEXT', '', 'NULL', 'Name suffix such as Jr., Sr., II, III, IV, V.'],
        ['date_of_birth', 'DATE', '', 'NULL', 'Child\'s date of birth in ISO format (YYYY-MM-DD).'],
        ['sex', 'TEXT', '', 'NULL', 'Male or Female.'],
        ['region', 'TEXT', '', 'NULL', 'Philippine region name (full text as selected in the form).'],
        ['province', 'TEXT', '', 'NULL', 'Province name.'],
        ['city_municipality', 'TEXT', '', 'NULL', 'City or municipality name.'],
        ['barangay', 'TEXT', '', 'NULL', 'Barangay name.'],
        ['street_address', 'TEXT', '', 'NULL', 'Street address or house number.'],
        ['contact_number', 'TEXT', '', 'NULL', 'Child or household contact number (optional).'],
        ['religion', 'TEXT', '', 'NULL', 'Religion from the standard list. NULL if "Others" was selected (religion_other is used instead).'],
        ['religion_other', 'TEXT', '', 'NULL', 'Free-text religion when "Others" is selected.'],
        ['ip_membership', 'TEXT', '', 'NULL', 'Indigenous Peoples group from the standard list. NULL if "Others".'],
        ['ip_membership_other', 'TEXT', '', 'NULL', 'Free-text IP group when "Others" is selected.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [18, 12, 16, 14, 40]
    ),

    // Table 3: respondents
    h2('10.3 respondents'),
    para('Purpose: Stores information about the adult who responded on behalf of the child during the assessment (Step 2 of the profiling form).'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['full_name', 'TEXT', 'NN', "''", 'Full name of the respondent.'],
        ['relationship_to_child', 'TEXT', '', 'NULL', 'Relationship to the child (Parent, Guardian, Social Worker, etc.).'],
        ['email', 'TEXT', '', 'NULL', 'Respondent email address (optional).'],
        ['contact_number', 'TEXT', '', 'NULL', 'Respondent contact number (optional).'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [18, 12, 16, 14, 40]
    ),

    // Table 4: pre_qualification
    h2('10.4 pre_qualification'),
    para('Purpose: Stores the pre-qualification data from Step 1 of the profiling form. Records whether the child belongs to the 4Ps (Pantawid Pamilyang Pilipino Program) conditional cash transfer program.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['is_4ps_member', 'BOOLEAN', 'NN', 'false', 'Whether the child is a member of the 4Ps program.'],
        ['household_id', 'TEXT', '', 'NULL', 'The 18-digit 4Ps household ID. Required only if is_4ps_member is true.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [18, 12, 16, 14, 40]
    ),

    // Table 5: child_education_health
    h2('10.5 child_education_health'),
    para('Purpose: Stores the child\'s educational attainment, disabilities, and critical illnesses (Step 3B of the profiling form). Disabilities and illnesses are stored as JSONB arrays.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['child_id', 'UUID', 'FK (children.id)', 'NULL', 'Links to the child record for direct reference.'],
        ['highest_education', 'TEXT', '', 'NULL', 'Highest educational attainment from the standard list. NULL if "Others".'],
        ['highest_education_other', 'TEXT', '', 'NULL', 'Free-text educational attainment when "Others" is selected.'],
        ['disabilities', 'JSONB', '', '[]', 'Array of disability labels from the VALID_DISABILITIES list. Validated server-side. Example: ["Visual Disability", "Hearing Disability"].'],
        ['critical_illnesses', 'JSONB', '', '[]', 'Array of illness labels from the VALID_ILLNESSES list. Validated server-side. Example: ["Diabetes"].'],
        ['illness_other', 'TEXT', '', 'NULL', 'Free-text illness description when "Others" is in critical_illnesses.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [20, 12, 16, 12, 40]
    ),

    // Table 6: family_members
    h2('10.6 family_members'),
    para('Purpose: Stores one row per family member added during Step 4 of the profiling form. Multiple rows per assessment are expected.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['member_number', 'INTEGER', 'NN', '1', 'Sequential number assigned to each family member within the assessment, starting from 1.'],
        ['full_name', 'TEXT', 'NN', "''", 'Full name of the family member.'],
        ['relationship_to_head', 'TEXT', '', 'NULL', 'Relationship to the household head.'],
        ['is_solo_parent', 'BOOLEAN', '', 'false', 'Whether this member is a solo parent.'],
        ['civil_status', 'TEXT', '', 'NULL', 'Civil status (Single, Married, Widowed, etc.).'],
        ['age', 'INTEGER', '', 'NULL', 'Age in years. Validated as 0 to 150 client-side.'],
        ['sex', 'TEXT', '', 'NULL', 'Male or Female.'],
        ['occupation', 'TEXT', '', 'NULL', 'Occupation from the standard 14-option list.'],
        ['occupation_class', 'TEXT', '', 'NULL', 'Occupation classification from the 9-option list.'],
        ['disabilities', 'JSONB', '', '[]', 'Array of disability labels from VALID_DISABILITIES.'],
        ['critical_illnesses', 'JSONB', '', '[]', 'Array of illness labels from VALID_ILLNESSES.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [18, 12, 16, 12, 42]
    ),

    // Table 7: socio_economic
    h2('10.7 socio_economic'),
    para('Purpose: Stores household living condition data from Step 5 of the profiling form.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['housing_materials', 'TEXT', '', 'NULL', 'Construction materials from the standard list. NULL if "Others".'],
        ['housing_materials_other', 'TEXT', '', 'NULL', 'Free-text when "Others" selected.'],
        ['tenure_status', 'TEXT', '', 'NULL', 'Ownership or rental status of the dwelling.'],
        ['tenure_status_other', 'TEXT', '', 'NULL', 'Free-text when "Others" selected.'],
        ['has_accessibility_modifications', 'BOOLEAN', '', 'false', 'Whether the home has been modified for the child\'s accessibility needs.'],
        ['modification_details', 'TEXT', '', 'NULL', 'Description of modifications made.'],
        ['electricity_source', 'TEXT', '', 'NULL', 'Source of electricity from the standard list.'],
        ['electricity_source_other', 'TEXT', '', 'NULL', 'Free-text when "Others" selected.'],
        ['water_source', 'TEXT', '', 'NULL', 'Source of drinking and household water from the standard 12-option list.'],
        ['water_source_other', 'TEXT', '', 'NULL', 'Free-text when "Others" selected.'],
        ['toilet_type', 'TEXT', '', 'NULL', 'Type of toilet facility from the standard 8-option list.'],
        ['toilet_type_other', 'TEXT', '', 'NULL', 'Free-text when "Others" selected.'],
        ['is_toilet_accessible', 'BOOLEAN', '', 'false', 'Whether the toilet is accessible to the child with disability.'],
        ['garbage_disposal', 'TEXT', '', 'NULL', 'Method of garbage disposal from the standard 8-option list.'],
        ['garbage_disposal_other', 'TEXT', '', 'NULL', 'Free-text when "Others" selected.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [22, 12, 16, 10, 40]
    ),

    // Table 8: health_info
    h2('10.8 health_info'),
    para('Purpose: Stores health status and monthly expense data from Step 6 of the profiling form.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['has_all_vaccinations', 'BOOLEAN', '', 'false', 'Whether the child has received all required vaccinations.'],
        ['has_ongoing_health_conditions', 'BOOLEAN', '', 'false', 'Whether the child has ongoing health conditions beyond their primary disability.'],
        ['health_conditions_details', 'TEXT', '', 'NULL', 'Details of ongoing health conditions.'],
        ['expense_food', 'NUMERIC', '', '0', 'Monthly food expense in Philippine Pesos.'],
        ['expense_medication', 'NUMERIC', '', '0', 'Monthly medication expense.'],
        ['expense_therapy', 'NUMERIC', '', '0', 'Monthly therapy expense.'],
        ['expense_hygiene', 'NUMERIC', '', '0', 'Monthly hygiene product expense.'],
        ['expense_assistive_device', 'NUMERIC', '', '0', 'Monthly expense for assistive devices (wheelchair, hearing aid, etc.).'],
        ['expense_other', 'NUMERIC', '', '0', 'Other monthly health-related expenses.'],
        ['availed_services_6months', 'BOOLEAN', '', 'false', 'Whether the child availed any government health services in the past 6 months.'],
        ['availed_services_details', 'TEXT', '', 'NULL', 'Details of services availed.'],
        ['is_facility_accessible', 'BOOLEAN', '', 'false', 'Whether the nearest health facility is physically accessible to the child.'],
        ['has_barriers_to_healthcare', 'BOOLEAN', '', 'false', 'Whether there are barriers preventing access to healthcare.'],
        ['healthcare_barriers_details', 'TEXT', '', 'NULL', 'Details of healthcare barriers.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [22, 12, 16, 10, 40]
    ),

    // Table 9: education_info
    h2('10.9 education_info'),
    para('Purpose: Stores detailed education status data from Step 7 of the profiling form.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['is_currently_enrolled', 'BOOLEAN', '', 'false', 'Whether the child is currently enrolled in school.'],
        ['grade_year_level', 'TEXT', '', 'NULL', 'Current grade or year level. Only relevant if enrolled.'],
        ['not_enrolled_reason', 'TEXT', '', 'NULL', 'Reason the child is not enrolled. Only relevant if not enrolled.'],
        ['has_accessibility_features', 'BOOLEAN', '', 'false', 'Whether the child\'s school has physical accessibility features (ramps, wide doorways, etc.).'],
        ['accessibility_features_details', 'TEXT', '', 'NULL', 'Description of accessibility features.'],
        ['has_sped_programs', 'BOOLEAN', '', 'false', 'Whether the school has Special Education (SPED) programs.'],
        ['sped_programs_details', 'TEXT', '', 'NULL', 'Description of SPED programs available.'],
        ['receives_learning_support', 'BOOLEAN', '', 'false', 'Whether the child receives additional learning support.'],
        ['learning_support_details', 'TEXT', '', 'NULL', 'Description of learning support received.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [22, 12, 16, 10, 40]
    ),

    // Table 10: economic_capacity
    h2('10.10 economic_capacity'),
    para('Purpose: Stores household economic information from Step 8 of the profiling form.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['primary_income_source', 'TEXT', '', 'NULL', 'Main source of household income (free text, 3 to 255 characters).'],
        ['monthly_income', 'NUMERIC', '', 'NULL', 'Total household monthly income in Philippine Pesos. NULL if zero or not provided.'],
        ['income_classification', 'TEXT', '', 'NULL', 'Income classification label computed client-side (e.g., Below Poverty Line, Low Income). Stored as free text.'],
        ['are_parents_employed', 'BOOLEAN', '', 'false', 'Whether the parents or guardians are employed.'],
        ['employment_details', 'TEXT', '', 'NULL', 'Details about employment situation.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [22, 12, 16, 10, 40]
    ),

    // Table 11: service_availment
    h2('10.11 service_availment'),
    para('Purpose: Records information about government and social services availed or known to the family (Step 9 of the profiling form).'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['receives_financial_assistance', 'BOOLEAN', '', 'false', 'Whether the family currently receives financial assistance from the government.'],
        ['financial_assistance_details', 'TEXT', '', 'NULL', 'Details of financial assistance received.'],
        ['is_aware_of_social_services', 'BOOLEAN', '', 'false', 'Whether the family is aware of available social services.'],
        ['awareness_details', 'TEXT', '', 'NULL', 'Details about which services they are aware of.'],
        ['has_availed_services', 'BOOLEAN', '', 'false', 'Whether the family has availed social services.'],
        ['availed_services_details', 'TEXT', '', 'NULL', 'Details of services availed.'],
        ['service_challenges', 'TEXT', '', 'NULL', 'Main challenges in availing services, from the standard list.'],
        ['service_challenges_other', 'TEXT', '', 'NULL', 'Free-text challenge description when "Others" is selected.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [22, 12, 16, 10, 40]
    ),

    // Table 12: assessment_notes
    h2('10.12 assessment_notes'),
    para('Purpose: Stores the interviewer\'s qualitative assessment notes and the final readiness score from Step 10 of the profiling form.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['assessment_id', 'UUID', 'FK (assessments.id), NN', '', 'Links to the parent assessment.'],
        ['strengths', 'TEXT', '', 'NULL', 'Interviewer\'s notes on the child\'s and family\'s strengths. Min 10, max 2000 characters.'],
        ['assessment_details', 'TEXT', '', 'NULL', 'General assessment observations. Min 10, max 2000 characters.'],
        ['recommended_actions', 'TEXT', '', 'NULL', 'Recommended interventions or referrals. Min 10, max 2000 characters.'],
        ['readiness_score', 'TEXT', '', 'NULL', 'Readiness level assessed by the interviewer. Values: severe, moderate, low, stable.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
      ],
      [22, 12, 16, 10, 40]
    ),

    // Table 13: interviewers
    h2('10.13 interviewers'),
    para('Purpose: Stores the accounts for all system users: field officers, STU heads, central office staff, and administrators. This table serves as the single user account table for the entire system.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique internal identifier.'],
        ['full_name', 'TEXT', 'NN', '', 'Full name of the user.'],
        ['interviewer_code', 'TEXT', 'UQ, NN', '', 'Eight-character alphanumeric code used by field officers to log in to the profiling tool. Must be uppercase.'],
        ['region', 'TEXT', 'NN', '', 'Philippine region assigned to this user.'],
        ['province', 'TEXT', '', 'NULL', 'Province within the region.'],
        ['position', 'TEXT', '', 'NULL', 'Job title or position.'],
        ['office', 'TEXT', '', 'NULL', 'Office or department.'],
        ['status', 'TEXT', '', 'active', 'Account status. Values: active, inactive.'],
        ['email', 'TEXT', 'UQ (partial — where not null)', 'NULL', 'Email address for dashboard login. Optional. If set, must be unique. Added by supabase-migration-dashboard-auth.sql.'],
        ['password_hash', 'TEXT', '', 'NULL', 'Bcrypt hash of the user\'s dashboard password. Cost factor 12. Stored as $2y$ format. Added by supabase-migration-dashboard-auth.sql.'],
        ['dashboard_role', 'TEXT', 'CHECK (admin, central, stu_head, field_officer)', 'NULL', 'Role that determines which dashboard the user can access and what data they can view. Added by supabase-migration-dashboard-auth.sql.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp.'],
        ['updated_at', 'TIMESTAMPTZ', '', 'NOW()', 'Last update timestamp.'],
      ],
      [18, 12, 20, 14, 36]
    ),

    // Table 14: sessions
    h2('10.14 sessions'),
    para('Purpose: Stores active login sessions for both profiling tool users (field interviewers) and dashboard users. Each login creates a new session record. The session ID is the token sent with every authenticated API request.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'The session token. Sent in the X-Session-ID header with every authenticated request.'],
        ['interviewer_id', 'UUID', 'FK (interviewers.id), NN', '', 'The user who owns this session.'],
        ['interviewer_code', 'TEXT', '', 'NULL', 'Denormalized copy of the interviewer code at session creation time.'],
        ['status', 'TEXT', '', 'active', 'Session status. Values: active, expired. Note: logout does not update this to expired. Only DB-side cleanup or expiry would change this.'],
        ['ip_address', 'TEXT', '', 'NULL', 'Client IP address at login time, for audit purposes.'],
        ['user_agent', 'TEXT', '', 'NULL', 'Browser user agent string at login time.'],
        ['started_at', 'TIMESTAMPTZ', '', 'NOW()', 'When the session was created.'],
        ['created_at', 'TIMESTAMPTZ', '', 'NOW()', 'Record creation timestamp (same as started_at in practice).'],
      ],
      [16, 12, 20, 14, 38]
    ),

    // Table 15: beneficiary_edit_requests
    h2('10.15 beneficiary_edit_requests'),
    para('Purpose: Stores correction requests submitted by field officers. Each request contains the proposed data changes as a JSONB payload. STU heads review these requests and approve, return for revision, or decline them. Source: supabase-migration-edit-requests.sql (full DDL available in repository).'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier for the edit request.'],
        ['aruga_id', 'TEXT', 'NN', '', 'The ARUGA ID of the beneficiary to be updated. Denormalized for easy lookup.'],
        ['assessment_id', 'UUID', 'FK (assessments.id) ON DELETE CASCADE, NN', '', 'The assessment record to be updated if the request is approved.'],
        ['interviewer_id', 'UUID', 'FK (interviewers.id) ON DELETE CASCADE, NN', '', 'The field officer who submitted the request.'],
        ['payload', 'JSONB', 'NN', '', 'The full proposed update payload. Same structure as the body accepted by update-beneficiary.php.'],
        ['status', 'TEXT', 'NN, CHECK (pending, approved, for_update, declined, superseded)', 'pending', 'Current state of the request. "superseded" means a newer request was submitted for the same assessment.'],
        ['reviewer_note', 'TEXT', '', 'NULL', 'Note from the STU Head. Required when action is "for_update".'],
        ['reviewed_by', 'UUID', 'FK (interviewers.id)', 'NULL', 'The STU Head who reviewed the request.'],
        ['reviewed_at', 'TIMESTAMPTZ', '', 'NULL', 'When the review action was taken.'],
        ['created_at', 'TIMESTAMPTZ', 'NN', 'NOW()', 'When the edit request was submitted.'],
        ['updated_at', 'TIMESTAMPTZ', 'NN', 'NOW()', 'Last update timestamp.'],
      ],
      [16, 14, 22, 12, 36]
    ),
    blankLine(),
    para('Indexes on beneficiary_edit_requests: idx_edit_requests_aruga_id (aruga_id), idx_edit_requests_status (status), idx_edit_requests_interviewer (interviewer_id), idx_edit_requests_assessment (assessment_id).'),

    // Table 16: audit_logs
    h2('10.16 audit_logs'),
    para('Purpose: Records every create, update, view, and login action performed in the system. Used for accountability, debugging, and security monitoring. All CRUD operations and login events write a row to this table.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['id', 'UUID', 'PK, NN', 'gen_random_uuid()', 'Unique identifier.'],
        ['action', 'TEXT', 'NN', '', 'Type of action. Values observed: create, update, view, login.'],
        ['table_name', 'TEXT', 'NN', '', 'The database table affected by the action.'],
        ['record_id', 'UUID', '', 'NULL', 'The ID of the specific record that was created or modified.'],
        ['old_values', 'JSONB', '', 'NULL', 'The data before the change. NULL for create actions.'],
        ['new_values', 'JSONB', '', 'NULL', 'The data after the change, or event metadata for login events.'],
        ['interviewer_id', 'UUID', '', 'NULL', 'The user who performed the action.'],
        ['assessment_id', 'UUID', '', 'NULL', 'The assessment record related to this action, if applicable.'],
        ['ip_address', 'TEXT', '', 'NULL', 'Client IP address at the time of the action.'],
        ['user_agent', 'TEXT', '', 'NULL', 'Browser user agent string.'],
        ['created_at', 'TIMESTAMPTZ', 'NN', 'NOW()', 'When the action occurred.'],
      ],
      [16, 12, 16, 12, 44]
    ),

    // Table 17: system_settings
    h2('10.17 system_settings'),
    para('Purpose: Stores configurable system-wide settings as key-value pairs. Currently used for security policy settings (idle timeouts, password rules, lockout policies). Managed through the admin security settings panel.'),
    makeTable(
      ['Column', 'Type', 'Constraints', 'Default', 'Description'],
      [
        ['key', 'TEXT', 'PK, NN', '', 'Setting name. Examples: idleTimeout, passwordMinLength, requireUppercase.'],
        ['value', 'TEXT', 'NN', '', 'Setting value stored as a string. The application casts to the appropriate type when reading. Example: "30" for idleTimeout minutes.'],
      ],
      [20, 12, 20, 14, 34]
    ),
    blankLine(),
    para('Default values (from api/settings/security.php): idleTimeout=30, absoluteTimeout=8, timeoutWarning=5, rememberMeDuration=30, maxFailedAttempts=5, lockoutDuration=30, forcePasswordChange=true, passwordMinLength=8, requireUppercase=true, requireNumbers=true, requireSpecialChars=true, passwordExpiry=90, preventPasswordReuse=false.'),
    pageBreak(),

    // ─── SECTION 11: ENTITY RELATIONSHIP DIAGRAM ─────────────
    h1('Section 11: Entity Relationship Diagram'),
    para('The following section describes all relationships between database tables. Use this information to draw a formal Entity Relationship Diagram (ERD). A developer or technical writer may use the relationship list below to create a visual diagram in a tool such as draw.io, Lucidchart, or dbdiagram.io.'),
    blankLine(),
    h2('11.1 Relationship Summary'),
    makeTable(
      ['Parent Table', 'Child Table', 'Relationship Type', 'Join Column', 'Notes'],
      [
        ['interviewers', 'sessions', 'One-to-Many', 'sessions.interviewer_id = interviewers.id', 'One interviewer can have many sessions (one per login). Sessions are not deleted on logout.'],
        ['interviewers', 'assessments', 'One-to-Many', 'assessments.interviewer_id = interviewers.id', 'One field officer can conduct many assessments over time.'],
        ['interviewers', 'beneficiary_edit_requests', 'One-to-Many', 'beneficiary_edit_requests.interviewer_id = interviewers.id', 'One field officer can submit many edit requests.'],
        ['interviewers', 'beneficiary_edit_requests (reviewer)', 'One-to-Many', 'beneficiary_edit_requests.reviewed_by = interviewers.id', 'One STU Head can review many edit requests.'],
        ['interviewers', 'audit_logs', 'One-to-Many', 'audit_logs.interviewer_id = interviewers.id', 'One user can generate many audit log entries.'],
        ['sessions', 'assessments', 'One-to-One', 'assessments.session_id = sessions.id', 'Each assessment is submitted within one profiling session.'],
        ['assessments', 'children', 'One-to-One', 'children.assessment_id = assessments.id', 'Each assessment has exactly one child record.'],
        ['assessments', 'respondents', 'One-to-One', 'respondents.assessment_id = assessments.id', 'Each assessment has exactly one respondent record.'],
        ['assessments', 'pre_qualification', 'One-to-One', 'pre_qualification.assessment_id = assessments.id', 'Each assessment has exactly one pre-qualification record.'],
        ['assessments', 'child_education_health', 'One-to-One', 'child_education_health.assessment_id = assessments.id', 'Each assessment has exactly one education and health record.'],
        ['assessments', 'family_members', 'One-to-Many', 'family_members.assessment_id = assessments.id', 'Each assessment can have multiple family member records. One row per person.'],
        ['assessments', 'socio_economic', 'One-to-One', 'socio_economic.assessment_id = assessments.id', 'Each assessment has exactly one socio-economic record.'],
        ['assessments', 'health_info', 'One-to-One', 'health_info.assessment_id = assessments.id', 'Each assessment has exactly one health information record.'],
        ['assessments', 'education_info', 'One-to-One', 'education_info.assessment_id = assessments.id', 'Each assessment has exactly one education information record.'],
        ['assessments', 'economic_capacity', 'One-to-One', 'economic_capacity.assessment_id = assessments.id', 'Each assessment has exactly one economic capacity record.'],
        ['assessments', 'service_availment', 'One-to-One', 'service_availment.assessment_id = assessments.id', 'Each assessment has exactly one service availment record.'],
        ['assessments', 'assessment_notes', 'One-to-One', 'assessment_notes.assessment_id = assessments.id', 'Each assessment has exactly one notes record.'],
        ['assessments', 'beneficiary_edit_requests', 'One-to-Many', 'beneficiary_edit_requests.assessment_id = assessments.id', 'One assessment can have multiple edit requests over time. Each new request supersedes the previous pending one.'],
        ['assessments', 'audit_logs', 'One-to-Many', 'audit_logs.assessment_id = assessments.id', 'One assessment can have many audit log entries across its lifecycle.'],
        ['children', 'child_education_health', 'One-to-One', 'child_education_health.child_id = children.id', 'Direct link from child to their education/health record (in addition to the assessment_id link).'],
      ],
      [16, 20, 16, 26, 22]
    ),
    blankLine(),
    h2('11.2 Central Entity Description'),
    para('The assessments table is the central entity in this database. Every other data table (children, respondents, pre_qualification, child_education_health, family_members, socio_economic, health_info, education_info, economic_capacity, service_availment, and assessment_notes) links back to assessments through the assessment_id foreign key.'),
    blankLine(),
    para('The interviewers table is the user account table. It links to sessions (login sessions), assessments (conducted assessments), beneficiary_edit_requests (submitted and reviewed requests), and audit_logs (all actions by the user).'),
    blankLine(),
    para('Text description for ERD drawing: Place assessments in the centre. Draw 11 lines going out to the 11 assessment-data tables (children, respondents, pre_qualification, child_education_health, family_members, socio_economic, health_info, education_info, economic_capacity, service_availment, assessment_notes). Label 10 of these lines as "1 to 1" and the line to family_members as "1 to many". Draw a separate interviewers entity and connect it to assessments ("1 to many"), sessions ("1 to many"), beneficiary_edit_requests ("1 to many" for both submitter and reviewer), and audit_logs ("1 to many"). Connect sessions to assessments with a "1 to 1" line. Connect beneficiary_edit_requests to assessments with a "1 to many" line. The system_settings table stands alone with no foreign key relationships.'),
    pageBreak(),
  ];
}

module.exports = { buildSection6 };
