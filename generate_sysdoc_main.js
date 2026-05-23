/**
 * System Documentation — Final Assembly
 * Combines all parts and writes System_Documentation_ProjectAruga_v1.0.0.docx
 */
'use strict';

const { Document, Packer, Paragraph, TextRun, AlignmentType,
        HeadingLevel, PageNumber, NumberFormat, Header, Footer,
        LineRuleType } = require('docx');

const p1 = require('./generate_sysdoc_p1');
const { buildSection6 } = require('./generate_sysdoc_p2');
const { buildSection12to14 } = require('./generate_sysdoc_p3');

const {
  buildCover, buildApprovalPage, buildSection1,
  para, blankLine, h1, h2, makeTable, bullet, pageBreak, pt,
  DARK_BLUE, MID_BLUE, BLACK,
} = p1;

// ─── TOC placeholder (Word will rebuild on open) ─────────────
function buildTOC() {
  return [
    new Paragraph({
      children: [new TextRun({ text: 'TABLE OF CONTENTS', font: 'Calibri', size: pt(14), bold: true, color: DARK_BLUE })],
      alignment: AlignmentType.CENTER,
      spacing: { before: 0, after: 200 },
    }),
    new Paragraph({
      children: [new TextRun({ text: '[Right-click this area in Microsoft Word and select "Update Field" to generate the table of contents automatically.]', font: 'Calibri', size: pt(10), color: '888888', italics: true })],
      alignment: AlignmentType.CENTER,
      spacing: { before: 0, after: 400 },
    }),
    // Manual TOC entries as a readable fallback
    ...buildManualTOC(),
    pageBreak(),
  ];
}

function tocEntry(text, level = 0) {
  const indent = level * 0.3;
  return new Paragraph({
    children: [new TextRun({ text, font: 'Calibri', size: pt(10.5), color: BLACK })],
    spacing: { before: 40, after: 40 },
    indent: indent ? { left: Math.round(indent * 1440) } : undefined,
  });
}

