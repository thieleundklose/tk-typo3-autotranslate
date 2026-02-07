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
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler as CoreDataHandler;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\Translator;

/**
 * DataHandler hooks for automatic translation
 */
final class DataHandler implements SingletonInterface
{
    /**
     * @var bool Hook suspended state.
     */
    private bool $suspended = false;

    /**
     * Handle after database operations for automatic translation
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int|string $recordUid,
        array $fields,
        CoreDataHandler $parentObject
    ): void {
        // Skip auto translation if hook is suspended
        if ($this->suspended) {
            return;
        }

        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? 'sys_language_uid';
        $languageUid = isset($parentObject->datamap[$table][$recordUid][$languageField])
            ? (int)$parentObject->datamap[$table][$recordUid][$languageField]
            : null;

        // Skip auto translation if page created on root level
        if ($table === 'pages' && $status === 'new' && $fields['pid'] === 0) {
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
            $this->updateRecord($table, (int)$recordUid, ['autotranslate_languages' => null]);
            return;
        }

        $pageId = $this->getRecordPid($table, (int)$recordUid);
        if ($pageId === 0 && $table === 'pages') {
            $pageId = (int)$recordUid;
        }

        // Skip auto translation if page id is not set
        if (empty($pageId)) {
            return;
        }

        $translator = GeneralUtility::makeInstance(Translator::class, $pageId);

        try {
            if (in_array($table, TranslationHelper::tablesToTranslate(), true)) {
                $translator->translate($table, (int)$recordUid, $parentObject);
            }
        } catch (\Exception $e) {
            FlashMessageUtility::addMessage(
                'Error during translation: ' . $e->getMessage(),
                'Translation Error',
                FlashMessageUtility::MESSAGE_WARNING
            );
        }
    }

    /**
     * Update a record in the database
     */
    private function updateRecord(string $table, int $uid, array $data): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);
        $connection->update($table, $data, ['uid' => $uid]);
    }

    /**
     * Get the PID of a record
     */
    private function getRecordPid(string $table, int $uid): int
    {
        $record = BackendUtility::getRecord($table, $uid, 'pid');
        return (int)($record['pid'] ?? 0);
    }

    /**
     * Dynamically enable or disable auto translation depending on command type.
     */
    public function processCmdmap(
        string $command,
        string $table,
        int|string $id,
        mixed $value,
        bool $commandIsProcessed,
        CoreDataHandler $dataHandler,
        mixed $pasteUpdate
    ): void {
        if ($command === 'copy') {
            $this->suspended = true;
        }
    }

    /**
     * Reenable auto translation if it has been suspended in processCmdmap() hook.
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        int|string $id,
        mixed $value,
        CoreDataHandler $dataHandler,
        mixed $pasteUpdate,
        mixed $pasteDatamap
    ): void {
        if ($command === 'copy') {
            $this->suspended = false;
        }
    }
}
