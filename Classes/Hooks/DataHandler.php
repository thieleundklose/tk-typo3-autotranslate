<?php
declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ThieleUndKlose\Autotranslate\Hooks;

use ThieleUndKlose\Autotranslate\Utility\FlashMessageUtility;
use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\Translator;

class DataHandler implements SingletonInterface
{
    private static int $suspensionLevel = 0;

    /**
     * @var bool Hook suspended state.
     */
    private bool $suspended = false;

    /**
     * @var array<string, array<int, array{pageId: int, changedFields: string[]|null}>>
     */
    private array $translationQueue = [];

    public static function runWithSuspendedHook(callable $callback): mixed
    {
        self::$suspensionLevel++;

        try {
            return $callback();
        } finally {
            self::$suspensionLevel--;
        }
    }

    /**
     * Generate a different preview link
     *
     * @param string $status status
     * @param string $table table name
     * @param int $recordUid id of the record
     * @param array $fields fieldArray
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $parentObject parent Object
     */
    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $recordUid,
        array $fields,
        \TYPO3\CMS\Core\DataHandling\DataHandler $parentObject
    )
    {

        // Skip auto translation if hook is suspended. @see processCmdmap() for detailed description.
        if ($this->suspended || self::$suspensionLevel > 0) {
            return;
        }

        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? 'sys_language_uid';
        $languageUid = isset($parentObject->datamap[$table][$recordUid][$languageField]) ? (int)$parentObject->datamap[$table][$recordUid][$languageField] : null;

        // Skip auto translation if page created on root level.
        if ($table == 'pages' && $status == 'new' && $fields['pid'] === 0) {
            return;
        }

        // replace real record uid if is new record
        if (isset($parentObject->substNEWwithIDs[$recordUid])) {
            $recordUid = $parentObject->substNEWwithIDs[$recordUid];
        }
        if (!isset($GLOBALS['TCA'][$table]['columns']['autotranslate_languages'])) {
            return;
        }
        if ($languageUid && $languageUid > 0) {
            $parentObject->updateDB($table, $recordUid, ['autotranslate_languages' => NULL]);
            return;
        }

        $pid = $parentObject->getPID($table, $recordUid);
        $pageId = ($pid === 0 && $table === 'pages') ? $recordUid : $pid;

        // Skip auto translation if page id is not set, because no site configuration could be exist on root page 0.
        if (empty($pageId)) {
            return;
        }

        $siteConfiguration = TranslationHelper::siteConfigurationValue((int)$pageId);
        if (!is_array($siteConfiguration)) {
            return;
        }

        $translationSettings = TranslationHelper::translationSettingsDefaults($siteConfiguration, $table);
        if ($translationSettings === null) {
            return;
        }

        $textFields = GeneralUtility::trimExplode(',', (string)($translationSettings['autotranslateTextfields'] ?? ''), true);
        if (empty($textFields)) {
            return;
        }

        $this->translationQueue[$table][(int)$recordUid] = [
            'pageId' => (int)$pageId,
            'changedFields' => TranslationHelper::extractChangedFieldsFromDatamap((string)$status, $fields),
        ];

        return;
    }

    public function processDatamap_afterAllOperations(\TYPO3\CMS\Core\DataHandling\DataHandler $parentObject): void
    {
        if ($this->suspended || self::$suspensionLevel > 0 || $this->translationQueue === []) {
            return;
        }

        $translationQueue = $this->translationQueue;
        $this->translationQueue = [];

        foreach ($translationQueue as $table => $records) {
            if (!in_array($table, TranslationHelper::tablesToTranslate(), true)) {
                continue;
            }

            foreach ($records as $recordUid => $queueItem) {
                $pageId = (int)$queueItem['pageId'];
                $changedFields = $queueItem['changedFields'];

                $record = Records::getRecord($table, (int)$recordUid);
                if ($record === null) {
                    continue;
                }

                $targetLanguages = GeneralUtility::trimExplode(
                    ',',
                    (string)($record[Translator::AUTOTRANSLATE_LANGUAGES] ?? ''),
                    true
                );

                if ($targetLanguages === []) {
                    continue;
                }

                $translator = GeneralUtility::makeInstance(Translator::class, (int)$pageId);

                try {
                    self::runWithSuspendedHook(static function () use ($translator, $table, $recordUid, $parentObject, $targetLanguages, $changedFields): void {
                        $translator->translate(
                            $table,
                            (int)$recordUid,
                            $parentObject,
                            implode(',', $targetLanguages),
                            Translator::TRANSLATE_MODE_BOTH,
                            $changedFields
                        );
                    });
                } catch (\Exception $e) {
                    FlashMessageUtility::addMessage(
                        'Error during translation: ' . $e->getMessage(),
                        'Translation Error',
                        FlashMessageUtility::MESSAGE_WARNING
                    );
                }
            }
        }
    }

    /**
     * Dynamically enable or disable auto translation depending on command type.
     *
     * @param string $command
     * @param $table
     * @param $id
     * @param $value
     * @param $commandIsProcessed
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @param $pasteUpdate
     * @return void
     */
    public function processCmdmap(string $command, $table, $id, $value, $commandIsProcessed, \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler, $pasteUpdate)
    {
        // Disable auto translation for copy actions.
        if ($command === 'copy') {
            $this->suspended = true;
        }
    }

    /**
     * Reenable auto translation if it has been suspended in processCmdmap() hook.
     *
     * @param string $command
     * @param $table
     * @param $id
     * @param $value
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @param $pasteUpdate
     * @param $pasteDatamap
     * @return void
     */
    public function processCmdmap_postProcess(string $command, $table, $id, $value, \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler, $pasteUpdate, $pasteDatamap)
    {
        // Reenable auto translation after copy command has finished.
        if ($command === 'copy') {
            $this->suspended = false;
        }
    }

}
