<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Property\TypeConverter;

use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\AbstractTypeConverter;

class DateTimeConverter extends AbstractTypeConverter
{
    /**
     * @var array<string>
     */
    protected $sourceTypes = ['string'];

    /**
     * @var string
     */
    protected $targetType = 'DateTime';

    /**
     * @var int
     */
    protected $priority = 10;

    /**
     * Convert from $source to $targetType, a noop if they are the same.
     *
     * @param mixed $source
     * @param string $targetType
     * @param array $convertedChildProperties
     * @param PropertyMappingConfigurationInterface|null $configuration
     * @return mixed
     */
    public function convertFrom($source, $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null)
    {
        return \DateTime::createFromFormat('H:i d-m-Y', $source);
    }
}
