# Thiele & Klose - Autotranslate

### Why we build this Extension

The aim of this extension is to support the editors in the content management process through automated translations of content elements in the backend.
This not only saves time, but also prevents possible errors in content maintenance.

### Features

DeepL autotranslator is a powerful tool for editors with many features, especially in terms of configuration.

* Automatically translate websites, content items, file references and messages
* Multiple languages can be translated
* Translatable text fields can be configured
* The languages to be translated can be configured
* All configurations (including DeepL access) are anchored in the TYPO3 page configurations

### Compatibility

The DeepL Autotranslate extension is available in the version for TYPO3 v10 and v11. Translations for news articles of the extension [News](https://extensions.typo3.org/extension/news) are also already integrated.

### Documentation

* [Installation](Documentation/Installation/Readme.md)
* [Configuration](Documentation/Configuration/Readme.md)

## Changelog

| Version     | Release Date | Description                                              |
|-------------|--------------|----------------------------------------------------------|
| 0.9.2       | 2023-10-14   | Fixed field names in site configuration , typo3 v12      |
|             |              |  support, php 8 bugfixes & stabilizations.               |
|             |              | Adjust following site config in yaml file if configured: |
|             |              | - autotranslatePagesFilereferences to                    |
|             |              |   autotranslatePagesFileReferences                       |
|             |              | - autotranslateTtContentFilereferences to                |
|             |              |   autotranslateTtContentFileReferences                   |
|             |              |   autotranslateTtContentFileReferences                   |

### Roadmap

* Batch translation with symfony command and scheduler.
* Support for alternative translation services such as Google Translate, ChatGPT.
* Support for glossaries.