<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Functional\Utility;

use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\SlugUtility;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SlugUtilityTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'thieleundklose/autotranslate',
    ];

    public function testPageSlugGenerationUsesCurrentCoreSlugHelper(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/pages.csv');
        $page = Records::getRecord('pages', 2);

        self::assertIsArray($page);
        self::assertSame('/active-page', SlugUtility::generateSlug(
            $page,
            'pages',
            'slug',
            ['slug' => ['config' => [
                'type' => 'slug',
                'generatorOptions' => ['fields' => ['title'], 'prefixParentPageSlug' => true],
                'fallbackCharacter' => '-',
            ]]]
        ));
    }

    public function testPageSlugUpdateUsesCurrentCoreDataHandler(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/pages.csv');
        $siteWriter = class_exists(SiteWriter::class) ? SiteWriter::class : SiteConfiguration::class;
        GeneralUtility::makeInstance($siteWriter)->write('slug-test', [
            'rootPageId' => 1,
            'base' => 'https://example.test/',
            'languages' => [[
                'title' => 'English',
                'enabled' => true,
                'languageId' => 0,
                'base' => '/',
                'locale' => 'en_US.UTF-8',
            ]],
        ]);
        $this->getConnectionPool()->getConnectionForTable('be_users')->insert('be_users', [
            'uid' => 1,
            'username' => 'admin',
            'admin' => 1,
            'disable' => 0,
            'deleted' => 0,
        ]);
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);

        self::assertTrue(SlugUtility::updatePageSlug(2, '/renamed-page'));
        self::assertSame('/renamed-page', Records::getRecord('pages', 2, 'slug'));
    }
}
