<?php
// MinglaDent review policy, not an official licensing checklist.
// Keep stable keys for existing submissions. Change labels/requirements here as needed.
return [
    'service_areas' => ['Minglanilla, Cebu'],
    'documents' => [
        'authorization' => [
            'label' => 'Proof of clinic ownership or authorization to represent the clinic',
            'required' => true,
        ],
    ],
    'max_file_bytes' => 5 * 1024 * 1024,
    'allowed_types' => ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'],
];
