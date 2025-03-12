<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Core\Foundation\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Logging Middleware (PSR-15)
 */
class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * LoggingMiddleware constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Process an incoming server request
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);

        $this->logger->info('Request started', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri()->getPath(),
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
        ]);

        try {
            $response = $handler->handle($request);

            $duration = microtime(true) - $startTime;
            $this->logger->info('Request completed', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri()->getPath(),
                'status' => $response->getStatusCode(),
                'duration' => round($duration * 1000, 2) . 'ms'
            ]);

            return $response;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            $this->logger->error('Request failed', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri()->getPath(),
                'error' => $e->getMessage(),
                'duration' => round($duration * 1000, 2) . 'ms'
            ]);

            throw $e;
        }
    }
}