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

namespace ThieleUndKlose\Autotranslate\Utility;

use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SlugUtility
{

    /**
     * Receive possible slug fields which should  be generated for new items.
     *
     * @param string $table
     * @return array|null
     */
    public static function slugFields(string $table): ?array 
    {

        $slugFields = array_filter($GLOBALS['TCA'][$table]['columns'], function($v) {
            return isset($v['config']['type']) && $v['config']['type'] == 'slug' ? true : false;
        });

        return $slugFields;
    }

    /**
     * @param array $record
     * @param string $tableName
     * @param string $field
     * @return string|null
     */
    public static function generateSlug(array $record, string $tableName, string $field): ?string
    {
        $slugFields = self::slugFields($tableName);

        if (empty($slugFields) || !isset($slugFields[$field]))
            return null;

        $fieldConfig = $slugFields[$field]['config'];

        $slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            $tableName,
            $field,
            $fieldConfig
        );

        $evalInfo = GeneralUtility::trimExplode(',', $fieldConfig['eval'], true);
        $state = RecordStateFactory::forName($tableName)->fromArray($record, $record['pid'], $record['uid']);

        // Generate slug
        $slug = $slugHelper->generate($record, (int)$record['pid']);

        // build slug depending on eval configuration
        if (in_array('uniqueInSite', $evalInfo)) {
            $slug = $slugHelper->buildSlugForUniqueInSite($slug, $state);
        } else if (in_array('uniqueInPid', $evalInfo)) {
            $slug = $slugHelper->buildSlugForUniqueInPid($slug, $state);
        } else if (in_array('unique', $evalInfo)) {
            $slug = $slugHelper->buildSlugForUniqueInTable($slug, $state);
        }

        return $slug;
    }
}
