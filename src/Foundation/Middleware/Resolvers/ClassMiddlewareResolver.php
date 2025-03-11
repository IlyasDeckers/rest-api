<?php

namespace Ody\Core\Foundation\Middleware\Resolvers;

use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Generic resolver for class-based middleware
 */
class ClassMiddlewareResolver implements MiddlewareResolverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var array
     */
    protected $middlewareMap;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param Container $container
     * @param array $middlewareMap
     */
    public function __construct(LoggerInterface $logger, Container $container, array $middlewareMap = [])
    {
        $this->logger = $logger;
        $this->container = $container;
        $this->middlewareMap = $middlewareMap;
    }

    /**
     * Check if this resolver can handle the given middleware name
     *
     * @param string $name
     * @return bool
     */
    public function supports(string $name): bool
    {
        // Check if we have this middleware in our map
        if (isset($this->middlewareMap[$name])) {
            return true;
        }

        // Check if the name itself is a valid class
        return class_exists($name);
    }

    /**
     * Resolve middleware to a callable
     *
     * @param string $name
     * @param array $options
     * @return callable
     */
    public function resolve(string $name, array $options = []): callable
    {
        // Get the middleware class from the map or use the name directly
        $middlewareClass = $this->middlewareMap[$name] ?? $name;

        return function (ServerRequestInterface $request, callable $next) use ($middlewareClass, $options) {
            // Try to resolve from container
            if ($this->container->has($middlewareClass)) {
                $middleware = $this->container->make($middlewareClass);
            } else {
                // Create a new instance with constructor arguments
                $middleware = new $middlewareClass(...array_values($options));
            }

            $handler = $this->createNextHandler($next);

            // Check if it's a PSR-15 middleware
            if ($middleware instanceof MiddlewareInterface) {
                return $middleware->process($request, $handler);
            }

            // Otherwise assume it's a callable middleware
            return $middleware($request, $next);
        };
    }

    /**
     * Create a next handler
     *
     * @param callable $next
     * @return RequestHandlerInterface
     */
    protected function createNextHandler(callable $next): RequestHandlerInterface
    {
        return new class($next) implements RequestHandlerInterface {
            private $next;

            public function __construct(callable $next) {
                $this->next = $next;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface {
                return call_user_func($this->next, $request);
            }
        };
    }
}