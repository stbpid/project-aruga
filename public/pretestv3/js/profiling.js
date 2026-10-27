// ============================================================================
// PROJECT ARUGA - v3 PROFILING FORM (PRETEST APP: desktop, Android and web)
// Questions from "Comprehensive Interview, Profiling and Scoring Tool
// (Integrated Field Version)", revised 25 October 2026. Screens, engine,
// offline outbox and feedback are the v2 app's; only the questions and the
// new answer types (1–5 rating, date of birth, address, computed amounts) are new.
//
// Answers are sent keyed by field id (s2_1_first, s7_1a, s11_1a_met, ...),
// which are also the column names of pretest.v3_profiles. Keep them in sync.
// Login and the Data Privacy Notice (the tool's consent step) are index.html.
// ============================================================================

// One ID per profile, created on this device when the form opens.
const PRETEST_RECORD_ID = PretestApi.newRecordId();
const PRETEST_CREATED_ON_DEVICE_AT = new Date().toISOString();
let pretestFeedback = [];

// ============================================================================
// v3 QUESTION BANK
// ============================================================================

// Agreement scale used by every scored item: 1 = Strongly agree … 5 = Strongly
// disagree. Not applicable / Don't know / Refused are recorded separately and
// are never scored as zero.
const LIKERT = [['1', 'Strongly agree'], ['2', 'Agree'], ['3', 'Neither agree nor disagree'], ['4', 'Disagree'], ['5', 'Strongly disagree']];
const LIKERT_OTHER = [['NA', 'Not applicable'], ['DK', 'Don’t know'], ['REF', 'Refused']];
const LIKERT_LABEL = Object.fromEntries([...LIKERT, ...LIKERT_OTHER]);
const RATED = ['1', '2', '3', '4', '5'];

// Residence: Region III only, from the main Profiling Tool's location list
// (data/locations.json, exported from api/admin-router.php?action=locations).
const V3_REGIONS = ['Region III (Central Luzon)'];
let locationData = {};

const L = (id, area, q, extra = {}) => ({ id, area, q, type: 'likert', ...extra });

const URGENT = [
  ['a', 'Health', 'My child’s urgent health-care needs are being met.', 'health'],
  ['b', 'Rehabilitation', 'My child’s urgent rehabilitation needs are being met.', 'rehabilitation'],
  ['c', 'Education', 'My child receives the urgent education support needed to begin or continue participating in learning.', 'education'],
  ['d', 'Protection', 'My child receives the protection needed to keep him/her safe from harm.', 'protection'],
  ['e', 'Care', 'My child receives the help needed to meet her/his essential daily care needs.', 'daily care'],
  ['f', 'Other support', 'My child receives the support needed to address other urgent needs.', 'other']
];

