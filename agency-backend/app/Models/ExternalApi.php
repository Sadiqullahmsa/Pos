<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExternalApi extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'provider',
        'category',
        'type',
        'base_url',
        'version',
        'endpoints',
        'authentication',
        'headers',
        'parameters',
        'timeout',
        'retry_attempts',
        'is_active',
        'is_sandbox',
        'rate_limits',
        'webhook_config',
        'error_handling',
        'response_mapping',
        'request_log_config',
        'auto_retry',
        'health_check',
        'last_health_check',
        'status',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'endpoints' => 'array',
        'authentication' => 'array',
        'headers' => 'array',
        'parameters' => 'array',
        'rate_limits' => 'array',
        'webhook_config' => 'array',
        'error_handling' => 'array',
        'response_mapping' => 'array',
        'request_log_config' => 'array',
        'health_check' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_sandbox' => 'boolean',
        'auto_retry' => 'boolean',
        'last_health_check' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($api) {
            $api->status = 'active';
        });
    }

    /**
     * Scope for active APIs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    /**
     * Scope for category.
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for provider.
     */
    public function scopeProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Make an API request.
     */
    public function makeRequest(string $endpoint, array $data = [], string $method = 'GET'): array
    {
        try {
            // Check rate limits
            if (!$this->checkRateLimit()) {
                return $this->errorResponse('Rate limit exceeded', 429);
            }

            // Get endpoint configuration
            $endpointConfig = $this->getEndpointConfig($endpoint);
            if (!$endpointConfig) {
                return $this->errorResponse('Endpoint not found', 404);
            }

            // Prepare request
            $url = $this->buildUrl($endpoint, $data);
            $headers = $this->prepareHeaders($endpointConfig);
            $payload = $this->preparePayload($data, $endpointConfig);

            // Make request with retries
            $response = $this->makeHttpRequest($method, $url, $headers, $payload);

            // Process response
            $processedResponse = $this->processResponse($response, $endpointConfig);

            // Log successful request
            $this->logRequest($endpoint, $method, $data, $response);

            return $processedResponse;

        } catch (\Exception $e) {
            // Handle errors
            $errorResponse = $this->handleError($e, $endpoint, $method, $data);
            
            // Log error
            $this->logError($endpoint, $method, $data, $e);

            return $errorResponse;
        }
    }

    /**
     * Check rate limits.
     */
    private function checkRateLimit(): bool
    {
        if (!$this->rate_limits) {
            return true;
        }

        $limits = $this->rate_limits;
        $cacheKey = "api_rate_limit_{$this->id}";

        foreach ($limits as $period => $limit) {
            $periodSeconds = $this->getPeriodSeconds($period);
            $currentCount = Cache::get("{$cacheKey}_{$period}", 0);

            if ($currentCount >= $limit['max_requests']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update rate limit counters.
     */
    private function updateRateLimitCounters(): void
    {
        if (!$this->rate_limits) {
            return;
        }

        $cacheKey = "api_rate_limit_{$this->id}";

        foreach ($this->rate_limits as $period => $limit) {
            $periodSeconds = $this->getPeriodSeconds($period);
            $currentCount = Cache::get("{$cacheKey}_{$period}", 0);
            Cache::put("{$cacheKey}_{$period}", $currentCount + 1, $periodSeconds);
        }
    }

    /**
     * Get period in seconds.
     */
    private function getPeriodSeconds(string $period): int
    {
        $periods = [
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            'month' => 2592000,
        ];

        return $periods[$period] ?? 3600;
    }

    /**
     * Get endpoint configuration.
     */
    private function getEndpointConfig(string $endpoint): ?array
    {
        return $this->endpoints[$endpoint] ?? null;
    }

    /**
     * Build request URL.
     */
    private function buildUrl(string $endpoint, array $data): string
    {
        $endpointConfig = $this->getEndpointConfig($endpoint);
        $path = $endpointConfig['path'] ?? $endpoint;

        // Replace path parameters
        foreach ($data as $key => $value) {
            $path = str_replace("{{$key}}", $value, $path);
        }

        return rtrim($this->base_url, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Prepare request headers.
     */
    private function prepareHeaders(array $endpointConfig): array
    {
        $headers = $this->headers ?? [];
        $endpointHeaders = $endpointConfig['headers'] ?? [];

        // Merge headers
        $headers = array_merge($headers, $endpointHeaders);

        // Add authentication headers
        $headers = array_merge($headers, $this->getAuthHeaders());

        return $headers;
    }

    /**
     * Get authentication headers.
     */
    private function getAuthHeaders(): array
    {
        $auth = $this->authentication ?? [];
        $headers = [];

        switch ($auth['type'] ?? 'none') {
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $auth['token'];
                break;
            case 'api_key':
                $headers[$auth['header_name'] ?? 'X-API-Key'] = $auth['api_key'];
                break;
            case 'basic':
                $headers['Authorization'] = 'Basic ' . base64_encode($auth['username'] . ':' . $auth['password']);
                break;
        }

        return $headers;
    }

    /**
     * Prepare request payload.
     */
    private function preparePayload(array $data, array $endpointConfig): array
    {
        $payload = $this->parameters ?? [];
        $endpointParams = $endpointConfig['parameters'] ?? [];

        // Merge parameters
        $payload = array_merge($payload, $endpointParams, $data);

        // Apply parameter mapping
        if (isset($endpointConfig['parameter_mapping'])) {
            $payload = $this->applyMapping($payload, $endpointConfig['parameter_mapping']);
        }

        return $payload;
    }

    /**
     * Make HTTP request with retries.
     */
    private function makeHttpRequest(string $method, string $url, array $headers, array $payload): array
    {
        $attempts = 0;
        $maxAttempts = $this->retry_attempts ?? 3;

        while ($attempts < $maxAttempts) {
            try {
                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout ?? 30)
                    ->send($method, $url, [
                        'json' => $payload
                    ]);

                if ($response->successful()) {
                    $this->updateRateLimitCounters();
                    return [
                        'success' => true,
                        'data' => $response->json(),
                        'status' => $response->status(),
                        'headers' => $response->headers(),
                    ];
                }

                // If not successful but shouldn't retry, break
                if (!$this->shouldRetry($response->status())) {
                    break;
                }

            } catch (\Exception $e) {
                if ($attempts === $maxAttempts - 1) {
                    throw $e;
                }
            }

            $attempts++;
            
            // Wait before retry
            if ($attempts < $maxAttempts) {
                sleep($this->getRetryDelay($attempts));
            }
        }

        throw new \Exception("API request failed after {$maxAttempts} attempts");
    }

    /**
     * Check if request should be retried.
     */
    private function shouldRetry(int $statusCode): bool
    {
        $retryableCodes = [408, 429, 500, 502, 503, 504];
        return in_array($statusCode, $retryableCodes);
    }

    /**
     * Get retry delay.
     */
    private function getRetryDelay(int $attempt): int
    {
        // Exponential backoff
        return min(pow(2, $attempt), 60);
    }

    /**
     * Process API response.
     */
    private function processResponse(array $response, array $endpointConfig): array
    {
        $data = $response['data'];

        // Apply response mapping
        if (isset($endpointConfig['response_mapping'])) {
            $data = $this->applyMapping($data, $endpointConfig['response_mapping']);
        }

        // Apply global response mapping
        if ($this->response_mapping) {
            $data = $this->applyMapping($data, $this->response_mapping);
        }

        return [
            'success' => true,
            'data' => $data,
            'status' => $response['status'],
            'metadata' => [
                'provider' => $this->provider,
                'endpoint' => $endpointConfig,
                'response_time' => microtime(true) - LARAVEL_START,
            ],
        ];
    }

    /**
     * Apply field mapping.
     */
    private function applyMapping(array $data, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $from => $to) {
            if (isset($data[$from])) {
                $mapped[$to] = $data[$from];
            }
        }

        return $mapped;
    }

    /**
     * Handle API errors.
     */
    private function handleError(\Exception $e, string $endpoint, string $method, array $data): array
    {
        $errorHandling = $this->error_handling ?? [];
        
        // Update API status if needed
        if ($this->shouldUpdateStatus($e)) {
            $this->update(['status' => 'error']);
        }

        // Get error message
        $errorMessage = $this->getErrorMessage($e, $errorHandling);

        return $this->errorResponse($errorMessage, $this->getErrorCode($e));
    }

    /**
     * Check if API status should be updated.
     */
    private function shouldUpdateStatus(\Exception $e): bool
    {
        $criticalErrors = [
            'Connection refused',
            'Connection timeout',
            'SSL certificate problem',
        ];

        foreach ($criticalErrors as $error) {
            if (strpos($e->getMessage(), $error) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get error message.
     */
    private function getErrorMessage(\Exception $e, array $errorHandling): string
    {
        $message = $e->getMessage();

        // Apply error message mapping
        if (isset($errorHandling['message_mapping'])) {
            foreach ($errorHandling['message_mapping'] as $pattern => $replacement) {
                if (strpos($message, $pattern) !== false) {
                    return $replacement;
                }
            }
        }

        return $message;
    }

    /**
     * Get error code.
     */
    private function getErrorCode(\Exception $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Create error response.
     */
    private function errorResponse(string $message, int $code): array
    {
        return [
            'success' => false,
            'error' => $message,
            'code' => $code,
            'provider' => $this->provider,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Log API request.
     */
    private function logRequest(string $endpoint, string $method, array $data, array $response): void
    {
        if (!$this->shouldLogRequest()) {
            return;
        }

        Log::info("External API Request", [
            'api_id' => $this->id,
            'provider' => $this->provider,
            'endpoint' => $endpoint,
            'method' => $method,
            'data' => $data,
            'response_status' => $response['status'],
            'response_size' => strlen(json_encode($response['data'])),
        ]);
    }

    /**
     * Log API error.
     */
    private function logError(string $endpoint, string $method, array $data, \Exception $e): void
    {
        Log::error("External API Error", [
            'api_id' => $this->id,
            'provider' => $this->provider,
            'endpoint' => $endpoint,
            'method' => $method,
            'data' => $data,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Check if request should be logged.
     */
    private function shouldLogRequest(): bool
    {
        $logConfig = $this->request_log_config ?? [];
        return $logConfig['enabled'] ?? false;
    }

    /**
     * Perform health check.
     */
    public function performHealthCheck(): array
    {
        $healthCheck = $this->health_check ?? [];
        
        if (!$healthCheck || !isset($healthCheck['endpoint'])) {
            return ['status' => 'skipped', 'message' => 'No health check configured'];
        }

        try {
            $response = $this->makeRequest($healthCheck['endpoint'], [], 'GET');
            
            $isHealthy = $this->evaluateHealthResponse($response, $healthCheck);
            
            $result = [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'response' => $response,
                'checked_at' => now()->toISOString(),
            ];

            // Update health check timestamp
            $this->update(['last_health_check' => now()]);

            // Update API status based on health
            if (!$isHealthy && $this->status !== 'error') {
                $this->update(['status' => 'error']);
            } elseif ($isHealthy && $this->status === 'error') {
                $this->update(['status' => 'active']);
            }

            return $result;

        } catch (\Exception $e) {
            $this->update([
                'status' => 'error',
                'last_health_check' => now(),
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'checked_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * Evaluate health response.
     */
    private function evaluateHealthResponse(array $response, array $healthCheck): bool
    {
        if (!$response['success']) {
            return false;
        }

        // Check expected status code
        if (isset($healthCheck['expected_status'])) {
            if ($response['status'] !== $healthCheck['expected_status']) {
                return false;
            }
        }

        // Check expected response fields
        if (isset($healthCheck['expected_fields'])) {
            foreach ($healthCheck['expected_fields'] as $field) {
                if (!isset($response['data'][$field])) {
                    return false;
                }
            }
        }

        // Check expected values
        if (isset($healthCheck['expected_values'])) {
            foreach ($healthCheck['expected_values'] as $field => $value) {
                if (($response['data'][$field] ?? null) !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get API statistics.
     */
    public function getStatistics(): array
    {
        $cacheKey = "api_stats_{$this->id}";
        
        return Cache::remember($cacheKey, 300, function () {
            // This would typically query an api_requests log table
            // For now, return basic stats
            return [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'success_rate' => 0,
                'average_response_time' => 0,
                'last_request' => null,
                'status' => $this->status,
                'last_health_check' => $this->last_health_check,
            ];
        });
    }

    /**
     * Test API connection.
     */
    public function testConnection(): array
    {
        try {
            $testEndpoint = $this->getTestEndpoint();
            $response = $this->makeRequest($testEndpoint, [], 'GET');
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'response' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get test endpoint.
     */
    private function getTestEndpoint(): string
    {
        // Return health check endpoint or first available endpoint
        $healthCheck = $this->health_check ?? [];
        
        if (isset($healthCheck['endpoint'])) {
            return $healthCheck['endpoint'];
        }

        $endpoints = array_keys($this->endpoints ?? []);
        return $endpoints[0] ?? '';
    }

    /**
     * Get provider-specific configuration.
     */
    public static function getProviderConfig(string $provider): array
    {
        $configs = [
            'razorpay' => [
                'name' => 'Razorpay',
                'category' => 'payment',
                'base_url' => 'https://api.razorpay.com/v1',
                'authentication' => ['type' => 'basic'],
                'endpoints' => [
                    'orders' => ['path' => '/orders', 'method' => 'POST'],
                    'payments' => ['path' => '/payments', 'method' => 'GET'],
                    'customers' => ['path' => '/customers', 'method' => 'POST'],
                ],
            ],
            'twilio' => [
                'name' => 'Twilio',
                'category' => 'sms',
                'base_url' => 'https://api.twilio.com/2010-04-01',
                'authentication' => ['type' => 'basic'],
                'endpoints' => [
                    'send_sms' => ['path' => '/Accounts/{account_sid}/Messages.json', 'method' => 'POST'],
                    'get_message' => ['path' => '/Accounts/{account_sid}/Messages/{message_sid}.json', 'method' => 'GET'],
                ],
            ],
            'sendgrid' => [
                'name' => 'SendGrid',
                'category' => 'email',
                'base_url' => 'https://api.sendgrid.com/v3',
                'authentication' => ['type' => 'bearer'],
                'endpoints' => [
                    'send_email' => ['path' => '/mail/send', 'method' => 'POST'],
                    'templates' => ['path' => '/templates', 'method' => 'GET'],
                ],
            ],
            'google_maps' => [
                'name' => 'Google Maps',
                'category' => 'maps',
                'base_url' => 'https://maps.googleapis.com/maps/api',
                'authentication' => ['type' => 'api_key'],
                'endpoints' => [
                    'geocoding' => ['path' => '/geocode/json', 'method' => 'GET'],
                    'directions' => ['path' => '/directions/json', 'method' => 'GET'],
                    'distance_matrix' => ['path' => '/distancematrix/json', 'method' => 'GET'],
                ],
            ],
        ];

        return $configs[$provider] ?? [];
    }

    /**
     * Initialize default APIs.
     */
    public static function initializeDefaults(): void
    {
        $defaultProviders = ['razorpay', 'twilio', 'sendgrid', 'google_maps'];

        foreach ($defaultProviders as $provider) {
            if (!static::where('provider', $provider)->exists()) {
                $config = static::getProviderConfig($provider);
                
                if ($config) {
                    static::create([
                        'name' => $config['name'],
                        'provider' => $provider,
                        'category' => $config['category'],
                        'type' => 'rest',
                        'base_url' => $config['base_url'],
                        'endpoints' => $config['endpoints'],
                        'authentication' => $config['authentication'],
                        'is_active' => false, // Inactive by default until configured
                    ]);
                }
            }
        }
    }
}
