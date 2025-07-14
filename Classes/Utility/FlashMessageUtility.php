<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class FlashMessageUtility
{
    const MESSAGE_NOTICE = -2;
    const MESSAGE_INFO = -1;
    const MESSAGE_OK = 0;
    const MESSAGE_WARNING = 1;
    const MESSAGE_ERROR = 2;

    /**
     * Add a flash message to the global queue.
     *
     * @param string $message The message content
     * @param string $title The message title
     * @param int $severity The severity level (use FlashMessage constants)
     * @return void
     */
    public static function addMessage(string $message, string $title = '', int $severity = self::MESSAGE_OK): void
    {
        // Adjust severity based on TYPO3 version
        $severity = self::adjustSeverityForTypo3Version($severity);


        // Create the FlashMessage
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity,
            true
        );

        // Add the FlashMessage to the FlashMessageQueue
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($flashMessage);
    }

    /**
     * Adjusts the severity level based on the TYPO3 version.
     *
     * @param int $severity The original severity level (FlashMessage constants)
     * @return int|ContextualFeedbackSeverity The adjusted severity level
     */
    public static function adjustSeverityForTypo3Version(int $severity): int|ContextualFeedbackSeverity
    {
        $typo3Version = GeneralUtility::makeInstance(Typo3Version::class);
        $majorVersion = $typo3Version->getMajorVersion();

        // Map severity for TYPO3 >= 12
        if ($majorVersion >= 12) {
            return self::mapSeverity($severity);
        }

        return $severity;
    }

    /**
     * Map old FlashMessage constants to new ContextualFeedbackSeverity constants for TYPO3 >= 12.
     *
     * @param int $oldSeverity
     * @return ContextualFeedbackSeverity
     */
    private static function mapSeverity(int $oldSeverity): ContextualFeedbackSeverity
    {
        switch ($oldSeverity) {
            case self::MESSAGE_NOTICE:
                return ContextualFeedbackSeverity::NOTICE;
            case self::MESSAGE_INFO:
                return ContextualFeedbackSeverity::INFO;
            case self::MESSAGE_OK:
                return ContextualFeedbackSeverity::OK;
            case self::MESSAGE_WARNING:
                return ContextualFeedbackSeverity::WARNING;
            case self::MESSAGE_ERROR:
                return ContextualFeedbackSeverity::ERROR;
            default:
                throw new \InvalidArgumentException('Invalid severity level');
        }
    }
}
