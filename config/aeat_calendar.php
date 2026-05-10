<?php

return [
    'risk_thresholds' => [
        'amber_days' => 30,
        'red_days' => 7,
    ],

    'business_day_holidays' => [
        // '2026' => ['2026-01-01', '2026-12-25'],
    ],

    'regime_defaults' => [
        'autonomo' => ['303', '111', '115', '130', '131', '390', '347'],
        'sociedad' => ['303', '111', '115', '202', '180', '190', '390', '347'],
        'mixto' => ['303', '111', '115', '130', '131', '202', '180', '190', '390', '347'],
        'default' => ['303', '111', '115', '130', '131', '202', '180', '190', '390', '347'],
    ],

    'models' => [
        '303' => [
            'code' => '303',
            'name' => 'IVA trimestral',
            'category' => 'IVA',
            'schedule' => [
                'type' => 'quarterly',
                'quarter_day' => 20,
                'q4_day' => 30,
            ],
            'source_label' => 'AEAT',
        ],
        '111' => [
            'code' => '111',
            'name' => 'Retenciones trabajo y profesionales',
            'category' => 'Retenciones',
            'schedule' => [
                'type' => 'quarterly',
                'quarter_day' => 20,
                'q4_day' => 30,
            ],
            'source_label' => 'AEAT',
        ],
        '115' => [
            'code' => '115',
            'name' => 'Retenciones alquileres',
            'category' => 'Retenciones',
            'schedule' => [
                'type' => 'quarterly',
                'quarter_day' => 20,
                'q4_day' => 30,
            ],
            'source_label' => 'AEAT',
        ],
        '130' => [
            'code' => '130',
            'name' => 'Pagos fraccionados IRPF',
            'category' => 'IRPF',
            'schedule' => [
                'type' => 'quarterly',
                'quarter_day' => 20,
                'q4_day' => 30,
            ],
            'source_label' => 'AEAT',
        ],
        '131' => [
            'code' => '131',
            'name' => 'Pagos fraccionados módulos',
            'category' => 'IRPF',
            'schedule' => [
                'type' => 'quarterly',
                'quarter_day' => 20,
                'q4_day' => 30,
            ],
            'source_label' => 'AEAT',
        ],
        '202' => [
            'code' => '202',
            'name' => 'Pagos fraccionados Impuesto sobre Sociedades',
            'category' => 'Sociedades',
            'schedule' => [
                'type' => 'quarterly',
                'quarter_day' => 20,
                'q4_day' => 30,
            ],
            'source_label' => 'AEAT',
        ],
        '180' => [
            'code' => '180',
            'name' => 'Resumen anual alquileres',
            'category' => 'Retenciones',
            'schedule' => [
                'type' => 'annual',
                'due_month' => 1,
                'due_day' => 30,
            ],
            'source_label' => 'AEAT',
        ],
        '190' => [
            'code' => '190',
            'name' => 'Resumen anual retenciones e ingresos a cuenta',
            'category' => 'Retenciones',
            'schedule' => [
                'type' => 'annual',
                'due_month' => 1,
                'due_day' => 30,
            ],
            'source_label' => 'AEAT',
        ],
        '347' => [
            'code' => '347',
            'name' => 'Operaciones con terceros',
            'category' => 'Informativas',
            'schedule' => [
                'type' => 'annual',
                'due_month' => 2,
                'due_day' => 28,
            ],
            'source_label' => 'AEAT',
        ],
        '390' => [
            'code' => '390',
            'name' => 'Resumen anual de IVA',
            'category' => 'IVA',
            'schedule' => [
                'type' => 'annual',
                'due_month' => 1,
                'due_day' => 30,
            ],
            'source_label' => 'AEAT',
        ],
    ],
];
