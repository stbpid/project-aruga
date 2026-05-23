/**
 * System Documentation Generator — Part 1
 * Builds: Cover, Approval, TOC placeholder, Sections 1-5
 */
'use strict';
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType,
  TableRow, TableCell, Table, WidthType, BorderStyle, ShadingType,
  PageBreak, Header, Footer, PageNumber, NumberFormat, Tab,
  TabStopType, TabStopLeader, LevelFormat, convertInchesToTwip,
  ImageRun, UnderlineType, LineRuleType, TableOfContents,
} = require('docx');

// ─── Colour palette ───────────────────────────────────────────
const BLUE_FILL  = 'BDD7EE';   // light blue header fill
const GRAY_FILL  = 'F2F2F2';   // alternate row fill
const DARK_BLUE  = '1F3864';   // heading colour
const MID_BLUE   = '1152D4';   // accent colour
const BLACK      = '000000';
const WHITE      = 'FFFFFF';

// ─── Reusable style helpers ────────────────────────────────────
function pt(n) { return n * 2; }   // half-points (docx unit)

function run(text, opts = {}) {
  return new TextRun({
    text,
    font:  opts.font  || 'Calibri',
    size:  opts.size  || pt(11),
    bold:  opts.bold  || false,
    italics: opts.italics || false,
    color: opts.color || BLACK,
    underline: opts.underline ? { type: UnderlineType.SINGLE } : undefined,
  });
}

function para(children, opts = {}) {
  const runs = (typeof children === 'string')
    ? [run(children, opts)]
    : children;
  return new Paragraph({
    children: runs,
    alignment: opts.align || AlignmentType.LEFT,
    spacing: { before: opts.before ?? 80, after: opts.after ?? 80,
               line: opts.line ?? 276, lineRule: LineRuleType.AUTO },
    indent: opts.indent ? { left: convertInchesToTwip(opts.indent) } : undefined,
  });
}

function h1(text) {
  return new Paragraph({
    children: [new TextRun({ text, font: 'Calibri', size: pt(16), bold: true, color: DARK_BLUE })],
    heading: HeadingLevel.HEADING_1,
    spacing: { before: 320, after: 120 },
    pageBreakBefore: true,
  });
}

function h2(text) {
  return new Paragraph({
    children: [new TextRun({ text, font: 'Calibri', size: pt(13), bold: true, color: DARK_BLUE })],
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 240, after: 80 },
  });
}

function h3(text) {
  return new Paragraph({
    children: [new TextRun({ text, font: 'Calibri', size: pt(11), bold: true, color: MID_BLUE })],
    heading: HeadingLevel.HEADING_3,
    spacing: { before: 160, after: 60 },
  });
}

function blankLine() {
  return new Paragraph({ children: [run('')], spacing: { before: 0, after: 0 } });
}

function bullet(text, level = 0) {
  return new Paragraph({
    children: [run(text)],
    bullet: { level },
    spacing: { before: 40, after: 40, line: 276, lineRule: LineRuleType.AUTO },
  });
}

function bold(text, size) { return run(text, { bold: true, size: size ? pt(size) : pt(11) }); }

// ─── Table helpers ────────────────────────────────────────────
function cell(text, opts = {}) {
  const isHeader = opts.header || false;
  return new TableCell({
    children: [new Paragraph({
      children: [new TextRun({
        text: String(text ?? ''),
        font:  'Calibri',
        size:  opts.size  ? pt(opts.size) : pt(10),
        bold:  isHeader || (opts.bold || false),
        color: isHeader ? WHITE : BLACK,
      })],
      alignment: opts.align || AlignmentType.LEFT,
      spacing: { before: 60, after: 60 },
    })],
    shading: isHeader
      ? { fill: DARK_BLUE, type: ShadingType.CLEAR, color: 'auto' }
      : (opts.fill ? { fill: opts.fill, type: ShadingType.CLEAR, color: 'auto' } : undefined),
    width: opts.width ? { size: opts.width, type: WidthType.PERCENTAGE } : undefined,
    columnSpan: opts.span,
    margins: { top: 60, bottom: 60, left: 120, right: 120 },
  });
}

function tableRow(cells) { return new TableRow({ children: cells }); }

