<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Domain\Model;

use DateInterval;
use DateTime;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class BatchItem extends AbstractEntity
{
    public const PRIORITY_LOW = '01_low';
    public const PRIORITY_MEDIUM = '02_medium';
    public const PRIORITY_HIGH = '03_high';

    public const FREQUENCY_ONCE = 'once';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_RECURRING = 'recurring';

    protected int $sysLanguageUid = 0;

    protected string $priority = self::PRIORITY_MEDIUM;

    protected ?\DateTime $translate = null;

    protected ?\DateTime $translated = null;

    protected string $mode = Translator::TRANSLATE_MODE_BOTH;

    protected string $frequency = '';

    protected string $error = '';

    protected bool $hidden = false;

    public function getSysLanguageUid(): int
    {
        return $this->sysLanguageUid;
    }

    public function setSysLanguageUid(int $sysLanguageUid): void
    {
        $this->sysLanguageUid = $sysLanguageUid;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): void
    {
        $this->priority = $priority;
    }

    public function getTranslate(): ?\DateTime
    {
        return $this->translate;
    }

    public function setTranslate(\DateTime $translate): void
    {
        $this->translate = $translate;
    }

    public function getTranslated(): ?\DateTime
    {
        return $this->translated;
    }

    public function setTranslated(?\DateTime $translated = null): void
    {
        $this->translated = $translated;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): void
    {
        $this->frequency = $frequency;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function setError(string $error): void
    {
        $this->error = $error;
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    /**
     * Get the page title of pid
     */
    public function getPageTitle(): string
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $page = $pageRepository->getPage($this->pid);
        return trim(($page['title'] ?? '') . ' [' . $this->pid . ']');
    }

    /**
     * Get the title of sysLanguageUid
     */
    public function getSysLanguageTitle(): string
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByPageId($this->pid);
            foreach ($site->getAllLanguages() as $siteLanguage) {
                // @extensionScannerIgnoreLine
                if ($siteLanguage->getLanguageId() === $this->getSysLanguageUid()) {
                    return $siteLanguage->getTitle();
                }
            }
        } catch (SiteNotFoundException $e) {
        }
        return 'not found';
    }

    /**
     * Get the value of frequency and return it as DateInterval
     */
    public function getFrequencyDateInterval(): ?DateInterval
    {
        return match ($this->getFrequency()) {
            self::FREQUENCY_RECURRING => DateInterval::createFromDateString('1 second'),
            self::FREQUENCY_DAILY => DateInterval::createFromDateString('1 day'),
            self::FREQUENCY_WEEKLY => DateInterval::createFromDateString('1 week'),
            default => null,
        };
    }

    public function isExecutable(): bool
    {
        return $this->getError() === '';
    }

    public function isRecurring(): bool
    {
        $now = new \DateTime();

        if ($this->getTranslated() > $this->getTranslate()) {
            return false;
        }

        if ($now > $this->getTranslate()) {
            return false;
        }

        if ($this->getFrequency() === self::FREQUENCY_ONCE) {
            return false;
        }

        return true;
    }

    public function isWaitingForRun(): bool
    {
        $now = new \DateTime();

        if (!empty($this->getError())) {
            return false;
        }

        if ($this->isFinishedRun()) {
            return false;
        }

        return $now > $this->getTranslate();
    }

    public function isFinishedRun(): bool
    {
        if ($this->getTranslated() > $this->getTranslate()) {
            return true;
        }

        return false;
    }

    public function markAsTranslated(): void
    {
        $this->translated = new \DateTime();
        $this->setNextTranslationDate();
    }

    /**
     * Set next translation date and return true if there is a next translation date
     */
    public function setNextTranslationDate(): bool
    {
        if ($this->getFrequencyDateInterval() !== null) {
            $this->translate = new \DateTime();
            $this->translate->add($this->getFrequencyDateInterval());

            return true;
        }

        return false;
    }
}
