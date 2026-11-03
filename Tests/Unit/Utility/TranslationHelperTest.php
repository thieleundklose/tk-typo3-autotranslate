<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;

final class TranslationHelperTest extends TestCase
{
    public function testExtractChangedFieldsReturnsNullForNewRecords(): void
    {
        self::assertNull(TranslationHelper::extractChangedFieldsFromDatamap('new', [
            'title' => 'Hello',
            'hidden' => 0,
        ]));
    }

    public function testExtractChangedFieldsReturnsDatamapKeysForUpdates(): void
    {
        self::assertSame(
            ['title', 'hidden'],
            TranslationHelper::extractChangedFieldsFromDatamap('update', [
                'title' => 'World',
                'hidden' => 1,
            ])
        );
    }

    public function testExtractChangedFieldsReturnsEmptyArrayForUpdateWithoutFields(): void
    {
        self::assertSame([], TranslationHelper::extractChangedFieldsFromDatamap('update', []));
    }

    public function testExtractChangedFieldsIgnoresSubmittedValuesThatDidNotChange(): void
    {
        self::assertSame(
            [],
            TranslationHelper::extractChangedFieldsFromDatamap(
                'update',
                ['header' => 'Unchanged headline', 'hidden' => 0],
                ['header' => 'Unchanged headline', 'hidden' => '0']
            )
        );
    }

    public function testExtractChangedFieldsReturnsOnlyValuesThatActuallyChanged(): void
    {
        self::assertSame(
            ['header'],
            TranslationHelper::extractChangedFieldsFromDatamap(
                'update',
                ['header' => 'New headline', 'bodytext' => 'Unchanged body'],
                ['header' => 'Old headline', 'bodytext' => 'Unchanged body']
            )
        );
    }

    public function testFilterChangedTranslatableColumnsReturnsAllColumnsWhenChangedFieldsAreNull(): void
    {
        self::assertSame(
            ['title', 'description'],
            TranslationHelper::filterChangedTranslatableColumns(['title', 'description'], null)
        );
    }

    public function testFilterChangedTranslatableColumnsReturnsOnlyChangedTranslatableColumns(): void
    {
        self::assertSame(
            ['title'],
            TranslationHelper::filterChangedTranslatableColumns(
                ['title', 'description', 'teaser'],
                ['title', 'hidden', 'tstamp']
            )
        );
    }

    public function testFilterChangedTranslatableColumnsReturnsEmptyArrayForStatusOnlyUpdates(): void
    {
        self::assertSame(
            [],
            TranslationHelper::filterChangedTranslatableColumns(
                ['title', 'description'],
                ['hidden', 'starttime', 'endtime']
            )
        );
    }

    public function testFilterChangedTranslatableColumnsPreservesConfiguredColumnOrder(): void
    {
        self::assertSame(
            ['title', 'description'],
            TranslationHelper::filterChangedTranslatableColumns(
                ['title', 'teaser', 'description'],
                ['description', 'title']
            )
        );
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    public function testResolveSitePageIdKeepsAPageIdThatIsSet(): void
    {
        self::assertSame(42, TranslationHelper::resolveSitePageId(42, 'tt_content'));
    }

    public function testResolveSitePageIdKeepsTheRootLevelForAPageRecord(): void
    {
        $this->registerSites(7);

        self::assertSame(0, TranslationHelper::resolveSitePageId(0, 'pages'));
    }

    public function testResolveSitePageIdUsesTheRootPageOfTheOnlySiteForARootLevelRecord(): void
    {
        $this->registerSites(7);

        self::assertSame(7, TranslationHelper::resolveSitePageId(0, 'sys_category'));
    }

    public function testResolveSitePageIdReturnsZeroWithSeveralSites(): void
    {
        $this->registerSites(7, 9);

        self::assertSame(0, TranslationHelper::resolveSitePageId(0, 'sys_category'));
    }

    public function testResolveSitePageIdReturnsZeroWithoutSites(): void
    {
        $this->registerSites();

        self::assertSame(0, TranslationHelper::resolveSitePageId(0, 'sys_category'));
    }

    private function registerSites(int ...$rootPageIds): void
    {
        $sites = [];
        foreach ($rootPageIds as $rootPageId) {
            $site = $this->createStub(Site::class);
            $site->method('getRootPageId')->willReturn($rootPageId);
            $sites['site' . $rootPageId] = $site;
        }

        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn($sites);
        GeneralUtility::addInstance(SiteFinder::class, $siteFinder);
    }
}
