# Configuration

This section covers all configuration options for the Autotranslate extension.

## Configuration Areas

- [Extension Configuration](ExtensionConfiguration/Readme.md) - Global extension settings
- [Site Configuration](SiteConfigurations/Readme.md) - Per-site DeepL API and language settings
- [Languages](TranslatableElements/Languages.md) - Configuring translatable languages
- [Text Fields](TranslatableElements/Fields.md) - Defining which fields to translate

## Quick Configuration

### Minimal Site Configuration

Add to your `config/sites/<site>/config.yaml`:

```yaml
autotranslate:
  deeplApiKey: 'your-deepl-api-key'
  languages: '1,2'
```

### Extension Configuration Options

| Option | Description | Default |
|--------|-------------|---------|
| `additionalTables` | Additional database tables to translate | `''` |
| `cacheEnabled` | Enable translation caching | `true` |
| `cacheLifetime` | Cache lifetime in seconds | `86400` |

See individual sections for detailed configuration options.