function makeTable(headers, rows, widths) {
  const borderOpts = {
    top:    { style: BorderStyle.SINGLE, size: 1, color: 'AAAAAA' },
    bottom: { style: BorderStyle.SINGLE, size: 1, color: 'AAAAAA' },
    left:   { style: BorderStyle.SINGLE, size: 1, color: 'AAAAAA' },
    right:  { style: BorderStyle.SINGLE, size: 1, color: 'AAAAAA' },
    insideH:{ style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
    insideV:{ style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
  };
  const headerRow = tableRow(
    headers.map((h, i) => cell(h, { header: true, width: widths ? widths[i] : undefined }))
  );
  const bodyRows = rows.map((r, ri) =>
    tableRow(r.map((v, i) => cell(v, {
      width: widths ? widths[i] : undefined,
      fill: ri % 2 === 1 ? GRAY_FILL : undefined,
    })))
  );
  return new Table({
    rows: [headerRow, ...bodyRows],
    borders: borderOpts,
    width: { size: 100, type: WidthType.PERCENTAGE },
  });
}

// ─── PAGE BREAK helper ────────────────────────────────────────
function pageBreak() {
  return new Paragraph({ children: [new TextRun({ break: 1 })] });
}

// ═══════════════════════════════════════════════════════════════
// SECTION BUILDERS
// ═══════════════════════════════════════════════════════════════

// ─── COVER PAGE ───────────────────────────────────────────────
function buildCover() {
  return [
    blankLine(), blankLine(), blankLine(), blankLine(),
    new Paragraph({
      children: [new TextRun({ text: 'DEPARTMENT OF SOCIAL WELFARE AND DEVELOPMENT', font: 'Calibri', size: pt(13), bold: true, color: DARK_BLUE })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 60 },
    }),
    new Paragraph({
      children: [new TextRun({ text: 'Social Technology Bureau', font: 'Calibri', size: pt(12), color: DARK_BLUE })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 240 },
    }),
    new Paragraph({
      children: [new TextRun({ text: 'PROJECT ARUGA', font: 'Calibri', size: pt(36), bold: true, color: MID_BLUE })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 80 },
    }),
    new Paragraph({
      children: [new TextRun({ text: 'Child Disability Profiling and Assessment System', font: 'Calibri', size: pt(16), color: DARK_BLUE, italics: true })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 240 },
    }),
    new Paragraph({
      children: [new TextRun({ text: 'SYSTEM DOCUMENTATION', font: 'Calibri', size: pt(18), bold: true, color: DARK_BLUE })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 80 },
    }),
    new Paragraph({
      children: [new TextRun({ text: 'Version 1.0.0', font: 'Calibri', size: pt(12), color: '444444' })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 480 },
    }),
    new Paragraph({
      children: [new TextRun({ text: '[LOGO PLACEHOLDER — insert DSWD or Project Aruga logo here]', font: 'Calibri', size: pt(10), color: '888888', italics: true })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 480 },
    }),
    new Paragraph({
      children: [new TextRun({ text: '2026', font: 'Calibri', size: pt(12), color: '444444' })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 40 },
    }),
    new Paragraph({
      children: [new TextRun({ text: 'CONFIDENTIAL — FOR OFFICIAL USE ONLY', font: 'Calibri', size: pt(10), bold: true, color: 'AA0000' })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 0 },
    }),
    pageBreak(),
  ];
}

// ─── APPROVAL PAGE ────────────────────────────────────────────
function buildApprovalPage() {
  return [
    new Paragraph({
      children: [new TextRun({ text: 'DOCUMENT INFORMATION', font: 'Calibri', size: pt(14), bold: true, color: DARK_BLUE })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 200 },
    }),
    makeTable(
      ['Field', 'Details'],
      [
        ['Issuing Office', 'Social Technology Bureau, Department of Social Welfare and Development (DSWD)'],
        ['Office Address', '[To be filled in] — e.g., Constitution Hills, Batasan Hills, Quezon City'],
        ['Contact Number', '[To be filled in]'],
        ['Email Address', '[To be filled in]'],
        ['System Name', 'Project Aruga — Child Disability Profiling and Assessment System'],
        ['Document Title', 'System Documentation'],
        ['Version', '1.0.0'],
        ['Date of Release', '2026-05-22 (placeholder — update before official release)'],
        ['Status', 'Draft — For Review'],
        ['Classification', 'Confidential — For Official Use Only'],
      ],
      [30, 70]
    ),
    blankLine(), blankLine(),
    new Paragraph({
      children: [new TextRun({ text: 'DOCUMENT APPROVAL', font: 'Calibri', size: pt(14), bold: true, color: DARK_BLUE })],
      alignment: AlignmentType.CENTER, spacing: { before: 0, after: 200 },
    }),
    makeTable(
      ['Role', 'Name and Designation', 'Signature', 'Date'],
      [
        ['Prepared By', '[To be filled in]', '', ''],
        ['Reviewed By', '[To be filled in]', '', ''],
        ['Recommended By', '[To be filled in]', '', ''],
        ['Approved By', '[To be filled in]', '', ''],
      ],
      [20, 40, 20, 20]
    ),
    blankLine(),
    para('Note: All fields marked "[To be filled in]" must be completed before this document is officially released.', { italics: true, color: '666666' }),
    pageBreak(),
  ];
}

// ─── SECTION 1: INTRODUCTION ─────────────────────────────────
function buildSection1() {
  return [
    h1('Section 1: Introduction'),
    para('Project Aruga is a web-based system developed by the Social Technology Bureau (STB) of the Department of Social Welfare and Development (DSWD) to support the profiling and assessment of children with disabilities across the Philippines. The word "Aruga" means care or nurture in Filipino, which reflects the purpose of the system.'),
    blankLine(),
    para('The system allows trained field officers to visit families and record detailed information about a child\'s health, education, economic situation, and access to government services. Each child assessed receives a unique identification number called an ARUGA ID. This ID follows the format ARUGA-YYYY-RR-NNNN, where YYYY is the year of assessment, RR is the region code, and NNNN is a sequence number. The ARUGA ID is printed as a QR code that families and social workers can use for quick reference.'),
    blankLine(),
    para('Supervisors at the regional level, called STU Heads (Social Technology Unit Heads), can review the data submitted by field officers and manage correction requests. Central office staff and system administrators have access to national analytics, reports, and system configuration tools. The goal is to build a reliable, centralized database of child welfare assessments that can guide policy, resource allocation, and direct service delivery.'),
    blankLine(),
    h2('1.1 System Architecture'),
    para('Project Aruga is built as a web application that runs entirely over the internet. It has three main layers that work together: the frontend (what the user sees in a browser), the backend (the logic that runs on the server), and the database (where all data is stored).'),
    blankLine(),
    para('The frontend consists of HTML pages and JavaScript files served as static files. The main pages include a profiling form for field officers, a dashboard for each user role, and a public profile lookup page. The frontend sends requests to the backend when it needs to save or retrieve data.'),
    blankLine(),
    para('The backend is a set of PHP files deployed as serverless functions on the Vercel platform. Each PHP file handles one specific task, such as logging in, submitting an assessment, or fetching analytics data. These files do not run on a traditional web server. Instead, Vercel spins them up on demand when a request arrives and shuts them down when the request is done.'),
    blankLine(),
    para('The database is a PostgreSQL database hosted on Supabase, a cloud database service. The backend communicates with Supabase through a secure REST API using an encrypted service-role key stored as an environment variable on Vercel. No database credentials are stored in the application code.'),
    blankLine(),
    para('Authentication works in two ways. Field interviewers who conduct assessments log in using an eight-character alphanumeric code on the profiling tool entry page. Dashboard users (field officers, STU heads, central office staff, and administrators) log in using an email address and password on the dashboard login page. After a successful login, the system creates a session record in the database and returns a session token to the browser. The browser stores this token in session storage and sends it with every subsequent request.'),
    blankLine(),
    h3('1.1.1 Architecture Diagram (Text Description)'),
    para('The following describes the data flow. A developer or technical writer may use this to draw a formal diagram.'),
    blankLine(),
    bullet('User opens a browser and navigates to projectaruga.com.'),
    bullet('Vercel serves the requested HTML page from the /public folder.'),
    bullet('The browser loads JavaScript files and renders the page.'),
    bullet('When the user submits a form or requests data, the JavaScript sends an HTTP request to an API endpoint (for example, POST /api/submit-assessment.php).'),
    bullet('Vercel routes the request to the matching PHP serverless function.'),
    bullet('The PHP function reads the request, validates the input, and sends a REST API call to Supabase using a service-role key.'),
    bullet('Supabase executes the database query and returns the result as JSON.'),
    bullet('The PHP function formats the result and sends a JSON response back to the browser.'),
    bullet('The JavaScript receives the response and updates the page accordingly.'),
    blankLine(),
    para('External services used: Supabase (database and password verification via pgcrypto), Vercel (hosting and serverless runtime), Google Fonts (Poppins typeface loaded from fonts.googleapis.com), Material Symbols (icons loaded from fonts.gstatic.com), and a QR code library loaded from cdn.jsdelivr.net.'),
    pageBreak(),

    // ─── SECTION 2 ───────────────────────────────────────────
    h1('Section 2: System Profile'),
    makeTable(
      ['Field', 'Details'],
      [
        ['System Name', 'Project Aruga — Child Disability Profiling and Assessment System'],
        ['Acronym', 'ARUGA'],
        ['Description', 'A web-based assessment tool that enables DSWD field officers to profile children with disabilities, capture socio-economic and health data, generate unique ARUGA IDs, and provide supervisors and administrators with analytics and management dashboards.'],
        ['Status', 'Live / Production (deployed on Vercel and Supabase)'],
        ['Development Server URL', '[To be filled in — no .env.example or local dev URL found in repository]'],
        ['Production Server URL', 'https://projectaruga.com (inferred from CORS allowed origins in api/config.php)'],
        ['Development Strategy', 'In-house development by DSWD Social Technology Bureau. Developer names are not recorded in the repository (README is empty, no CONTRIBUTORS file, no git history accessible). [To be filled in]'],
        ['Computing Scheme', 'Internet-based. The system requires an active internet connection. All data is stored in the cloud (Supabase PostgreSQL on a Supabase-managed server). No on-premises server is required.'],
        ['Platform / Hosting', 'Vercel (serverless, auto-scaling, global CDN)'],
        ['Database Platform', 'Supabase (managed PostgreSQL)'],
        ['Backend Language', 'PHP 8.x (runtime: vercel-php@0.7.1 as declared in vercel.json)'],
        ['Frontend Technology', 'HTML5, Vanilla JavaScript (ES2020+), Tailwind CSS v3.4.19'],
        ['Source Code Version', 'v1.0.0 (as declared in package.json)'],
        ['National Assessment Target', '1,040 beneficiary assessments (hardcoded in analytics engine, broken down by region)'],
        ['Regions with Geographic Data', 'Regions I, II, III, IV-A, IV-B, V, VI, XI, and NCR (9 of 17 Philippine administrative regions)'],
      ],
      [28, 72]
    ),
    pageBreak(),

    // ─── SECTION 3: SYSTEM INPUTS ────────────────────────────
    h1('Section 3: System Inputs'),
    para('This section documents every screen, form, and user input in the system. Each subsection corresponds to one screen or module. Screenshots are not included in this version of the document.'),
    blankLine(),
    para('[Screenshot placeholder format: Each screenshot note below should be replaced with the actual screenshot before official release.]'),

    // 3.1
    h2('3.1 Entry Page and Privacy Agreement'),
    para('Location: / (root URL, file: public/index.html)'),
    para('The user opens the application. Before accessing any assessment form, the user must read the data privacy notice and accept it. The user then enters their interviewer code to identify themselves.'),
    blankLine(),
    para('[Screenshot placeholder: Entry page showing privacy agreement text, checkbox, and interviewer code input]'),
    blankLine(),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Privacy Agreement Checkbox', 'Checkbox', 'Yes', 'Must be checked. The Submit button remains disabled until both the checkbox is checked and a valid code is entered. If submitted without checking, the system shows a toast warning: "Please accept the Data Privacy terms".'],
        ['Interviewer Code', 'Text input (uppercase, max 8 characters)', 'Yes', 'Must be exactly 8 alphanumeric characters (A-Z, 0-9). Input is automatically converted to uppercase and stripped of non-alphanumeric characters. If empty: "Please enter your interviewer code". If not 8 characters: "Interviewer code must be exactly 8 characters". If the code does not exist or the account is inactive: the API returns "Invalid interviewer code or account is inactive".'],
      ],
      [22, 18, 12, 48]
    ),
    blankLine(),
    para('On success: The system creates a session, stores the session ID and interviewer information in the browser\'s session storage, shows a toast message "Welcome, [Name]!", and redirects the user to /profiling after one second.'),

    // 3.2
    h2('3.2 Beneficiary Profiling Form'),
    para('Location: /profiling (file: public/profiling.html, logic: public/js/profiling.js)'),
    para('This is the main data-entry form for field officers. It is divided into ten steps plus a review step. The user completes each step and clicks Next. Validation runs before the form advances to the next step. The user cannot skip steps. A session timer in the header counts elapsed time (display only, no forced logout on the profiling form). The dashboard idle timeout of 30 minutes does not apply here.'),
    blankLine(),
    para('[Screenshot placeholder: Profiling form showing the step progress bar and Step 1]'),

    h3('3.2.1 Step 1: Pre-Qualification'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Is the child a 4Ps member?', 'Radio button (Yes / No)', 'No (defaults to No)', 'If "Yes" is selected, the Household ID field becomes required. If no option is selected, the form advances treating the answer as No (see UAT finding: no forced selection).'],
        ['Household ID', 'Text input', 'Conditional (required only if 4Ps = Yes)', 'Must be exactly 18 digits (numbers only). Regex: /^\\d{18}$/. Error if empty when 4Ps = Yes: "Household ID is required for 4Ps members". Error if not 18 digits: "Household ID must be exactly 18 digits (numbers only)".'],
      ],
      [22, 18, 18, 42]
    ),

    h3('3.2.2 Step 2: Respondent Information'),
    para('The respondent is the adult accompanying the child during the assessment (parent, guardian, social worker, etc.).'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Name of Respondent', 'Text input', 'Yes', 'Min 2 characters, max 255 characters. Only letters, spaces, hyphens, and apostrophes allowed. Error if empty: "Name of Respondent is required". Error if too short: "Name of Respondent must be at least 2 characters". Error if invalid characters: "Name of Respondent can only contain letters, spaces, hyphens, and apostrophes".'],
        ['Relationship to Child', 'Dropdown', 'Yes', 'Must select one of: Parent, Guardian, Grandparent, Sibling, Aunt/Uncle, Relative, Social Worker, Foster Parent. Error if unselected: "Please select a relationship".'],
        ['Email Address', 'Email input', 'No', 'If provided, must match email format (regex: /^[^\\s@]+@[^\\s@]+\\.[^\\s@]{2,}$/). Error: "Please enter a valid email (e.g., name@example.com)". Max 255 characters.'],
        ['Contact Number', 'Phone input (auto-formatted)', 'No', 'If provided, must be 11 digits starting with 09. Error: "Contact number must be 11 digits starting with 09". The field auto-formats input as 09XX XXX XXXX.'],
      ],
      [22, 14, 12, 52]
    ),

    h3('3.2.3 Step 3: Child Demographics and Education'),
    para('Steps 3A (demographics) and 3B (education and disability) are validated together as Step 3 in the codebase.'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['First Name', 'Text input', 'Yes', 'Min 2, max 100 characters. Letters, spaces, hyphens, apostrophes only. Error if empty: "First Name is required".'],
        ['Middle Name', 'Text input', 'No', 'Max 100 characters. Letters, spaces, hyphens only.'],
        ['Last Name', 'Text input', 'Yes', 'Min 2, max 100 characters. Same character rules. Error if empty: "Last Name is required".'],
        ['Name Extension', 'Dropdown', 'No', 'Options: None, Jr., Sr., II, III, IV, V.'],
        ['Date of Birth', 'Date picker', 'Yes', 'Cannot be a future date. Error if empty: "Date of birth is required". Error if future date: "Date of birth cannot be in the future". The date picker\'s max attribute is set to today\'s date.'],
        ['Sex', 'Radio button (Male / Female)', 'Yes (implicit)', 'Must be Male or Female.'],
        ['Region', 'Cascading dropdown', 'Yes', 'Must select one of the Philippine regions. Error: "Please select a region". Selecting a region populates the Province dropdown.'],
        ['Province', 'Cascading dropdown', 'Yes', 'Populated from Region selection. Error: "Please select a province".'],
        ['City / Municipality', 'Cascading dropdown', 'Yes', 'Populated from Province selection. Error: "Please select a city".'],
        ['Barangay', 'Cascading dropdown', 'Yes', 'Populated from City selection. Error: "Please select a barangay".'],
        ['Street Address', 'Text input', 'Yes', 'Min 5, max 255 characters. Error if empty: "Street address is required". Error if short: "Street address must be at least 5 characters".'],
        ['Contact Number (Child)', 'Phone input', 'No', 'Same 11-digit 09XX rule as respondent contact. Error: "Contact number must be 11 digits starting with 09".'],
        ['Religion', 'Dropdown', 'Yes', 'Options: No Religion, Roman Catholic, Islam, Iglesia ni Cristo, Protestant, Born Again Christian, Buddhism, Hinduism, Others. Error if unselected: "Please select a religion". If "Others": free-text specify field required. Error: "Please specify religion".'],
        ['IP Membership', 'Dropdown', 'Yes', 'Options: Not a member, Aeta, Igorot, Lumad, Mangyan, Tagbanua, Badjao, T\'boli, Manobo, Others. Error if unselected: "Please select IP membership status". If "Others": specify field required. Error: "Please specify IP group".'],
        ['Highest Educational Attainment', 'Dropdown', 'Yes', 'Options: No formal education, Elementary Undergraduate, Elementary Graduate, High School Undergraduate, High School Graduate, Senior High School Graduate, College Undergraduate, College Graduate, Vocational/Technical, Post Graduate, Others. Error: "Please select educational attainment". If "Others": specify required. Error: "Please specify educational attainment".'],
        ['Disability or Special Needs', 'Multi-select checkbox list', 'Yes (at least one)', 'Options from VALID_DISABILITIES list (11 items). At least one must be selected. Error: "Please select at least one disability/special need". "None" is a valid selection.'],
        ['Critical Illness', 'Multi-select checkbox list', 'Yes (at least one)', 'Options from VALID_ILLNESS list (10 items). At least one must be selected including "None". Error: "Please select at least one critical illness (or select None)". If "Others" selected: specify field required. Error: "Please specify the critical illness".'],
      ],
      [22, 14, 12, 52]
    ),

    h3('3.2.4 Step 4: Family Members'),
    para('The interviewer adds one or more family members. Each member is added using an "Add Family Member" button which inserts a repeating card. All fields below apply to each member card.'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Full Name', 'Text input', 'Yes', 'Min 2 characters. Letters, spaces, hyphens only. Error: "Member #N: Full name is required".'],
        ['Relationship to Head', 'Dropdown', 'Yes', 'Error: "Member #N: Relationship is required".'],
        ['Civil Status', 'Dropdown', 'Yes', 'Error: "Member #N: Civil status is required".'],
        ['Age', 'Number input', 'Yes', 'Must be a whole number between 0 and 150. Error if empty: "Member #N: Age is required". Error if invalid: "Member #N: Age must be 0-150".'],
        ['Sex', 'Radio button (Male / Female)', 'No (not explicitly validated)', 'No error generated if left blank.'],
        ['Is Solo Parent?', 'Toggle (Yes / No)', 'No', 'Boolean toggle, defaults to No.'],
        ['Occupation', 'Dropdown', 'Yes', 'Must select from 14 occupation options. Error: "Member #N: Occupation is required".'],
        ['Occupation Class', 'Dropdown', 'Yes', 'Must select from 9 occupation class options. Error: "Member #N: Occupation class is required".'],
        ['Disability or Special Needs', 'Multi-select', 'Yes (at least one)', 'Error: "Member #N: Select at least one disability/special need".'],
        ['Critical Illness', 'Multi-select', 'Yes (at least one)', 'Error: "Member #N: Select at least one critical illness".'],
      ],
      [22, 16, 12, 50]
    ),

    h3('3.2.5 Step 5: Socio-Economic Status'),
    para('This step records information about the household\'s living conditions.'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Housing Materials', 'Dropdown', 'Yes', '5 options including "Others". If "Others": specify field required (max 255 characters). Error: "Please select housing materials" / "Please specify housing materials".'],
        ['Tenure Status', 'Dropdown', 'Yes', '6 options including "Others". Same Others pattern. Error: "Please select tenure status".'],
        ['Accessibility Modifications?', 'Toggle (Yes / No)', 'No', 'If "Yes": modification details text field is required (max 500 characters). Error: "Please specify modification details".'],
        ['Electricity Source', 'Dropdown', 'Yes', '7 options. Others pattern applies. Error: "Please select electricity source".'],
        ['Water Source', 'Dropdown', 'Yes', '12 options. Others pattern applies. Error: "Please select water source".'],
        ['Toilet Type', 'Dropdown', 'Yes', '8 options. Others pattern applies. Error: "Please select toilet type".'],
        ['Is Toilet Accessible?', 'Toggle (Yes / No)', 'No', 'Boolean, no validation.'],
        ['Garbage Disposal', 'Dropdown', 'Yes', '8 options. Others pattern applies. Error: "Please select garbage disposal system" / "Please specify garbage disposal system".'],
      ],
      [22, 16, 12, 50]
    ),

    h3('3.2.6 Step 6: Health Information'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Has All Vaccinations?', 'Toggle (Yes / No)', 'No', 'Boolean.'],
        ['Has Ongoing Health Conditions?', 'Toggle (Yes / No)', 'No', 'If "Yes": details text field required (max 500 characters). Error: "Please specify health condition details".'],
        ['Monthly Expense: Food', 'Number input', 'No', 'Numeric. No range validation at client side. Server stores as float.'],
        ['Monthly Expense: Medication', 'Number input', 'No', 'Same as Food.'],
        ['Monthly Expense: Therapy', 'Number input', 'No', 'Same as Food.'],
        ['Monthly Expense: Hygiene', 'Number input', 'No', 'Same as Food.'],
        ['Monthly Expense: Assistive Device', 'Number input', 'No', 'Same as Food.'],
        ['Monthly Expense: Other', 'Number input', 'No', 'Same as Food.'],
        ['Availed Health Services (last 6 months)?', 'Toggle (Yes / No)', 'No', 'If "Yes": details text field required (max 500 characters). Error: "Please specify services availed in 6 months".'],
        ['Health Facility Accessible?', 'Toggle (Yes / No)', 'No', 'Boolean.'],
        ['Has Barriers to Healthcare?', 'Toggle (Yes / No)', 'No', 'If "Yes": barrier details text field required (max 500 characters). Error: "Please specify healthcare barrier details".'],
      ],
      [30, 16, 10, 44]
    ),

    h3('3.2.7 Step 7: Education Information'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Currently Enrolled?', 'Toggle (Yes / No)', 'No', 'If "Yes": Grade/Year level required (max 50 characters). Error: "Grade/Year level is required for enrolled children". If "No": reason required (max 500 characters). Error: "Please provide a reason for not being enrolled".'],
        ['Grade / Year Level', 'Text input', 'Conditional', 'Required if Currently Enrolled = Yes. Max 50 characters.'],
        ['Reason Not Enrolled', 'Text input', 'Conditional', 'Required if Currently Enrolled = No.'],
        ['School Has Accessibility Features?', 'Toggle (Yes / No)', 'No', 'If "Yes": details required (max 500 characters). Error: "Please specify accessibility feature details". Only validated when enrolled = Yes.'],
        ['Has SPED Programs?', 'Toggle (Yes / No)', 'No', 'If "Yes": details required (max 500 characters). Error: "Please specify SPED program details". Only when enrolled = Yes.'],
        ['Receives Learning Support?', 'Toggle (Yes / No)', 'No', 'If "Yes": details required (max 500 characters). Error: "Please specify learning support details". Only when enrolled = Yes.'],
      ],
      [28, 14, 12, 46]
    ),

    h3('3.2.8 Step 8: Economic Capacity'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Primary Income Source', 'Text input', 'Yes', 'Min 3, max 255 characters. Error if empty: "Primary income source is required". Error if short: "Income source must be at least 3 characters".'],
        ['Monthly Income (PHP)', 'Number input', 'Yes', 'Must be a whole number (regex /^\\d+$/). Error if empty: "Monthly income is required". Error if non-numeric or decimal: "Monthly income must be a whole number". Note: "0" passes client validation but the server stores NULL for values not greater than zero.'],
        ['Are Parents / Guardians Employed?', 'Toggle (Yes / No)', 'No', 'If "Yes": employment details required (max 500 characters). Error: "Please specify employment details".'],
      ],
      [28, 16, 12, 44]
    ),

    h3('3.2.9 Step 9: Service Availment'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Receives Financial Assistance?', 'Toggle (Yes / No)', 'No', 'If "Yes": details required (max 500 characters). Error: "Please specify financial assistance details".'],
        ['Aware of Social Services?', 'Toggle (Yes / No)', 'No', 'If "Yes": awareness details required. Error: "Please specify service awareness details".'],
        ['Has Availed Services?', 'Toggle (Yes / No)', 'No', 'If "Yes": availed services details required. Error: "Please specify availed services details".'],
        ['Service Challenges', 'Dropdown', 'Yes', 'Must select one option. If "Others": specify field required (max 255 characters). Error: "Please select service challenge" / "Please specify service challenge".'],
      ],
      [28, 16, 12, 44]
    ),

    h3('3.2.10 Step 10: Assessment Notes and Readiness Score'),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Strengths', 'Textarea', 'Yes', 'Min 10, max 2000 characters. Error if empty: "Strengths is required". Error if short: "Strengths must be at least 10 characters". Error if too long: "Strengths must not exceed 2000 characters".'],
        ['Assessment', 'Textarea', 'Yes', 'Same rules. Error messages use the label "Assessment".'],
        ['Recommended Actions / Interventions', 'Textarea', 'Yes', 'Same rules. Error messages use the label "Recommendations".'],
        ['Readiness Score', 'Radio button group', 'Yes', 'Must select one of: severe, moderate, low, stable. Error if not selected: "Please select a readiness score". Labels shown to user: Severe (Immediate intervention needed), Moderate (Address within a short period), Low (No immediate action, monitor regularly), Stable (Meets all needs effectively).'],
      ],
      [28, 14, 12, 46]
    ),

    h3('3.2.11 Review and Submit'),
    para('After Step 10, the form advances to a read-only review screen showing all data entered across all 10 steps. The user reviews the information and clicks "Submit Assessment". On success, the system generates a unique ARUGA ID, saves all data to 12 database tables, clears the session, and redirects to the success page at /success with the ARUGA ID and child name as URL parameters.'),

    // 3.3
    h2('3.3 Dashboard Login'),
    para('Location: /login (file: public/login-dashboard.html)'),
    para('[Screenshot placeholder: Dashboard login page showing email and password fields]'),
    blankLine(),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Email Address', 'Email input', 'Yes', 'Must not be empty. Must be a valid email format (PHP FILTER_VALIDATE_EMAIL). Error if empty (with password): "Email and password are required". Error if invalid format: "Invalid email address". HTTP 400 returned.'],
        ['Password', 'Password input', 'Yes', 'Must not be empty. Error if empty: "Email and password are required". No minimum length is enforced at login (only on password change). Incorrect password: "Invalid email or password". HTTP 401 returned.'],
      ],
      [22, 14, 12, 52]
    ),
    blankLine(),
    para('Rate limiting: After 5 failed login attempts from the same IP address and email within 15 minutes, the system returns HTTP 429 with the message: "Too many failed login attempts. Please try again in 15 minutes."'),
    blankLine(),
    para('On success: The system creates a session, returns the user\'s role, and the browser redirects to the role-appropriate dashboard (/dashboard-admin, /dashboard-central, /dashboard-stu-head, or /dashboard-field-officer). The session idle timeout is 30 minutes. A 2-minute warning appears before automatic logout.'),

    // 3.4
    h2('3.4 Profile Settings'),
    para('Location: Available from all dashboards via a profile menu (endpoint: PUT /api/settings/profile.php)'),
    blankLine(),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Full Name', 'Text input', 'No (only if changing)', 'Min 2, max 120 characters. Error: "Full name must be 2-120 characters."'],
        ['Position', 'Text input', 'No', 'Max 120 characters. Error: "Position must be 120 characters or fewer."'],
        ['Office', 'Text input', 'No (central role only)', 'Max 120 characters. Only editable by users with the central_office role. Others receive HTTP 403: "You are not allowed to change your office."'],
        ['Current Password', 'Password input', 'Conditional (required if changing password)', 'Error if empty when new password is provided: "Current password is required." Verified against stored bcrypt hash.'],
        ['New Password', 'Password input', 'No (only if changing)', 'Min 8 characters: "New password must be at least 8 characters." Must contain uppercase: "New password must contain at least one uppercase letter." Must contain number: "New password must contain at least one number." Must contain special character: "New password must contain at least one special character."'],
      ],
      [22, 14, 14, 50]
    ),

    // 3.5
    h2('3.5 Security Settings (Admin)'),
    para('Location: Admin dashboard settings panel (endpoint: GET/PUT /api/settings/security.php)'),
    blankLine(),
    makeTable(
      ['Setting Name', 'Default Value', 'Description'],
      [
        ['Idle Timeout', '30 minutes', 'Minutes of inactivity before the system shows a logout warning.'],
        ['Absolute Timeout', '8 hours', 'Maximum session duration regardless of activity.'],
        ['Timeout Warning', '5 minutes', 'Minutes before idle timeout that the warning popup appears.'],
        ['Remember Me Duration', '30 days', 'Duration for remember-me sessions (if implemented).'],
        ['Max Failed Attempts', '5', 'Failed logins before lockout. Applies to both dashboard login and interviewer code validation.'],
        ['Lockout Duration', '30 minutes', 'Minutes the account is locked after max failed attempts.'],
        ['Force Password Change', 'true', 'Whether users must change their password on first login.'],
        ['Password Min Length', '8 characters', 'Minimum password length.'],
        ['Require Uppercase', 'true', 'Password must contain at least one uppercase letter.'],
        ['Require Numbers', 'true', 'Password must contain at least one number.'],
        ['Require Special Characters', 'true', 'Password must contain at least one special character.'],
        ['Password Expiry', '90 days', 'Days before a password must be changed.'],
        ['Prevent Password Reuse', 'false', 'Whether to block reuse of previous passwords.'],
      ],
      [28, 18, 54]
    ),
    blankLine(),
    para('Note: These settings are stored in the system_settings table in the database. The security settings page is intended for admin users but the endpoint does not enforce a role check beyond a valid session. This should be restricted to admin role only.'),

    // 3.6
    h2('3.6 Add / Update Interviewer (Admin)'),
    para('Location: Admin dashboard user management panel (endpoints: POST /api/add-interviewer.php and POST /api/update-interviewer.php)'),
    blankLine(),
    para('[Screenshot placeholder: Add Interviewer form]'),
    blankLine(),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Full Name', 'Text input', 'Yes', 'Must not be empty. Error: "Name, Code, and Region are required."'],
        ['Interviewer Code', 'Text input', 'Yes', 'Must not be empty. Must be unique in the interviewers table. Error if empty: "Name, Code, and Region are required."'],
        ['Region', 'Dropdown', 'Yes', 'Must not be empty. Error: "Name, Code, and Region are required."'],
        ['Province', 'Text input', 'No', 'Free text.'],
        ['Position', 'Text input', 'No', 'Free text.'],
        ['Office', 'Text input', 'No', 'Free text.'],
        ['Email Address', 'Email input', 'No', 'If provided: must be valid email format. Error: "Invalid email format." Must be unique. Error if duplicate: "An interviewer with this email already exists."'],
        ['Password', 'Password input', 'Conditional (required if email is provided)', 'Min 8 characters. Error: "Password must be at least 8 characters." Stored as bcrypt hash (cost factor 12).'],
        ['Dashboard Role', 'Dropdown', 'No (default: field_officer)', 'Options: admin, central, stu_head, field_officer.'],
        ['Account Status', 'Dropdown', 'No (default: active)', 'Options: active, inactive.'],
      ],
      [22, 16, 14, 48]
    ),

    // 3.7
    h2('3.7 Edit Request Submission (Field Officer)'),
    para('Location: Field officer dashboard, action-required items (endpoint: POST /api/field-officer/submit-edit-request.php)'),
    para('A field officer who needs to correct data in a submitted assessment fills out the relevant sections of the assessment form again and submits an edit request. The payload is the same structure as the original assessment update. The system automatically cancels any previous pending request for the same assessment and creates a new one with status "pending". The request then awaits review by the STU Head.'),

    // 3.8
    h2('3.8 Edit Request Review (STU Head)'),
    para('Location: STU Head dashboard, Pending Reviews (endpoint: POST /api/stu-head/review-edit-request.php)'),
    blankLine(),
    makeTable(
      ['Field Name', 'Type', 'Required', 'Validation Rules'],
      [
        ['Request ID', 'Hidden (auto-populated)', 'Yes', 'UUID of the edit request. Error if missing: "request_id required".'],
        ['Action', 'Radio button or dropdown', 'Yes', 'Must be one of: approved, for_update, declined. Error if invalid: "action must be approved, for_update, or declined".'],
        ['Reviewer Note', 'Textarea', 'Conditional', 'Required when Action = "for_update". Error: "reviewer_note required when action is for_update". Optional for approved or declined.'],
      ],
      [22, 18, 16, 44]
    ),
    blankLine(),
    para('The system rejects reviews of requests that are no longer in pending status. Error: "Edit request is no longer pending (status: [current status])."'),
    blankLine(),
    para('On approved: The beneficiary record is updated in all affected tables. Success message: "Edit request approved. Beneficiary record has been updated."'),
    para('On for_update: The request is returned to the field officer for revision. Success message: "Edit request returned to field officer for revision."'),
    para('On declined: The request is rejected. Success message: "Edit request declined."'),
    pageBreak(),

    // ─── SECTION 4: SYSTEM OUTPUTS ──────────────────────────
    h1('Section 4: System Outputs'),
    para('This section lists every output the system produces, including pages displayed on screen, downloadable files, and any data the system sends out.'),
    blankLine(),
    makeTable(
      ['Output Name', 'Type', 'Who Receives It', 'When Generated', 'Contents'],
      [
        ['ARUGA ID', 'On-screen display and URL parameter', 'Field officer and family', 'Immediately after successful assessment submission', 'Unique identifier in format ARUGA-YYYY-RR-NNNN. Displayed on the success page (/success).'],
        ['QR Code', 'On-screen image, printable and downloadable', 'Field officer and family', 'Immediately after successful assessment submission', 'QR code image encoding a public profile URL. The URL allows anyone with the code to look up the beneficiary profile without logging in. Generated on the success page using the qrcodejs library.'],
        ['Success Confirmation Page', 'HTML page on screen', 'Field officer', 'After assessment submission', 'Child name, generated ARUGA ID, QR code, and instructions for next steps.'],
        ['Dashboard Statistics', 'On-screen charts and counters', 'All logged-in dashboard users', 'On dashboard load and on refresh', 'Depending on role: total beneficiaries, active interviewers, regions covered, completion rate, trend charts, regional breakdowns, disability distributions, and interviewer workload metrics.'],
        ['Beneficiary Profile Page', 'HTML page on screen', 'Anyone with the URL (no login required)', 'On demand when the URL is visited', 'ARUGA ID, child name, registration date, and readiness score. This is a limited public view, not the full assessment data.'],
        ['Full Beneficiary Detail', 'JSON data rendered on screen', 'Authenticated dashboard users', 'On demand when a beneficiary record is opened', 'Complete assessment data across all 12 related tables.'],
        ['Data Export (JSON)', 'Downloadable .json file', 'Admin only (requires X-Admin-Token header)', 'On demand via POST /api/export-backup.php with format=json', 'All data from all 15 database tables in a single JSON bundle. File is named aruga-backup-all-tables-[timestamp].json.'],
        ['Data Export (CSV)', 'Downloadable .csv file', 'Admin only (requires X-Admin-Token header)', 'On demand via POST /api/export-backup.php with format=csv', 'If one table: single CSV file with UTF-8 BOM for Excel compatibility, named aruga-[tablename]-[timestamp].csv. If all tables: a JSON bundle containing one base64-encoded CSV per table.'],
        ['Audit Log View', 'On-screen table', 'Authenticated dashboard users (no role restriction enforced at present)', 'On demand from audit logs menu', 'Action performed, table affected, record ID, user name and code, region, IP address, and timestamp. Human-readable description of each action.'],
        ['Toast Notifications', 'On-screen popup message', 'Current user', 'After any system action (success or failure)', 'Short message with one of four types: success (green), error (red), warning (yellow), info (blue). Auto-dismisses after 4 to 5 seconds.'],
      ],
      [20, 14, 16, 18, 32]
    ),
    blankLine(),
    para('Note: The system does not currently send email notifications. No email-sending code (SMTP, SendGrid, or similar) was found in the codebase. All notifications are on-screen toast messages only.'),
    pageBreak(),

    // ─── SECTION 5: SYSTEM PROCESSES ────────────────────────
    h1('Section 5: System Processes'),
    para('This section describes the main workflows in the system in plain, step-by-step language.'),

    h2('5.1 Field Interviewer Assessment Workflow'),
    para('This is the primary workflow. It is used by field officers during home visits.'),
    blankLine(),
    para('1. The field officer opens the application in a browser and navigates to the home page.'),
    para('2. The field officer reads the data privacy notice on the page.'),
    para('3. The field officer checks the "I agree to the Privacy Policy" checkbox.'),
    para('4. The field officer types their interviewer code (8 characters) in the input field.'),
    para('5. The system sends the code to the server. The server checks that the code belongs to an active interviewer account.'),
    para('6. If the code is invalid or the account is inactive, the system shows an error toast. The field officer may try again. After 5 failed attempts in 15 minutes, the system blocks further attempts from the same device for 15 minutes.'),
    para('7. If the code is valid, the system creates a session, stores the session ID in the browser, and redirects to the profiling form.'),
    para('8. The field officer completes all 10 steps of the assessment form, entering information about the child, family, living conditions, health, education, and service needs.'),
    para('9. Before moving to each next step, the system checks that all required fields are filled in correctly. If any field has an error, the system highlights it and shows an error message. The form does not advance until all errors are corrected.'),
    para('10. After Step 10, the system shows a review screen with all entered data. The field officer checks the information for accuracy.'),
    para('11. The field officer clicks "Submit Assessment".'),
    para('12. The system sends the data to the server. The server generates a unique ARUGA ID based on the current year and the child\'s region.'),
    para('13. The server saves the assessment across 12 database tables.'),
    para('14. The system clears the session from the browser and redirects to the success page.'),
    para('15. The success page displays the child\'s name, the ARUGA ID, and a QR code. The field officer may print the QR code for the family.'),

    h2('5.2 Dashboard Login Workflow'),
    para('This workflow applies to all dashboard users: field officers, STU heads, central office staff, and administrators.'),
    blankLine(),
    para('1. The user opens the browser and navigates to /login.'),
    para('2. The user enters their email address and password.'),
    para('3. The system sends the credentials to the server.'),
    para('4. The server checks that the email is not empty and is a valid email format. If not, it returns an error.'),
    para('5. The server checks the rate limit. If the same IP and email have had 5 or more failed login attempts in the past 15 minutes, the server blocks the request.'),
    para('6. The server looks up the email in the interviewers table. If not found or if the account is inactive, the server returns "Invalid email or password" without revealing which part was wrong.'),
    para('7. The server verifies the password against the stored bcrypt hash. If incorrect, the server logs a failed login attempt and returns "Invalid email or password".'),
    para('8. If the password is correct, the server creates a session record in the database and returns the session ID, user details, and dashboard role.'),
    para('9. The browser stores the session ID and user information in session storage.'),
    para('10. The browser redirects to the appropriate dashboard based on the role returned.'),
    para('11. While using the dashboard, if the user is inactive for 30 minutes, a warning popup appears with a countdown. The user may click "Stay Logged In" to reset the timer. If the user does not respond within 2 minutes, the system clears the session and redirects to the login page.'),

    h2('5.3 Edit Request Workflow'),
    para('This workflow allows a field officer to request a correction to a submitted assessment, and allows the regional STU Head to review and act on it.'),
    blankLine(),
    para('1. The field officer logs in to the dashboard and navigates to the action-required list or finds the beneficiary record that needs correction.'),
    para('2. The field officer fills in the corrected information and submits an edit request.'),
    para('3. The system checks that the ARUGA ID and interviewer code are valid.'),
    para('4. If a previous pending request exists for the same assessment, the system marks it as "superseded" automatically.'),
    para('5. The system creates a new edit request with status "pending" and saves the proposed changes as a data payload.'),
    para('6. The STU Head logs in to the STU Head dashboard and navigates to "Pending Reviews".'),
    para('7. The STU Head sees the list of pending edit requests for their region. Each request shows the current data and the proposed changes.'),
    para('8. The STU Head selects one of three actions:'),
    para('   a. Approved: The system applies all proposed changes to the beneficiary record and marks the request as approved.', { indent: 0.3 }),
    para('   b. For Update: The system marks the request as needing revision. The STU Head must provide a reviewer note explaining what to fix. The note is shown to the field officer.', { indent: 0.3 }),
    para('   c. Declined: The system rejects the request. The beneficiary record is not changed.', { indent: 0.3 }),
    para('9. All review actions are recorded in the audit log.'),

    h2('5.4 Beneficiary Lookup via QR Code'),
    para('1. A social worker or authorized person scans the QR code on the beneficiary\'s printed card using any QR scanner.'),
    para('2. The QR code encodes a URL pointing to the public profile page with the ARUGA ID as a parameter.'),
    para('3. The browser opens the public profile page (/profile) and the system looks up the beneficiary by ARUGA ID.'),
    para('4. The page displays the child\'s name, ARUGA ID, registration date, and readiness score. No login is required. Full assessment details are not shown on this public page.'),

    h2('5.5 Analytics and Reporting Workflow'),
    para('1. A central office user or administrator logs in to their dashboard.'),
    para('2. The dashboard automatically loads summary statistics: total beneficiaries, active interviewers, regions covered, and overall completion rate.'),
    para('3. The user may navigate to the analytics section for detailed charts.'),
    para('4. The user may filter by region, province, date range (last 30 days or all time), readiness score, disability type, or 4Ps membership.'),
    para('5. The system fetches assessment and child data from the database and computes trends, regional breakdowns, disability distributions, and interviewer workload categories on the server.'),
    para('6. Charts and tables are rendered in the browser using the returned data.'),

    h2('5.6 Admin User Management Workflow'),
    para('1. An administrator logs in to the admin dashboard.'),
    para('2. The administrator navigates to the user management section and clicks "Add Interviewer".'),
    para('3. The administrator fills in the required fields: full name, interviewer code, and region.'),
    para('4. The administrator optionally provides an email, password, position, office, dashboard role, and status.'),
    para('5. If an email is provided, the system checks that it is not already used by another interviewer.'),
    para('6. If a password is provided, the system checks that it is at least 8 characters.'),
    para('7. On success, the system saves the new interviewer with the password stored as a bcrypt hash and returns "Interviewer added successfully".'),
    para('8. To update an existing interviewer, the administrator finds the record in the list, edits the fields, and saves. All changes are logged in the audit trail.'),

    h2('5.7 Data Export Workflow'),
    para('1. An administrator sends a POST request to /api/export-backup.php with the X-Admin-Token header set to the configured admin export token.'),
    para('2. The administrator specifies the format (json or csv) and the target table (a specific table name or "all").'),
    para('3. The system fetches all rows from the requested tables in batches of 1,000 rows.'),
    para('4. For JSON format: the system returns a single downloadable JSON file containing all table data.'),
    para('5. For CSV format with one table: the system returns a single CSV file with a UTF-8 BOM so it opens correctly in Microsoft Excel.'),
    para('6. For CSV format with all tables: the system returns a JSON bundle containing one base64-encoded CSV per table, which the browser can decode and download as separate files.'),
    para('Note: This endpoint uses a separate token-based authentication (X-Admin-Token header), not the standard session-based authentication used by all other protected endpoints.'),
    pageBreak(),
  ];
}

module.exports = {
  buildCover, buildApprovalPage, buildSection1,
  run, para, h1, h2, h3, blankLine, bullet, bold, cell,
  tableRow, makeTable, pageBreak, BLUE_FILL, GRAY_FILL, DARK_BLUE, MID_BLUE, WHITE, BLACK, pt,
};
