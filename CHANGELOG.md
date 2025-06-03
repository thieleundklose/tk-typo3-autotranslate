# Changelog


## [2.2.0] - 2025-06-03

### Stabilizations
- Add grid support for batch translations, thx to Thomas Schöne (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/42 / Pull request: https://github.com/thieleundklose/tk-typo3-autotranslate/pull/41)
- Keep original values for given fields in translated records, thx to Thomas Schöne (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/37 / Pull request: https://github.com/thieleundklose/tk-typo3-autotranslate/pull/36)
- Use PSR-14 event for TCA adjustments because ob exception in production, thx to Thomas Schöne (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/47 / Pull request: https://github.com/thieleundklose/tk-typo3-autotranslate/pull/46)

## [2.1.3] - 2025-06-01

### Feature
- Attribute translation for title tags of links in html content, thx to t-bittner (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/35)

### Stabilizations
- Remove deprecations
- Request processing of CLI queries for batch translations, thx to bznovak (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/40)

## [2.1.2] - 2025-04-13

### Fixes
- Stabilizations on TCA dependencies
- Support for 3rd party extensions with allowLanguageSynchronization like tt_address, thx to Wolfgang Wagner (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/26)

## [2.1.1] - 2025-03-25

### Fixes
- Keep line breaks in translations, thx to Jahn Blechinger (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/34)

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

### Fix
- Add Missing BatchTranslation filename for usage in TYPO3 v13
- BatchTranslation created now with correct time offset from php / typo3 timezone settings

## [1.3.1] - 2025-02-20

### Fix
- Handling of delete tca definition on additional tables
- Stabilizations, thx to Rico Sonntag

## [1.3.0] - 2025-02-20

### Added
- Extension configuration to improve translation of 3rd party content (Check documentation for upgrade instructions)

## [1.2.3] - 2025-01-09

### Fix
- Fixed: Empty database query caused SyntaxErrorException, thanks to Rico Sonntag

## [1.2.2] - 2024-10-27

### Fix
- Stabilizations
- Fixed backend bugs when editing pages and content elements on pages without site configuration

## [1.2.1] - 2024-10-20

### Fix
- Symfony command to perform a BatchTranslation in TYPO3 v13
- Number of translations per run limited to the maximum defined number

## [1.2.0] - 2024-10-15 (TYPO3 v13.4 LTS Release)

### Added
- Now with TYPO3 v13 support
- Compatibility of event listeners for PageTsConfig up to TYPO3 v13 established

## [1.1.1] - 2024-09-18

### Feature
- Added the option to select a source language for DeepL translations in the page configuration. Otherwise, when translating individual words, the source language is often incorrectly recognized, thx to Schorsch.

## [1.1.0] - 2024-09-09

### Feature
- Added a new backend module "BatchTranslation", which enables automatic translation of pages and content elements. This feature streamlines the localization process, improving efficiency when managing multilingual content.
- Added a symfony command/scheduler task to process scheduled translations. This allows for automated handling of translations based on predefined plans, ensuring timely updates and improved workflow automation.

## [1.0.4] - 2024-05-22

### Fixed
- The news extension loaded condition did not work due to an incorrect extension key, thx to SventB

## [1.0.3] - 2024-05-02

### Feature
- Improved translations with tag_handling in DeepL

## [1.0.2] - 2024-04-21

### Fixed
- Bugfix a variable usage exception that was thrown caused by editing elements on the root page

## [1.0.1] - 2024-04-19

### Fixed
- Fixed Exception on DataHandler if no page or root page is selected

## [1.0.0] - 2024-03-22

### Fixed
- Translation mechanism restructured and errors in the translation process fixed

## [0.9.3] - 2024-02-26

### Fixed
- Fixed installation with Extension Manager from TER to use DeepL Vendor Files with TYPO3 autoloader (https://forge.typo3.org/issues/102443)
- PHP 8 bugfixes & translation stabilizations

## [0.9.2] - 2023-10-15

### Added
- TYPO3 v12 support

### Fixed
- Fixed field names in site configuration
- PHP 8 bugfixes & stabilizations