const STEPS = [
  // ---------------------------------------------------------------- STEP 1 · C
  {
    no: 2, title: 'Identifying Information', label: 'Identifying Information', heading: 'Identifying Information',
    subtext: 'Basic details about the child, residence, and caregiver.',
    sections: [
      { title: 'Child’s Name', icon: 'badge', fields: [
        { id: 's2_1_first', area: 'First name', q: 'First Name', type: 'text', icon: 'person', ph: 'First name', half: true, optional: true },
        { id: 's2_1_middle', area: 'Middle name', q: 'Middle Name', type: 'text', icon: 'person', ph: 'Middle name', half: true, optional: true },
        { id: 's2_1_last', area: 'Last name', q: 'Last Name', type: 'text', icon: 'person', ph: 'Last name', half: true, optional: true },
        { id: 's2_1_ext', area: 'Extension', q: 'Extension', type: 'select', ph: 'None', options: ['None', 'Jr.', 'Sr.', 'II', 'III', 'IV'], emptyValue: 'None', half: true, optional: true }
      ] },
      { title: 'Child', icon: 'face', fields: [
        { id: 's2_2', area: 'PhilSys ID Number', q: 'PhilSys ID Number', type: 'text', icon: 'fingerprint', ph: 'Numbers only (optional)', digits: true, half: true, optional: true },
        { id: 's2_3', area: 'Preferred name', q: 'What name does the child usually use at home?', type: 'text', icon: 'badge', ph: 'Preferred name', half: true },
        { id: 's2_4', area: 'Date of birth', q: 'What is the child’s date of birth?', type: 'date', half: true },
        { id: 's2_5', area: 'Sex', q: 'What is the child’s sex?', type: 'select', ph: 'Select sex', options: ['Male', 'Female', 'Other'], specify: ['Other'], specSuffix: '_other', specifyPh: 'Specify', half: true }
      ] },
      { title: 'Residence and Living Arrangement', icon: 'home', fields: [
        { id: 's2_6', area: 'Residence', q: 'Where does the child currently live?', type: 'address' },
        { id: 's2_7', area: 'Living arrangement', q: 'Is the child currently living with a parent?', type: 'yn' }
      ] },
      { title: 'Caregiver', icon: 'family_restroom', fields: [
        { id: 's2_8', area: 'Primary caregiver', q: 'Who is the child’s primary caregiver?', type: 'text', icon: 'person', ph: 'Name of primary caregiver', half: true },
        { id: 's2_9', area: 'Relationship', q: 'What is your relationship to the child?', type: 'select', ph: 'Select relationship', options: ['Parent', 'Sibling', 'Grandparent', 'Aunt/Uncle', 'Cousin', 'Other'], specify: ['Other'], specSuffix: '_other', half: true },
        { id: 's2_10', area: 'Decision-maker', q: 'Who usually makes decisions about health, education and care?', type: 'select', ph: 'Select decision-maker', options: ['Parent', 'Sibling', 'Grandparent', 'Other'], specify: ['Other'], specSuffix: '_other' }
      ] }
    ]
  },
  // ---------------------------------------------------------------- STEP 2 · D1
  {
    no: 3, title: 'Child Disability and Functional Status', label: 'Domain 1 · Child Disability and Functional Status', heading: 'Child Disability and Functional Status',
    subtext: 'Identification only. This domain is not scored.',
    sections: [{
      title: 'Type of Disability or Condition', icon: 'accessible',
      tip: 'Ask: “Has the child been identified as having a disability, developmental condition, or difficulty doing activities that other children of the same age can usually do?” More than one answer is allowed.',
      fields: [
        { id: 's3_types', area: 'Type', q: 'Disability or condition type', type: 'multiselect', ph: 'Select all that apply…', specify: ['Other'], specSuffix: '_other', specifyPh: 'Specify other type',
          options: ['Physical/Mobility', 'Visual', 'Hearing', 'Intellectual Disability', 'Communication/Speech', 'Cognitive/Intellectual', 'Learning/Developmental', 'Mental Disability (as reported or documented)', 'Physical disability, including orthopedic disability', 'Speech and language impairment', 'Psychosocial disability (as reported or documented)', 'Cancer', 'Rare disease', 'Autism/Neurodevelopmental', 'Psychosocial/Behavioral', 'Multiple Disabilities', 'Other'] }
      ]
    }]
  },
  // ---------------------------------------------------------------- STEP 3 · D2
  {
    no: 4, title: 'Child Care and Family Support', label: 'Domain 2 · Child Care and Family Support', heading: 'Child Care and Family Support',
    subtext: 'Choose the number that best matches the respondent’s answer.',
    sections: [
      { title: 'Adequacy of Family Caregiving Support', icon: 'volunteer_activism',
        tip: 'Together, my family and I have enough time to provide the help and care my child needs, which include the following:', fields: [
        L('s4_1_1', 'Family activities', 'Family activities with the child’s needed support or adaptations'),
        L('s4_1_2', 'Household tasks', 'Age-appropriate household tasks', { specify: RATED, specifyPh: 'Specify household tasks' }),
        L('s4_1_3', 'Family interaction', 'Interaction with family members'),
        L('s4_1_4', 'Other', 'Other (optional)', { specify: RATED, specifyPh: 'Specify other', optional: true })
      ] },
      { title: 'Dependency and Caregiver Assistance', icon: 'family_restroom', fields: [
        L('s4_2', 'Dependency on caregivers', 'Compared with children of the same age, my child needs more help from a parent or recognized caregiver to carry out everyday activities.'),
        L('s4_3', 'Adequacy of caregiver assistance', 'My child receives the help they need from a parent or recognized caregiver for everyday activities.')
      ] }
    ]
  },
  // ---------------------------------------------------------------- STEP 4 · D3
  {
    no: 5, title: 'Education and Participation', label: 'Domain 3 · Education and Participation', heading: 'Education and Participation',
    subtext: 'Ask how the child learns, takes part, and interacts with other children.',
    sections: [
      { title: 'Learning Arrangement', icon: 'school', fields: [
        { id: 's5_1', area: 'Learning arrangement', q: 'Where and how does the child currently learn?', type: 'select', ph: 'Select arrangement', options: ['School', 'Early learning', 'Home-based', 'Online-distance', 'Other', 'None'], specify: ['Other'], specSuffix: '_other' }
      ] },
      { title: 'Learning and Participation', icon: 'menu_book', fields: [
        L('s5_2', 'Suitability of learning arrangement', 'My child’s current learning arrangement meets his/her learning and support needs.'),
        L('s5_3', 'Accessibility barriers', 'My child’s learning environment provides the accessibility and support needed to participate in learning activities.'),
        L('s5_4', 'Peer interaction', 'When opportunities are available, my child can interact with other children, with the support or communication methods s/he needs.'),
        L('s5_5', 'Participation in community recreation', 'When opportunities are available, my child can participate in community play, sports, or recreational activities they enjoy, with the support or adaptations s/he needs.')
      ] }
    ]
  },
  // ---------------------------------------------------------------- STEP 5 · D4
  {
    no: 6, title: 'Health, Rehabilitation and Assistive Devices', label: 'Domain 4 · Health, Rehabilitation and Assistive Devices', heading: 'Health, Rehabilitation and Assistive Devices',
    subtext: 'Ask about health care, rehabilitation services, and assistive products.',
    sections: [{ title: 'Health, Rehabilitation and Assistive Devices', icon: 'local_hospital', fields: [
      L('s6_1', 'Access to needed health assessment', 'My child has received the health assessment or evaluation needed to understand her/his condition and support needs, without unnecessary delay.'),
      L('s6_2', 'Access to medical rehabilitation services', 'My child currently receives the medical and/or rehabilitation services s/he needs.'),
      L('s6_3', 'Accessibility of health or rehabilitation services', 'I can bring my child to the health or rehabilitation services s/he needs without difficulty.'),
      L('s6_4', 'Referral completion', 'I have been able to obtain the health or rehabilitation services to which my child was referred.'),
      L('s6_5', 'Availability of assistive devices', 'My child has the assistive products they need for everyday activities, such as glasses, hearing aids, mobility aids, or communication aids.')
    ] }]
  },
  // ---------------------------------------------------------------- STEP 6 · D5
  {
    no: 7, title: 'Caregiver Capacity and Support', label: 'Domain 5 · Caregiver Capacity and Support', heading: 'Caregiver Capacity and Support',
    subtext: 'Ask the caregiver about understanding the child, care skills, and coping.',
    sections: [
      { title: 'Caregiver’s Understanding of the Child', icon: 'psychology', fields: [
        L('s7_1a', 'Understanding the child’s condition', 'I understand my child’s condition (what it is and how it affects his/her everyday life).'),
        L('s7_1b', 'Understanding the child’s daily needs', 'I understand my child’s daily needs (such as food, rest, personal care, and safety).'),
        L('s7_1c', 'Understanding the child’s strengths', 'I understand my child’s strengths (his/her abilities and what s/he does well, with or without support).'),
        L('s7_1d', 'Understanding the child’s preferences', 'I understand my child’s preferences (what s/he likes, dislikes, enjoys, or chooses).'),
        L('s7_1e', 'Understanding the child’s communication', 'I understand how my child communicates (through words, sounds, gestures, signs, pictures, or other methods).'),
        L('s7_1f', 'Understanding the child’s support needs', 'I understand the support my child needs (assistance, equipment, or adaptations that help him/her participate in everyday activities).')
      ] },
      { title: 'Care Skills and Coping', icon: 'favorite', fields: [
        L('s7_2', 'Care skills', 'I can safely carry out the daily care tasks my child needs.'),
        L('s7_3', 'Home care', 'I regularly carry out the home-care activities recommended for my child.'),
        L('s7_4', 'Caregiving responsibilities', 'I can manage my caregiving responsibilities.'),
        L('s7_5', 'Coping', 'I can look after my own wellbeing while caring for my child.'),
        L('s7_6', 'Support', 'When I experience stress or difficulty, I can get counseling or other support if I need it.')
      ] },
      { title: 'Caregiver Dependency', icon: 'support', fields: [
        { id: 's7_6a', area: 'Caregiver dependency', q: 'Do you currently need additional help or support to look after your own well-being while caring for your child?', type: 'select', ph: 'Select answer', options: ['Yes', 'No', 'Not sure'], specify: ['Yes'], specifyPh: 'What help or support do you need?' }
      ] }
    ]
  },
  // ---------------------------------------------------------------- STEP 7 · D6
  {
    no: 8, title: 'Household Economic Situation', label: 'Domain 6 · Household Economic Situation', heading: 'Household Economic Situation',
    subtext: 'Ask about household income, disability-related costs, and affordability.',
    sections: [
      { title: 'Monthly Household Cash Income', icon: 'monetization_on', note: 'Enter 0 if the household received nothing from a source.',
        tip: 'During the past month, approximately how much money did your household receive from all sources combined? Mention each source and ask for the average amount received during the past month.', fields: [
        { id: 's8_1_earn', area: 'Earnings', q: 'Earnings and net business/livelihood income', type: 'money', half: true },
        { id: 's8_1_earn_note', area: 'Earnings', q: 'Notes/Explanations', type: 'text', ph: 'Notes (optional)', half: true, optional: true },
        { id: 's8_1_pension', area: 'Pensions and remittances', q: 'Pensions, remittances, and other financial support', type: 'money', half: true },
        { id: 's8_1_pension_note', area: 'Pensions and remittances', q: 'Notes/Explanations', type: 'text', ph: 'Notes (optional)', half: true, optional: true },
        { id: 's8_1_govt', area: 'Government assistance', q: 'Government cash grants or assistance', type: 'money', half: true },
        { id: 's8_1_govt_prog', area: 'Government assistance', q: 'Specify program', type: 'text', ph: 'e.g. 4Ps, AICS (optional)', half: true, optional: true },
        { id: 's8_1_govt_note', area: 'Government assistance', q: 'Notes/Explanations', type: 'text', ph: 'Notes (optional)', optional: true },
        { id: 's8_1_other', area: 'Other cash income', q: 'Other cash income', type: 'money', half: true },
        { id: 's8_1_other_spec', area: 'Other cash income', q: 'Specify other income', type: 'text', ph: 'Specify (optional)', half: true, optional: true },
        { id: 's8_1_other_note', area: 'Other cash income', q: 'Notes/Explanations', type: 'text', ph: 'Notes (optional)', optional: true },
        { id: 's8_1_total', area: 'Total household cash income', q: 'Total household cash income and assistance', type: 'computed', money: true, compute: incomeTotal }
      ] },
      { title: 'Disability-related Expenses', icon: 'receipt_long', note: 'Expense share (%) = disability-related expenses ÷ total household cash income × 100',
        tip: 'During the past month, approximately how much did your household pay out of pocket for your child’s disability-related care and support needs?', fields: [
        { id: 's8_2_amount', area: 'Disability-related expenses', q: 'Amount spent during the past month', type: 'money', half: true },
        { id: 's8_2_note', area: 'Disability-related expenses', q: 'Notes/Explanations', type: 'text', ph: 'What the money was spent on (optional)', half: true, optional: true },
        { id: 's8_2_share', area: 'Expense share', q: 'Expense share', type: 'computed', compute: expenseShare }
      ] },
      { title: 'Affordability', icon: 'account_balance_wallet', fields: [
        L('s8_3', 'Unmet needs due to cost', 'During the past six months, my household could afford the services my child needed.'),
        L('s8_4', 'Financial capacity for emergencies', 'If my child had an emergency, my household could afford the expenses needed to respond.'),
        L('s8_5', 'Affordability of health or rehabilitation services', 'I can afford the health or rehabilitation services my child needs.'),
        L('s8_6', 'Ability to sustain medical needs', 'My household can continue to afford my child’s prescribed medicines and recommended medical care for as long as needed.')
      ] }
    ]
  },
  // ---------------------------------------------------------------- STEP 8 · D7
  {
    no: 9, title: 'Social Protection and Entitlements', label: 'Domain 7 · Social Protection and Entitlements', heading: 'Social Protection and Entitlements',
    subtext: 'Use “Not applicable” when an item does not apply to this child. Verify eligibility and documents where feasible.',
    sections: [
      { title: 'Disability Support Allowance (DSA)', icon: 'payments', fields: [
        L('s9_1', 'DSA', 'I receive my child’s Disability Support Allowance according to the expected payment schedule.'),
        { id: 's9_1a', area: 'Use of DSA', q: 'If you received your child’s DSA, what did you use it for? (Check all that apply.)', type: 'multiselect', ph: 'Select all that apply…', optional: true, specify: ['Other'], specSuffix: '_other', specifyPh: 'Specify other use',
          options: ['Food or nutritional needs', 'Medicines', 'Medical consultations, tests, or treatment', 'Therapy or rehabilitation', 'Transportation', 'Assistive products, including repair or maintenance', 'Education or learning materials', 'Personal care or hygiene supplies', 'Paid caregiving assistance', 'Other', 'Not yet used'] },
        { id: 's9_1a_main', area: 'Main use of DSA', q: 'Which was the main use?', type: 'dynselect', from: 's9_1a', exclude: ['Not yet used'], ph: 'Select main use',
          showIf: a => (a['s9_1a'] || []).some(v => v !== 'Not yet used') }
      ] },
      { title: 'Health Protection and Referrals', icon: 'health_and_safety', fields: [
        L('s9_3', 'Health protection', 'I can access PhilHealth benefits for my child when needed.'),
        L('s9_4', 'Access to referred programs and services', 'Through the referrals made for my child, I have been able to obtain the assistance or services s/he needs, such as:'),
        L('s9_4_1', 'Referred: financial assistance', 'Financial assistance', { sub: true }),
        L('s9_4_2', 'Referred: education support', 'Education support', { sub: true }),
        L('s9_4_3', 'Referred: health care', 'Health care', { sub: true }),
        L('s9_4_4', 'Referred: rehabilitation', 'Rehabilitation', { sub: true }),
        L('s9_4_5', 'Referred: assistive products', 'Assistive products', { sub: true }),
        L('s9_4_6', 'Referred: other', 'Other (optional)', { sub: true, optional: true, specify: RATED, specifyPh: 'Specify other service' }),
        L('s9_5', 'Receipt of DSA', 'My household has received the Disability Support Allowance intended for my child.')
      ] },
      { title: 'Awareness of Rights', icon: 'gavel', tip: 'I understand my child’s rights, specifically:', fields: [
        L('s9_6a_1', 'Rights: access to education', 'Access to education'),
        L('s9_6a_2', 'Rights: access to health care', 'Access to health care'),
        L('s9_6a_3', 'Rights: participation', 'Participation'),
        L('s9_6a_4', 'Rights: protection from abuse and exploitation', 'Protection from abuse and exploitation'),
        L('s9_6a_5', 'Rights: protection from discrimination', 'Protection from discrimination'),
        L('s9_6a_6', 'Rights: other', 'Other (optional)', { optional: true, specify: RATED, specifyPh: 'Specify other right' })
      ] },
      { title: 'Awareness of Benefits', icon: 'redeem', tip: 'I understand the benefits my child may be eligible for, specifically:', fields: [
        L('s9_6b_1', 'Benefits: financial assistance', 'Financial assistance'),
        L('s9_6b_2', 'Benefits: discounts', 'Discounts'),
        L('s9_6b_3', 'Benefits: other', 'Other (optional)', { optional: true, specify: RATED, specifyPh: 'Specify other benefit' })
      ] },
      { title: 'Awareness of Available Services', icon: 'storefront', fields: [
        L('s9_6c', 'Awareness of available services', 'I know what services are available to support my child and where to access them, such as:'),
        L('s9_6c_1', 'Services: health care', 'Health care', { sub: true }),
        L('s9_6c_2', 'Services: rehabilitation', 'Rehabilitation', { sub: true }),
        L('s9_6c_3', 'Services: education', 'Education', { sub: true }),
        L('s9_6c_4', 'Services: social welfare', 'Social welfare services', { sub: true, specify: RATED, specifyPh: 'Specify social welfare services' })
      ] }
    ]
  },
  // ---------------------------------------------------------------- STEP 9 · D8
  {
    no: 10, title: 'Environmental Barriers', label: 'Domain 8 · Environmental Barriers', heading: 'Environmental Barriers',
    subtext: 'Ask about barriers at home, in travel, and in the community.',
    sections: [
      { title: 'Home and Travel', icon: 'directions_bus', fields: [
        L('s10_1', 'Child-friendly home environment', 'Our home is friendly to my child’s needs (it allows my child to move safely and carry out daily activities, with support or adaptations when needed).'),
        L('s10_2', 'Transportation', 'I can arrange safe and suitable transportation for my child to reach the services they need without difficulty.'),
        { id: 's10_2a', area: 'Travel time', q: 'How long does the journey usually take, one way, to reach the services?', type: 'select', ph: 'Select travel time', options: ['15 minutes or less', '16–30 minutes', '31–40 minutes', '41–50 minutes', '51–60 minutes', 'Other'], specify: ['Other'], specSuffix: '_other' }
      ] },
      { title: 'Attitudes and Discrimination', icon: 'diversity_3', fields: [
        L('s10_3', 'Negative attitudes', 'My child can participate in everyday activities without being excluded or treated unfairly because of her/his disability (such as being teased, judged, or avoided by others).'),
        L('s10_5', 'Stigma/discrimination', 'My child and our family are treated with respect and fairness, without discrimination because of my child’s disability (such as exclusion, ridicule, or other negative treatment).')
      ] }
    ]
  },
  // ---------------------------------------------------------------- STEP 10 · D9
  {
    no: 11, title: 'Priority Needs and Referral', label: 'Domain 9 · Priority Needs and Referral', heading: 'Priority Needs and Referral',
    subtext: 'Separate trigger domain, not part of the score. If an urgent concern is found, follow the approved referral procedure immediately.',
    sections: [
      { title: 'Urgent Needs', icon: 'priority_high', fields: URGENT.flatMap(([k, area, q, kind]) => [
        L('s11_1' + k, area, q, k === 'f' ? { optional: true, specify: RATED, specifyPh: 'Specify the need and support received' } : {}),
        { id: `s11_1${k}_met`, area: `${area}: need met?`, q: `Is your child currently receiving enough support to meet this urgent ${kind} need?`, type: 'yn', sub: true,
          showIf: k === 'f' ? a => RATED.includes(a['s11_1f']) : undefined }
      ]) },
      { title: 'Social Protection and Follow-up', icon: 'assignment_turned_in', fields: [
        L('s11_2', 'Awareness of social protection programs', 'I know which social protection programs may meet my child’s needs and where to apply for assistance, including financial aid, health benefits, and other support.'),
        L('s11_3', 'Need for referral or follow-up', 'My child receives the referrals and follow-up visits needed to address his/her care and support needs.')
      ] }
    ]
  },
  // ---------------------------------------------------------------- STEP 11 · N + Q
  {
    no: 12, title: 'Summary', label: 'Summary', heading: 'Summary',
    subtext: 'To be completed by the interviewer after the interview.', last: true,
    sections: [
      { title: 'Case Flags', icon: 'flag', fields: [
        ['functional', 'Functional difficulty identified'], ['priority', 'Priority support need'], ['unmet', 'Unmet service need'],
        ['environment', 'Environmental barrier'], ['caregiver', 'Caregiver support concern'], ['economic', 'Economic barrier'],
        ['social', 'Social protection concern'], ['urgent', 'Urgent referral trigger']
      ].map(([k, label]) => ({ id: 's12_' + k, area: 'Concern', q: label, type: 'yn', order: ['No', 'Yes'], notes: true })) }
    ]
  }
];

