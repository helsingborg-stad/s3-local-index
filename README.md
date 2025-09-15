# S3 Local Index

A WordPress plugin that provides local indexing of S3 files with CLI commands and cache flushing capabilities. This plugin improves performance when working with large S3 buckets by creating local indexes that enable fast file existence checks and directory listings without expensive S3 API calls.

## Description

The S3 Local Index plugin creates and maintains local JSON index files that mirror the structure of your S3 bucket. This enables WordPress to quickly determine if files exist on S3 without making API calls for every check. The plugin supports both single-site and multisite WordPress installations.

### Key Features

- **Fast File Operations**: Local indexes eliminate the need for S3 API calls during file existence checks
- **WordPress Integration**: Seamless integration with WordPress's filesystem API through stream wrappers
- **CLI Management**: Comprehensive WP-CLI commands for index management
- **Cache Support**: Multiple caching strategies including WordPress object cache and static memory cache
- **Multisite Support**: Full support for WordPress multisite networks
- **Selective Rebuilding**: Ability to rebuild specific indexes rather than the entire bucket

## Requirements

- WordPress 5.0 or higher
- PHP 8.0 or higher
- [S3 Uploads Plugin](https://github.com/humanmade/S3-Uploads) (required dependency)
- WP-CLI (for command-line operations)
- Composer (for dependency management)

## Installation

1. **Install via Composer** (recommended):
   ```bash
   composer require helsingborg-stad/s3-local-index
   ```

2. **Manual Installation**:
   - Download the plugin files
   - Upload to `/wp-content/plugins/s3-local-index/`
   - Run `composer install` in the plugin directory

3. **Activate the Plugin**:
   - Through WordPress admin: Plugins → Installed Plugins → Activate "S3 Local Index"
   - Via WP-CLI: `wp plugin activate s3-local-index`

## Configuration

The plugin is enabled by default when the S3 Uploads plugin is active. You can customize behavior using WordPress filters:

### Available Filters

```php
// Enable/disable the plugin
add_filter('S3LocalIndex/Config/IsEnabled', '__return_false');

// Set CLI command priority (default: 10)
add_filter('S3LocalIndex/Config/GetCliPriority', function() {
    return 15;
});

// Set plugin initialization priority (default: 20)
add_filter('S3LocalIndex/Config/GetPluginPriority', function() {
    return 25;
});
```

## CLI Commands

The plugin provides several WP-CLI commands for managing S3 indexes:

### Create Full Index

Creates a complete index by scanning all objects in the S3 bucket:

```bash
wp s3-index create
```

This command:
- Scans the entire S3 bucket
- Groups files by blog ID, year, and month
- Creates JSON index files in the temporary directory
- Clears existing cache before starting
- Provides progress updates during execution

### Flush Cache

Flush cache for specific paths or view the rebuild queue:

```bash
# Flush cache for a specific file
wp s3-index flush uploads/2023/01/image.jpg

# Flush cache and add to rebuild queue
wp s3-index flush uploads/2023/01/image.jpg --add

# View current rebuild queue
wp s3-index flush
```

### Rebuild Indexes

Rebuild specific indexes from the rebuild queue:

```bash
# Rebuild indexes in the queue
wp s3-index rebuild

# Rebuild and clear the queue
wp s3-index rebuild --clear

# Rebuild all indexes (equivalent to create)
wp s3-index rebuild --all
```

## How It Works

### Index Structure

The plugin creates JSON index files organized by:
- **Blog ID**: `1` for single-site, specific ID for multisite
- **Year**: 4-digit year (e.g., `2023`)
- **Month**: 2-digit month (e.g., `01`)

Index files are stored as: `/tmp/s3-index-temp/s3-index-{blogId}-{year}-{month}.json`

### File Path Patterns

The plugin recognizes these S3 path patterns:

**Single Site:**
```
uploads/2023/01/image.jpg
```

**Multisite:**
```
uploads/networks/1/sites/2/2023/01/image.jpg
```

### Caching Strategy

The plugin uses a multi-layer caching approach:

1. **Static Cache**: In-memory cache for the current request
2. **WordPress Object Cache**: Persistent cache using WordPress's object cache system
3. **Composite Cache**: Combines both strategies for optimal performance

Cache keys follow the pattern: `index_{blogId}_{year}_{month}`

### Stream Wrapper Integration

The plugin registers a custom S3 stream wrapper that:
- Intercepts S3 file operations
- Checks local indexes before making S3 API calls
- Provides transparent integration with WordPress filesystem functions
- Supports both file and directory operations

## Development

### Architecture

The plugin follows a modular architecture:

```
source/php/
├── App.php                 # Main application class
├── Config/                 # Configuration management
│   ├── Config.php         # Default configuration provider
│   └── ConfigInterface.php # Configuration contract
├── CLI/                   # Command-line interface
│   └── Command.php        # WP-CLI commands
├── Cache/                 # Caching implementations
│   ├── CacheInterface.php # Cache contract
│   ├── CacheFactory.php   # Cache factory
│   ├── StaticCache.php    # In-memory cache
│   ├── WpCache.php        # WordPress object cache
│   └── CompositeCache.php # Multi-layer cache
├── Stream/                # Stream wrapper components
│   ├── Reader.php         # File reading operations
│   ├── Directory.php      # Directory operations
│   └── Wrapper.php        # Stream wrapper registration
├── FileSystem/            # File system abstraction
│   ├── FileSystemInterface.php
│   └── NativeFileSystem.php
├── Rebuild/               # Selective rebuild functionality
│   └── RebuildTracker.php
└── Integration/           # WordPress integration
    └── WordPressIntegration.php
```

### Key Interfaces

- **HookableInterface**: Contract for WordPress hook registration
- **ConfigInterface**: Configuration provider contract
- **CacheInterface**: Cache implementation contract
- **FileSystemInterface**: File system operation contract

### Running Tests

If tests are available, run them with:

```bash
# Install test dependencies
composer install --dev

# Run PHPUnit tests (if configured)
vendor/bin/phpunit

# Run PHP Code Sniffer (if configured)
vendor/bin/phpcs
```

### Code Standards

The plugin follows PSR-4 autoloading and PSR-12 coding standards. All classes use dependency injection and implement appropriate interfaces for testability.

## Troubleshooting

### Common Issues

**Plugin not working:**
- Ensure S3 Uploads plugin is installed and active
- Check that S3 credentials are properly configured
- Verify WordPress has write permissions to the temp directory

**Index files not created:**
- Run `wp s3-index create` to generate initial indexes
- Check error logs for permission issues
- Ensure sufficient disk space in temp directory

**Performance issues:**
- Verify cache is working: check WordPress object cache status
- Consider increasing cache TTL for static content
- Monitor temp directory size and clean up old indexes periodically

### Debug Logging

The plugin logs important events to the WordPress error log:

```
[S3 Local Index] Stream wrapper registered.
[S3 Local Index] Cache cleared.
[S3 Local Index] Indexed 1000 objects...
```

Enable WordPress debug logging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes with proper documentation
4. Add or update tests as needed
5. Submit a pull request

Please ensure all new code includes proper PHPDoc blocks and follows the existing code style.

## License

This plugin is licensed under the MIT License. See the `composer.json` file for details.

## Authors

- **Sebastian Thulin** - sebastian.thulin@helsingborg.se

## Support

For issues and questions:
- Create an issue on the GitHub repository
- Contact the development team at Helsingborg Stad

## Changelog

### Version 0.1.14
- Current stable release
- Full multisite support
- Selective rebuild functionality
- Comprehensive CLI commands
- Enhanced caching system