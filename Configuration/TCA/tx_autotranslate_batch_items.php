<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.record_label',
        'label' => 'title',
    ],
    'types' => [
        ['showitem' => 'priority'], 
    ], 
    'columns' => [
        'priority' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.priority_label',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'Low',
                        'value' => 0,
                    ],
                    [
                        'label' => 'Medium',
                        'value' => 1,
                    ],
                    [
                        'label' => 'High',
                        'value' => 2,
                    ],
                ],
            ],
        ]
    ],
];
