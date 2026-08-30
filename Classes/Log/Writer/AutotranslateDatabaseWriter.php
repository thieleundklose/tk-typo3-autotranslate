<?php

namespace ThieleUndKlose\Autotranslate\Log\Writer;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Writer\AbstractWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class AutotranslateDatabaseWriter extends AbstractWriter
{
    private const LOG_TABLE = 'tx_autotranslate_log';

    public function writeLog(LogRecord $record)
    {
        try {
            if (!GeneralUtility::getContainer()->get('boot.state')->complete) {
                return $this;
            }
        } catch (\LogicException $exception) {
            return $this;
        }

        $context = $record->getData();
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $context['exception'] = (string)$context['exception'];
        }

        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::LOG_TABLE)
            ->insert(
                self::LOG_TABLE,
                [
                    'request_id' => $record->getRequestId(),
                    'time_micro' => $record->getCreated(),
                    'component' => $record->getComponent(),
                    'level' => LogLevel::normalizeLevel($record->getLevel()),
                    'message' => $record->getMessage(),
                    'data' => $context === [] ? '' : json_encode($context),
                ]
            );

        return $this;
    }
}
