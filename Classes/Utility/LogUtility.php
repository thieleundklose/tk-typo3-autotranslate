<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class LogUtility
{
    public const MESSAGE_INFO = 0;
    public const MESSAGE_WARNING = 1;
    public const MESSAGE_ERROR = 2;

    /**
     * Write a log message if debug mode is enabled.
     */
    public static function log(?LoggerInterface $logger, string $message, array $data = [], int $type = self::MESSAGE_INFO): void
    {
        if ($logger === null) {
            return;
        }

        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('autotranslate');
        if (!($extensionConfiguration['debug'] ?? false)) {
            return;
        }

        match ($type) {
            self::MESSAGE_WARNING => $logger->warning($message, $data),
            self::MESSAGE_ERROR => $logger->error($message, $data), // @extensionScannerIgnoreLine
            default => $logger->info($message, $data),
        };
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @see https://www.php-fig.org/psr/psr-3/
     */
    public static function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }
}
