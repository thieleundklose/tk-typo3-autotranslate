<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Domain\Model;

use DateTime;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

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

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    public const TYPE_TRANSLATION_ADD_NEW = 0;
    public const TYPE_TRANSLATION_OVERWRITE_EXISTING = 1;

    public const FREQUENCY_ONCE = 'once';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_DAILY = 'daily';

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
     * @var int
     */
    protected int $type = 0;

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
     * @param \DateTime $translated
     * @return void
     */
    public function setTranslated(\DateTime $translated): void
    {
        $this->translated = $translated;
    }

    /**
     * Get the value of type
     *
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Set the value of type
     *
     * @param int $type
     * @return void
     */
    public function setType(int $type): void
    {
        $this->type = $type;
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
        return $page['title'];
    }

    /**
     * Get the title of sysLanguageUid
     *
     * @return string
     */
    public function getSysLanguageTitle(): string
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($this->pid);

        foreach ($site->getAllLanguages() as $siteLanguage) {
            if ($siteLanguage->getLanguageId() === $this->getSysLanguageUid()) {
                return $siteLanguage->getTitle();
            }
        }
        return 'not found';
    }

    /**
     * Get the value of frequency
     *
     * @return string|null
     */
    public function getFrequencyDateInterval(): ?string
    {
        // TODO make this extensible
        switch ($this->getFrequency()) {
            case self::FREQUENCY_DAILY:
                return '1d';
            break;
            case self::FREQUENCY_WEEKLY:
                return '1w';
            break;
        }
        return null;
    }
}
