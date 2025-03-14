# ODY Framework Documentation

> [!WARNING]
> 🚧 !! This is a WIP, build completely from scratch. This will replace ody-core which was build on top of Slim framework. !!

## Introduction

ODY is a modern PHP API framework built with a focus on high performance and modern architecture. It leverages 
Swoole's coroutines for asynchronous processing, follows PSR standards for interoperability, and provides a 
clean architecture for building robust APIs.

!! Swoole not fully implemented yet !!

## Key Features

- **High Performance**: Built with Swoole support for asynchronous processing and coroutines
- **PSR Compliance**: Implements PSR-7, PSR-15, and PSR-17 for HTTP messaging and middleware
- **Modern PHP**: Requires PHP 8.3+ and takes advantage of modern language features
- **Modular Design**: Build and integrate different modules with a clean architecture
- **Middleware System**: Powerful middleware system for request/response processing
- **Dependency Injection**: Built-in IoC container for dependency management
- **Console Support**: CLI commands for various tasks and application management
- **Routing**: Simple and flexible routing system with support for route groups and middleware

## Installation

### Requirements

- PHP 8.3 or higher
- Swoole PHP extension (≥ 6.0.0)
- Composer

### Basic Installation

```bash
composer create-project ody/api-core your-project-name
cd your-project-name
```

## Configuration

Configuration files are stored in the `config` directory. The primary configuration files include:

- `app.php`: Application settings, service providers, and middleware
- `database.php`: Database connections configuration
- `logging.php`: Logging configuration and channels

Environment-specific configurations can be set in `.env` files. A sample `.env.example` file is provided that you can copy to `.env` and customize:

```bash
cp .env.example .env
```

## Project Structure

```
your-project/
├── app/                  # Application code
│   ├── Controllers/      # Controller classes
│   └── ...
├── config/               # Configuration files
├── public/               # Public directory (web server root)
│   └── index.php         # Application entry point
├── routes/               # Route definitions
│   ├── api.php           # API routes
│   └── web.php           # Web routes
├── src/                  # Framework core components
├── storage/              # Storage directory for logs, cache, etc.
├── tests/                # Test files
├── vendor/               # Composer dependencies
├── .env                  # Environment variables
├── .env.example          # Environment variables example
├── composer.json         # Composer package file
├── ody                   # CLI entry point
└── README.md             # Project documentation
```

## Routing

Routes are defined in the `routes` directory. The framework supports various HTTP methods and route patterns:

```php
// Basic route definition
Route::get('/hello', function (ServerRequestInterface $request, ResponseInterface $response) {
    return $response->json([
        'message' => 'Hello World'
    ]);
});

// Route with named controller
Route::post('/users', 'App\Controllers\UserController@store');

// Route with middleware
Route::get('/users/{id}', 'App\Controllers\UserController@show')
    ->middleware('auth:api');

// Route groups
Route::group(['prefix' => '/api/v1', 'middleware' => ['throttle:60,1']], function ($router) {
    $router->get('/status', function ($request, $response) {
        return $response->json([
            'status' => 'operational'
        ]);
    });
});
```

## Controllers

Controllers handle the application logic and are typically stored in the `app/Controllers` directory:

```php
<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class UserController
{
    private $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get all users
        $users = [
            ['id' => 1, 'name' => 'John Doe'],
            ['id' => 2, 'name' => 'Jane Smith']
        ];
        
        return $response->withHeader('Content-Type', 'application/json')
                       ->withBody(json_encode($users));
    }
    
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $id = $params['id'];
        
        // Get user by ID
        $user = ['id' => $id, 'name' => 'John Doe'];
        
        return $response->withHeader('Content-Type', 'application/json')
                       ->withBody(json_encode($user));
    }
}
```

## Middleware

Middleware provides a mechanism to filter HTTP requests/responses. The framework includes several built-in middleware 
classes and supports custom middleware:

```php
<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CustomMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Perform actions before the request is handled
        
        // Pass the request to the next middleware in the stack
        $response = $handler->handle($request);
        
        // Perform actions after the request is handled
        
        return $response;
    }
}
```

Register your middleware in `config/app.php`:

```php
'middleware' => [
    'global' => [
        // Global middleware applied to all routes
        App\Middleware\CustomMiddleware::class,
    ],
    'named' => [
        // Named middleware that can be referenced in routes
        'custom' => App\Middleware\CustomMiddleware::class,
    ],
],
```

