<?php

namespace App\Http\Controllers;

use App\Models\ExternalApi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ExternalApiController extends Controller
{
    /**
     * Display a listing of external APIs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ExternalApi::orderBy('is_active', 'desc')
                ->orderBy('provider', 'asc')
                ->orderBy('name', 'asc');

            // Apply filters
            if ($request->has('provider')) {
                $query->where('provider', $request->provider);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('provider', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $apis = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $apis,
                'summary' => [
                    'total_apis' => ExternalApi::count(),
                    'active_apis' => ExternalApi::where('is_active', true)->count(),
                    'healthy_apis' => ExternalApi::where('health_status', 'healthy')->count(),
                    'providers' => ExternalApi::distinct('provider')->pluck('provider'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('External API listing failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve external APIs'
            ], 500);
        }
    }

    /**
     * Store a newly created external API
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'provider' => 'required|string|max:100',
                'description' => 'nullable|string|max:500',
                'base_url' => 'required|url|max:500',
                'api_key' => 'nullable|string|max:255',
                'api_secret' => 'nullable|string|max:255',
                'authentication_type' => 'required|string|in:none,api_key,bearer_token,basic_auth,oauth2,custom',
                'headers' => 'nullable|array',
                'timeout' => 'nullable|integer|min:1|max:300',
                'retry_attempts' => 'nullable|integer|min:0|max:10',
                'retry_delay' => 'nullable|integer|min:0|max:60',
                'rate_limit_requests' => 'nullable|integer|min:1',
                'rate_limit_period' => 'nullable|integer|min:1',
                'health_check_url' => 'nullable|url|max:500',
                'health_check_interval' => 'nullable|integer|min:1|max:1440',
                'is_active' => 'boolean',
                'enable_logging' => 'boolean',
                'enable_caching' => 'boolean',
                'cache_duration' => 'nullable|integer|min:1|max:86400',
                'configuration' => 'nullable|array',
            ]);

            DB::beginTransaction();

            $api = ExternalApi::create([
                'name' => $request->name,
                'provider' => $request->provider,
                'description' => $request->description,
                'base_url' => $request->base_url,
                'api_key' => $request->api_key,
                'api_secret' => $request->api_secret,
                'authentication_type' => $request->authentication_type,
                'headers' => $request->headers ?? [],
                'timeout' => $request->timeout ?? 30,
                'retry_attempts' => $request->retry_attempts ?? 3,
                'retry_delay' => $request->retry_delay ?? 1,
                'rate_limit_requests' => $request->rate_limit_requests ?? 100,
                'rate_limit_period' => $request->rate_limit_period ?? 60,
                'health_check_url' => $request->health_check_url,
                'health_check_interval' => $request->health_check_interval ?? 30,
                'is_active' => $request->is_active ?? true,
                'enable_logging' => $request->enable_logging ?? true,
                'enable_caching' => $request->enable_caching ?? false,
                'cache_duration' => $request->cache_duration ?? 300,
                'configuration' => $request->configuration ?? [],
                'status' => 'pending',
                'health_status' => 'unknown',
                'created_by' => Auth::id(),
                'metadata' => [
                    'created_source' => 'web',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);

            // Perform initial health check
            if ($api->health_check_url) {
                $this->performHealthCheck($api);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'External API created successfully',
                'data' => $api
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('External API creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create external API'
            ], 500);
        }
    }

    /**
     * Display the specified external API
     */
    public function show(ExternalApi $api): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $api,
                'health_history' => $api->health_history ?? [],
                'usage_statistics' => $api->usage_statistics ?? [],
                'recent_requests' => $api->recent_requests ?? [],
                'rate_limit_status' => $this->getRateLimitStatus($api),
            ]);
        } catch (\Exception $e) {
            Log::error('External API retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve external API'
            ], 500);
        }
    }

    /**
     * Update the specified external API
     */
    public function update(Request $request, ExternalApi $api): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'provider' => 'sometimes|string|max:100',
                'description' => 'nullable|string|max:500',
                'base_url' => 'sometimes|url|max:500',
                'api_key' => 'nullable|string|max:255',
                'api_secret' => 'nullable|string|max:255',
                'authentication_type' => 'sometimes|string|in:none,api_key,bearer_token,basic_auth,oauth2,custom',
                'headers' => 'nullable|array',
                'timeout' => 'nullable|integer|min:1|max:300',
                'retry_attempts' => 'nullable|integer|min:0|max:10',
                'retry_delay' => 'nullable|integer|min:0|max:60',
                'rate_limit_requests' => 'nullable|integer|min:1',
                'rate_limit_period' => 'nullable|integer|min:1',
                'health_check_url' => 'nullable|url|max:500',
                'health_check_interval' => 'nullable|integer|min:1|max:1440',
                'is_active' => 'sometimes|boolean',
                'enable_logging' => 'sometimes|boolean',
                'enable_caching' => 'sometimes|boolean',
                'cache_duration' => 'nullable|integer|min:1|max:86400',
                'configuration' => 'nullable|array',
            ]);

            $api->update($request->all());

            // Update health check if URL changed
            if ($request->has('health_check_url') && $api->health_check_url) {
                $this->performHealthCheck($api);
            }

            return response()->json([
                'success' => true,
                'message' => 'External API updated successfully',
                'data' => $api
            ]);

        } catch (\Exception $e) {
            Log::error('External API update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update external API'
            ], 500);
        }
    }

    /**
     * Remove the specified external API
     */
    public function destroy(ExternalApi $api): JsonResponse
    {
        try {
            // Check if API is currently being used
            if ($this->isApiInUse($api)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete API that is currently in use'
                ], 400);
            }

            $api->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'External API deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('External API deletion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete external API'
            ], 500);
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(Request $request, ExternalApi $api): JsonResponse
    {
        try {
            $request->validate([
                'endpoint' => 'nullable|string|max:255',
                'method' => 'nullable|string|in:GET,POST,PUT,DELETE,PATCH',
                'data' => 'nullable|array',
            ]);

            $endpoint = $request->endpoint ?? '';
            $method = $request->method ?? 'GET';
            $data = $request->data ?? [];

            $startTime = microtime(true);
            $response = $this->makeApiRequest($api, $endpoint, $method, $data);
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);

            return response()->json([
                'success' => true,
                'message' => 'API connection test completed',
                'data' => [
                    'status' => $response['success'] ? 'success' : 'failed',
                    'response_time' => $responseTime,
                    'status_code' => $response['status_code'] ?? null,
                    'response_data' => $response['data'] ?? null,
                    'error' => $response['error'] ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('API connection test failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to test API connection'
            ], 500);
        }
    }

    /**
     * Perform health check
     */
    public function performHealthCheck(ExternalApi $api): JsonResponse
    {
        try {
            $healthStatus = $this->checkApiHealth($api);
            
            // Update API health status
            $api->update([
                'health_status' => $healthStatus['status'],
                'last_health_check' => now(),
                'health_check_response_time' => $healthStatus['response_time'],
            ]);

            // Log health check
            $this->logHealthCheck($api, $healthStatus);

            return response()->json([
                'success' => true,
                'message' => 'Health check completed',
                'data' => $healthStatus
            ]);

        } catch (\Exception $e) {
            Log::error('API health check failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform health check'
            ], 500);
        }
    }

    /**
     * Get API statistics
     */
    public function getStatistics(ExternalApi $api): JsonResponse
    {
        try {
            $statistics = [
                'total_requests' => $api->total_requests ?? 0,
                'successful_requests' => $api->successful_requests ?? 0,
                'failed_requests' => $api->failed_requests ?? 0,
                'average_response_time' => $api->average_response_time ?? 0,
                'uptime_percentage' => $this->calculateUptime($api),
                'rate_limit_hits' => $api->rate_limit_hits ?? 0,
                'last_request_at' => $api->last_request_at,
                'health_status' => $api->health_status,
                'last_health_check' => $api->last_health_check,
                'daily_usage' => $this->getDailyUsage($api),
                'error_breakdown' => $this->getErrorBreakdown($api),
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('API statistics retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve API statistics'
            ], 500);
        }
    }

    /**
     * Update API status
     */
    public function updateStatus(Request $request, ExternalApi $api): JsonResponse
    {
        try {
            $request->validate([
                'is_active' => 'required|boolean',
                'reason' => 'nullable|string|max:255',
            ]);

            $api->update([
                'is_active' => $request->is_active,
                'status_updated_at' => now(),
                'status_updated_by' => Auth::id(),
            ]);

            // Log status change
            $this->logStatusChange($api, $request->is_active, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'API status updated successfully',
                'data' => $api
            ]);

        } catch (\Exception $e) {
            Log::error('API status update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update API status'
            ], 500);
        }
    }

    /**
     * Get provider configurations
     */
    public function getProviderConfigs(): JsonResponse
    {
        try {
            $configs = [
                'payment_gateways' => [
                    'razorpay' => [
                        'name' => 'Razorpay',
                        'auth_type' => 'api_key',
                        'required_fields' => ['key_id', 'key_secret'],
                        'base_url' => 'https://api.razorpay.com/v1',
                        'health_check_endpoint' => '/payments',
                    ],
                    'paytm' => [
                        'name' => 'Paytm',
                        'auth_type' => 'custom',
                        'required_fields' => ['merchant_id', 'merchant_key'],
                        'base_url' => 'https://securegw.paytm.in',
                        'health_check_endpoint' => '/theia/api/v1/showPaymentPage',
                    ],
                    'stripe' => [
                        'name' => 'Stripe',
                        'auth_type' => 'bearer_token',
                        'required_fields' => ['secret_key'],
                        'base_url' => 'https://api.stripe.com/v1',
                        'health_check_endpoint' => '/charges',
                    ],
                ],
                'sms_providers' => [
                    'twilio' => [
                        'name' => 'Twilio',
                        'auth_type' => 'basic_auth',
                        'required_fields' => ['account_sid', 'auth_token'],
                        'base_url' => 'https://api.twilio.com/2010-04-01',
                        'health_check_endpoint' => '/Accounts.json',
                    ],
                    'textlocal' => [
                        'name' => 'TextLocal',
                        'auth_type' => 'api_key',
                        'required_fields' => ['api_key'],
                        'base_url' => 'https://api.textlocal.in',
                        'health_check_endpoint' => '/balance',
                    ],
                ],
                'email_providers' => [
                    'sendgrid' => [
                        'name' => 'SendGrid',
                        'auth_type' => 'bearer_token',
                        'required_fields' => ['api_key'],
                        'base_url' => 'https://api.sendgrid.com/v3',
                        'health_check_endpoint' => '/user/profile',
                    ],
                    'mailgun' => [
                        'name' => 'Mailgun',
                        'auth_type' => 'basic_auth',
                        'required_fields' => ['api_key', 'domain'],
                        'base_url' => 'https://api.mailgun.net/v3',
                        'health_check_endpoint' => '/domains',
                    ],
                ],
                'maps_providers' => [
                    'google_maps' => [
                        'name' => 'Google Maps',
                        'auth_type' => 'api_key',
                        'required_fields' => ['api_key'],
                        'base_url' => 'https://maps.googleapis.com/maps/api',
                        'health_check_endpoint' => '/geocode/json',
                    ],
                    'mapbox' => [
                        'name' => 'Mapbox',
                        'auth_type' => 'api_key',
                        'required_fields' => ['access_token'],
                        'base_url' => 'https://api.mapbox.com',
                        'health_check_endpoint' => '/geocoding/v5/mapbox.places',
                    ],
                ],
                'currency_providers' => [
                    'fixer' => [
                        'name' => 'Fixer.io',
                        'auth_type' => 'api_key',
                        'required_fields' => ['api_key'],
                        'base_url' => 'https://api.fixer.io',
                        'health_check_endpoint' => '/latest',
                    ],
                    'openexchangerates' => [
                        'name' => 'Open Exchange Rates',
                        'auth_type' => 'api_key',
                        'required_fields' => ['app_id'],
                        'base_url' => 'https://openexchangerates.org/api',
                        'health_check_endpoint' => '/latest.json',
                    ],
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $configs
            ]);

        } catch (\Exception $e) {
            Log::error('Provider configs retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve provider configurations'
            ], 500);
        }
    }

    /**
     * Initialize default APIs
     */
    public function initializeDefaults(): JsonResponse
    {
        try {
            $defaultApis = [
                [
                    'name' => 'Razorpay Payment Gateway',
                    'provider' => 'razorpay',
                    'description' => 'Payment processing for Indian customers',
                    'base_url' => 'https://api.razorpay.com/v1',
                    'authentication_type' => 'basic_auth',
                    'health_check_url' => 'https://api.razorpay.com/v1/payments',
                    'is_active' => false,
                ],
                [
                    'name' => 'Twilio SMS Service',
                    'provider' => 'twilio',
                    'description' => 'SMS notifications and communication',
                    'base_url' => 'https://api.twilio.com/2010-04-01',
                    'authentication_type' => 'basic_auth',
                    'health_check_url' => 'https://api.twilio.com/2010-04-01/Accounts.json',
                    'is_active' => false,
                ],
                [
                    'name' => 'SendGrid Email Service',
                    'provider' => 'sendgrid',
                    'description' => 'Email notifications and communication',
                    'base_url' => 'https://api.sendgrid.com/v3',
                    'authentication_type' => 'bearer_token',
                    'health_check_url' => 'https://api.sendgrid.com/v3/user/profile',
                    'is_active' => false,
                ],
                [
                    'name' => 'Google Maps API',
                    'provider' => 'google_maps',
                    'description' => 'Location services and geocoding',
                    'base_url' => 'https://maps.googleapis.com/maps/api',
                    'authentication_type' => 'api_key',
                    'health_check_url' => 'https://maps.googleapis.com/maps/api/geocode/json',
                    'is_active' => false,
                ],
                [
                    'name' => 'Fixer Currency Exchange',
                    'provider' => 'fixer',
                    'description' => 'Real-time currency exchange rates',
                    'base_url' => 'https://api.fixer.io',
                    'authentication_type' => 'api_key',
                    'health_check_url' => 'https://api.fixer.io/latest',
                    'is_active' => false,
                ],
            ];

            $created = 0;
            $existing = 0;

            foreach ($defaultApis as $apiData) {
                $existingApi = ExternalApi::where('provider', $apiData['provider'])->first();
                
                if (!$existingApi) {
                    $apiData['status'] = 'pending';
                    $apiData['health_status'] = 'unknown';
                    $apiData['created_by'] = Auth::id();
                    
                    ExternalApi::create($apiData);
                    $created++;
                } else {
                    $existing++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Default APIs initialization completed',
                'data' => [
                    'created' => $created,
                    'existing' => $existing,
                    'total' => count($defaultApis),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Default APIs initialization failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize default APIs'
            ], 500);
        }
    }

    /**
     * Make API request with retry logic
     */
    private function makeApiRequest(ExternalApi $api, string $endpoint, string $method = 'GET', array $data = []): array
    {
        $attempts = 0;
        $maxAttempts = $api->retry_attempts + 1;

        while ($attempts < $maxAttempts) {
            try {
                // Check rate limit
                if (!$this->checkRateLimit($api)) {
                    return [
                        'success' => false,
                        'error' => 'Rate limit exceeded',
                        'status_code' => 429,
                    ];
                }

                // Prepare request
                $url = rtrim($api->base_url, '/') . '/' . ltrim($endpoint, '/');
                $headers = $this->buildHeaders($api);
                
                // Make request
                $response = Http::timeout($api->timeout)
                    ->withHeaders($headers)
                    ->retry($api->retry_attempts, $api->retry_delay * 1000)
                    ->{strtolower($method)}($url, $data);

                // Log request if enabled
                if ($api->enable_logging) {
                    $this->logApiRequest($api, $method, $url, $data, $response);
                }

                // Update statistics
                $this->updateApiStatistics($api, $response->successful());

                return [
                    'success' => $response->successful(),
                    'status_code' => $response->status(),
                    'data' => $response->json(),
                    'headers' => $response->headers(),
                ];

            } catch (\Exception $e) {
                $attempts++;
                
                if ($attempts >= $maxAttempts) {
                    // Log error
                    if ($api->enable_logging) {
                        $this->logApiError($api, $method, $endpoint, $e);
                    }
                    
                    // Update statistics
                    $this->updateApiStatistics($api, false);
                    
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'attempts' => $attempts,
                    ];
                }

                // Wait before retry
                sleep($api->retry_delay);
            }
        }

        return [
            'success' => false,
            'error' => 'Max retry attempts reached',
            'attempts' => $attempts,
        ];
    }

    /**
     * Build request headers
     */
    private function buildHeaders(ExternalApi $api): array
    {
        $headers = $api->headers ?? [];

        switch ($api->authentication_type) {
            case 'api_key':
                $headers['Authorization'] = 'Bearer ' . $api->api_key;
                break;
            case 'bearer_token':
                $headers['Authorization'] = 'Bearer ' . $api->api_key;
                break;
            case 'basic_auth':
                $headers['Authorization'] = 'Basic ' . base64_encode($api->api_key . ':' . $api->api_secret);
                break;
        }

        return $headers;
    }

    /**
     * Check rate limit
     */
    private function checkRateLimit(ExternalApi $api): bool
    {
        $key = "rate_limit_api_{$api->id}";
        $requests = Cache::get($key, 0);

        if ($requests >= $api->rate_limit_requests) {
            return false;
        }

        Cache::put($key, $requests + 1, now()->addSeconds($api->rate_limit_period));
        return true;
    }

    /**
     * Check API health
     */
    private function checkApiHealth(ExternalApi $api): array
    {
        if (!$api->health_check_url) {
            return [
                'status' => 'unknown',
                'message' => 'No health check URL configured',
                'response_time' => null,
            ];
        }

        try {
            $startTime = microtime(true);
            $response = Http::timeout($api->timeout)
                ->withHeaders($this->buildHeaders($api))
                ->get($api->health_check_url);
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'message' => $response->successful() ? 'API is responding' : 'API is not responding',
                'response_time' => $responseTime,
                'status_code' => $response->status(),
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'response_time' => null,
            ];
        }
    }

    /**
     * Get rate limit status
     */
    private function getRateLimitStatus(ExternalApi $api): array
    {
        $key = "rate_limit_api_{$api->id}";
        $requests = Cache::get($key, 0);
        $remaining = max(0, $api->rate_limit_requests - $requests);
        $ttl = Cache::getStore()->getRedis()->ttl($key);

        return [
            'limit' => $api->rate_limit_requests,
            'remaining' => $remaining,
            'reset_in' => $ttl > 0 ? $ttl : 0,
            'period' => $api->rate_limit_period,
        ];
    }

    /**
     * Calculate uptime percentage
     */
    private function calculateUptime(ExternalApi $api): float
    {
        $history = $api->health_history ?? [];
        if (empty($history)) {
            return 0;
        }

        $totalChecks = count($history);
        $healthyChecks = count(array_filter($history, fn($h) => $h['status'] === 'healthy'));

        return round(($healthyChecks / $totalChecks) * 100, 2);
    }

    /**
     * Get daily usage statistics
     */
    private function getDailyUsage(ExternalApi $api): array
    {
        $usage = $api->usage_statistics ?? [];
        $today = now()->format('Y-m-d');
        
        return $usage[$today] ?? [
            'requests' => 0,
            'successful' => 0,
            'failed' => 0,
            'average_response_time' => 0,
        ];
    }

    /**
     * Get error breakdown
     */
    private function getErrorBreakdown(ExternalApi $api): array
    {
        $errors = $api->error_statistics ?? [];
        return array_slice($errors, 0, 10); // Top 10 errors
    }

    /**
     * Check if API is in use
     */
    private function isApiInUse(ExternalApi $api): bool
    {
        // Check if API is currently being used by any active processes
        return false; // Placeholder implementation
    }

    /**
     * Log health check
     */
    private function logHealthCheck(ExternalApi $api, array $healthStatus): void
    {
        $history = $api->health_history ?? [];
        $history[] = [
            'status' => $healthStatus['status'],
            'message' => $healthStatus['message'],
            'response_time' => $healthStatus['response_time'],
            'timestamp' => now(),
        ];

        // Keep only last 100 records
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        $api->update(['health_history' => $history]);
    }

    /**
     * Log API request
     */
    private function logApiRequest(ExternalApi $api, string $method, string $url, array $data, $response): void
    {
        $requests = $api->recent_requests ?? [];
        $requests[] = [
            'method' => $method,
            'url' => $url,
            'data' => $data,
            'status_code' => $response->status(),
            'response_time' => $response->getHeaders()['X-Response-Time'][0] ?? null,
            'timestamp' => now(),
        ];

        // Keep only last 50 requests
        if (count($requests) > 50) {
            $requests = array_slice($requests, -50);
        }

        $api->update(['recent_requests' => $requests]);
    }

    /**
     * Log API error
     */
    private function logApiError(ExternalApi $api, string $method, string $endpoint, \Exception $e): void
    {
        $errors = $api->error_statistics ?? [];
        $errorKey = $e->getCode() . ': ' . $e->getMessage();
        
        if (!isset($errors[$errorKey])) {
            $errors[$errorKey] = [
                'count' => 0,
                'first_occurrence' => now(),
            ];
        }
        
        $errors[$errorKey]['count']++;
        $errors[$errorKey]['last_occurrence'] = now();

        $api->update(['error_statistics' => $errors]);
    }

    /**
     * Log status change
     */
    private function logStatusChange(ExternalApi $api, bool $isActive, ?string $reason): void
    {
        $statusHistory = $api->status_history ?? [];
        $statusHistory[] = [
            'status' => $isActive ? 'activated' : 'deactivated',
            'reason' => $reason,
            'timestamp' => now(),
            'changed_by' => Auth::id(),
        ];

        $api->update(['status_history' => $statusHistory]);
    }

    /**
     * Update API statistics
     */
    private function updateApiStatistics(ExternalApi $api, bool $success): void
    {
        $api->increment('total_requests');
        
        if ($success) {
            $api->increment('successful_requests');
        } else {
            $api->increment('failed_requests');
        }

        $api->update(['last_request_at' => now()]);
    }
}
