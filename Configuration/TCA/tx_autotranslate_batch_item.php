<?php

declare(strict_types=1);

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
        ['showitem' => 'priority, sys_language_uid, hidden, crdate, tstamp, translate, translated, mode, frequency, error'],
    ],
    'columns' => [
        'priority' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority.01_low', 'value' => \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::PRIORITY_LOW],
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority.02_medium', 'value' => \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::PRIORITY_MEDIUM],
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority.03_high', 'value' => \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::PRIORITY_HIGH],
                ],
            ],
        ],
        'sys_language_uid' => [
            'exclude' => false,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.sys_language_uid',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'required' => true,
                'itemsProcFunc' => \ThieleUndKlose\Autotranslate\Utility\BatchLanguages::class . '->populateLanguagesFromSiteConfiguration',
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.disabled',
            'config' => [
                'type' => 'check',
                'items' => [
                    [
                        'label' => ''
                    ]
                ]
            ],
        ],
        'translate' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.translate',
            'config' => [
                'type' => 'datetime',
                'required' => true,
                'default' => time(),
            ],
        ],
        'translated' => [
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.translated',
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'mode' => [
            'exclude' => false,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.mode',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.mode.' . \ThieleUndKlose\Autotranslate\Utility\Translator::TRANSLATE_MODE_BOTH, 'value' => \ThieleUndKlose\Autotranslate\Utility\Translator::TRANSLATE_MODE_BOTH],
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.mode.' . \ThieleUndKlose\Autotranslate\Utility\Translator::TRANSLATE_MODE_UPDATE_ONLY, 'value' => \ThieleUndKlose\Autotranslate\Utility\Translator::TRANSLATE_MODE_UPDATE_ONLY],
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.mode.' . \ThieleUndKlose\Autotranslate\Utility\Translator::TRANSLATE_MODE_CREATE_ONLY, 'value' => \ThieleUndKlose\Autotranslate\Utility\Translator::TRANSLATE_MODE_CREATE_ONLY],
                ],
                'default' => \ThieleUndKlose\Autotranslate\Utility\Translator::TRANSLATE_MODE_BOTH,
                'required' => true
            ],
        ],
        'frequency' => [
            'exclude' => false,
            'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.once', 'value' => \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::FREQUENCY_ONCE],
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.recurring', 'value' => \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::FREQUENCY_RECURRING],
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.weekly', 'value' => \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::FREQUENCY_WEEKLY],
                    ['label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.daily', 'value' => \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::FREQUENCY_DAILY],
                ],
                'default' => \ThieleUndKlose\Autotranslate\Domain\Model\BatchItem::FREQUENCY_ONCE,
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
