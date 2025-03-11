<?php

namespace Ody\Core\Foundation\Middleware\Resolvers;

use Illuminate\Container\Container;
use Ody\Core\Foundation\Logger;
use Ody\Core\Foundation\Support\Config;

/**
 * Factory for middleware resolvers
 */
class MiddlewareResolverFactory
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var array
     */
    protected $resolvers = [];

    /**
     * Constructor
     *
     * @param Logger $logger
     * @param Container $container
     * @param Config $config
     */
    public function __construct(Logger $logger, Container $container, Config $config)
    {
        $this->logger = $logger;
        $this->container = $container;
        $this->config = $config;

        $this->registerDefaultResolvers();
    }

    /**
     * Register default resolvers
     *
     * @return void
     */
    protected function registerDefaultResolvers(): void
    {
        // Register built-in resolvers
        $this->addResolver(new AuthMiddlewareResolver($this->logger));
        $this->addResolver(new RoleMiddlewareResolver($this->logger));
        $this->addResolver(new ThrottleMiddlewareResolver($this->logger));

        // Get middleware map from config
        $middlewareMap = $this->config->get('app.middleware.named', []);

        // Add generic class resolver
        $this->addResolver(new ClassMiddlewareResolver($this->logger, $this->container, $middlewareMap));
    }

    /**
     * Add a resolver
     *
     * @param MiddlewareResolverInterface $resolver
     * @return self
     */
    public function addResolver(MiddlewareResolverInterface $resolver): self
    {
        $this->resolvers[] = $resolver;
        return $this;
    }

    /**
     * Resolve middleware by name
     *
     * @param string $name
     * @param array $options
     * @return callable
     * @throws \InvalidArgumentException
     */
    public function resolve(string $name, array $options = []): callable
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($name)) {
                return $resolver->resolve($name, $options);
            }
        }

        throw new \InvalidArgumentException("No resolver found for middleware: {$name}");
    }
}