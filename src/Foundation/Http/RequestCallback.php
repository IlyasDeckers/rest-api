<?php
namespace Ody\Foundation\Http;

use Ody\Foundation\Application;
use Ody\Foundation\Bootstrap;
use Ody\Foundation\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use function Ody\Foundation\Http\normalizeUploadedFiles;

final class RequestCallback
{
    private RequestHandlerInterface $handler;
    private RequestCallbackOptions $options;

    public function __construct(RequestHandlerInterface $handler, ?RequestCallbackOptions $options = null)
    {
        $this->handler = $handler;
        $this->options = $options ?? new RequestCallbackOptions();

        // Safeguard against bootstrap process
        if ($handler instanceof Application) {
            if (!$handler->isBootstrapped()) {
                error_log("CRITICAL: Application instance passed to RequestCallback is not bootstrapped");
            }
        }
    }

    public function handle(Request $request, Response $response): void
    {
        try {
            // Convert Swoole request to PSR-7
            $serverRequest = $this->createServerRequest($request);

            // Directly handle the request without reinitializing
            $psrResponse = $this->handler->handle($serverRequest);

            // Log the response
            error_log("RequestCallback: Got PSR-7 response with status " . $psrResponse->getStatusCode());

            // Convert PSR-7 response to Swoole response
            $this->emit($psrResponse, $response);
        } catch (\Throwable $e) {
            // Log any exceptions
            error_log("RequestCallback Exception: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // Send error response
            $response->status(500);
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode([
                'error' => 'Internal Server Error',
                'message' => env('APP_DEBUG', false) ? $e->getMessage() : 'Server Error'
            ]));
        }
    }

    private function createServerRequest(Request $swooleRequest): ServerRequestInterface
    {
        /** @var array<string, string> $server */
        $server = $swooleRequest->server;

        /** @var array<array> | array<empty> $files */
        $files = $swooleRequest->files ?? [];

        /** @var array<string, string> | array<empty> $headers */
        $headers = $swooleRequest->header ?? [];

        /** @var array<string, string> | array<empty> $cookies */
        $cookies = $swooleRequest->cookie ?? [];

        /** @var array<string, string> | array<empty> $query_params */
        $query_params = $swooleRequest->get ?? [];

        // Debug the path being processed
        $path = $server['request_uri'] ?? '/';
        $method = $server['request_method'] ?? 'GET';
        error_log("RequestCallback: Converting Swoole request to PSR-7: {$method} {$path}");

        $serverRequest = new ServerRequest(
            $server,
            normalizeUploadedFiles($files),
            $path,
            $method,
            $this->options->getStreamFactory()->createStream($swooleRequest->rawContent()),
            $headers,
            $cookies,
            $query_params,
        );

        // Log the created PSR-7 request for debugging
        error_log("RequestCallback: Created PSR-7 request: " .
            $serverRequest->getMethod() . ' ' .
            $serverRequest->getUri()->getPath()
        );

        return $serverRequest;
    }

    private function emit(ResponseInterface $psrResponse, Response $swooleResponse): void
    {
        $swooleResponse->setStatusCode($psrResponse->getStatusCode(), $psrResponse->getReasonPhrase());

        foreach ($psrResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $swooleResponse->setHeader($name, $value);
            }
        }

        $body = $psrResponse->getBody();
        $body->rewind();

        if ($body->isReadable()) {
            if ($body->getSize() <= $this->options->getResponseChunkSize()) {
                if ($contents = $body->getContents()) {
                    $swooleResponse->write($contents);
                }
            } else {
                while (!$body->eof() && ($contents = $body->read($this->options->getResponseChunkSize()))) {
                    $swooleResponse->write($contents);
                }
            }

            $swooleResponse->end();
        } else {
            $swooleResponse->end((string) $body);
        }

        $body->close();
    }
}