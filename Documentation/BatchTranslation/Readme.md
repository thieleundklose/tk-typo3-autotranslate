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

![Backend Module](../Images/BatchTranslationBackend.png)

### Creating a Translation Job

1. Navigate to the page you want to translate
2. Open the Autotranslate module
3. Select target language(s)
4. Choose recursion level (include subpages)
5. Set priority and frequency
6. Click "Create"

## CLI Command

Run translations from the command line:

```bash
# Translate 1 item (default)
vendor/bin/typo3 autotranslate:batch:run

# Translate 10 items
vendor/bin/typo3 autotranslate:batch:run 10
```

### Scheduler Integration

You can integrate the command with the TYPO3 Scheduler:

1. Go to **System > Scheduler**
2. Add a new task
3. Select "Execute console commands"
4. Choose `autotranslate:batch:run`
5. Set the number of translations per run
6. Configure the execution frequency

![Scheduler Task](../Images/BatchTranslationCommand.png)

### Cron Job

Alternatively, run via cron:

```bash
*/5 * * * * /path/to/vendor/bin/typo3 autotranslate:batch:run 5
```

This example processes 5 translation jobs every 5 minutes.
