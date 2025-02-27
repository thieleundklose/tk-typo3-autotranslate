# Autotranslate

## Why we build this Extension

The aim of this extension is to support the editors in the content management process through automated translations of content elements in the backend.
This not only saves time, but also prevents possible errors in content maintenance.

## Features

DeepL autotranslator is a powerful tool for editors with many features, especially in terms of configuration.

* Automatically translate websites, content items, file references and messages
* Multiple languages can be translated
* Translatable text fields can be configured
* The languages to be translated can be configured
* All configurations (including DeepL access) are anchored in the TYPO3 page configurations
* Backend module to handle “BatchTranslation” jobs
* Symfony command/scheduler task to handle scheduled translations for automatic handling of translations based on predefined plans

## Compatibility

| Autotranslate | TYPO3     | PHP          | DeepL PHP | Notes                                     |
|---------------|-----------|--------------|-----------|-------------------------------------------|
| 2.x           | 11 - 13   | 7.4 - 8.4.99 | 1.4 - 1.x | BatchTranslation now for none admin users |
| 1.x           | 10 - 13   | 7.4 - 8.3.99 | 1.4       |

The DeepL Autotranslate extension is available in the version for TYPO3 v10, v11, v12 and v13. Translations for news articles of the extension [News](https://extensions.typo3.org/extension/news) are also already integrated.

## Documentation

* [Installation](Documentation/Installation/Readme.md)
* [Configuration](Documentation/Configuration/Readme.md)
* [Product Website](https://www.thieleklose.de/referenzen/typo3-autotranslate)

## Authors
- [Mike Zimmer](https://github.com/mikezimmer-tuk)
- [Thiele & Klose GmbH](https://www.thieleklose.de)
