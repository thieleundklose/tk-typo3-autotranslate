# Autotranslate

A TYPO3 extension for automatic content translation using the DeepL API.

## Overview

This extension automates the translation of content elements in the TYPO3 backend, helping editors save time and reduce errors in multilingual content management.

## Features

- **Automatic Translation**: Translate pages, content elements, file references, and news articles
- **Multi-Language Support**: Translate to multiple target languages simultaneously
- **Configurable Fields**: Define which text fields should be translated
- **Site Configuration**: All settings including DeepL API credentials are managed via TYPO3 site configuration
- **Backend Module**: Visual interface for managing batch translation jobs
- **Translation Modes**: "Create & Update", "Update only", and "Create only" modes
- **Duplicate Prevention**: Prevents creation of duplicate batch items when pending items already exist
- **Error Reporting**: Displays existing errors on pages when creating new batch items
- **CLI Command**: `autotranslate:batch:run` for scheduled/automated translations
- **Scheduler Task**: Custom scheduler task with visual progress bar and status display
- **Glossary Support**: Compatible with `deepltranslate_glossary` extension
- **Grid Elements**: Support for translating nested Grid Elements containers
- **Translation Cache**: Optional caching to reduce API calls and costs

## Requirements

| Autotranslate | TYPO3      | PHP       | DeepL PHP |
|---------------|------------|-----------|-----------|
| 3.x           | 13.4 - 14  | 8.2 - 8.5 | ^1.5      |
| 2.x           | 11 - 13    | 7.4 - 8.4 | 1.4 - 1.x |
| 1.x           | 10 - 13    | 7.4 - 8.3 | 1.4       |

## Installation

### Via Composer (recommended)

```bash
composer require thieleundklose/autotranslate
```

## Quick Start

1. **Install the extension** via Composer
2. **Configure your DeepL API key** in your site configuration (Sites > Edit Site > Autotranslate tab)
3. **Configure languages** per site language (set DeepL source/target language codes)
4. **Enable translation** for tables in the site configuration (e.g. Pages, Content)
5. **Define text fields** to translate per table in the site configuration
6. **Use the backend module** (Web > Autotranslate) to manage batch translations

## CLI Usage

Translate queued items via command line:

```bash
# Translate 50 items (default)
vendor/bin/typo3 autotranslate:batch:run

# Translate 10 items
vendor/bin/typo3 autotranslate:batch:run 10
```

## Documentation

- [Installation Guide](Documentation/Installation/Readme.md)
- [Configuration Reference](Documentation/Configuration/Readme.md)
- [Product Website](https://www.thieleklose.de/referenzen/typo3-autotranslate)

## Upgrade from 2.x to 3.x

Version 3.0.0 drops support for TYPO3 11 and 12. Key changes:

- **TYPO3 13.4 LTS / 14** required
- **PHP 8.2 - 8.5** required
- Modernized codebase using PHP 8.2+ features (`final`, `readonly`, `match`, arrow functions)
- Removed all TYPO3 11/12 compatibility code
- Site Configuration items use modern `label`/`value` format
- Uses `PropagateResponseException` instead of `header()` + `exit` for redirects
- Flash messages in DataHandler hooks use TYPO3 `FlashMessageService`
- Updated TCA configuration for TYPO3 13+ standards
- New "Create only" translation mode (only creates missing translations)
- Dedicated scheduler task with visual progress bar (`ProgressProviderInterface`)
- Duplicate batch item prevention
- Error reporting when creating batch items
- German backend translations

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed version history.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later license.

## Authors

- [Mike Zimmer](https://github.com/mikezimmer-tuk)
- [Thiele & Klose GmbH](https://www.thieleklose.de)
