<?php

declare(strict_types=1);

namespace App\Services\LLM;

use App\Models\Provider;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class CliproxyService
{
    private const DEFAULT_BASE_URL = "http://localhost:8317";

    private const TIMEOUT_SECONDS = 10;

    public function getBaseUrl(Provider $provider): string
    {
        return mb_rtrim($provider->base_url ?: self::DEFAULT_BASE_URL, "/");
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(Provider $provider): array
    {
        $headers = [];

        if ($provider->api_key) {
            $headers["Authorization"] = "Bearer " . $provider->api_key;
        }

        $extraConfig = $provider->extra_config ?? [];
        $customHeaders = $extraConfig["headers"] ?? [];

        return array_merge($headers, $customHeaders);
    }

    /**
     * @return array{success: bool, message: string, models?: array<string>}
     */
    public function testConnection(Provider $provider): array
    {
        try {
            $baseUrl = $this->getBaseUrl($provider);
            $path = "/models";
            $response = null;
            $workingPath = null;

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders($this->getHeaders($provider))
                ->get($baseUrl . $path);

            if ($response && $response->successful()) {
                $data = $response->json();
                $models = collect($data["data"] ?? [])
                    ->pluck("id")
                    ->toArray();

                return [
                    "success" => true,
                    "message" => "Connected successfully. Found " . count($models) . " models.",
                    "models" => $models,
                ];
            }

            if ($response && $response->status() === 401) {
                return [
                    "success" => false,
                    "message" => "Authentication failed. Check your API key.",
                ];
            }

            return [
                "success" => false,
                "message" => $response
                    ? "Server returned error: " . $response->status()
                    : "Could not connect to CliproxyAPI endpoint.",
            ];
        } catch (ConnectionException) {
            return [
                "success" => false,
                "message" =>
                    "Cannot connect to server. Is it running at " .
                    $this->getBaseUrl($provider) .
                    "?",
            ];
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Connection error: " . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function fetchModels(Provider $provider): array
    {
        try {
            $baseUrl = $this->getBaseUrl($provider);
            $paths = ["/v1/models", "/models", "/api/v1/models"];
            $response = null;

            foreach ($paths as $path) {
                try {
                    $response = Http::timeout(self::TIMEOUT_SECONDS)
                        ->withHeaders($this->getHeaders($provider))
                        ->get($baseUrl . $path);

                    if ($response->successful()) {
                        break;
                    }
                } catch (Exception) {
                    continue;
                }
            }

            if ($response && $response->successful()) {
                $data = $response->json();
                $models = [];

                foreach ($data["data"] ?? [] as $model) {
                    $models[] = [
                        "id" => $model["id"],
                        "name" => $model["id"],
                    ];
                }

                return $models;
            }

            return [];
        } catch (Exception) {
            return [];
        }
    }
}
