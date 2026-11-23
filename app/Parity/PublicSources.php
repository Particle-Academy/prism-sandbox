<?php

declare(strict_types=1);

namespace App\Parity;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The only way the parity dashboard reaches the network.
 *
 * Every address here is a PUBLIC, unauthenticated GitHub endpoint. No token
 * is sent on purpose: this app is deployed and credential-light, and a request
 * that needed a credential to succeed would be reading something that is not
 * public. An unauthenticated client gets 60 API calls an hour; one snapshot
 * spends about eight, which is why it runs on a schedule and never per request.
 *
 * Nothing here interprets what it fetched. A failure comes back as a value with
 * the reason, so the builder can record it as "unknown" instead of as empty.
 */
class PublicSources
{
    public const RAW = 'https://raw.githubusercontent.com';

    public const API = 'https://api.github.com';

    private const TIMEOUT_SECONDS = 15;

    /**
     * @return array{ok: bool, url: string, status: int|null, json: mixed, body: string|null, error: string|null}
     */
    public function get(string $url): array
    {
        try {
            return $this->result($url, $this->request()->get($url));
        } catch (Throwable $e) {
            return $this->failure($url, null, $e::class);
        }
    }

    /**
     * Fetch several addresses concurrently, keyed as given.
     *
     * @param  array<string, string>  $urls
     * @return array<string, array{ok: bool, url: string, status: int|null, json: mixed, body: string|null, error: string|null}>
     */
    public function getMany(array $urls): array
    {
        if ($urls === []) {
            return [];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($urls): array {
                $requests = [];
                foreach ($urls as $key => $url) {
                    $requests[] = $pool->as((string) $key)
                        ->timeout(self::TIMEOUT_SECONDS)
                        ->withHeaders($this->headers())
                        ->get($url);
                }

                return $requests;
            });
        } catch (Throwable $e) {
            return array_map(fn (string $url): array => $this->failure($url, null, $e::class), $urls);
        }

        $results = [];
        foreach ($urls as $key => $url) {
            $response = $responses[(string) $key] ?? null;
            $results[$key] = $response instanceof Response
                ? $this->result($url, $response)
                : $this->failure($url, null, $response instanceof Throwable ? $response::class : 'no response');
        }

        return $results;
    }

    private function request(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SECONDS)->withHeaders($this->headers());
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        // A generic client name: GitHub asks API clients to identify themselves,
        // and nothing about the person running this belongs in that header.
        return [
            'Accept' => 'application/vnd.github+json, application/json;q=0.9, text/plain;q=0.8',
            'User-Agent' => 'prism-sandbox-parity-dashboard',
        ];
    }

    /**
     * @return array{ok: bool, url: string, status: int|null, json: mixed, body: string|null, error: string|null}
     */
    private function result(string $url, Response $response): array
    {
        if (! $response->successful()) {
            return $this->failure($url, $response->status(), 'HTTP '.$response->status());
        }

        $body = $response->body();
        $json = json_decode($body, true);

        return ['ok' => true, 'url' => $url, 'status' => $response->status(), 'json' => $json, 'body' => $body, 'error' => null];
    }

    /**
     * @return array{ok: bool, url: string, status: int|null, json: mixed, body: string|null, error: string|null}
     */
    private function failure(string $url, ?int $status, string $error): array
    {
        return ['ok' => false, 'url' => $url, 'status' => $status, 'json' => null, 'body' => null, 'error' => $error];
    }
}
