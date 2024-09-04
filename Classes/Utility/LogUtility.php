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

use Psr\Log\LoggerInterface;

class LogUtility
{

    public const MESSAGE_INFO = 0;
    public const MESSAGE_WARNING = 1;
    public const MESSAGE_ERROR = 2;

    /**
     * Write a log message.
     *
     * @param string $message
     * @param array $data
     * @return void
     */
    public static function log(LoggerInterface $logger, string $message, array $data = [], int $type = self::MESSAGE_INFO): void
    {
        switch ($type) {
            case self::MESSAGE_INFO:
                    $logger->info($message, $data);
                break;
            case self::MESSAGE_WARNING:
                    $logger->warning($message, $data);
                break;
            case self::MESSAGE_ERROR:
                    $logger->error($message, $data);
                break;
        }

    }

    /**
     * See https://www.php-fig.org/psr/psr-3/
     * Interpolates context values into the message placeholders.
     * @param string $message
     * @param array $context
     * @return string
     */
    public static function interpolate(string $message, array $context = []): string
    {
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            // check that the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

}