function buildManualTOC() {
  return [
    tocEntry('Cover Page'),
    tocEntry('Document Information and Approval'),
    tocEntry('Table of Contents'),
    tocEntry('Section 1: Introduction'),
    tocEntry('1.1 System Architecture', 1),
    tocEntry('1.1.1 Architecture Diagram (Text Description)', 2),
    tocEntry('Section 2: System Profile'),
    tocEntry('Section 3: System Inputs'),
    tocEntry('3.1 Entry Page and Privacy Agreement', 1),
    tocEntry('3.2 Beneficiary Profiling Form', 1),
    tocEntry('3.2.1 Step 1: Pre-Qualification', 2),
    tocEntry('3.2.2 Step 2: Respondent Information', 2),
    tocEntry('3.2.3 Step 3: Child Demographics and Education', 2),
    tocEntry('3.2.4 Step 4: Family Members', 2),
    tocEntry('3.2.5 Step 5: Socio-Economic Status', 2),
    tocEntry('3.2.6 Step 6: Health Information', 2),
    tocEntry('3.2.7 Step 7: Education Information', 2),
    tocEntry('3.2.8 Step 8: Economic Capacity', 2),
    tocEntry('3.2.9 Step 9: Service Availment', 2),
    tocEntry('3.2.10 Step 10: Assessment Notes and Readiness Score', 2),
    tocEntry('3.2.11 Review and Submit', 2),
    tocEntry('3.3 Dashboard Login', 1),
    tocEntry('3.4 Profile Settings', 1),
    tocEntry('3.5 Security Settings (Admin)', 1),
    tocEntry('3.6 Add / Update Interviewer (Admin)', 1),
    tocEntry('3.7 Edit Request Submission (Field Officer)', 1),
    tocEntry('3.8 Edit Request Review (STU Head)', 1),
    tocEntry('Section 4: System Outputs'),
    tocEntry('Section 5: System Processes'),
    tocEntry('5.1 Field Interviewer Assessment Workflow', 1),
    tocEntry('5.2 Dashboard Login Workflow', 1),
    tocEntry('5.3 Edit Request Workflow', 1),
    tocEntry('5.4 Beneficiary Lookup via QR Code', 1),
    tocEntry('5.5 Analytics and Reporting Workflow', 1),
    tocEntry('5.6 Admin User Management Workflow', 1),
    tocEntry('5.7 Data Export Workflow', 1),
    tocEntry('Section 6: Access Control List'),
    tocEntry('Section 7: Software Server Requirements'),
    tocEntry('Section 8: Hardware Server Requirements'),
    tocEntry('Section 9: Client Requirements'),
    tocEntry('Section 10: Database Dictionary'),
    tocEntry('10.1 assessments', 1),
    tocEntry('10.2 children', 1),
    tocEntry('10.3 respondents', 1),
    tocEntry('10.4 pre_qualification', 1),
    tocEntry('10.5 child_education_health', 1),
    tocEntry('10.6 family_members', 1),
    tocEntry('10.7 socio_economic', 1),
    tocEntry('10.8 health_info', 1),
    tocEntry('10.9 education_info', 1),
    tocEntry('10.10 economic_capacity', 1),
    tocEntry('10.11 service_availment', 1),
    tocEntry('10.12 assessment_notes', 1),
    tocEntry('10.13 interviewers', 1),
    tocEntry('10.14 sessions', 1),
    tocEntry('10.15 beneficiary_edit_requests', 1),
    tocEntry('10.16 audit_logs', 1),
    tocEntry('10.17 system_settings', 1),
    tocEntry('Section 11: Entity Relationship Diagram'),
    tocEntry('11.1 Relationship Summary', 1),
    tocEntry('11.2 Central Entity Description', 1),
    tocEntry('Section 12: Security and Data Privacy'),
    tocEntry('12.1 Password Storage', 1),
    tocEntry('12.2 Session Management', 1),
    tocEntry('12.3 Transport Security', 1),
    tocEntry('12.4 CORS', 1),
    tocEntry('12.5 Content Security Policy', 1),
    tocEntry('12.6 Input Sanitization', 1),
    tocEntry('12.7 Rate Limiting', 1),
    tocEntry('12.8 Data Privacy Compliance', 1),
    tocEntry('12.9 Audit Logging', 1),
    tocEntry('12.10 Backup Strategy', 1),
    tocEntry('Section 13: System Maintenance'),
    tocEntry('13.1 Deploying Updates', 1),
    tocEntry('13.2 Modifying the CSS', 1),
    tocEntry('13.3 Database Maintenance', 1),
    tocEntry('13.4 Checking Application Logs', 1),
    tocEntry('13.5 Adding a New Interviewer', 1),
    tocEntry('13.6 Deactivating a User Account', 1),
    tocEntry('13.7 Troubleshooting Common Issues', 1),
    tocEntry('Section 14: Document Control'),
    tocEntry('14.1 Document Information', 1),
    tocEntry('14.2 Document History', 1),
    tocEntry('14.3 Document Approval', 1),
  ];
}

// ─── Header/Footer ────────────────────────────────────────────
const docHeader = {
  default: new Header({
    children: [
      new Paragraph({
        children: [
          new TextRun({ text: 'Project Aruga — System Documentation  v1.0.0', font: 'Calibri', size: pt(9), color: '666666' }),
          new TextRun({ text: '     |     DSWD Social Technology Bureau', font: 'Calibri', size: pt(9), color: '999999' }),
        ],
        alignment: AlignmentType.RIGHT,
        border: { bottom: { style: 'single', size: 1, color: 'CCCCCC' } },
      }),
    ],
  }),
  first: new Header({ children: [new Paragraph({ children: [] })] }),
};

const docFooter = {
  default: new Footer({
    children: [
      new Paragraph({
        children: [
          new TextRun({ text: 'CONFIDENTIAL — FOR OFFICIAL USE ONLY     ', font: 'Calibri', size: pt(9), color: '999999' }),
          new TextRun({ children: [PageNumber.CURRENT], font: 'Calibri', size: pt(9), color: '666666' }),
          new TextRun({ text: ' of ', font: 'Calibri', size: pt(9), color: '666666' }),
          new TextRun({ children: [PageNumber.TOTAL_PAGES], font: 'Calibri', size: pt(9), color: '666666' }),
        ],
        alignment: AlignmentType.CENTER,
        border: { top: { style: 'single', size: 1, color: 'CCCCCC' } },
      }),
    ],
  }),
  first: new Footer({ children: [new Paragraph({ children: [] })] }),
};

