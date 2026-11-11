<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Functional\Utility;

use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The mode "Create new (missing) translation only" keeps an existing
 * translation and still creates a missing one.
 *
 * No DeepL API key is configured, so a translation that is attempted ends in
 * an "invalid API key" error. That error is how these tests see whether the
 * translator was reached at all.
 */
final class TranslatorCreateOnlyModeTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'frontend',
    ];

    protected array $testExtensionsToLoad = [
        'thieleundklose/autotranslate',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/create_only.csv');
        $this->writeSiteConfiguration();
    }

    public function testAnExistingTranslationIsKept(): void
    {
        $result = $this->translator()->translateWithResult('tt_content', 10, null, '1', Translator::TRANSLATE_MODE_CREATE_ONLY);

        self::assertFalse($result->hasErrors(), 'The translator must not be reached: ' . $result->getErrorSummary());
        self::assertStringContainsString('create-only', $result->getSkippedReasonSummary());
        self::assertSame('Hallo, von Hand korrigiert', $this->header(11));
    }

    public function testTheOtherModesStillTranslateAnExistingTranslation(): void
    {
        foreach ([Translator::TRANSLATE_MODE_BOTH, Translator::TRANSLATE_MODE_UPDATE_ONLY] as $mode) {
            $result = $this->translator()->translateWithResult('tt_content', 10, null, '1', $mode);

            self::assertTrue($result->hasErrors(), $mode . ' must reach the translator');
            self::assertStringContainsString('API Key', $result->getErrorSummary());
        }
    }

    public function testAMissingTranslationIsStillCreated(): void
    {
        $result = $this->translator()->translateWithResult('tt_content', 20, null, '1', Translator::TRANSLATE_MODE_CREATE_ONLY);

        self::assertStringNotContainsString('create-only', $result->getSkippedReasonSummary());
        self::assertTrue($result->hasErrors(), 'A missing translation must reach the translator');
        self::assertStringContainsString('API Key', $result->getErrorSummary());
    }

    private function translator(): Translator
    {
        return GeneralUtility::makeInstance(Translator::class, 1);
    }

    private function header(int $uid): string
    {
        return (string)$this->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->select(['header'], 'tt_content', ['uid' => $uid])
            ->fetchOne();
    }

    private function writeSiteConfiguration(): void
    {
        $directory = Environment::getConfigPath() . '/sites/autotranslate';
        GeneralUtility::mkdir_deep($directory);
        GeneralUtility::writeFile($directory . '/config.yaml', implode("\n", [
            'rootPageId: 1',
            "base: 'http://localhost/'",
            'languages:',
            '  -',
            '    languageId: 0',
            "    title: English",
            "    locale: en_US.UTF-8",
            "    base: /",
            '    deeplSourceLang: EN',
            '  -',
            '    languageId: 1',
            "    title: Deutsch",
            "    locale: de_DE.UTF-8",
            "    base: /de/",
            '    deeplTargetLang: DE',
            'autotranslateTtContentEnabled: 1',
            "autotranslateTtContentLanguages: '1'",
            "autotranslateTtContentTextfields: 'header'",
            '',
        ]));
    }
}
