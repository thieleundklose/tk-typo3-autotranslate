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

use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler as DataHandlerOriginal;


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

        $newRecordUid = $recordUid;
        
        // replace real record uid if is new record
        if (isset($parentObject->substNEWwithIDs[$recordUid])) {
            $recordUid = $parentObject->substNEWwithIDs[$recordUid];
        }

        $pid = $parentObject->getPID($table, $recordUid);
        $pageId = ($pid === 0 && $table === 'pages') ? $recordUid : $pid;
        $translator = GeneralUtility::makeInstance(Translator::class, $pageId);

        if (in_array($table, TranslationHelper::translateableTables())) {
            $translator->translate($table, (int)$recordUid);
        }

        $columnsSysFileLanguage = TranslationHelper::translationTextfields($pageId, 'sys_file_reference');
        $parentFieldSysFileReference = TranslationHelper::translationOrigPointerField('sys_file_reference');

        // TODO beim update eines content elements wird eine neu angelegte referenz nicht in die translation übernommen
        // TODO das image feld bei tt_content wird beim ergänzen sys_file_reference nicht mit den counts gesetzt
        // check if table has sys_file_references which must be localized
        if ($table !== 'sys_file_reference') {

            $tableColumns = TranslationHelper::translationFileReferences($pageId, $table);
            
            foreach ($tableColumns as $tableColumn) {

                $datamapSysFileReferences = GeneralUtility::trimExplode(',', $parentObject->datamap[$table][$newRecordUid][$tableColumn] ?? '', true);
                if (empty($datamapSysFileReferences)) {
                    continue;
                }

                $targetLanguages = $parentObject->datamap[$table][$newRecordUid][Translator::AUTOTRANSLATE_LANGUAGES] ?? NULL;
                if ($targetLanguages === NULL) {
                    continue;
                }
                if ($targetLanguages !== NULL && !is_array($targetLanguages)) {
                    $targetLanguages = GeneralUtility::trimExplode(',', $targetLanguages, true);
                }


                $parentField = TranslationHelper::translationOrigPointerField($table);

                if ($status === 'update') {

                    foreach ($datamapSysFileReferences as $uid) {

                        $newUid = $parentObject->substNEWwithIDs[$uid] ?? null;

                        if ($newUid !== null) {
                            foreach ($targetLanguages as $targetLanguage) {
                            
                                // do not localize if sys_language is a new localized one if new sys_file_reference was added to existend content element
                                if ($parentObject->substNEWwithIDs_table[$uid] === 'sys_file_reference') {
                                    // only translate this file reference
                                    continue;
                                }

                                $dataHandler = GeneralUtility::makeInstance(DataHandlerOriginal::class);
                                $dataHandler->start([], []);
                                $localizedUid = $dataHandler->localize('sys_file_reference', $newUid, $targetLanguage);

                                // update foreign field after localize to move translation of sys_file_reference to translated content element
                                if ($localizedUid === false) {
                                    continue;
                                }
                                $parentRecordUid = Records::getRecord('sys_file_reference', $localizedUid, 'uid_foreign');
                                $originalReferencesUid = current(Records::getRecords($table, 'uid', ["{$parentField} = '{$parentRecordUid}'", "sys_language_uid = '{$targetLanguage}'"]));

                                Records::updateRecord('sys_file_reference', $localizedUid, ['uid_foreign' => $originalReferencesUid]);

                            }
                            
                            // get columns from original ds and translate this
                            $sysFileRecord = Records::getRecord('sys_file_reference', $newUid);
                            
                            $tca = BackendUtility::getTcaFieldConfiguration($table, $tableColumn);
                            $translatedReferencesByLanguage = Records::getLocalizedUids($tca['foreign_table'], $newUid);

                            foreach ($targetLanguages as $language) {
                                $translatedColumns = [];
                                $translatedColumns += $translator->translateRecordProperties($sysFileRecord, (int)$language, $columnsSysFileLanguage);
                                if (count($translatedColumns) && isset($translatedReferencesByLanguage[(int)$language])) {
                                    Records::updateRecord('sys_file_reference', $translatedReferencesByLanguage[(int)$language], $translatedColumns);
                                }
                            }
                        }
                    }

                } else { // action == new && $table !== 'sys_file_reference

                    foreach ($datamapSysFileReferences as $uid) {

                        $newUid = $parentObject->substNEWwithIDs[$uid] ?? null;

                        $originalReferencesUid = current(Records::getRecords('sys_file_reference', 'uid', ["{$parentFieldSysFileReference} = '{$uid}'"]));
                        $sysFileRecord = Records::getRecord('sys_file_reference', (int)$uid);
                        if ($sysFileRecord === NULL) {
                            continue;
                        }
                        $sysFileRecordOriginal = Records::getRecord('sys_file_reference', $sysFileRecord[$parentFieldSysFileReference]);

                        $translatedColumns = [];
                        $translatedColumns += $translator->translateRecordProperties($sysFileRecordOriginal, $sysFileRecord['sys_language_uid'], $columnsSysFileLanguage);
                        
                        if (count($translatedColumns)) {
                            Records::updateRecord('sys_file_reference', $sysFileRecord['uid'], $translatedColumns);
                        }
                    }
                }
            }
        } elseif ($status == 'update') {
            $recordItem = $parentObject->datamap[$table][$recordUid];
            
            // try to translate items
            $targetLanguages = $translator->checkToTranslateUpdatedSysFileReference($parentObject->datamap, $table, $recordUid);

            // wird glaub nicht mehr benltigt?!!!!
            if ($recordItem['sys_language_uid'] === '0') {

               $targetLanguages = $recordParent[Translator::AUTOTRANSLATE_LANGUAGES] ?? [];
               $sysFileRecordOriginal = Records::getRecord('sys_file_reference', $recordUid);
               foreach ($targetLanguages as $targetLanguage) {

                   $sysFileRecordUid = current(Records::getRecords('sys_file_reference', 'uid', [$parentFieldSysFileReference . ' = ' . $recordUid, 'sys_language_uid = ' . $targetLanguage]));

                   $translatedColumns = [];
                   $translatedColumns += $translator->translateRecordProperties($sysFileRecordOriginal, (int)$targetLanguage, $columnsSysFileLanguage);
                   if (count($translatedColumns)) {
                       Records::updateRecord('sys_file_reference', $sysFileRecordUid, $translatedColumns);
                   }
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
        // Reenable auto translation after copy cammond has finished.
        if ($command === 'copy') {
            $this->suspended = false;
        }
    }
}
