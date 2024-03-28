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

use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\Translator;

class DataHandler implements SingletonInterface
{
    /**
     * @var bool Hook suspended state.
     */
    private bool $suspended = false;

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
        if ($this->suspended) {
            return;
        }

        // Skip auto translation if page created on root level.
        if ($table == 'pages' && $status == 'new' && $fields['pid'] === 0) {
            return;
        }

        // replace real record uid if is new record
        if (isset($parentObject->substNEWwithIDs[$recordUid])) {
            $recordUid = $parentObject->substNEWwithIDs[$recordUid];
        }

        $pid = $parentObject->getPID($table, $recordUid);
        $pageId = ($pid === 0 && $table === 'pages') ? $recordUid : $pid;
        $translator = GeneralUtility::makeInstance(Translator::class, $pageId);

        if (in_array($table, TranslationHelper::translateableTables())) {
            $translator->translate($table, (int)$recordUid, $parentObject);
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

    /**
     * Check that the target language is unique per page
     *
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @return void
     */
    public function processDatamap_beforeStart(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler) {
        foreach ($dataHandler->datamap as $table => $idArray) {
            if ($table === 'tx_autotranslate_batch_items') {
                foreach ($idArray as $id => $fieldArray) {
                    if (isset($fieldArray['sys_language_uid'])) {
                        $value = $fieldArray['sys_language_uid'];
                        $pid = $fieldArray['pid'] ?: $dataHandler->checkValue_currentRecord['pid'];
                        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
                        $queryBuilder = $connection->createQueryBuilder();
                        $count = $queryBuilder->select('*')
                            ->from($table)
                            ->where(
                                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)),
                                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($value, \PDO::PARAM_STR))
                            )
                            ->andWhere(
                                $queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT))
                            )
                            ->execute()
                            ->rowCount();

                        if ($count > 0) {
                            $dataHandler->log(
                                $table,
                                $id,
                                2,
                                0,
                                1,
                                'A translation record for this target language already exists on this page.',
                                -1,
                                [],
                                [$value]
                            );
                            unset($dataHandler->datamap[$table][$id]);
                        }
                    }
                }
            }
        }
    }
}