## Dependency Injection

The framework includes a powerful IoC container for managing dependencies:

```php
<?php

// Binding an interface to a concrete implementation
$container->bind(UserRepositoryInterface::class, UserRepository::class);

// Registering a singleton
$container->singleton(UserService::class, function($container) {
    return new UserService(
        $container->make(UserRepositoryInterface::class)
    );
});

// Resolving dependencies
$userService = $container->make(UserService::class);
```

## Command Line Interface

The framework provides a CLI tool for various tasks. To use it, run the `ody` command from your project root:

```bash
# List available commands
php ody list

# Get environment information
php ody env

# Create a new command
php ody make:command MyCustomCommand
```

## Logging

Logging is configured in `config/logging.php` and provides various channels for logging:

```php
// Using the logger
$logger->info('User logged in', ['id' => $userId]);
$logger->error('Failed to process payment', ['order_id' => $orderId]);

// Using the logger helper function
logger()->info('Processing request');
```

# Custom Loggers in ODY Framework
## Creating Custom Loggers

### Basic Requirements

All custom loggers must:

1. Extend `Ody\Logger\AbstractLogger`
2. Implement a static `create(array $config)` method
3. Override the `write(string $level, string $message, array $context = [])` method

### Example: Creating a Custom Logger

Here's a simple example of a custom logger that logs to Redis:

```php
<?php

namespace App\Logging;

use Ody\Logger\AbstractLogger;
use Ody\Logger\FormatterInterface;
use Ody\Logger\JsonFormatter;
use Ody\Logger\LineFormatter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Redis;

class RedisLogger extends AbstractLogger
{
    /**
     * @var Redis
     */
    protected Redis $redis;
    
    /**
     * @var string
     */
    protected string $channel;
    
    /**
     * Constructor
     */
    public function __construct(
        Redis $redis,
        string $channel = 'logs',
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null
    ) {
        parent::__construct($level, $formatter);
        $this->redis = $redis;
        $this->channel = $channel;
    }
    
    /**
     * Create a Redis logger from configuration
     */
    public static function create(array $config): LoggerInterface
    {
        // Create Redis connection
        $redis = new Redis();
        $redis->connect(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 6379
        );
        
        if (isset($config['password'])) {
            $redis->auth($config['password']);
        }
        
        // Create formatter
        $formatter = null;
        if (isset($config['formatter'])) {
            $formatter = self::createFormatter($config);
        }
        
        // Return new logger instance
        return new self(
            $redis,
            $config['channel'] ?? 'logs',
            $config['level'] ?? LogLevel::DEBUG,
            $formatter
        );
    }
    
    /**
     * Create a formatter based on configuration
     */
    protected static function createFormatter(array $config): FormatterInterface
    {
        $formatterType = $config['formatter'] ?? 'json';
        
        if ($formatterType === 'line') {
            return new LineFormatter(
                $config['format'] ?? null,
                $config['date_format'] ?? null
            );
        }
        
        return new JsonFormatter();
    }
    
    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        // Format log data
        $logData = [
            'timestamp' => time(),
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];
        
        // Publish to Redis channel
        $this->redis->publish(
            $this->channel,
            json_encode($logData)
        );
    }
}
```

### The `create()` Method

The static `create()` method is responsible for instantiating your logger based on configuration:

```php
public static function create(array $config): LoggerInterface
{
    // Create dependencies based on configuration
    // ...
    
    // Return new logger instance
    return new self(...);
}
```

This method receives the channel configuration from the `logging.php` config file and should:

1. Create any dependencies the logger needs
2. Configure those dependencies based on the config array
3. Return a new instance of the logger

### The `write()` Method

The `write()` method is where the actual logging happens:

```php
protected function write(string $level, string $message, array $context = []): void
{
    // Implement logging logic here
}
```

This method is called by the parent `AbstractLogger` class when a log message needs to be written. It receives:

- `$level`: The log level (debug, info, warning, etc.)
- `$message`: The formatted log message
- `$context`: Additional context data

## Using Custom Loggers

### Method 1: Configuration-Based Discovery

The simplest way to use a custom logger is to specify the fully-qualified class name in your logging configuration:

