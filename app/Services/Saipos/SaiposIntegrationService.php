<?php

namespace App\Services\Saipos;

use App\DataTransferObjects\Saipos\NormalizedOrder;
use App\DataTransferObjects\Saipos\NormalizedOrderItem;
use App\Exceptions\Saipos\SaiposApiException;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talks to the Saipos Order API and turns its payloads into NormalizedOrder DTOs.
 * Knows nothing about stock: that is StockService's job.
 */
class SaiposIntegrationService
{
    private const TOKEN_CACHE_KEY = 'saipos:auth_token';

    public function __construct(private readonly Cache $cache) {}

    /**
     * Fetch orders created in [$since, $until] and return them normalized.
     * Rows that cannot be read are logged and skipped so one bad order never blocks the batch.
     *
     * @return list<NormalizedOrder>
     */
    public function fetchRecentOrders(CarbonInterface $since, ?CarbonInterface $until = null): array
    {
        $endpoint = config('saipos.endpoints.orders');

        if (blank($endpoint)) {
            throw SaiposApiException::listEndpointNotConfigured();
        }

        $timezone = config('saipos.timezone');
        $format = config('saipos.list_query.date_format');

        $payload = $this->get($endpoint, [
            config('saipos.list_query.store') => $this->codStore(),
            config('saipos.list_query.from') => $since->copy()->setTimezone($timezone)->format($format),
            config('saipos.list_query.to') => ($until ?? now())->copy()->setTimezone($timezone)->format($format),
        ])->json();

        $rows = is_array($payload) && array_is_list($payload) ? $payload : ($payload['data'] ?? []);

        $orders = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            try {
                // A listing may return summaries only; load the full order when items are missing.
                $orders[] = array_key_exists('items', $row)
                    ? $this->normalizeOrder($row)
                    : $this->fetchOrder($this->extractOrderId($row) ?? throw SaiposApiException::malformedOrder('missing order_id'));
            } catch (SaiposApiException $e) {
                Log::warning('Skipping unreadable Saipos order.', ['error' => $e->getMessage(), 'row' => $row]);
            }
        }

