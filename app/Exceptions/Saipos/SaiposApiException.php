<?php

namespace App\Exceptions\Saipos;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class SaiposApiException extends RuntimeException
{
    /** Configuration problems will not fix themselves; the poller stops instead of retrying. */
    public bool $isConfigurationError = false;

    public static function missingCredentials(): self
    {
        return self::configuration('Saipos credentials missing: set SAIPOS_ID_PARTNER, SAIPOS_SECRET and SAIPOS_COD_STORE.');
    }

    public static function listEndpointNotConfigured(): self
    {
        return self::configuration(
            'SAIPOS_ORDERS_ENDPOINT is not set. The public Saipos Order API docs do not describe an order-listing '
            .'endpoint, so ask Saipos which endpoint your account should use to read sales.'
        );
    }

    public static function tokenMissing(): self
    {
        return new self('Saipos /auth answered successfully but no token was found in the response.');
    }

    public static function fromResponse(string $path, Response $response): self
    {
        return new self(sprintf(
            'Saipos %s failed with HTTP %d: %s',
            $path,
            $response->status(),
            mb_strimwidth($response->body(), 0, 500, '...'),
        ));
    }

    public static function connectionFailed(string $path, Throwable $previous): self
    {
        return new self("Could not reach Saipos ({$path}): {$previous->getMessage()}", previous: $previous);
    }

    public static function malformedOrder(string $reason): self
    {
        return new self("Malformed Saipos order payload: {$reason}");
    }

    private static function configuration(string $message): self
    {
        $exception = new self($message);
        $exception->isConfigurationError = true;

        return $exception;
    }
}
