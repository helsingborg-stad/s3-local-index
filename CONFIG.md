# S3 Local Index Configuration

This plugin provides a configurable system that can be controlled via WordPress filters.

## Available Configuration Options

### Plugin Enable/Disable

Control whether the plugin is enabled:

```php
// Disable the plugin entirely
add_filter('s3_local_index/Enabled', '__return_false');

// Or use a conditional
add_filter('s3_local_index/Enabled', function() {
    return !is_admin(); // Only enable on frontend
});
```

### CLI Commands

Control whether CLI commands are enabled:

```php
// Disable CLI commands
add_filter('s3_local_index/CliCommands', '__return_false');
```

### Custom Features

You can also check for custom features in your own code:

```php
use S3_Local_Index\Config\ConfigFactory;

$config = ConfigFactory::createDefault();

// Check if a custom feature is enabled
if ($config->isEnabled('myCustomFeature')) {
    // Feature is enabled
}
```

## Filter Naming Convention

All filters follow the pattern: `s3_local_index/{FeatureName}`

- The filter prefix is: `s3_local_index`
- Feature names are automatically capitalized (e.g., 'enabled' becomes 'Enabled')
- Use forward slashes to separate the prefix from the feature name

## Dependency Injection

The configuration system uses dependency injection for better testability:

```php
use S3_Local_Index\Config\ConfigFactory;
use S3_Local_Index\Config\WordPressService;

// Default configuration
$config = ConfigFactory::createDefault();

// Custom configuration with dependency injection
$wpService = new WordPressService();
$config = ConfigFactory::create($wpService, 'my_custom_prefix');
```

## Testing

For testing purposes, you can reset the singleton and provide mock services:

```php
use S3_Local_Index\Config\ConfigFactory;

// Reset singleton for testing
ConfigFactory::reset();

// Create with mock service
$mockWpService = new MockWordPressService();
$config = ConfigFactory::create($mockWpService);
```