const TOTAL = STEPS.length;          // 11
const REVIEW = TOTAL + 1;            // 12

// ---------------------------------------------------------------------------
// Computed answers (Domain 6)
// ---------------------------------------------------------------------------
const INCOME_KEYS = ['s8_1_earn', 's8_1_pension', 's8_1_govt', 's8_1_other'];
const num = v => (v === '' || v == null || isNaN(Number(v)) ? null : Number(v));

function incomeTotal(a) {
  const parts = INCOME_KEYS.map(k => num(a[k])).filter(v => v !== null);
  return parts.length ? String(Math.round(parts.reduce((s, v) => s + v, 0) * 100) / 100) : '';
}

function expenseShare(a) {
  const total = num(incomeTotal(a));
  const spent = num(a['s8_2_amount']);
  if (spent === null || !total) return 'Cannot calculate—income is zero or information is missing';
  const pct = spent / total * 100;
  if (pct === 0) return '0%—no out-of-pocket expenses reported';
  if (pct <= 10) return 'More than 0% to 10%';
  if (pct <= 25) return 'More than 10% to 25%';
  if (pct <= 50) return 'More than 25% to 50%';
  if (pct <= 100) return 'More than 50% to 100%';
  return 'More than 100%';
}

// ---------------------------------------------------------------------------
// Date of birth → age in whole years (null if not a valid past date)
// ---------------------------------------------------------------------------
function ageFromDob(s) {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
  if (!m) return null;
  const dob = new Date(+m[1], +m[2] - 1, +m[3]);
  if (dob.getMonth() !== +m[2] - 1) return null;
  const today = new Date();
  if (dob > today) return null;
  let age = today.getFullYear() - dob.getFullYear();
  if (today.getMonth() < dob.getMonth() || (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) age--;
  return age;
}

const isoDate = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

// ============================================================================
// RENDERING — markup mirrors the v2 app
// ============================================================================

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const INPUT = 'w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400';
const req = f => f.optional || f.type === 'computed' ? '' : ' <span class="text-red-500">*</span>';
const specId = f => f.id + (f.specSuffix || '_spec');

function specifyInput(f) {
  return `<input id="${specId(f)}" type="text" class="mt-2 ${INPUT} hidden" placeholder="${esc(f.specifyPh || 'Please specify')}">`;
}

// Custom-styled single-select dropdown with a thin-scrollbar option list.
// A real <select> (visually hidden, but focusable/tabbable) stays the source
// of truth so valueOf()/fillSample()/validateStep() need no special cases.
// `onchange` runs after the label sync (the address cascade uses it).
function customSelect(id, options, placeholder, onchange) {
  return `<div class="relative" data-dropdown="${id}">
    <select id="${id}" class="sr-only" required
      onchange="syncDropdownFromSelect('${id}')${onchange ? `; ${onchange}` : ''}">
      <option value="" disabled selected>${esc(placeholder)}</option>${options.map(v => `<option>${esc(v)}</option>`).join('')}
    </select>
    <button type="button" onclick="toggleDropdown('${id}')" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm text-left flex items-center" id="${id}_trigger" ${options.length ? '' : 'disabled'}>
      <span id="${id}_trigger_label" class="text-gray-400 truncate" data-ph="${esc(placeholder)}">${esc(placeholder)}</span>
    </button>
    <span class="material-symbols-outlined pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[18px] text-gray-400">expand_more</span>
    <div id="${id}_panel" class="hidden absolute z-20 mt-1 w-full max-h-64 overflow-y-auto thin-scroll bg-white border border-gray-200 rounded-lg shadow-lg py-1">${dropdownOptions(id, options)}
    </div>
  </div>`;
}

function dropdownOptions(id, options) {
  return options.map(v => `
      <button type="button" class="dropdown-option w-full text-left px-3 py-2 text-xs sm:text-sm text-gray-700 hover:bg-blue-50" onclick="selectDropdownOption('${id}', this.textContent)">${esc(v)}</button>`).join('');
}

// Small grey label above the question; skipped when it would repeat the question.
function areaLabel(f) {
  return f.area && f.area.toLowerCase() !== f.q.toLowerCase() ?`<p class="text-[10px] uppercase tracking-wider font-bold text-gray-400">${esc(f.area)}</p>` : '';
}

function questionHead(f) {
  return `
    <div class="mb-2">
      ${areaLabel(f)}
      <p class="font-bold text-brand-dark text-xs sm:text-sm leading-snug">${esc(f.q)}${req(f)}</p>
    </div>`;
}

function likertCard(id, v, label) {
  return `<label class="radio-card likert-card"><input type="radio" name="${id}" value="${v}" class="sr-only"><span class="font-bold likert-num">${esc(v.length === 1 ? v : label)}</span>${v.length === 1 ? `<span class="likert-txt">${esc(label)}</span>` : ''}</label>`;
}

function renderField(f) {
  let body = '';
  switch (f.type) {
    case 'yn': {
      const opts = f.order || ['Yes', 'No'];
      const toggle = `
        <div class="slide-toggle-container h-8 w-52">
          <div class="slide-toggle-slider" style="opacity:0"></div>
          ${opts.map(v => `<label class="slide-toggle-label text-gray-500" onclick="toggleBtn(this)"><input type="radio" name="${f.id}" value="${v}" class="hidden">${v}</label>`).join('')}
        </div>`;
      const notesBtn = f.notes ? `
          <button type="button" id="${f.id}_note_btn" onclick="toggleNotes('${f.id}')" class="mt-2 text-[11px] sm:text-xs font-bold text-brand-blue hover:text-brand-blueHover flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">add</span>Add notes
          </button>` : '';
      const head = `
          <div class="flex-1 min-w-0">
            ${areaLabel(f)}
            <p class="font-bold text-brand-dark text-xs sm:text-sm leading-snug">${esc(f.q)}${req(f)}</p>
            ${notesBtn}
          </div>`;
      return `<div class="q-block ${f.half ? '' : 'sm:col-span-2'} ${f.sub ? 'q-sub' : ''}" data-q="${f.id}">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">${head}${toggle}</div>
        <div>
          ${f.specify ? specifyInput(f) : ''}
          ${f.notes ? `<input id="${f.id}_note" type="text" class="mt-2 hidden ${INPUT}" placeholder="Notes / action (optional)">` : ''}
        </div>
      </div>`;
    }

    case 'likert':
      body = `<div class="likert-row">${LIKERT.map(([v, l]) => likertCard(f.id, v, l)).join('')}</div>
        <div class="likert-alt">${LIKERT_OTHER.map(([v, l]) => likertCard(f.id, v, l)).join('')}</div>
        ${f.specify ? specifyInput(f) : ''}`;
      return `<div class="q-block sm:col-span-2 ${f.sub ? 'q-sub' : ''}" data-q="${f.id}">${questionHead(f)}<div>${body}</div></div>`;

    case 'choice':
      body = `<div class="pill-group flex flex-wrap gap-2">${f.options.map(v => `
        <label class="radio-card relative border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:border-blue-300 transition-all select-none flex items-center bg-white">
          <input type="radio" name="${f.id}" value="${esc(v)}" class="peer sr-only">
          <span class="font-bold text-gray-700 text-[11px] sm:text-xs leading-tight">${esc(v)}</span>
        </label>`).join('')}</div>${f.specify ? specifyInput(f) : ''}`;
      break;

    case 'select':
      body = `${customSelect(f.id, f.options, f.ph || 'Select')}${f.specify ? specifyInput(f) : ''}`;
      break;

    case 'dynselect':
      body = customSelect(f.id, [], f.ph || 'Select');
      break;

    case 'checks':
      body = `<div class="pill-group grid grid-cols-1 ${f.cols === 1 ? '' : 'sm:grid-cols-2'} gap-2">${f.options.map(v => `
        <label class="radio-card relative border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:border-blue-300 transition-all select-none flex items-center gap-3 bg-white">
          <input type="checkbox" name="${f.id}" value="${esc(v)}" class="w-4 h-4 accent-[#1152d4] shrink-0">
          <span class="font-bold text-gray-700 text-[11px] sm:text-xs leading-tight">${esc(v)}</span>
        </label>`).join('')}</div>${f.specify ? specifyInput(f) : ''}`;
      break;

    case 'multiselect':
      body = `<div class="relative" data-multiselect="${f.id}">
        <button type="button" onclick="toggleMultiselect('${f.id}')" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm text-left flex items-center" id="${f.id}_trigger">
          <span id="${f.id}_trigger_label" class="text-gray-400 truncate" data-ph="${esc(f.ph || 'Select options…')}">${esc(f.ph || 'Select options…')}</span>
        </button>
        <span class="material-symbols-outlined pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[18px] text-gray-400">expand_more</span>
        <div id="${f.id}_panel" class="hidden absolute z-20 mt-1 w-full max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1">${f.options.map(v => `
          <label class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-blue-50 select-none">
            <input type="checkbox" name="${f.id}" value="${esc(v)}" onchange="updateMultiselectLabel('${f.id}')" class="w-4 h-4 accent-[#1152d4] shrink-0">
            <span class="text-xs sm:text-sm text-gray-700">${esc(v)}</span>
          </label>`).join('')}
        </div>
      </div>${f.specify ? specifyInput(f) : ''}`;
      break;

    case 'text':
      body = `<div class="relative">
        ${f.icon ? `<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="material-symbols-outlined text-[16px] text-gray-400">${f.icon}</span></div>` : ''}
        <input id="${f.id}" type="text" ${f.digits ? `inputmode="numeric" maxlength="30" oninput="this.value=this.value.replace(/[^0-9 -]/g,'')"` : ''} class="${INPUT} ${f.icon ? 'pl-10' : ''}" placeholder="${esc(f.ph || '')}">
      </div>`;
      break;

    case 'date': {
      const today = new Date();
      const oldest = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate() + 1);
      body = `<div class="flex items-center gap-3 w-full">
        <input id="${f.id}" type="date" min="${isoDate(oldest)}" max="${isoDate(today)}" class="${INPUT.replace('w-full ', '')} w-44">
        <span id="${f.id}_age_label" class="text-xs text-gray-500 font-medium"></span>
      </div>`;
      break;
    }

    case 'money':
      body = `<div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold pointer-events-none text-xs">₱</span>
          <input id="${f.id}" type="text" inputmode="decimal" oninput="this.value=this.value.replace(/[^0-9.]/g,'')" class="${INPUT} pl-6 text-right" placeholder="0">
        </div>`;
      break;

    case 'computed':
      body = `<div class="relative">
          ${f.money ? '<span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold pointer-events-none text-xs">₱</span>' : ''}
          <input id="${f.id}" type="text" readonly tabindex="-1" class="${INPUT} ${f.money ? 'pl-6 text-right' : ''} bg-gray-50 font-bold text-brand-dark" placeholder="Calculated automatically">
        </div>`;
      break;

    case 'textarea':
      body = `<textarea id="${f.id}" class="w-full p-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none h-20 resize-none placeholder-gray-400" placeholder="${esc(f.ph || '')}"></textarea>`;
      break;

    case 'address':
      body = `<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div><label class="block text-[10px] font-bold text-gray-500 mb-1">Region <span class="text-red-500">*</span></label>${customSelect(`${f.id}_region`, [], 'Select region', `addressChanged('${f.id}', 'region')`)}</div>
        <div><label class="block text-[10px] font-bold text-gray-500 mb-1">Province <span class="text-red-500">*</span></label>${customSelect(`${f.id}_province`, [], 'Select province', `addressChanged('${f.id}', 'province')`)}</div>
        <div><label class="block text-[10px] font-bold text-gray-500 mb-1">City/Municipality <span class="text-red-500">*</span></label>${customSelect(`${f.id}_city`, [], 'Select city/municipality', `addressChanged('${f.id}', 'city')`)}</div>
        <div><label class="block text-[10px] font-bold text-gray-500 mb-1">Barangay <span class="text-red-500">*</span></label>${customSelect(`${f.id}_barangay`, [], 'Select barangay', `addressChanged('${f.id}', 'barangay')`)}</div>
        <div class="sm:col-span-2"><label class="block text-[10px] font-bold text-gray-500 mb-1">Street Address</label><input id="${f.id}_street" type="text" class="${INPUT}" placeholder="House no., street, purok/sitio (optional)"></div>
      </div>`;
      break;
  }
  return `<div class="q-block ${f.half ? '' : 'sm:col-span-2'} ${f.sub ? 'q-sub' : ''}" data-q="${f.id}">${questionHead(f)}<div>${body}</div></div>`;
}

