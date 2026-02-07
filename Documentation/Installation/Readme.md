# Installation

## Requirements

- TYPO3 13.4+ or 14.x
- PHP 8.2 or higher
- A DeepL API key (free or pro account)

## Getting a DeepL API Key

1. Create an account at [DeepL](https://www.deepl.com/pro)
2. Navigate to your account settings
3. Generate an API key (works with both free and pro plans)

## Installation Methods

### Via Composer (Recommended)

```bash
composer require thieleundklose/autotranslate
```

### Via TYPO3 Extension Manager

1. Open the TYPO3 backend
2. Go to **Admin Tools > Extensions**
3. Search for "autotranslate"
4. Click the install button

## Post-Installation

After installation:

1. Clear all caches
2. Configure your DeepL API key in the site configuration
3. Set up the languages you want to translate

See [Configuration](../Configuration/Readme.md) for detailed setup instructions.
