<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class FlashMessageUtility
{
    public const MESSAGE_NOTICE = -2;
    public const MESSAGE_INFO = -1;
    public const MESSAGE_OK = 0;
    public const MESSAGE_WARNING = 1;
    public const MESSAGE_ERROR = 2;

    /**
     * Add a flash message to the global queue.
     */
    public static function addMessage(string $message, string $title = '', int $severity = self::MESSAGE_OK): void
    {
        $severity = self::adjustSeverityForTypo3Version($severity);

        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity,
            true
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($flashMessage);
    }

    /**
     * Adjusts the severity level to ContextualFeedbackSeverity enum for TYPO3 13+.
     */
    public static function adjustSeverityForTypo3Version(int $severity): ContextualFeedbackSeverity
    {
        return match ($severity) {
            self::MESSAGE_NOTICE => ContextualFeedbackSeverity::NOTICE,
            self::MESSAGE_INFO => ContextualFeedbackSeverity::INFO,
            self::MESSAGE_OK => ContextualFeedbackSeverity::OK,
            self::MESSAGE_WARNING => ContextualFeedbackSeverity::WARNING,
            self::MESSAGE_ERROR => ContextualFeedbackSeverity::ERROR,
            default => throw new \InvalidArgumentException('Invalid severity level'),
        };
    }
}
