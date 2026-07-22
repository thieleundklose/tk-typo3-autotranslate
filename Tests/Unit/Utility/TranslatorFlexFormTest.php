<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ThieleUndKlose\Autotranslate\Utility\Translator;

final class TranslatorFlexFormTest extends TestCase
{
    private array $tcaBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tcaBackup = $GLOBALS['TCA'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['TCA'] = $this->tcaBackup;
        parent::tearDown();
    }

    public function testFlexFormFieldConfigsResolveDirectStringDataStructure(): void
    {
        $GLOBALS['TCA']['tt_content'] = [
            'ctrl' => [
                'type' => 'CType',
            ],
            'columns' => [
                'tx_siteautotranslate_flexform' => [
                    'config' => [
                        'type' => 'flex',
                        'ds' => $this->flexFormDataStructureXml('settings.plainText'),
                    ],
                ],
            ],
        ];

        self::assertSame(
            ['settings.plainText' => ['richtext' => false]],
            $this->flexFormTranslationFieldConfigs([
                'CType' => 'text',
            ], 'tt_content', 'tx_siteautotranslate_flexform')
        );
    }

    public function testFlexFormFieldConfigsResolveTypeSpecificColumnsOverride(): void
    {
        $GLOBALS['TCA']['tt_content'] = [
            'ctrl' => [
                'type' => 'CType',
            ],
            'columns' => [
                'pi_flexform' => [
                    'config' => [
                        'type' => 'flex',
                    ],
                ],
            ],
            'types' => [
                'text' => [
                    'columnsOverrides' => [
                        'pi_flexform' => [
                            'config' => [
                                'ds' => $this->flexFormDataStructureXml('settings.text', true),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertSame(
            ['settings.text' => ['richtext' => true]],
            $this->flexFormTranslationFieldConfigs([
                'CType' => 'text',
            ], 'tt_content', 'pi_flexform')
        );
    }

    public function testFlexFormFieldConfigsStillResolveLegacyDefaultArrayDataStructure(): void
    {
        $GLOBALS['TCA']['tt_content'] = [
            'columns' => [
                'pi_flexform' => [
                    'config' => [
                        'type' => 'flex',
                        'ds' => [
                            'default' => $this->flexFormDataStructureXml('settings.legacy'),
                        ],
                    ],
                ],
            ],
        ];

        self::assertSame(
            ['settings.legacy' => ['richtext' => false]],
            $this->flexFormTranslationFieldConfigs([], 'tt_content', 'pi_flexform')
        );
    }

    private function flexFormTranslationFieldConfigs(array $record, string $table, string $columnName): array
    {
        $reflection = new ReflectionClass(Translator::class);
        $translator = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('flexFormTranslationFieldConfigs');

        return $method->invoke($translator, $record, $table, $columnName);
    }

    private function flexFormDataStructureXml(string $fieldName, bool $richtext = false): string
    {
        $richtextConfiguration = $richtext ? '<enableRichtext>1</enableRichtext>' : '';

        return <<<XML
<T3DataStructure>
    <sheets>
        <sDEF>
            <ROOT>
                <type>array</type>
                <el>
                    <$fieldName>
                        <config>
                            <type>text</type>
                            $richtextConfiguration
                        </config>
                    </$fieldName>
                    <link>
                        <config>
                            <type>input</type>
                            <renderType>inputLink</renderType>
                        </config>
                    </link>
                </el>
            </ROOT>
        </sDEF>
    </sheets>
</T3DataStructure>
XML;
    }
}