function renderSection(s, si, stepNo) {
  return `
    <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6" data-section="${stepNo}-${si}">

      <div class="mb-4 flex items-center gap-3 border-b border-gray-100 pb-3">
        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-[18px] text-brand-blue">${s.icon}</span>
        </div>
        <div>
          <h3 class="font-bold text-brand-dark text-base flex items-center gap-1.5">${esc(s.title)}${s.tip ? `
            <span class="section-tip relative inline-flex">
              <button type="button" class="w-6 h-6 rounded-full flex items-center justify-center text-gray-400 hover:text-brand-blue hover:bg-blue-50 focus:text-brand-blue focus:bg-blue-50 outline-none transition-colors" aria-label="Interviewer tip">
                <span class="material-symbols-outlined text-[18px]">help</span>
              </button>
              <span class="tip-bubble" role="tooltip">${esc(s.tip)}</span>
            </span>` : ''}</h3>
          ${s.note ? `<p class="text-gray-500 text-[11px] sm:text-xs">${esc(s.note)}</p>` : ''}
        </div>
      </div>
      ${s.intro ? `<div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3 sm:p-4"><p class="text-xs sm:text-sm text-brand-dark italic leading-relaxed">${esc(s.intro)}</p></div>` : ''}
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-6">${s.fields.map(renderField).join('')}</div>
    </section>`;
}

function renderStep(step, n) {
  const back = `<button onclick="goToStep(${n - 1})" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all"><span class="material-symbols-outlined text-[16px]">arrow_back</span> Back</button>`;
  const next = step.last
    ? `<button onclick="if(validateStep(${n})) generateReview()" class="px-6 h-10 bg-green-600 rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-green-700 flex items-center gap-2 shadow-md transition-all"><span class="material-symbols-outlined text-[16px]">check</span> Review Profile</button>`
    : `<button onclick="if(validateStep(${n})) goToStep(${n + 1})" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover shadow-md transition-all flex items-center justify-center gap-2">
        Next: ${esc(STEPS[n].title)}
        <span class="w-6 h-6 rounded-md bg-white/20 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[16px]">arrow_forward</span></span>
      </button>`;
  return `
    <div id="step-${n}" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">${esc(step.heading)}</h1>
        <p class="text-gray-500 text-xs sm:text-sm">${esc(step.subtext)}</p>
      </div>
      ${step.sections.map((s, si) => renderSection(s, si, n)).join('')}
      <div class="w-full flex justify-between gap-3 pb-6">${back}${next}</div>
    </div>`;
}

