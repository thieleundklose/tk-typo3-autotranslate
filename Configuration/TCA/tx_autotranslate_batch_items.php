<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch',
        'label' => 'title',
        'iconfile' => 'EXT:autotranslate/Resources/Public/Icons/batch_record_icon.svg',
        'hideTable' => false,
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title',
    ],
    'types' => [
        ['showitem' => 'title, priority, sys_language_uid, hidden, crdate, tstamp, translate, translated, type, frequency, error'],
    ],
    'columns' => [
        'priority' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.priority',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.priority.low', 0],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.priority.medium', 1],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.priority.high', 2],
                ],
            ],
        ],
        'sys_language_uid' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'sys_language',
                'items' => [
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages', -1],
                    ['LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.default_value', 0]
                ],
                'default' => 0,
            ],
        ],
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'items' => [
                    '1' => [
                        '0' => 'LLL:EXT:lang/locallang_core.xlf:labels.enabled'
                    ]
                ]
            ],
        ],
        'crdate' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.creationDate',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'tstamp' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.timestamp',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'translate' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.translate',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'default' => 0,
            ],
        ],
        'translated' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.translated',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'type' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.type.add', 0],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.type.override', 1],
                ],
            ],
        ],
        'frequency' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.frequency',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.frequency.once', 0],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.frequency.weekly', 1],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.frequency.daily', 2],
                ],
            ],
        ],
        'error' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_tca.xlf:autotranslate_batch.error',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
            ],
        ],
    ],
];
