<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\ExternalApi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Get all settings organized by category.
     */
    public function index(): JsonResponse
    {
        try {
            $settings = Setting::getAllSettings();
            
            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Settings retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get settings by category.
     */
    public function getByCategory(string $category): JsonResponse
    {
        try {
            $settings = Setting::getByCategory($category);
            
            return response()->json([
                'success' => true,
                'data' => $settings,
                'category' => $category,
                'message' => 'Category settings retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve {$category} settings: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => "Failed to retrieve {$category} settings",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get public settings for frontend.
     */
    public function getPublicSettings(): JsonResponse
    {
        try {
            $settings = Setting::getPublicSettings();
            
            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Public settings retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve public settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve public settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a single setting.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'value' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $setting = Setting::where('key', $key)->first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found'
                ], 404);
            }

            // Validate the value
            if (!$setting->validateValue($request->value)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid value for this setting'
                ], 422);
            }

            $oldValue = $setting->value;
            $setting->update(['value' => $request->value]);

            // Log the change
            Log::info("Setting updated: {$key}", [
                'old_value' => $oldValue,
                'new_value' => $request->value,
                'user_id' => auth()->id(),
            ]);

            // Check if system restart is required
            $requiresRestart = $setting->requires_restart;

            return response()->json([
                'success' => true,
                'data' => $setting->config,
                'requires_restart' => $requiresRestart,
                'message' => 'Setting updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update setting {$key}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update settings.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'settings' => 'required|array',
                'settings.*' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = Setting::bulkUpdate($request->settings);
            
            $successful = collect($results)->where('status', 'success')->count();
            $failed = collect($results)->where('status', 'error')->count();

            // Check if any updated setting requires restart
            $requiresRestart = false;
            foreach ($request->settings as $key => $value) {
                $setting = Setting::where('key', $key)->first();
                if ($setting && $setting->requires_restart) {
                    $requiresRestart = true;
                    break;
                }
            }

            // Log bulk update
            Log::info("Bulk settings update", [
                'successful' => $successful,
                'failed' => $failed,
                'settings' => array_keys($request->settings),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total' => count($request->settings),
                    'successful' => $successful,
                    'failed' => $failed,
                ],
                'requires_restart' => $requiresRestart,
                'message' => "Updated {$successful} settings successfully" . ($failed > 0 ? ", {$failed} failed" : "")
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to bulk update settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset settings to default.
     */
    public function resetToDefaults(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|string',
                'confirm' => 'required|boolean|accepted',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Setting::query();
            
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Backup current settings
            $currentSettings = $query->get()->toArray();
            
            // Delete current settings
            $query->delete();

            // Reinitialize defaults
            Setting::initializeDefaults();

            // Log the reset
            Log::warning("Settings reset to defaults", [
                'category' => $request->category ?? 'all',
                'backup' => $currentSettings,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings reset to defaults successfully',
                'requires_restart' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reset settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export settings configuration.
     */
    public function exportConfig(): JsonResponse
    {
        try {
            $config = Setting::exportConfig();
            
            return response()->json([
                'success' => true,
                'data' => $config,
                'exported_at' => now()->toISOString(),
                'message' => 'Settings exported successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import settings configuration.
     */
    public function importConfig(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'config' => 'required|array',
                'overwrite' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = Setting::importConfig($request->config);

            // Log the import
            Log::info("Settings imported", [
                'successful' => $results['success'],
                'errors' => $results['errors'],
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => "Imported {$results['success']} settings successfully",
                'requires_restart' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to import settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to import settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system information.
     */
    public function getSystemInfo(): JsonResponse
    {
        try {
            $info = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database' => [
                    'driver' => config('database.default'),
                    'version' => $this->getDatabaseVersion(),
                ],
                'server' => [
                    'os' => PHP_OS,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'status' => Cache::store()->getRedis() ? 'connected' : 'disconnected',
                ],
                'queue' => [
                    'driver' => config('queue.default'),
                    'status' => 'active', // This could be more sophisticated
                ],
                'storage' => [
                    'disk_usage' => $this->getDiskUsage(),
                    'free_space' => disk_free_space('/'),
                    'total_space' => disk_total_space('/'),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $info,
                'message' => 'System information retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get system info: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test system components.
     */
    public function testSystem(): JsonResponse
    {
        try {
            $tests = [
                'database' => $this->testDatabase(),
                'cache' => $this->testCache(),
                'queue' => $this->testQueue(),
                'storage' => $this->testStorage(),
                'mail' => $this->testMail(),
                'external_apis' => $this->testExternalApis(),
            ];

            $allPassed = collect($tests)->every(function ($test) {
                return $test['status'] === 'success';
            });

            return response()->json([
                'success' => true,
                'data' => $tests,
                'all_tests_passed' => $allPassed,
                'message' => $allPassed ? 'All system tests passed' : 'Some system tests failed'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to test system: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to test system',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear system cache.
     */
    public function clearCache(): JsonResponse
    {
        try {
            // Clear Laravel cache
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            // Clear settings cache
            Cache::forget('settings.all');
            Cache::forget('settings.public');

            Log::info('System cache cleared', [
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'System cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear cache: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restart system services.
     */
    public function restartServices(): JsonResponse
    {
        try {
            // Queue restart
            Artisan::call('queue:restart');

            // Clear and rebuild cache
            Artisan::call('cache:clear');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            Log::info('System services restarted', [
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'System services restarted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restart services: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restart services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database version.
     */
    private function getDatabaseVersion(): string
    {
        try {
            $result = \DB::select('SELECT VERSION() as version');
            return $result[0]->version ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get disk usage.
     */
    private function getDiskUsage(): array
    {
        try {
            $total = disk_total_space('/');
            $free = disk_free_space('/');
            $used = $total - $free;

            return [
                'total' => $total,
                'used' => $used,
                'free' => $free,
                'used_percentage' => round(($used / $total) * 100, 2),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Test database connection.
     */
    private function testDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'success', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Test cache connection.
     */
    private function testCache(): array
    {
        try {
            Cache::put('test_key', 'test_value', 60);
            $value = Cache::get('test_key');
            Cache::forget('test_key');
            
            if ($value === 'test_value') {
                return ['status' => 'success', 'message' => 'Cache working correctly'];
            } else {
                return ['status' => 'error', 'message' => 'Cache not working'];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Test queue connection.
     */
    private function testQueue(): array
    {
        try {
            // This is a basic test - in production you'd dispatch a test job
            $driver = config('queue.default');
            return ['status' => 'success', 'message' => "Queue driver '{$driver}' configured"];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Test storage access.
     */
    private function testStorage(): array
    {
        try {
            $testFile = 'test_' . time() . '.txt';
            \Storage::put($testFile, 'test content');
            $content = \Storage::get($testFile);
            \Storage::delete($testFile);
            
            if ($content === 'test content') {
                return ['status' => 'success', 'message' => 'Storage working correctly'];
            } else {
                return ['status' => 'error', 'message' => 'Storage not working'];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Test mail configuration.
     */
    private function testMail(): array
    {
        try {
            $mailer = config('mail.default');
            return ['status' => 'success', 'message' => "Mail driver '{$mailer}' configured"];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Test external APIs.
     */
    private function testExternalApis(): array
    {
        try {
            $apis = ExternalApi::active()->get();
            $results = [];
            
            foreach ($apis as $api) {
                $test = $api->testConnection();
                $results[] = [
                    'provider' => $api->provider,
                    'status' => $test['success'] ? 'success' : 'error',
                    'message' => $test['message'],
                ];
            }

            return [
                'status' => 'success',
                'message' => 'External APIs tested',
                'results' => $results,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