function renderReviewShell() {
  return `
    <div id="step-${REVIEW}" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">Review Profile</h1>
        <p class="text-gray-500 text-xs sm:text-sm">Please review all information before submitting.</p>
      </div>
      <div id="review-content" class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-6"></div>
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(${TOTAL})" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">Edit Information</button>
        <button id="btn-submit-profile" onclick="submitProfile()" class="px-6 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">Submit Profile</button>
      </div>
    </div>`;
}

// ============================================================================
// ADDRESS (Region → Province → City/Municipality → Barangay)
// ============================================================================

const ADDRESS_LEVELS = ['region', 'province', 'city', 'barangay'];

async function loadLocations() {
  try {
    const all = await (await fetch('data/locations.json')).json();
    locationData = Object.fromEntries(V3_REGIONS.filter(r => all[r]).map(r => [r, all[r]]));
  } catch (e) {
    locationData = {};
  }
  if (!Object.keys(locationData).length) {
    window.toast.error('The address list could not be loaded. Close and reopen the app.', 'Address');
  }
}

function addressOptions(id, level) {
  const pick = k => document.getElementById(`${id}_${k}`).value;
  if (level === 'region') return Object.keys(locationData);
  const r = locationData[pick('region')] || {};
  if (level === 'province') return Object.keys(r);
  const p = r[pick('province')] || {};
  if (level === 'city') return Object.keys(p);
  return p[pick('city')] || [];
}

