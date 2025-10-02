# Stream Architecture Documentation

This document describes the refactored Stream catalogue architecture that follows SOLID principles and provides clear separation of concerns.

## Architecture Overview

The new architecture separates stream handling into distinct, focused components:

### Core Components

1. **StreamResolverInterface** - Contract for resolvers that handle specific types of requests
2. **StreamWrapperRegistrarInterface** - Contract for stream wrapper registration services
3. **S3LocalIndexResolver** - Resolves file existence using local index (renamed from Reader)
4. **S3OriginalStreamWrapper** - Provides access to original S3 functionality (renamed from WrapperOriginal)
5. **S3StreamWrapperProxy** - Main proxy that coordinates resolvers (renamed from Wrapper)
6. **StreamResolverChain** - Manages multiple resolvers using Chain of Responsibility pattern
7. **StreamWrapperRegistrar** - Handles stream wrapper registration with PHP

## Key Design Patterns

### Chain of Responsibility
The `StreamResolverChain` allows multiple resolvers to be chained together. Each resolver can decide whether it can handle a specific request, and the first capable resolver processes the request.

### Proxy Pattern
The `S3StreamWrapperProxy` acts as a proxy, intercepting stream operations and routing them to the appropriate resolver or falling back to the original S3 wrapper.

### Dependency Injection
All components receive their dependencies through constructor injection, making the system testable and flexible.

## SOLID Principles Applied

### Single Responsibility Principle (SRP)
- `StreamWrapperRegistrar`: Only handles registration/unregistration of stream wrappers
- `S3LocalIndexResolver`: Only resolves file existence using local index
- `StreamResolverChain`: Only manages the chain of resolvers
- `S3StreamWrapperProxy`: Only proxies requests between resolvers and original wrapper

### Open/Closed Principle (OCP)
- New resolvers can be added to the chain without modifying existing code
- The system is closed for modification but open for extension

### Liskov Substitution Principle (LSP)
- All resolvers implement `StreamResolverInterface` and can be used interchangeably
- All wrappers implement `WrapperInterface` and can substitute each other

### Interface Segregation Principle (ISP)
- Small, focused interfaces: `StreamResolverInterface`, `StreamWrapperRegistrarInterface`
- Components only depend on the methods they actually use

### Dependency Inversion Principle (DIP)
- High-level modules depend on abstractions (interfaces), not concretions
- All dependencies are injected as interfaces

## Usage Example

```php
// Create dependencies
$logger = new Logger();
$pathParser = new PathParser();
$cache = $cacheFactory->createDefault();
$fileSystem = new NativeFileSystem($config);
$indexManager = new IndexManager($cache, $fileSystem, $logger, $pathParser);

// Create resolver
$localIndexResolver = new S3LocalIndexResolver(
    $cache, 
    $fileSystem, 
    $logger, 
    $pathParser, 
    $indexManager
);

// Create resolver chain
$resolverChain = new StreamResolverChain($logger);
$resolverChain->addResolver($localIndexResolver);

// Create original wrapper and proxy
$originalWrapper = new S3OriginalStreamWrapper();
$streamProxy = new S3StreamWrapperProxy();
$streamProxy->setDependencies($resolverChain, $pathParser, $logger, $originalWrapper);

// Register the stream wrapper
$registrar = new StreamWrapperRegistrar($logger);
$registrar->register('s3', S3StreamWrapperProxy::class);
```

## Benefits

1. **Clear Responsibilities**: Each class has a single, well-defined purpose
2. **Easy Testing**: All components can be unit tested independently
3. **Flexible Architecture**: Easy to add new resolvers or change behavior
4. **Maintainable Code**: Clear naming and separation makes the code easier to understand
5. **SOLID Compliance**: Follows all SOLID principles for maintainable design

## Migration Notes

The refactoring maintains backward compatibility through interfaces. The main changes are:

- `Reader` → `S3LocalIndexResolver` (with added resolver interface methods)
- `Wrapper` → `S3StreamWrapperProxy` (separated registration concerns)
- `WrapperOriginal` → `S3OriginalStreamWrapper` (clearer naming)

All original functionality is preserved while providing a cleaner, more maintainable architecture.