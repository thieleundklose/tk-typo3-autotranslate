# Autotranslate

![TYPO3 extension](https://typo3-badges.dev/badge/autotranslate/extension/shields.svg)
![Total downloads](https://typo3-badges.dev/badge/autotranslate/downloads/shields.svg)
![Stability](https://typo3-badges.dev/badge/autotranslate/stability/shields.svg)

<a href="https://localize.typo3.org/xliff/status.html">
  <picture>
    <source media="(prefers-color-scheme: light)" srcset="https://badges.crowdin.net/badge/dark/crowdin-on-light.png 1x, https://badges.crowdin.net/badge/dark/crowdin-on-light@2x.png 2x">
    <img style="width:140px;height:40px" src="https://badges.crowdin.net/badge/light/crowdin-on-dark.png" srcset="https://badges.crowdin.net/badge/light/crowdin-on-dark.png 1x, https://badges.crowdin.net/badge/light/crowdin-on-dark@2x.png 2x" alt="Crowdin | Agile localization for tech companies">
  </picture>
</a>

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
* Glossary support via compatibility with deepltranslate_glossary extension

## Compatibility

| Autotranslate | TYPO3     | PHP          | DeepL PHP | Notes                                     |
|---------------|-----------|--------------|-----------|-------------------------------------------|
| 3.x           | 12 - 14   | 8.1 - 8.5.99 | 1.5 - 1.x | BatchTranslation now for none admin users |
| 2.x           | 11 - 13   | 7.4 - 8.4.99 | 1.4 - 1.x | Previous release path                     |
| 1.x           | 10 - 13   | 7.4 - 8.3.99 | 1.4       |

The DeepL Autotranslate extension is available for TYPO3 v10 through v14 across its supported release lines. The current 3.x release supports TYPO3 v12 and v13, while older releases remain available for TYPO3 v10 and v11. Translations for news articles of the extension [News](https://extensions.typo3.org/extension/news) are also already integrated.

## Documentation

* [Installation](Documentation/Installation/Readme.md)
* [Configuration](Documentation/Configuration/Readme.md)
* [Crowdin Translation Status](https://localize.typo3.org/xliff/status.html)
* [Product Website](https://www.thieleklose.de/referenzen/typo3-autotranslate)

## Authors
- [Mike Zimmer](https://github.com/mikezimmer-tuk)
- [Thiele & Klose GmbH](https://www.thieleklose.de)