// Refills every level below `changed`. A level with a single choice is picked
// automatically (e.g. the only region, province or municipality).
function addressChanged(id, changed) {
  const start = changed === null ? 0 : ADDRESS_LEVELS.indexOf(changed) + 1;
  for (let i = start; i < ADDRESS_LEVELS.length; i++) {
    const level = ADDRESS_LEVELS[i];
    const selId = `${id}_${level}`;
    const sel = document.getElementById(selId);
    const opts = addressOptions(id, level);
    const ph = sel.options[0].textContent;
    sel.innerHTML = `<option value="" disabled selected>${esc(ph)}</option>` + opts.map(o => `<option>${esc(o)}</option>`).join('');
    document.getElementById(selId + '_panel').innerHTML = dropdownOptions(selId, opts);
    document.getElementById(selId + '_trigger').disabled = opts.length === 0;
    if (opts.length === 1) sel.value = opts[0];
    syncDropdownFromSelect(selId);
  }
  refresh();
}

// ============================================================================
// STATE, SKIP LOGIC, VALIDATION
// ============================================================================

const ALL_FIELDS = [];
STEPS.forEach((s, i) => s.sections.forEach(sec => sec.fields.forEach(f => ALL_FIELDS.push({ f, sec, step: i + 1 }))));

function valueOf(f) {
  if (['yn', 'choice', 'likert'].includes(f.type)) return document.querySelector(`input[name="${f.id}"]:checked`)?.value || '';
  if (f.type === 'checks' || f.type === 'multiselect') return [...document.querySelectorAll(`input[name="${f.id}"]:checked`)].map(i => i.value);
  if (f.type === 'address') return [...ADDRESS_LEVELS, 'street'].map(k => document.getElementById(`${f.id}_${k}`).value.trim());
  return document.getElementById(f.id)?.value.trim() || '';
}

function answers() {
  const a = {};
  ALL_FIELDS.forEach(({ f }) => { a[f.id] = valueOf(f); });
  return a;
}

function needsSpecify(f, v) {
  if (!f.specify) return false;
  return Array.isArray(v) ? v.some(x => f.specify.includes(x)) : f.specify.includes(v);
}

function isVisible({ f, sec }, a) {
  if (sec.showIf && !sec.showIf(a)) return false;
  if (f.showIf && !f.showIf(a)) return false;
  return true;
}

function refreshDynSelect(f, a) {
  const sel = document.getElementById(f.id);
  const opts = (a[f.from] || []).filter(v => !(f.exclude || []).includes(v));
  const current = sel.value;
  const key = opts.join('\n');
  if (sel.dataset.opts !== key) {
    sel.dataset.opts = key;
    sel.innerHTML = `<option value="" disabled selected>${esc(f.ph || 'Select')}</option>` + opts.map(o => `<option>${esc(o)}</option>`).join('');
    if (opts.includes(current)) sel.value = current;
    document.getElementById(f.id + '_panel').innerHTML = dropdownOptions(f.id, opts);
    updateDropdownLabel(f.id);
  }
  document.getElementById(f.id + '_trigger').disabled = opts.length === 0;
}

function refresh() {
  let a = answers();
  ALL_FIELDS.forEach(({ f }) => {
    if (f.type === 'computed') document.getElementById(f.id).value = f.compute(a);
    if (f.type === 'dynselect') refreshDynSelect(f, a);
    if (f.type === 'date') {
      const age = ageFromDob(a[f.id]);
      document.getElementById(f.id + '_age_label').textContent = age === null ? '' : `Age: ${age} year${age === 1 ? '' : 's'}`;
    }
  });
  a = answers();
  ALL_FIELDS.forEach(entry => {
    const { f } = entry;
    const block = document.querySelector(`[data-q="${f.id}"]`);
    block.classList.toggle('hidden', !isVisible(entry, a));
    const spec = document.getElementById(specId(f));
    if (spec && f.specify) spec.classList.toggle('hidden', !needsSpecify(f, a[f.id]));
  });
  STEPS.forEach((s, i) => s.sections.forEach((sec, si) => {
    const el = document.querySelector(`[data-section="${i + 1}-${si}"]`);
    if (el) el.classList.toggle('hidden', !!sec.showIf && !sec.showIf(a));
  }));
}

function clearErrors() {
  document.querySelectorAll('.field-error').forEach(e => e.remove());
  document.querySelectorAll('.input-invalid').forEach(e => e.classList.remove('input-invalid'));
  document.querySelectorAll('.q-block.has-error').forEach(e => e.classList.remove('has-error'));
}

function flag(f, msg, inputEl) {
  const block = document.querySelector(`[data-q="${f.id}"]`);
  block.classList.add('has-error');
  if (inputEl) inputEl.classList.add('input-invalid');
  const p = document.createElement('p');
  p.className = 'field-error';
  p.textContent = msg;
  block.appendChild(p);
}

function validateStep(n) {
  clearErrors();
  const a = answers();
  let ok = true;
  ALL_FIELDS.filter(e => e.step === n && isVisible(e, a)).forEach(({ f }) => {
    const v = a[f.id];
    if (f.type === 'computed') return;
    if (f.type === 'address') {
      ADDRESS_LEVELS.forEach((k, i) => {
        if (!v[i]) { ok = false; document.getElementById(`${f.id}_${k}_trigger`).classList.add('input-invalid'); }
      });
      if (ADDRESS_LEVELS.some((k, i) => !v[i])) flag(f, 'Please complete the region, province, city/municipality and barangay.');
      return;
    }
    if (f.type === 'checks' && f.allRequired && v.length < f.options.length) { ok = false; flag(f, 'Please confirm all items.'); return; }
    const empty = Array.isArray(v) ? v.length === 0 : !v;
    if (empty) {
      if (f.optional) return;
      ok = false;
      const input = ['select', 'dynselect'].includes(f.type) ? document.getElementById(f.id + '_trigger')
        : ['text', 'money', 'textarea', 'date'].includes(f.type) ? document.getElementById(f.id) : null;
      flag(f, f.type === 'checks' || f.type === 'multiselect' ? 'Please select at least one.' : 'This question is required.', input);
      return;
    }
    if (f.type === 'date') {
      const age = ageFromDob(v);
      if (age === null || age > 17) { ok = false; flag(f, 'Enter a valid date of birth. The child must be under 18.', document.getElementById(f.id)); return; }
    }
    if (f.type === 'money' && isNaN(Number(v))) { ok = false; flag(f, 'Enter an amount, e.g. 1500 or 1500.50.', document.getElementById(f.id)); return; }
    if (needsSpecify(f, v)) {
      const spec = document.getElementById(specId(f));
      if (!spec.value.trim()) { ok = false; flag(f, 'Please specify.', spec); }
    }
  });
  if (!ok) document.querySelector('.field-error')?.closest('.q-block')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  return ok;
}

// ============================================================================
// NAVIGATION, TOGGLES, REVIEW
// ============================================================================

let currentStep = 1;

function updateProgress(n) {
  document.getElementById('progress-bar').style.width = (n / TOTAL * 100) + '%';
  document.getElementById('step-indicator').innerText = `STEP ${n} OF ${TOTAL}`;
  document.getElementById('step-label').innerText = STEPS[n - 1].label;
}

function goToStep(n) {
  // Back from Step 1 leaves the form, which ends the session (the login page is separate).
  if (n === 0) { showLogoutModal(); return; }
  clearErrors();
  document.querySelectorAll('.step-section').forEach(el => { el.classList.add('hidden-step'); el.style.opacity = 0; });
  const target = document.getElementById('step-' + n);
  target.classList.remove('hidden-step');
  setTimeout(() => target.style.opacity = 1, 50);
  window.scrollTo({ top: 0, behavior: 'smooth' });
  const prog = document.getElementById('progress-section');
  if (n === REVIEW) prog.style.display = 'none';
  else { prog.style.display = 'block'; updateProgress(n); }
  currentStep = n;
  refresh();
}

function toggleNotes(id) {
  const btn = document.getElementById(id + '_note_btn');
  const input = document.getElementById(id + '_note');
  const adding = input.classList.contains('hidden');
  input.classList.toggle('hidden', !adding);
  if (adding) {
    btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">close</span>Remove notes';
    btn.classList.remove('text-brand-blue', 'hover:text-brand-blueHover');
    btn.classList.add('text-red-600', 'hover:text-red-700');
    input.focus();
  } else {
    input.value = '';
    btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">add</span>Add notes';
    btn.classList.remove('text-red-600', 'hover:text-red-700');
    btn.classList.add('text-brand-blue', 'hover:text-brand-blueHover');
    refresh();
  }
}

