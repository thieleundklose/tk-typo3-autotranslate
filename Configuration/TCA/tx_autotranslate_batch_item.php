<?php

defined('TYPO3') or die;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch',
        'label' => 'uid',
        'label_userFunc' => \ThieleUndKlose\Autotranslate\UserFunction\Tca::class . '->batchLabel',
        'iconfile' => 'EXT:autotranslate/Resources/Public/Icons/Extension.png',
        'hideTable' => false,
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        ['showitem' => 'priority, sys_language_uid, hidden, crdate, tstamp, translate, translated, type, frequency, error'],
    ],
    'columns' => [
        'priority' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority.low', \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::PRIORITY_LOW],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority.medium', \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::PRIORITY_MEDIUM],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority.high', \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::PRIORITY_HIGH],
                ],
            ],
        ],
        'sys_language_uid' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.sys_language_uid',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'required' => true,
                'itemsProcFunc' => \ThieleUndKlose\Autotranslate\Utility\BatchLanguages::class . '->populateLanguagesFromSiteConfiguration',
            ],
        ],
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.disabled',
            'config' => [
                'type' => 'check',
                'items' => [
                    [
                        ''
                    ]
                ]
            ],
        ],
        'translate' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.translate',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'required' => true,
                'default' => time(),
            ],
        ],
        'translated' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.translated',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'type' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.type.add', \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::TYPE_TRANSLATION_ADD_NEW],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.type.override', \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::TYPE_TRANSLATION_OVERWRITE_EXISTING],
                ],
                'default' => 0,
                'required' => true
            ],
        ],
        'frequency' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.once', \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::FREQUENCY_ONCE],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.weekly', \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::FREQUENCY_WEEKLY],
                    ['LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.daily', \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::FREQUENCY_DAILY],
                ],
                'default' => 0,
                'required' => true
            ],
        ],
        'error' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.error',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
            ],
        ],
    ],
];
