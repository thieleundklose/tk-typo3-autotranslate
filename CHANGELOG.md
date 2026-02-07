# Changelog

## [3.0.0] - 2026-02-07

### Breaking Changes
- Dropped support for TYPO3 11 and 12; now requires TYPO3 13.4 LTS or 14
- Dropped support for PHP < 8.2; now requires PHP 8.2 - 8.5
- Removed all TYPO3 11/12 compatibility code (version checks, old TCA formats)

### Features
- Added "Create only" translation mode — only creates missing translations, never overwrites existing ones
- Added dedicated scheduler task (`BatchTranslationTask`) with visual progress bar via TYPO3's `ProgressProviderInterface`
- Added scheduler task status display (items done/pending/errors, last run info) in the Scheduler module
- Added duplicate batch item prevention — skips creation when pending items already exist for the same page/language
- Added error reporting — displays existing errors on pages when creating new batch items, with error summaries
- Added German backend translations (`de.locallang.xlf`, `de.locallang_db.xlf`, `de.locallang_mod.xlf`)
- Added scheduler run statistics display in the backend module (last run time, succeeded/failed/remaining counts)
- Added extension icon registration via `Configuration/Icons.php`

### Modernization
- Refactored batch translation into `BatchTranslationRunner` service — shared by CLI command and scheduler task, eliminating code duplication
- Replaced missing `FlashMessageUtility` with TYPO3 core `FlashMessageService` in DataHandler hook
- Replaced `header()` + `exit` redirect pattern with `PropagateResponseException` + `RedirectResponse`
- Updated SiteConfiguration items from old numeric array format to modern `label`/`value` associative format
- Removed TYPO3 version check in `PageUtility` (always uses `executeQuery()`)
- Replaced `Doctrine\DBAL\ParameterType` with `TYPO3\CMS\Core\Database\Connection` constants
- Replaced `strpos()` / `is_null()` with `str_starts_with()` / `str_contains()` / `=== null`
- Replaced `switch` statements with `match` expressions
- Added `final` keyword to utility, service, and repository classes
- Added `readonly` properties and promoted constructor parameters where appropriate
- Added proper visibility keywords to all class constants, methods, and properties
- Added typed properties and typed closure parameters throughout the codebase
- Added `declare(strict_types=1)` to all PHP files
- Used arrow functions and strict comparisons consistently
- Removed debug code from `TranslationCacheService`
- Removed empty `BackendLegacyModule.js`
- Removed `eval` from SiteConfiguration field configs (deprecated in TYPO3 13)
- Removed references to TYPO3 < v13 from `ext_conf_template.txt`
- Removed unused imports across the codebase
- Updated documentation to reflect TYPO3 13/14 only support

## [2.5.0] - 2026-01-17

### Features
- Added comprehensive support for relation tables including ContentBlocks collections, `sys_file_reference`, and custom inline relations
- Autotranslate fields now available on all page doctypes (Standard, External URL, Shortcut, etc.)

## [2.4.1] - 2025-12-29

### Fixes
- Compatibility and security, thanks to Sebastian Mendel ([Pull request #97](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/97)).
- Improved cache serialization to preserve array indices and prevent data corruption when mixing cached and fresh translations.
- Added defensive null checks in translation pipeline to prevent str_replace() errors with null values, thanks to Rainer Becker ([Issue #94](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/94)).

## [2.4.0] - 2025-10-12

### Features
- Use caching to save quota and speed up translations ([Issue #55](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/55)).

## [2.3.3] - 2025-10-08

### Fixes
- Corrected translation logic for handling with l10n_state fields, thanks to Rico Sonntag ([Issue #89](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/89)).
- Drop duplicate assignment, thanks to Rico Sonntag ([Pull request #88](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/88)).
- Determination of the API key in the hook in CLI mode corrected, tanks to Richard Krikler ([Pull request #86](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/86)).

## [2.3.2] - 2025-10-05

### Fixes
- Content can be created in free mode without being automatically translated into other languages, thanks to Michael Henke and Wolfgang Wagner([Issue #69](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/69)).

## [2.3.1] - 2025-07-23

### Stabilizations
- The DeepL source and target languages are now cached using the TYPO3 Cache Framework (“Autotranslate” cache), which ensures automatic cleanup when clearing the cache and no duplicate cache/data directories.

## [2.3.0] - 2025-07-18

### Features
- Log output and options for deleting logs in the backend module, log deactivation possible in the extension settings

## [2.2.5] - 2025-07-15

### Fixes
- (Un)Check all button is not working in TYPO3 v13, thanks to Rainer Becker ([Issue #57](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/57))
- Missing required dependency php-http/discovery causes fatal error in non-Composer TYPO3 installation, thanks to Andrew ([Issue #72](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/72))

## [2.2.4] - 2025-07-14

### Fixes
- Translation of the field title of pages and header of content elements: & becomes &amp;, thanks to Andreas Kessel ([Issue #49](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/49))
- $localizedUid being null during record localization, thanks to Andreas Kessel ([Issue #39](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/39))
- Optimization for determining the page ID due to php variable type check error, thanks to tot-bittner ([Issue #67](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/67))

## [2.2.3] - 2025-06-19

### Stabilizations
- Added index for big page trees like 1000+ pages, thanks to Thomas Schöne ([Issue #64](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/64) / [Pull request #63](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/63))

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