// ─── Assemble ─────────────────────────────────────────────────
async function main() {
  console.log('Assembling document sections...');

  const allChildren = [
    ...buildCover(),
    ...buildApprovalPage(),
    ...buildTOC(),
    ...buildSection1(),     // Sections 1-5
    ...buildSection6(),     // Sections 6-11
    ...buildSection12to14(),// Sections 12-14
  ];

  console.log(`Total content blocks: ${allChildren.length}`);

  const doc = new Document({
    creator: 'DSWD Social Technology Bureau',
    title:   'Project Aruga — System Documentation v1.0.0',
    subject: 'Child Disability Profiling and Assessment System',
    keywords:'DSWD, Aruga, system documentation, disability, profiling',
    styles: {
      paragraphStyles: [
        {
          id: 'Normal',
          name: 'Normal',
          run: { font: 'Calibri', size: pt(11) },
          paragraph: { spacing: { line: 276, lineRule: LineRuleType.AUTO } },
        },
        {
          id: 'Heading1',
          name: 'Heading 1',
          basedOn: 'Normal',
          next: 'Normal',
          run: { font: 'Calibri', size: pt(16), bold: true, color: DARK_BLUE },
          paragraph: { spacing: { before: 320, after: 120 } },
        },
        {
          id: 'Heading2',
          name: 'Heading 2',
          basedOn: 'Normal',
          next: 'Normal',
          run: { font: 'Calibri', size: pt(13), bold: true, color: DARK_BLUE },
          paragraph: { spacing: { before: 240, after: 80 } },
        },
        {
          id: 'Heading3',
          name: 'Heading 3',
          basedOn: 'Normal',
          next: 'Normal',
          run: { font: 'Calibri', size: pt(11), bold: true, color: MID_BLUE },
          paragraph: { spacing: { before: 160, after: 60 } },
        },
      ],
    },
    sections: [
      {
        properties: {
          page: {
            margin: {
              top:    1440,   // 1 inch
              right:  1440,
              bottom: 1440,
              left:   1440,
            },
          },
          titlePage: true,
        },
        headers: docHeader,
        footers: docFooter,
        children: allChildren,
      },
    ],
  });

  const outputPath = 'System_Documentation_ProjectAruga_v1.0.0.docx';
  const buf = await Packer.toBuffer(doc);
  require('fs').writeFileSync(outputPath, buf);

  const sizeKB = Math.round(buf.length / 1024);
  console.log(`\nOutput: ${outputPath}  (${sizeKB} KB)`);
  console.log('\nSECTIONS COMPLETED:');
  [
    'Cover Page',
    'Document Information and Approval Page',
    'Table of Contents (manual + auto-update instructions)',
    'Section 1: Introduction + System Architecture',
    'Section 2: System Profile',
    'Section 3: System Inputs (all 8 input screens, 10-step form fully documented)',
    'Section 4: System Outputs (10 output types)',
    'Section 5: System Processes (7 workflows)',
    'Section 6: Access Control List (4 roles, known security gaps noted)',
    'Section 7: Software Server Requirements',
    'Section 8: Hardware Server Requirements',
    'Section 9: Client Requirements',
    'Section 10: Database Dictionary (all 17 tables)',
    'Section 11: Entity Relationship Diagram (text description + relationship table)',
    'Section 12: Security and Data Privacy (10 subsections)',
    'Section 13: System Maintenance (7 subsections + troubleshooting table)',
    'Section 14: Document Control',
  ].forEach((s, i) => console.log(`  ${String(i+1).padStart(2,'0')}. ${s}`));

  console.log('\nITEMS TO FILL IN MANUALLY:');
  [
    'Cover page: Insert DSWD or Project Aruga logo',
    'Approval page: Office address and contact numbers',
    'Section 2: Development server URL',
    'Section 2: Developer / author names',
    'Section 7: Supabase project ID and region URL',
    'Section 14: All four approval signatures (Prepared By, Reviewed By, Recommended By, Approved By)',
    'TOC: Right-click in Word and select "Update Field" to auto-generate with page numbers',
    'All screenshot placeholders throughout Section 3 (8 placeholders)',
  ].forEach((item, i) => console.log(`  ${i+1}. ${item}`));
}

main().catch(e => { console.error(e); process.exit(1); });
