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

namespace ThieleUndKlose\Autotranslate\UserFunction\FormEngine;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileMetadataLanguagesItems
{
    /**
     * Provide target language checkboxes from the file metadata language mapping.
     *
     * Only target languages are listed; language uid 0 is used as source and
     * localized metadata records do not expose the selection field.
     */
    public function itemsProcFunc(array &$config, &$pObj): void
    {
        $row = $config['row'] ?? [];
        if (isset($row['sys_language_uid']) && !is_array($row['sys_language_uid']) && (int)$row['sys_language_uid'] > 0) {
            return;
        }

        foreach ($this->languageMapping() as $languageUid => $deeplLanguageCode) {
            if ($languageUid <= 0) {
                continue;
            }
            $config['items'][] = [
                sprintf('Language UID %d (%s)', $languageUid, $deeplLanguageCode),
                $languageUid,
            ];
        }
    }

    /**
     * Read the explicit TYPO3 language uid to DeepL language code mapping.
     *
     * @return array<int, string>
     */
    private function languageMapping(): array
    {
        try {
            $mappingConfiguration = (string)GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('autotranslate', 'fileMetadataLanguageMapping');
        } catch (\Exception $e) {
            return [];
        }

        $mapping = [];
        $items = GeneralUtility::trimExplode(',', $mappingConfiguration, true);
        foreach ($items as $item) {
            $parts = GeneralUtility::trimExplode('=', $item, true, 2);
            if (count($parts) !== 2 || !is_numeric($parts[0]) || $parts[1] === '') {
                continue;
            }
            $mapping[(int)$parts[0]] = strtoupper(str_replace('_', '-', $parts[1]));
        }

        return $mapping;
    }
}
