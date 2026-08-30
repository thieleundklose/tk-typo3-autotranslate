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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use ThieleUndKlose\Autotranslate\Utility\FlashMessageUtility;
use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use ThieleUndKlose\Autotranslate\Service\FileMetadataTranslationService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
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

    /**
     * @var array<int, string[]|null>
     */
    private array $fileMetadataTranslationQueue = [];

    /**
     * Original records captured before DataHandler writes submitted values.
     *
     * @var array<string, array<string, array>>
     */
    private array $originalRecords = [];

    public static function runWithSuspendedHook(callable $callback): mixed
    {
        self::$suspensionLevel++;

        try {
            return $callback();
        } finally {
            self::$suspensionLevel--;
        }
    }

    public function processDatamap_preProcessFieldArray(
        &$incomingFieldArray,
        $table,
        $recordUid,
        \TYPO3\CMS\Core\DataHandling\DataHandler $parentObject
    ): void {
        if ($this->suspended || self::$suspensionLevel > 0) {
            return;
        }

        if (
            !isset($GLOBALS['TCA'][$table]['columns']['autotranslate_languages'])
            || !is_numeric($recordUid)
        ) {
            return;
        }

        $recordKey = (string)$recordUid;
        if (isset($this->originalRecords[$table][$recordKey])) {
            return;
        }

        $record = Records::getRecord((string)$table, (int)$recordUid);
        if ($record !== null) {
            $this->originalRecords[(string)$table][$recordKey] = $record;
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

        $originalRecordKey = (string)$recordUid;
        $originalRecord = $this->originalRecords[$table][$originalRecordKey] ?? null;
        unset($this->originalRecords[$table][$originalRecordKey]);

        // Skip auto translation if hook is suspended. @see processCmdmap() for detailed description.
        if ($this->suspended || self::$suspensionLevel > 0) {
            return;
        }

        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? null;
        if ($languageField !== null && isset($parentObject->datamap[$table][$recordUid][$languageField])) {
            $languageUid = (int)$parentObject->datamap[$table][$recordUid][$languageField];
        } elseif (
            $languageField !== null
            && is_array($originalRecord)
            && array_key_exists($languageField, $originalRecord)
        ) {
            $languageUid = (int)$originalRecord[$languageField];
        } else {
            $currentRecord = $languageField !== null && is_numeric($recordUid)
                ? Records::getRecord((string)$table, (int)$recordUid)
                : null;
            $languageUid = $languageField !== null
                && is_array($currentRecord)
                && array_key_exists($languageField, $currentRecord)
                ? (int)$currentRecord[$languageField]
                : null;
        }

        // Skip auto translation if page created on root level.
        if ($table == 'pages' && $status == 'new' && $fields['pid'] === 0) {
            return;
        }

        // replace real record uid if is new record
        if (isset($parentObject->substNEWwithIDs[$recordUid])) {
            $recordUid = $parentObject->substNEWwithIDs[$recordUid];
        }

        if ($languageField !== null && $languageUid === null && is_numeric($recordUid)) {
            $record = BackendUtility::getRecord($table, (int)$recordUid, $languageField);
            if (is_array($record) && isset($record[$languageField])) {
                $languageUid = (int)$record[$languageField];
            }
        }

        if ($table === 'sys_file_metadata') {
            $this->queueFileMetadataTranslation((int)$recordUid, $languageUid, (string)$status, $fields);
            return;
        }

        if (!isset($GLOBALS['TCA'][$table]['columns']['autotranslate_languages'])) {
            return;
        }
        if ($languageUid && $languageUid > 0) {
            // Localized records must never enter the autotranslate queue.
            // The TCA fields are only meant for source-language records, so there is nothing
            // to reset here when editors create translations manually in the TYPO3 backend.
            return;
        }

        $pid = $this->resolvePid($table, (int)$recordUid, $fields);
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

        $this->translationQueue[$table][(int)$recordUid] = [
            'pageId' => (int)$pageId,
            'changedFields' => TranslationHelper::extractChangedFieldsFromDatamap(
                (string)$status,
                $fields,
                $originalRecord
            ),
        ];

        return;
    }

    /**
     * Resolve the record pid in a way that works across TYPO3 v12-v14.
     */
    private function resolvePid(string $table, int $recordUid, array $fields): int
    {
        if (isset($fields['pid']) && is_numeric($fields['pid'])) {
            return (int)$fields['pid'];
        }

        $record = BackendUtility::getRecord($table, $recordUid, 'pid');
        return is_array($record) ? (int)($record['pid'] ?? 0) : 0;
    }

    public function processDatamap_afterAllOperations(\TYPO3\CMS\Core\DataHandling\DataHandler $parentObject): void
    {
        if ($this->suspended || self::$suspensionLevel > 0) {
            return;
        }

        $fileMetadataTranslationQueue = $this->fileMetadataTranslationQueue;
        $this->fileMetadataTranslationQueue = [];

        if ($fileMetadataTranslationQueue !== []) {
            $fileMetadataTranslationService = GeneralUtility::makeInstance(FileMetadataTranslationService::class);
            foreach ($fileMetadataTranslationQueue as $metadataUid => $changedFields) {
                try {
                    $fileMetadataTranslationService->translate((int)$metadataUid, $changedFields);
                } catch (\Exception $e) {
                    FlashMessageUtility::addMessage(
                        'Error during file metadata translation: ' . $e->getMessage(),
                        'File Metadata Translation Error',
                        FlashMessageUtility::MESSAGE_ERROR
                    );
                }
            }
        }

        if ($this->translationQueue === []) {
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
                    $translationResult = self::runWithSuspendedHook(static function () use ($translator, $table, $recordUid, $parentObject, $targetLanguages, $changedFields) {
                        return $translator->translateWithResult(
                            $table,
                            (int)$recordUid,
                            $parentObject,
                            implode(',', $targetLanguages),
                            Translator::TRANSLATE_MODE_BOTH,
                            $changedFields
                        );
                    });
                    if ($translationResult->hasErrors()) {
                        $hasTranslations = $translationResult->hasTranslations();
                        FlashMessageUtility::addMessage(
                            'Translation completed with errors: ' . $translationResult->getErrorSummary(),
                            $hasTranslations ? 'Translation incomplete' : 'Translation failed',
                            FlashMessageUtility::MESSAGE_ERROR
                        );
                    } elseif (!$translationResult->hasTranslations()) {
                        $reason = $translationResult->getSkippedReasonSummary();
                        FlashMessageUtility::addMessage(
                            $reason !== ''
                                ? 'No translation was performed: ' . $reason
                                : 'No translation was performed because there was no translation work for this record.',
                            'Translation skipped',
                            $translationResult->hasWarnings()
                                ? FlashMessageUtility::MESSAGE_WARNING
                                : FlashMessageUtility::MESSAGE_NOTICE
                        );
                    }
                } catch (\Throwable $e) {
                    FlashMessageUtility::addMessage(
                        'Error during translation: ' . $e->getMessage(),
                        'Translation Error',
                        FlashMessageUtility::MESSAGE_ERROR
                    );
                }
            }
        }
    }

    /**
     * Queue global FAL metadata records for translation after all DataHandler operations.
     *
     * sys_file_metadata has no stable page/site context, so it is handled
     * separately from the regular page-tree based translation queue.
     */
    private function queueFileMetadataTranslation(
        int $recordUid,
        ?int $languageUid,
        string $status,
        array $fields
    ): void {
        if (!isset($GLOBALS['TCA']['sys_file_metadata']['columns']['autotranslate_languages'])) {
            return;
        }

        if ($languageUid === null) {
            $record = Records::getRecord('sys_file_metadata', $recordUid);
            $languageUid = is_array($record) ? (int)($record['sys_language_uid'] ?? 0) : null;
        }

        if ($languageUid !== null && $languageUid > 0) {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('sys_file_metadata');
            $connection->update(
                'sys_file_metadata',
                ['autotranslate_languages' => null],
                ['uid' => $recordUid]
            );
            return;
        }

        $this->fileMetadataTranslationQueue[$recordUid] = TranslationHelper::extractChangedFieldsFromDatamap($status, $fields);
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
