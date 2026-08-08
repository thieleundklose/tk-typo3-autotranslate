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

namespace ThieleUndKlose\Autotranslate\Event;

/**
 * Allows project-specific post-processing of translated record columns.
 *
 * The event is dispatched after DeepL results have been mapped back to record
 * columns and after configured copy fields have been applied, but before
 * AutoTranslate writes its internal l10n_state and autotranslate timestamp.
 */
final class AfterTranslateEvent
{
    private array $translatedColumns;
    private array $sourceRecord;
    private int $targetLanguageUid;
    private string $table;
    private int $localizedUid;
    private ?string $deeplSourceLang;
    private ?string $deeplTargetLang;

    public function __construct(
        array $translatedColumns,
        array $sourceRecord,
        int $targetLanguageUid,
        string $table,
        int $localizedUid,
        ?string $deeplSourceLang,
        ?string $deeplTargetLang
    ) {
        $this->translatedColumns = $translatedColumns;
        $this->sourceRecord = $sourceRecord;
        $this->targetLanguageUid = $targetLanguageUid;
        $this->table = $table;
        $this->localizedUid = $localizedUid;
        $this->deeplSourceLang = $deeplSourceLang;
        $this->deeplTargetLang = $deeplTargetLang;
    }

    public function getTranslatedColumns(): array
    {
        return $this->translatedColumns;
    }

    public function setTranslatedColumns(array $translatedColumns): void
    {
        $this->translatedColumns = $translatedColumns;
    }

    public function hasTranslatedColumn(string $columnName): bool
    {
        return array_key_exists($columnName, $this->translatedColumns);
    }

    public function getTranslatedColumn(string $columnName)
    {
        return $this->translatedColumns[$columnName] ?? null;
    }

    public function setTranslatedColumn(string $columnName, $value): void
    {
        $this->translatedColumns[$columnName] = $value;
    }

    public function unsetTranslatedColumn(string $columnName): void
    {
        unset($this->translatedColumns[$columnName]);
    }

    public function getSourceRecord(): array
    {
        return $this->sourceRecord;
    }

    public function getTargetLanguageUid(): int
    {
        return $this->targetLanguageUid;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getLocalizedUid(): int
    {
        return $this->localizedUid;
    }

    public function getDeeplSourceLang(): ?string
    {
        return $this->deeplSourceLang;
    }

    public function getDeeplTargetLang(): ?string
    {
        return $this->deeplTargetLang;
    }
}