function toggleBtn(label) {
  const container = label.closest('.slide-toggle-container');
  const slider = container.querySelector('.slide-toggle-slider');
  const labels = [...container.querySelectorAll('.slide-toggle-label')];
  slider.style.transform = `translateX(${labels.indexOf(label) * 100}%)`;
  slider.style.opacity = 1;
  labels.forEach(l => { l.classList.remove('text-white'); l.classList.add('text-gray-500'); });
  label.classList.remove('text-gray-500');
  label.classList.add('text-white');
  label.querySelector('input').checked = true;
  refresh();
}

function toggleMultiselect(id) {
  const panel = document.getElementById(id + '_panel');
  const opening = panel.classList.contains('hidden');
  document.querySelectorAll('[id$="_panel"]').forEach(p => p.classList.add('hidden'));
  if (opening) panel.classList.remove('hidden');
}

function toggleDropdown(id) {
  const panel = document.getElementById(id + '_panel');
  const opening = panel.classList.contains('hidden');
  document.querySelectorAll('[id$="_panel"]').forEach(p => p.classList.add('hidden'));
  if (opening) {
    panel.classList.remove('hidden');
    const current = document.getElementById(id).value;
    panel.querySelectorAll('.dropdown-option').forEach(btn => btn.classList.toggle('is-selected', btn.textContent === current));
  }
}

function selectDropdownOption(id, value) {
  const select = document.getElementById(id);
  select.value = value;
  select.dispatchEvent(new Event('change'));
  document.getElementById(id + '_panel').classList.add('hidden');
}

// Keeps the visible trigger label/panel in sync whenever the hidden <select>'s
// value changes directly (fillSample(), programmatic set(), etc.), not just via click.
function syncDropdownFromSelect(id) {
  updateDropdownLabel(id);
  refresh();
}

// Label only, no refresh(): refreshDynSelect() runs inside refresh().
function updateDropdownLabel(id) {
  const value = document.getElementById(id).value;
  const label = document.getElementById(id + '_trigger_label');
  if (label) {
    label.textContent = value || label.dataset.ph;
    label.classList.toggle('text-gray-400', !value);
    label.classList.toggle('text-gray-800', !!value);
  }
}

function updateMultiselectLabel(id) {
  const values = [...document.querySelectorAll(`input[name="${id}"]:checked`)].map(i => i.value);
  const label = document.getElementById(id + '_trigger_label');
  label.textContent = values.length ? values.join(', ') : label.dataset.ph;
  label.classList.toggle('text-gray-400', values.length === 0);
  label.classList.toggle('text-gray-800', values.length > 0);
  refresh();
}

document.addEventListener('click', e => {
  document.querySelectorAll('[data-multiselect]').forEach(wrap => {
    if (!wrap.contains(e.target)) document.getElementById(wrap.dataset.multiselect + '_panel')?.classList.add('hidden');
  });
  document.querySelectorAll('[data-dropdown]').forEach(wrap => {
    if (!wrap.contains(e.target)) document.getElementById(wrap.dataset.dropdown + '_panel')?.classList.add('hidden');
  });
});

function display(f, v) {
  if (f.type === 'address') v = [v[4], v[3], v[2], v[1], v[0]].filter(Boolean).join(', ');
  else if (Array.isArray(v)) v = v.join(', ');
  if (f.emptyValue && v === f.emptyValue) v = '';
  if (!v) return '<span class="text-gray-400">—</span>';
  let out = esc(v);
  if (f.type === 'likert') out = esc(RATED.includes(v) ? `${v} · ${LIKERT_LABEL[v]}` : LIKERT_LABEL[v]);
  if (f.type === 'money' || (f.type === 'computed' && f.money)) out = '₱ ' + Number(v).toLocaleString('en-PH');
  if (f.type === 'date') {
    const age = ageFromDob(v);
    if (age !== null) out += ` <span class="text-gray-500 font-medium">(${age} year${age === 1 ? '' : 's'} old)</span>`;
  }
  const spec = document.getElementById(specId(f));
  if (f.specify && spec && !spec.classList.contains('hidden') && spec.value.trim()) out += ` <span class="text-gray-500 font-medium">(${esc(spec.value.trim())})</span>`;
  const notes = document.getElementById(f.id + '_note');
  if (notes && notes.value.trim()) out += ` <span class="text-gray-500 font-medium">— ${esc(notes.value.trim())}</span>`;
  return out;
}

function generateReview() {
  const a = answers();
  let html = '';
  STEPS.forEach((step, i) => {
    const items = ALL_FIELDS.filter(e => e.step === i + 1 && isVisible(e, a));
    if (!items.length) return;
    const icon = step.sections[0].icon;
    html += `<h3 class="review-section-header"><span class="material-symbols-outlined">${icon}</span>${i + 1}. ${esc(step.heading)}
      <button onclick="goToStep(${i + 1})" class="ml-auto text-[11px] font-bold text-gray-500 hover:text-brand-blue flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">edit</span>Edit</button></h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6">${items.map(({ f }) => `
        <div class="review-group"><span class="review-label">${esc(f.type === 'yn' && f.notes ? f.q : f.area)}</span><div class="review-value">${display(f, a[f.id])}</div></div>`).join('')}
      </div>`;
  });
  document.getElementById('review-content').innerHTML = html;
  goToStep(REVIEW);
}

// ============================================================================
// SAMPLE ANSWERS (developer use only; button shown when SHOW_SAMPLE_FILL is on)
// ============================================================================

function fillSample() {
  const pick = arr => arr[Math.floor(Math.random() * arr.length)];
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
  set('s2_1_first', 'Juan'); set('s2_1_middle', 'Santos'); set('s2_1_last', 'Dela Cruz');
  set('s2_3', 'Jun'); set('s2_8', 'Maria Dela Cruz');
  const dob = new Date(); dob.setFullYear(dob.getFullYear() - pick([4, 8, 12, 15])); dob.setDate(dob.getDate() - 30);
  set('s2_4', isoDate(dob));
  addressChanged('s2_6', null);
  const brgy = document.getElementById('s2_6_barangay');
  if (brgy.options.length > 1) brgy.value = brgy.options[1 + Math.floor(Math.random() * (brgy.options.length - 1))].value;
  syncDropdownFromSelect('s2_6_barangay');
  set('s2_6_street', 'Purok 3');
  set('s8_1_earn', '12000'); set('s8_1_pension', '0'); set('s8_1_govt', '3000'); set('s8_1_other', '0');
  set('s8_1_govt_prog', '4Ps'); set('s8_2_amount', String(pick([0, 800, 2500, 6000])));
  ALL_FIELDS.forEach(({ f }) => {
    if (['yn', 'choice', 'likert'].includes(f.type)) {
      const radios = [...document.querySelectorAll(`input[name="${f.id}"]`)];
      const r = f.type === 'likert' ? radios[Math.floor(Math.random() * (Math.random() < 0.9 ? 5 : radios.length))] : pick(radios);
      if (f.type === 'yn') toggleBtn(r.closest('label')); else r.checked = true;
    }
    if (f.type === 'select' && !f.emptyValue) {
      const el = document.getElementById(f.id);
      el.value = pick(f.options);
      syncDropdownFromSelect(f.id);
    }
    if (f.type === 'checks' || f.type === 'multiselect') {
      const boxes = [...document.querySelectorAll(`input[name="${f.id}"]`)];
      boxes.forEach((b, i) => { b.checked = f.allRequired || i === 0 || Math.random() < 0.2; });
      if (f.type === 'multiselect') updateMultiselectLabel(f.id);
    }
  });
  refresh();
  const main = document.getElementById('s9_1a_main');
  if (main.options.length > 1) main.value = main.options[1].value;
  document.querySelectorAll('input[id$="_spec"], input[id$="_other"]').forEach(s => { if (!s.classList.contains('hidden') && !s.value) s.value = 'Sample detail'; });
  refresh();
  window.toast.info('Sample answers filled. Use Next to continue to the review.');
}

// ============================================================================
// SESSION, LOGOUT
// ============================================================================

