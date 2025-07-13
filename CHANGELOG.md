# Changelog


## [2.2.3] - 2025-07-13

### Fixes
- Translation of the field title of pages and header of content elements: & becomes &amp;, thanks to Andreas Kessel ([Issue #49](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/49))
- $localizedUid being null during record localization, thanks to Andreas Kessel ([Issue #39](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/39))
- Optimization for determining the page ID due to php variable type check error, thanks tot-bittner ([Issue #67](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/67))

## [2.2.2] - 2025-06-14

### Fixes
- TypeError on HTML Attribute detection, thanks to Wolfgang Wagner ([Issue #60](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/60))

## [2.2.1] - 2025-06-09

### Fixes
- Backend authentication for CLI execution of batch translation to use the data handler to localize content elements

### Stabilizations
- API key check added to prevent site module breaks, the extension now provides feedback whether an API key is valid and whether the quota has been used up, thanks to Rainer Becker / saneinsane ([Issue #56](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/56))

### Features
- Show usage and quota in backend module, thanks to Rainer Becker / saneinsane ([Issue #54](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/54))

## [2.2.0] - 2025-06-04

### Features
- Added grid support for batch translations, thanks to Thomas Schöne ([Issue #42](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/42) / [Pull request #41](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/41))
- Preserve original values for specified fields in translated records, thanks to Thomas Schöne ([Issue #37](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/37) / [Pull request #36](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/36))
- Use PSR-14 event for TCA adjustments to avoid exceptions in production, thanks to Thomas Schöne ([Issue #47](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/47) / [Pull request #46](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/46))

## [2.1.3] - 2025-06-01

### Features
- Attribute translation for title tags of links in html content, thanks to t-bittner (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/35)

### Stabilizations
- Remove deprecations
- Request processing of CLI queries for batch translations, thanks to bznovak (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/40)

## [2.1.2] - 2025-04-13

### Fixes
- Stabilizations on TCA dependencies
- Support for 3rd party extensions with allowLanguageSynchronization like tt_address, thanks to Wolfgang Wagner (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/26)

## [2.1.1] - 2025-03-25

### Fixes
- Keep line breaks in translations, thanks to Jahn Blechinger (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/34)

## [2.1.0] - 2025-03-14

### Features
- Glossary support via compatibility with deepltranslate_glossary extension
- Possibility to define the DeepL API key globally for all sites in the extension configuration

### Fixes
- Stabilization backward compatibility
- Increased dependency on Official DeepL API version to support php 8.4

## [2.0.0] - 2025-02-27

### Added
- Editors can now also use BatchTranslation after approval via TYPO3 access rights
- PHP v8.4 support
- Update the DeepL PHP package for compatibility reasons

## [1.3.2] - 2025-02-26

### Fixes
- Add Missing BatchTranslation filename for usage in TYPO3 v13
- BatchTranslation created now with correct time offset from php / typo3 timezone settings

## [1.3.1] - 2025-02-20

### Fixes
- Handling of delete tca definition on additional tables
- Stabilizations, thanks to Rico Sonntag

## [1.3.0] - 2025-02-20

### Added
- Extension configuration to improve translation of 3rd party content (Check documentation for upgrade instructions)

## [1.2.3] - 2025-01-09

### Fixes
- Fixed: Empty database query caused SyntaxErrorException, thanks to Rico Sonntag

## [1.2.2] - 2024-10-27

### Fixes
- Stabilizations
- Fixed backend bugs when editing pages and content elements on pages without site configuration

## [1.2.1] - 2024-10-20

### Fixes
- Symfony command to perform a BatchTranslation in TYPO3 v13
- Number of translations per run limited to the maximum defined number

## [1.2.0] - 2024-10-15 (TYPO3 v13.4 LTS Release)

### Added
- Now with TYPO3 v13 support
- Compatibility of event listeners for PageTsConfig up to TYPO3 v13 established

## [1.1.1] - 2024-09-18

### Features
- Added the option to select a source language for DeepL translations in the page configuration. Otherwise, when translating individual words, the source language is often incorrectly recognized, thanks to Schorsch.

## [1.1.0] - 2024-09-09

### Features
- Added a new backend module "BatchTranslation", which enables automatic translation of pages and content elements. This feature streamlines the localization process, improving efficiency when managing multilingual content.
- Added a symfony command/scheduler task to process scheduled translations. This allows for automated handling of translations based on predefined plans, ensuring timely updates and improved workflow automation.

## [1.0.4] - 2024-05-22

### Fixes
- The news extension loaded condition did not work due to an incorrect extension key, thanks to SventB

## [1.0.3] - 2024-05-02

### Features
- Improved translations with tag_handling in DeepL

## [1.0.2] - 2024-04-21

### Fixes
- Bugfix a variable usage exception that was thrown caused by editing elements on the root page

## [1.0.1] - 2024-04-19

### Fixes
- Fixed Exception on DataHandler if no page or root page is selected

## [1.0.0] - 2024-03-22

### Fixes
- Translation mechanism restructured and errors in the translation process fixed

## [0.9.3] - 2024-02-26

### Fixes
- Fixed installation with Extension Manager from TER to use DeepL Vendor Files with TYPO3 autoloader (https://forge.typo3.org/issues/102443)
- PHP 8 bugfixes & translation stabilizations

## [0.9.2] - 2023-10-15

### Added
- TYPO3 v12 support

### Fixes
- Fixed field names in site configuration
- PHP 8 bugfixes & stabilizations