<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$options = [
    'List_Religion' => [
        'Roman Catholic',
        'Islam',
        'Iglesia ni Cristo',
        'Protestant',
        'Born Again Christian',
        'Buddhism',
        'Hinduism',
        'Others'
    ],
    'List_IP' => [
        'Not a member',
        'Aeta',
        'Igorot',
        'Lumad',
        'Mangyan',
        'Tagbanua',
        'Badjao',
        'T\'boli',
        'Manobo',
        'Others'
    ],
    'List_Education' => [
        'No formal education',
        'Elementary Undergraduate',
        'Elementary Graduate',
        'High School Undergraduate',
        'High School Graduate',
        'Senior High School Graduate',
        'College Undergraduate',
        'College Graduate',
        'Vocational/Technical',
        'Post Graduate',
        'Others'
    ],
    'List_Relationship' => [
        'Parent',
        'Guardian',
        'Grandparent',
        'Sibling',
        'Aunt/Uncle',
        'Relative',
        'Social Worker',
        'Foster Parent'
    ],
    'List_Disability' => [
        'Visual Impairment',
        'Hearing Impairment',
        'Speech Impairment',
        'Physical Disability',
        'Intellectual Disability',
        'Learning Disability',
        'Autism Spectrum Disorder',
        'Psychosocial Disability',
        'Multiple Disabilities',
        'Others'
    ],
    'List_Illness' => [
        'None',
        'Cancer',
        'Heart Disease',
        'Kidney Disease',
        'Diabetes',
        'Respiratory Disease',
        'Neurological Disorder',
        'Blood Disorder',
        'Chronic Illness',
        'Others'
    ],
    'List_Extension' => [
        'None',
        'Jr.',
        'Sr.',
        'II',
        'III',
        'IV',
        'V'
    ],
    'List_Occupation' => [
        'Unemployed',
        'Employed (Private Sector)',
        'Employed (Government)',
        'Self-employed',
        'Farmer',
        'Fisherman',
        'Laborer',
        'Vendor',
        'Driver',
        'Housewife/Househusband',
        'Student',
        'OFW (Overseas Filipino Worker)',
        'Retired',
        'Others'
    ],
    'List_Occupation_Class' => [
        'Professional',
        'Technical',
        'Clerical',
        'Sales',
        'Service Worker',
        'Agricultural Worker',
        'Production/Laborer',
        'Elementary Occupation',
        'Not Applicable'
    ],
    'List_Materials' => [
        'Strong materials (Concrete, brick, stone)',
        'Light materials (Wood, bamboo, nipa)',
        'Mixed strong and light materials',
        'Salvaged/Makeshift materials',
        'Others'
    ],
    'List_Tenure' => [
        'Own house and lot',
        'Own house, rented lot',
        'Rent house and lot',
        'Rent-free with consent of owner',
        'Informal settler',
        'Others'
    ],
    'List_Electricity' => [
        'Electricity from distribution company',
        'Community electricity system',
        'Solar panel',
        'Generator set',
        'Kerosene lamp/Candles',
        'No electricity',
        'Others'
    ],
    'List_Water' => [
        'Own use, faucet, community water system',
        'Own use, faucet, NAWASA/Water district',
        'Own use, tubed/piped deep well',
        'Shared, faucet, community water system',
        'Shared, faucet, NAWASA/Water district',
        'Shared, tubed/piped deep well',
        'Public tap/standpipe',
        'Tubed/piped shallow well',
        'Dug/open well',
        'Spring, lake, river, rain',
        'Peddler, bottled water',
        'Others'
    ],
    'List_Toilet' => [
        'Water-sealed, sewer/septic tank, used exclusively by household',
        'Water-sealed, sewer/septic tank, shared with other households',
        'Water-sealed, other depository',
        'Closed pit',
        'Open pit',
        'Drop/overhang',
        'None',
        'Others'
    ],
    'List_Garbage' => [
        'Picked up by garbage truck',
        'Burning',
        'Burying',
        'Composting',
        'Dumping in pit',
        'Throw in river/sea/creek',
        'Recycling',
        'Others'
    ]
];

echo json_encode($options, JSON_PRETTY_PRINT);
?>