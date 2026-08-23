<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace ThieleUndKlose\Autotranslate\ValueObject;

final class TranslationResult
{
    private int $translatedFieldCount = 0;

    /** @var string[] */
    private array $skippedReasons = [];

    /** @var string[] */
    private array $errors = [];

    public static function skipped(string $reason): self
    {
        $result = new self();
        $result->addSkippedReason($reason);

        return $result;
    }

    public function addTranslatedFields(int $count): void
    {
        $this->translatedFieldCount += max(0, $count);
    }

    public function addSkippedReason(string $reason): void
    {
        $reason = trim($reason);
        if ($reason !== '' && !in_array($reason, $this->skippedReasons, true)) {
            $this->skippedReasons[] = $reason;
        }
    }

    public function addError(string $error): void
    {
        $error = trim($error);
        if ($error !== '' && !in_array($error, $this->errors, true)) {
            $this->errors[] = $error;
        }
        $this->addSkippedReason($error);
    }

    public function merge(self $result): void
    {
        $this->addTranslatedFields($result->getTranslatedFieldCount());
        foreach ($result->getSkippedReasons() as $reason) {
            $this->addSkippedReason($reason);
        }
        foreach ($result->getErrors() as $error) {
            $this->addError($error);
        }
    }

    public function getTranslatedFieldCount(): int
    {
        return $this->translatedFieldCount;
    }

    public function hasTranslations(): bool
    {
        return $this->translatedFieldCount > 0;
    }

    /** @return string[] */
    public function getSkippedReasons(): array
    {
        return $this->skippedReasons;
    }

    public function getSkippedReasonSummary(): string
    {
        return implode('; ', $this->skippedReasons);
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorSummary(): string
    {
        return implode('; ', $this->errors);
    }
}
