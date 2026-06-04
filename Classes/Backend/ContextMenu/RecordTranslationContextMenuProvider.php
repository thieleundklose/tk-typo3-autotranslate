<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Backend\ContextMenu;

use ThieleUndKlose\Autotranslate\Service\RecordTranslationConfigurationService;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\RecordProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class RecordTranslationContextMenuProvider extends RecordProvider
{
    private ?RecordTranslationConfigurationService $recordTranslationConfigurationService = null;

    public function getPriority(): int
    {
        // Must be unique and lower than RecordProvider (60), otherwise our item
        // is either processed too early or overwritten by another custom provider.
        return 59;
    }

    public function addItems(array $items): array
    {
        if ($items === []) {
            return $items;
        }

        $this->initialize();
        if ($this->record === []) {
            return $items;
        }

        try {
            $configuration = $this->getRecordTranslationConfigurationService()->getConfiguration($this->table, $this->record);
        } catch (\Throwable) {
            $configuration = null;
        }

        if ($configuration === null) {
            return $items;
        }

        $autotranslateItems = $this->prepareItems([
            'autotranslateRecordDivider' => [
                'type' => 'divider',
            ],
            'autotranslateRecord' => [
                'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:record_translation.button',
                'iconIdentifier' => 'autotranslate-extension',
                'callbackAction' => 'triggerRecordTranslation',
            ],
        ]);

        return $this->insertItemsBefore($items, 'history', $autotranslateItems);
    }

    protected function canRender(string $itemName, string $type): bool
    {
        if ($itemName === 'autotranslateRecordDivider' && $type === 'divider') {
            return true;
        }

        if ($itemName === 'autotranslateRecord') {
            return !in_array($itemName, $this->disabledItems, true);
        }

        return parent::canRender($itemName, $type);
    }

    protected function getAdditionalAttributes(string $itemName): array
    {
        if ($itemName === 'autotranslateRecord') {
            return [
                'data-callback-module' => $this->getCallbackModulePath(),
            ];
        }

        return parent::getAdditionalAttributes($itemName);
    }

    /**
     * @param array<string, array<string, mixed>> $items
     * @param array<string, array<string, mixed>> $itemsToInsert
     * @return array<string, array<string, mixed>>
     */
    private function insertItemsBefore(array $items, string $beforeKey, array $itemsToInsert): array
    {
        if (!array_key_exists($beforeKey, $items)) {
            return $items + $itemsToInsert;
        }

        $result = [];
        foreach ($items as $key => $item) {
            if ($key === $beforeKey) {
                foreach ($itemsToInsert as $insertKey => $insertItem) {
                    $result[$insertKey] = $insertItem;
                }
            }
            $result[$key] = $item;
        }

        return $result;
    }

    private function getCallbackModulePath(): string
    {
        $absoluteFilePath = GeneralUtility::getFileAbsFileName(
            'EXT:autotranslate/Resources/Public/JavaScript/record-translation-context-menu.js'
        );

        return preg_replace(
            '/\.js$/',
            '',
            PathUtility::getAbsoluteWebPath($absoluteFilePath)
        ) ?: '';
    }

    private function getRecordTranslationConfigurationService(): RecordTranslationConfigurationService
    {
        return $this->recordTranslationConfigurationService ??= GeneralUtility::makeInstance(RecordTranslationConfigurationService::class);
    }
}
