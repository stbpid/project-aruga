<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Mock location data for 5 regions
// In production, this would come from your database
$locations = [
    'National Capital Region' => [
        'Metro Manila' => [
            'Quezon City' => [
                'Bagong Pag-asa',
                'Bahay Toro',
                'Balingasa',
                'Batasan Hills',
                'Commonwealth',
                'Fairview',
                'Kamuning',
                'Novaliches',
                'Project 4',
                'Project 6'
            ],
            'Manila' => [
                'Ermita',
                'Intramuros',
                'Malate',
                'Paco',
                'Pandacan',
                'Port Area',
                'Sampaloc',
                'San Miguel',
                'Santa Ana',
                'Tondo'
            ],
            'Makati' => [
                'Bel-Air',
                'Cembo',
                'Forbes Park',
                'Guadalupe Nuevo',
                'Poblacion',
                'Rockwell',
                'Salcedo Village',
                'San Lorenzo',
                'Urdaneta',
                'Valenzuela'
            ],
            'Pasig' => [
                'Bagong Ilog',
                'Kapitolyo',
                'Manggahan',
                'Maybunga',
                'Oranbo',
                'Pinagbuhatan',
                'Rosario',
                'San Joaquin',
                'Santolan',
                'Ugong'
            ]
        ]
    ],
    'Region II (Cagayan Valley)' => [
        'Cagayan' => [
            'Tuguegarao City' => [
                'Atulayan',
                'Bagay',
                'Buntun',
                'Caggay',
                'Caritan',
                'Centro 1',
                'Dadda',
                'Larion',
                'Pengue',
                'Ugac'
            ],
            'Aparri' => [
                'Backiling',
                'Binalan',
                'Bulala',
                'Centro',
                'Macanaya',
                'Navagan',
                'Plaza',
                'Punta',
                'San Antonio',
                'Zuzuarregui'
            ]
        ],
        'Isabela' => [
            'Ilagan City' => [
                'Alibagu',
                'Bagong Silang',
                'Centro',
                'Manano',
                'Marana',
                'Minabang',
                'San Felipe',
                'San Vicente',
                'Santo Tomas',
                'Villa Imelda'
            ],
            'Santiago City' => [
                'Abra',
                'Balintocatoc',
                'Centro East',
                'Centro West',
                'Divisoria',
                'Malvar',
                'Naggasican',
                'Rosario',
                'Sinili',
                'Villa Gonzaga'
            ]
        ]
    ],
    'Region III (Central Luzon)' => [
        'Bulacan' => [
            'Malolos' => [
                'Anilao',
                'Atlag',
                'Babatnin',
                'Bagna',
                'Bagong Bayan',
                'Balayong',
                'Balite',
                'Bangkal',
                'Barihan',
                'Bulihan'
            ],
            'San Jose del Monte' => [
                'Assumption',
                'Bagong Buhay',
                'Citrus',
                'Dulong Bayan',
                'Fatima',
                'Francisco Homes',
                'Gaya-gaya',
                'Graceville',
                'Gumaoc',
                'Kaybanban'
            ]
        ],
        'Pampanga' => [
            'Angeles City' => [
                'Agapito del Rosario',
                'Anunas',
                'Balibago',
                'Cutcut',
                'Lourdes',
                'Malabañas',
                'Pampang',
                'Pulungbulu',
                'Sapalibutad',
                'Sapangbato'
            ],
            'San Fernando' => [
                'Alasas',
                'Baliti',
                'Bulaon',
                'Calulut',
                'Dela Paz Norte',
                'Dela Paz Sur',
                'Dolores',
                'Juliana',
                'Lara',
                'Magliman'
            ]
        ]
    ],
    'Region IV-A (CALABARZON)' => [
        'Cavite' => [
            'Bacoor' => [
                'Alima',
                'Aniban I',
                'Banalo',
                'Bayanan',
                'Campo Santo',
                'Digman',
                'Habay I',
                'Kaingin',
                'Molino I',
                'Panapaan I'
            ],
            'Dasmariñas' => [
                'Burol',
                'Emmanuel Bergado I',
                'Langkaan I',
                'Paliparan I',
                'Salawag',
                'Salitran I',
                'San Agustin I',
                'San Simon',
                'Zone I',
                'Zone II'
            ]
        ],
        'Laguna' => [
            'Calamba' => [
                'Bagong Kalsada',
                'Barandal',
                'Bucal',
                'Burol',
                'Canlubang',
                'Halang',
                'Kaytikling',
                'Looc',
                'Parian',
                'Real'
            ],
            'Santa Rosa' => [
                'Aplaya',
                'Balibago',
                'Caingin',
                'Dila',
                'Dita',
                'Don Jose',
                'Labas',
                'Macabling',
                'Malitlit',
                'Pooc'
            ]
        ]
    ],
    'Region V (Bicol)' => [
        'Albay' => [
            'Legazpi City' => [
                'Bagumbayan',
                'Banquerohan',
                'Bitano',
                'Bonot',
                'Cabagñan',
                'Cruzada',
                'Dinaga',
                'Estanza',
                'Gogon',
                'Hobo'
            ],
            'Tabaco City' => [
                'Bacolod',
                'Bangkilingan',
                'Basud',
                'Bogñabong',
                'Bonot',
                'Buang',
                'Comon',
                'Cobo',
                'Divino Rostro',
                'Fatima'
            ]
        ],
        'Camarines Sur' => [
            'Naga City' => [
                'Abella',
                'Bagumbayan Norte',
                'Balatas',
                'Calauag',
                'Carolina',
                'Concepcion Grande',
                'Concepcion Pequena',
                'Dinaga',
                'Igualdad',
                'Lerma'
            ],
            'Iriga City' => [
                'Cristo Rey',
                'Del Rosario',
                'Francia',
                'La Anunciacion',
                'La Medalla',
                'La Purisima',
                'San Agustin',
                'San Francisco',
                'San Isidro',
                'San Jose'
            ]
        ]
    ]
];

echo json_encode($locations, JSON_PRETTY_PRINT);
?>