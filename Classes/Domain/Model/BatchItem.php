<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Domain\Model;

use DateTime;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use DateInterval;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;

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

class BatchItem extends AbstractEntity
{

    public const PRIORITY_LOW = '01_low';
    public const PRIORITY_MEDIUM = '02_medium';
    public const PRIORITY_HIGH = '03_high';

    public const FREQUENCY_ONCE = 'once';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_RECURRING = 'recurring';

    /**
     * @var int
     */
    protected int $sysLanguageUid = 0;

    /**
     * @var string
     */
    protected string $priority = self::PRIORITY_MEDIUM;

    /**
     * @var \DateTime
     */
    protected $translate;

    /**
     * @var \DateTime
     */
    protected $translated;

    /**
     * @var string
     */
    protected string $mode = Translator::TRANSLATE_MODE_BOTH;

    /**
     * @var string
     */
    protected string $frequency = '';

    /**
     * @var string
     */
    protected string $error = '';

    /**
     * @var bool
     */
    protected $hidden = false;

    /**
     * Get the value of sysLanguageUid
     *
     * @return int
     */
    public function getSysLanguageUid(): int
    {
        return $this->sysLanguageUid;
    }

    /**
     * Set the value of sysLanguageUid
     *
     * @param int $sysLanguageUid
     * @return void
     */
    public function setSysLanguageUid(int $sysLanguageUid): void
    {
        $this->sysLanguageUid = $sysLanguageUid;
    }

    /**
     * Get the value of priority
     *
     * @return string
     */
    public function getPriority(): string
    {
        return $this->priority;
    }

    /**
     * Set the value of priority
     *
     * @param string $priority
     * @return void
     */
    public function setPriority(string $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * Get the value of translate
     *
     * @return \DateTime
     */
    public function getTranslate(): \DateTime
    {
        return $this->translate;
    }

    /**
     * Set the value of translate
     *
     * @param \DateTime $translate
     * @return void
     */
    public function setTranslate(\DateTime $translate): void
    {
        $this->translate = $translate;
    }

    /**
     * Get the value of translated
     *
     * @return \DateTime|null
     */
    public function getTranslated(): ?\DateTime
    {
        return $this->translated;
    }

    /**
     * Set the value of translated
     *
     * @param \DateTime|null $translated
     * @return void
     */
    public function setTranslated(?\DateTime $translated = null): void
    {
        $this->translated = $translated;
    }

    /**
     * Get the value of mode
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Set the value of mode
     *
     * @param string $mode
     * @return void
     */
    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Get the value of frequency
     *
     * @return string
     */
    public function getFrequency(): string
    {
        return $this->frequency;
    }

    /**
     * Set the value of frequency
     *
     * @param string $frequency
     * @return void
     */
    public function setFrequency(string $frequency): void
    {
        $this->frequency = $frequency;
    }

    /**
     * Get the value of error
     *
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * Set the value of error
     *
     * @param string $error
     * @return void
     */
    public function setError(string $error): void
    {
        $this->error = $error;
    }

        /**
     * Get hidden flag
     *
     * @return bool
     */
    public function getHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Set hidden flag
     *
     * @param bool $hidden hidden flag
     */
    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    /**
     * Get the page title of pid
     *
     * @return string
     */
    public function getPageTitle(): string
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $page = $pageRepository->getPage($this->pid);
        return trim(($page['title'] ?? '') . ' [' . $this->pid . ']');
    }

    /**
     * Get the title of sysLanguageUid
     *
     * @return string
     */
    public function getSysLanguageTitle(): string
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByPageId($this->pid);
            foreach ($site->getAllLanguages() as $siteLanguage) {
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

    /**
     * @return bool
     */
    public function isExecutable(): bool
    {
        return $this->getError() === '';
    }

    /**
     * @return bool
     */
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

    /**
     * @return bool
     */
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

    /**
     * @return bool
     */
    public function isFinishedRun(): bool
    {
        if ($this->getTranslated() > $this->getTranslate()) {
            return true;
        }

        return false;
    }

    /**
     * Set the value of translated
     *
     * @return void
     */
    public function markAsTranslated(): void
    {
        $this->translated = new \DateTime();
        $this->setNextTranslationDate();
    }

    /**
     * Set next translation date and return true if there is a next translation date
     *
     * @return boolean
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