```php
// In config/logging.php
'channels' => [
    'redis' => [
        'driver' => 'redis',
        'class' => \App\Logging\RedisLogger::class,
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'channel' => 'application_logs',
        'level' => 'debug',
    ],
]
```

When you specify a `class` parameter, that class will be used regardless of the driver name.

### Method 2: Driver Name Registration

You can register your logger with a driver name, which allows you to reference it using just the driver name:

```php
// In a service provider's register method
$this->app->make(\Ody\Logger\LogManager::class)
    ->registerDriver('redis', \App\Logging\RedisLogger::class);
```

Then in your configuration:

```php
// In config/logging.php
'channels' => [
    'redis' => [
        'driver' => 'redis', // This will use the registered RedisLogger
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'channel' => 'application_logs',
        'level' => 'debug',
    ],
]
```

### Method 3: Automatic Discovery

If your logger follows the naming convention `{Driver}Logger` and is in one of the registered namespaces, it will be discovered automatically:

```php
// In config/logging.php
'channels' => [
    'redis' => [
        'driver' => 'redis', // Will look for RedisLogger
        // Configuration...
    ],
]
```

The framework will search for `RedisLogger` in the registered namespaces (`\Ody\Logger\` and `\App\Logging\` by default).

### Creating Custom Formatters

If the standard formatters don't meet your needs, you can create your own by implementing the `FormatterInterface`:

```php
namespace App\Logging;

use Ody\Logger\FormatterInterface;

class CustomFormatter implements FormatterInterface
{
    public function format(string $level, string $message, array $context = []): string
    {
        // Custom formatting logic
        return "[$level] $message " . json_encode($context);
    }
}
```

## Complete Example: Using Redis Logger

### Configuration

```php
// In config/logging.php
'channels' => [
    // Using explicit class
    'redis' => [
        'driver' => 'redis',
        'class' => \App\Logging\RedisLogger::class,
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'channel' => 'app_logs',
        'formatter' => 'json',
        'level' => 'debug',
    ],
    
    // Using it in a stack
    'production' => [
        'driver' => 'group',
        'channels' => ['file', 'redis'],
    ],
],
```

### Usage

```php
// Send to redis channel
logger('User registered', ['id' => 123], 'redis');

// Or use the stack
logger('API request processed', ['endpoint' => '/users']);
```
---

With this system, you can create custom loggers that integrate seamlessly with the ODY Framework logging infrastructure.

## Service Providers

Service providers are used to register services with the application. Custom service providers can be created in the 
`app/Providers` directory:

```php
<?php

namespace App\Providers;

use Ody\Foundation\Providers\ServiceProvider;

class CustomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register bindings
        $this->singleton('custom.service', function() {
            return new CustomService();
        });
    }
    
    public function boot(): void
    {
        // Bootstrap services
    }
}
```

Register your service provider in `config/app.php`:

```php
'providers' => [
    // Framework providers
    Ody\Foundation\Providers\DatabaseServiceProvider::class,
    
    // Application providers
    App\Providers\CustomServiceProvider::class,
],
```

## Running the Application

### Development Server

For development, you can use the built-in PHP server:

```bash
php -S localhost:8000 -t public
```

### Production Deployment

For production, you can use Swoole HTTP server:

```bash
php ody serve --swoole --host=0.0.0.0 --port=9501
```

## Advanced Features

### Custom Commands

Create custom console commands by extending the base Command class:

```php
<?php

namespace App\Console\Commands;

use Ody\Foundation\Console\Command;

class CustomCommand extends Command
{
    protected $name = 'app:custom';
    
    protected $description = 'A custom command';
    
    protected function handle(): int
    {
        $this->info('Custom command executed successfully!');
        
        return self::SUCCESS;
    }
}
```

### Working with Databases

The framework provides a simple database abstraction layer:

```php
<?php

// Using PDO directly
$db = app('db');
$users = $db->query('SELECT * FROM users')->fetchAll();

// Or inject PDO into your classes
public function __construct(PDO $db)
{
    $this->db = $db;
}
```

## Resources

- [GitHub Repository](https://github.com/ody-dev/ody-core)
- [Issue Tracker](https://github.com/ody-dev/ody-core/issues)
- [PSR Standards](https://www.php-fig.org/psr/)
- [Swoole Documentation](https://www.swoole.co.uk/docs/)

## License

ODY Framework is open-source software licensed under the MIT license.