// Logged in online (server session) or offline (checked against the tester list).
function checkAuthentication() {
  const loggedIn = sessionStorage.getItem('session_id') || sessionStorage.getItem('session_mode') === 'offline';
  if (!loggedIn || !sessionStorage.getItem('interviewer_code') || !sessionStorage.getItem('privacyAccepted')) {
    window.location.replace('index.html');
    return false;
  }
  const el = document.getElementById('interviewer-code');
  el.textContent = sessionStorage.getItem('interviewer_name') || sessionStorage.getItem('interviewer_code');
  el.title = sessionStorage.getItem('interviewer_code') || '';
  return true;
}

function showLogoutModal() {
  const n = AppOffline.myItems().length;
  const note = document.getElementById('logout-outbox-note');
  note.textContent = `${n} profile${n > 1 ? 's are' : ' is'} still waiting to upload. ${n > 1 ? 'They stay' : 'It stays'} saved on this device and will upload the next time you log in online.`;
  note.classList.toggle('hidden', n === 0);
  document.getElementById('logout-modal').classList.remove('hidden');
}
function hideLogoutModal() { document.getElementById('logout-modal').classList.add('hidden'); }

async function confirmLogout() {
  await PretestApi.logout();
  window.location.replace('index.html');
}

// ============================================================================
// TESTER FEEDBACK ("Report a problem")
// Comments are kept with this profile and uploaded together with it.
// ============================================================================

function openFeedbackModal() {
  document.getElementById('feedback-step').textContent = currentStep >= 1 && currentStep <= TOTAL ? STEPS[currentStep - 1].title : 'Review Profile';
  document.getElementById('feedback-text').value = '';
  document.getElementById('feedback-modal').classList.remove('hidden');
  document.getElementById('feedback-text').focus();
}

function closeFeedbackModal() {
  document.getElementById('feedback-modal').classList.add('hidden');
}

function saveFeedback() {
  const text = document.getElementById('feedback-text').value.trim();
  if (!text) {
    window.toast.warning('Please describe the problem first.', 'Feedback');
    return;
  }
  // Stored with the tool's section number (2–12, the s2_…–s12_… field prefix);
  // a comment made on the review screen has no single section.
  pretestFeedback.push({ step: currentStep >= 1 && currentStep <= TOTAL ? STEPS[currentStep - 1].no : null, comment: text.slice(0, 2000) });
  document.getElementById('feedback-count').textContent = String(pretestFeedback.length);
  document.getElementById('feedback-count').classList.remove('hidden');
  closeFeedbackModal();
  window.toast.success('Saved. It will be sent together with this profile.', 'Feedback');
}

// ============================================================================
// SUBMIT
// ============================================================================

// Only questions the interviewer could see are sent, so an answer left behind
// by a skip rule is never uploaded.
function collectAnswers() {
  const a = answers();
  const out = { s1_language: sessionStorage.getItem('dialect') || '' };
  ALL_FIELDS.filter(entry => isVisible(entry, a)).forEach(({ f }) => {
    const v = a[f.id];
    if (f.type === 'address') {
      [...ADDRESS_LEVELS, 'street'].forEach((k, i) => { out[`${f.id}_${k}`] = v[i]; });
    } else if (f.split) {
      f.options.forEach((opt, i) => { if (v.includes(opt)) out[`${f.id}_${i + 1}`] = 'Yes'; });
    } else if (!(f.emptyValue && v === f.emptyValue)) {
      out[f.id] = v;
    }
    if (f.type === 'date') {
      const age = ageFromDob(v);
      out[f.id + '_age'] = age === null ? '' : String(age);
    }
    if (needsSpecify(f, v)) out[specId(f)] = document.getElementById(specId(f)).value.trim();
    const notes = document.getElementById(f.id + '_note');
    if (f.notes && notes && notes.value.trim()) out[f.id + '_note'] = notes.value.trim();
  });
  return out;
}

function childName(a) {
  return [a.s2_1_first, a.s2_1_last].filter(Boolean).join(' ') || a.s2_3 || '';
}

// Saves the profile on this device first (offline outbox), then uploads it.
// With no internet it stays in the outbox and uploads later by itself.
async function submitProfile() {
  const btn = document.getElementById('btn-submit-profile');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = 'Submitting...';
  const restore = () => { btn.disabled = false; btn.innerHTML = original; };

  const answersOut = collectAnswers();
  const completedAt = new Date().toISOString();
  const item = {
    id:               PRETEST_RECORD_ID,
    interviewer_code: sessionStorage.getItem('interviewer_code'),
    child_name:       childName(answersOut),
    saved_at:         completedAt,
    body: {
      assessment_id:          PRETEST_RECORD_ID,
      created_on_device_at:   PRETEST_CREATED_ON_DEVICE_AT,
      completed_on_device_at: completedAt,
      app_version:            window.ARUGA_CONFIG.APP_VERSION,
      answers:                answersOut,
      feedback:               pretestFeedback,
    },
  };

  let savedOnDevice = true;
  try {
    AppOffline.addToOutbox(item);
  } catch (e) {
    savedOnDevice = false; // device storage full: can only upload directly
  }

  if (await AppOffline.checkOnline() && await AppOffline.ensureServerSession()) {
    const result = await AppOffline.uploadOne(item);
    if (result.ok) {
      AppOffline.removeFromOutbox(item.id);
      const d = result.json.data || {};
      // Kept in sessionStorage (not the URL) so names never appear in links or logs.
      sessionStorage.setItem('pretest_last_result', JSON.stringify({
        aruga_id:   d.aruga_id || '',
        child_name: item.child_name,
        duplicate:  !!d.duplicate,
      }));
      window.location.href = 'success.html';
      return;
    }
    if (result.status !== 0) {
      // The server refused this profile: it would never upload, so take it out
      // of the outbox and let the interviewer fix it on this screen.
      AppOffline.removeFromOutbox(item.id);
      const fields = (result.json && result.json.data && result.json.data.fields) || [];
      window.toast.error(((result.json && result.json.message) || 'Failed to submit the profile. Please try again.') + (fields.length ? ` (${fields.join(', ')})` : ''), 'Submission Error');
      restore();
      return;
    }
  }

  if (!savedOnDevice) {
    window.toast.error('No internet, and this device has no space left to save the profile. Connect to the internet and press Submit Profile again. Your answers are still on this screen.', 'Submission Error');
    restore();
    return;
  }

  sessionStorage.setItem('pretest_last_result', JSON.stringify({ offline: true, child_name: item.child_name }));
  window.location.href = 'success.html';
}

// ============================================================================
// INIT
// ============================================================================

if (checkAuthentication()) {
  document.getElementById('steps-container').innerHTML = STEPS.map((s, i) => renderStep(s, i + 1)).join('') + renderReviewShell();
  document.getElementById('steps-container').addEventListener('change', refresh);
  document.getElementById('steps-container').addEventListener('input', e => {
    if (INCOME_KEYS.includes(e.target.id) || e.target.id === 's8_2_amount' || e.target.id === 's2_4') refresh();
  });
  window.addEventListener('scroll', () => document.getElementById('back-to-top').classList.toggle('hidden', window.scrollY <= 8));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeFeedbackModal(); hideLogoutModal(); } });

  if (window.ARUGA_CONFIG.SHOW_SAMPLE_FILL) document.getElementById('btn-fill-sample').classList.remove('hidden');

  // Online/offline pill; uploads anything this interviewer saved offline.
  AppOffline.init();

  let secs = 0;
  setInterval(() => { secs++; document.getElementById('session-timer').innerText = `${String(Math.floor(secs / 60)).padStart(2, '0')}:${String(secs % 60).padStart(2, '0')}`; }, 1000);

  // The window's back action (e.g. Alt+Left, Android back) would leave the form
  // and lose the answers. Catch it and ask to end the session instead.
  history.pushState({ v3Form: true }, '');
  window.addEventListener('popstate', () => {
    history.pushState({ v3Form: true }, '');
    showLogoutModal();
  });
  // Re-check the login when the page is restored from the back/forward cache.
  window.addEventListener('pageshow', e => { if (e.persisted) checkAuthentication(); });

  goToStep(1);
  loadLocations().then(() => addressChanged('s2_6', null));
}
