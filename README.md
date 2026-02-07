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
- **CLI Command**: `autotranslate:batch:run` for scheduled/automated translations
- **Scheduler Support**: Integrate with TYPO3 scheduler for periodic translations
- **Glossary Support**: Compatible with `deepltranslate_glossary` extension
- **Grid Elements**: Support for translating nested Grid Elements containers
- **Translation Cache**: Optional caching to reduce API calls and costs

## Requirements

| Autotranslate | TYPO3      | PHP       | DeepL PHP |
|---------------|------------|-----------|-----------|
| 3.x           | 13.4 - 14  | 8.2 - 8.4 | ^1.5      |
| 2.x           | 11 - 13    | 7.4 - 8.4 | 1.4 - 1.x |
| 1.x           | 10 - 13    | 7.4 - 8.3 | 1.4       |

## Installation

### Via Composer (recommended)

```bash
composer require thieleundklose/autotranslate
```

### Via TYPO3 Extension Manager

Search for "autotranslate" in the Extension Manager and install.

## Quick Start

1. **Install the extension** via Composer or Extension Manager
2. **Configure your DeepL API key** in your site configuration (`config/sites/*/config.yaml`):

```yaml
autotranslate:
  deeplApiKey: 'your-api-key-here'
  languages: '1,2,3'  # Target language UIDs
```

3. **Enable translation** on pages by setting the `autotranslate_languages` field
4. **Use the backend module** (Web > Autotranslate) to manage batch translations

## CLI Usage

Translate queued items via command line:

```bash
# Translate 1 item (default)
vendor/bin/typo3 autotranslate:batch:run

# Translate 10 items
vendor/bin/typo3 autotranslate:batch:run 10
```

## Documentation

- [Installation Guide](Documentation/Installation/Readme.md)
- [Configuration Reference](Documentation/Configuration/Readme.md)
- [Product Website](https://www.thieleklose.de/referenzen/typo3-autotranslate)

## Changelog

### Version 3.0.0

- **Breaking**: Dropped support for TYPO3 11 and 12
- **New**: Full TYPO3 13 and 14 compatibility
- **New**: PHP 8.2+ required
- **Improved**: Modernized codebase with PHP 8.2+ features
- **Improved**: Better code structure and readability
- **Improved**: Enhanced translation cache functionality
- **Fixed**: Various bug fixes and performance improvements

### Version 2.x

- Batch translation support for non-admin users
- Backend module improvements
- Scheduler task support

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later license.

## Authors

- [Mike Zimmer](https://github.com/mikezimmer-tuk)
- [Thiele & Klose GmbH](https://www.thieleklose.de)
