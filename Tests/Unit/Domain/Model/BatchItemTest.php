<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Domain\Model;

use DateTime;
use PHPUnit\Framework\TestCase;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;

final class BatchItemTest extends TestCase
{
    public function testSuccessfulRecurringRunIsReportedWhileWaitingForNextExecution(): void
    {
        $item = $this->createRecurringItem(new DateTime('+1 hour'));
        $item->setTranslated(new DateTime('-1 hour'));

        self::assertTrue($item->isSuccessfulRecurringRun());
    }

    public function testRecurringRunWithoutCompletedTranslationIsNotReportedAsSuccessful(): void
    {
        $item = $this->createRecurringItem(new DateTime('+1 hour'));

        self::assertFalse($item->isSuccessfulRecurringRun());
    }

    public function testRecurringRunWithErrorIsNotReportedAsSuccessful(): void
    {
        $item = $this->createRecurringItem(new DateTime('+1 hour'));
        $item->setTranslated(new DateTime('-1 hour'));
        $item->setError('DeepL request failed.');

        self::assertFalse($item->isSuccessfulRecurringRun());
    }

    public function testRecurringRunIsNoLongerReportedAsSuccessfulWhenNextExecutionIsDue(): void
    {
        $item = $this->createRecurringItem(new DateTime('-1 hour'));
        $item->setTranslated(new DateTime('-2 hours'));

        self::assertFalse($item->isSuccessfulRecurringRun());
    }

    private function createRecurringItem(DateTime $nextExecution): BatchItem
    {
        $item = new BatchItem();
        $item->setFrequency(BatchItem::FREQUENCY_DAILY);
        $item->setTranslate($nextExecution);

        return $item;
    }
}