        return $orders;
    }

    /** Documented endpoint: GET /order?cod_store={store}&order_id={id}. */
    public function fetchOrder(string $orderId): NormalizedOrder
    {
        $payload = $this->get(config('saipos.endpoints.order'), [
            'cod_store' => $this->codStore(),
            'order_id' => $orderId,
        ])->json();

        if (! is_array($payload)) {
            throw SaiposApiException::malformedOrder("order {$orderId} returned a non-JSON body");
        }

        // The detail response does not echo order_id, so fall back to the one requested.
        return $this->normalizeOrder($payload, $orderId);
    }

    /**
     * Flatten a Saipos order into stock-relevant lines.
     *
     * Field names follow the docs, which use two shapes:
     *  - order JSON:  items[].integration_code / desc_item,     choice_items[].desc_item_choice
     *  - GET /order:  items[].desc_sale_item (+ deleted flag),  choice_items[].desc_sale_item_choice
     * Choice quantities are per unit of the parent item, so they are multiplied by the parent quantity.
     *
     * @param  array<string, mixed>  $raw
     */
    public function normalizeOrder(array $raw, ?string $fallbackOrderId = null): NormalizedOrder
    {
        $orderId = $this->extractOrderId($raw) ?? $fallbackOrderId
            ?? throw SaiposApiException::malformedOrder('missing order_id');

        $items = [];

        foreach ($raw['items'] ?? [] as $item) {
            if (! is_array($item) || $this->isDeleted($item)) {
                continue;
            }

            $quantity = (float) ($item['quantity'] ?? 1);

            if ($quantity <= 0) {
                continue;
            }

            $items[] = new NormalizedOrderItem(
                skuId: $this->stringOrNull($item['integration_code'] ?? $item['item_external_code'] ?? null),
                name: (string) ($item['desc_item'] ?? $item['desc_sale_item'] ?? ''),
                quantity: $quantity,
                unitPrice: $this->numberOrNull($item['unit_price'] ?? null),
            );

            foreach ($item['choice_items'] ?? [] as $choice) {
                if (! is_array($choice) || $this->isDeleted($choice)) {
                    continue;
                }

                $sku = $this->stringOrNull($choice['integration_code'] ?? null);

                // Modifiers without a code (e.g. "no onion", "medium rare") carry no stock impact.
                if ($sku === null) {
                    continue;
                }

                $items[] = new NormalizedOrderItem(
                    skuId: $sku,
                    name: (string) ($choice['desc_item_choice'] ?? $choice['desc_sale_item_choice'] ?? ''),
                    quantity: $quantity * (float) ($choice['quantity'] ?? 1),
                    isChoice: true,
                    // Saipos: item total = (unit_price + Σ aditional_price × choice qty) × quantity.
                    unitPrice: $this->numberOrNull($choice['aditional_price'] ?? null),
                );
            }
        }

        return new NormalizedOrder(
            saiposOrderId: $orderId,
            status: $this->stringOrNull($raw['status'] ?? $raw['event'] ?? $raw['orderStatus'] ?? null),
            items: $items,
            rawPayload: $raw,
            // Order JSON uses total_discount; GET /order uses totalDiscount.
            discount: max(0.0, $this->numberOrNull($raw['total_discount'] ?? $raw['totalDiscount'] ?? null) ?? 0.0),
        );
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /** Returns a cached token, authenticating when there is none. */
    public function token(): string
    {
        return $this->cache->remember(
            self::TOKEN_CACHE_KEY,
            now()->addMinutes((int) config('saipos.token_ttl_minutes')),
            fn (): string => $this->authenticate(),
        );
    }

    /** Documented endpoint: POST /auth { idPartner, secret }. */
    private function authenticate(): string
    {
        $idPartner = config('saipos.id_partner');
        $secret = config('saipos.secret');

        if (blank($idPartner) || blank($secret)) {
            throw SaiposApiException::missingCredentials();
        }

        $path = config('saipos.endpoints.auth');

        try {
            $response = $this->http()->post($path, ['idPartner' => $idPartner, 'secret' => $secret]);
        } catch (ConnectionException $e) {
            throw SaiposApiException::connectionFailed($path, $e);
        }

        if ($response->failed()) {
            throw SaiposApiException::fromResponse($path, $response);
        }

        // The docs do not show the /auth response body, so accept a JSON field or a raw token string.
        $token = $response->json('token') ?? $response->json('access_token');

        if (! is_string($token) || $token === '') {
            $body = trim($response->body());
            $token = ($body !== '' && ! str_starts_with($body, '{')) ? trim($body, '"') : null;
        }

        return $token ?? throw SaiposApiException::tokenMissing();
    }

    /** Authenticated GET with one transparent re-auth if the cached token was rejected. */
    private function get(string $path, array $query): Response
    {
        $response = $this->send($path, $query, $this->token());

        if (in_array($response->status(), [401, 403], true)) {
            $this->cache->forget(self::TOKEN_CACHE_KEY);
            $response = $this->send($path, $query, $this->token());
        }

        if ($response->failed()) {
            throw SaiposApiException::fromResponse($path, $response);
        }

        return $response;
    }

    private function send(string $path, array $query, string $token): Response
    {
        try {
            // Per the docs the raw token goes in the Authorization header, without a "Bearer" prefix.
            return $this->http()->withHeaders(['Authorization' => $token])->get($path, $query);
        } catch (ConnectionException $e) {
            throw SaiposApiException::connectionFailed($path, $e);
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('saipos.base_url'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('saipos.timeout_seconds'))
            ->connectTimeout(5)
            // Retry only transient failures (network, 5xx); 4xx are returned to the caller as-is.
            ->retry(
                [250, 1000, 2000],
                when: fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
    }

    private function codStore(): string
    {
        $codStore = config('saipos.cod_store');

        return blank($codStore) ? throw SaiposApiException::missingCredentials() : (string) $codStore;
    }

    private function extractOrderId(array $row): ?string
    {
        return $this->stringOrNull($row['order_id'] ?? null);
    }

    private function isDeleted(array $row): bool
    {
        $flag = $row['deleted'] ?? false;

        return is_scalar($flag) && in_array(strtoupper((string) $flag), ['1', 'Y', 'S', 'TRUE'], true);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
