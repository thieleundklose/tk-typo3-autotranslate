<?php

return [
    'autotranslate_record_translation_languages' => [
        'path' => '/autotranslate/record/languages',
        'target' => \ThieleUndKlose\Autotranslate\Controller\RecordTranslationAjaxController::class . '::languages',
        'inheritAccessFromModule' => 'web_list',
    ],
    'autotranslate_record_translation_translate' => [
        'path' => '/autotranslate/record/translate',
        'target' => \ThieleUndKlose\Autotranslate\Controller\RecordTranslationAjaxController::class . '::translate',
        'inheritAccessFromModule' => 'web_list',
    ],
];
