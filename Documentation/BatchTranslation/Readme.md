# Batch Translation

## Backend Module

The batch translation module provides a visual interface for managing translation jobs. Access it via **Web > Autotranslate** in the TYPO3 backend.

### Features

- **Create Jobs**: Add new translation tasks for pages and subpages
- **Job List**: View all translation jobs sorted by priority
- **Manage Jobs**: Enable, disable, or delete translation jobs
- **Execute Jobs**: Run individual translations manually
- **Reset Jobs**: Reset completed jobs for re-translation
- **View Logs**: Monitor translation history and errors
- **Duplicate Prevention**: Prevents creation of duplicate items when pending items already exist
- **Error Reporting**: Shows existing errors on pages when creating new batch items
- **Scheduler Status**: Displays last scheduler run statistics (succeeded/failed/remaining)

![Backend Module](../Images/BatchTranslationBackend.png)

### Creating a Translation Job

1. Navigate to the page you want to translate
2. Open the Autotranslate module
3. Select target language(s)
4. Choose recursion level (include subpages)
5. Set priority, frequency, and translation mode
6. Click "Create"

### Translation Modes

| Mode | Description |
|------|-------------|
| **Create & Update** | Creates new translations and updates existing ones (default) |
| **Update only** | Only updates existing translations, does not create new ones |
| **Create only** | Only creates missing translations, never overwrites existing ones |

## CLI Command

Run translations from the command line:

```bash
# Translate 50 items (default)
vendor/bin/typo3 autotranslate:batch:run

# Translate 10 items
vendor/bin/typo3 autotranslate:batch:run 10
```

## Scheduler Integration

### Custom Scheduler Task (Recommended)

The extension provides a dedicated scheduler task with a visual progress bar:

1. Go to **System > Scheduler**
2. Add a new task
3. Select **Autotranslate Batch Translation** from the task type dropdown
4. The task processes 50 items per run by default
5. Configure the execution frequency (e.g. every 2 hours: `0 */2 * * *`)

The task displays in the Scheduler module:
- A **visual progress bar** showing completion percentage
- **Status text** with done/pending/error counts and last run details

### Cron Job

Alternatively, run via cron using the CLI command:

```bash
# Process 50 items every 5 minutes
*/5 * * * * /path/to/vendor/bin/typo3 autotranslate:batch:run 50
```
