<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ProgressController extends Controller
{
    /**
     * Create a new progress tracker.
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $validator = validator($request->all(), [
                'operation' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'total_steps' => 'required|integer|min:1',
                'category' => 'nullable|string|max:50',
                'user_id' => 'nullable|integer',
                'metadata' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $trackerId = 'progress_' . Str::uuid();
            
            $progressData = [
                'id' => $trackerId,
                'operation' => $request->operation,
                'description' => $request->description ?? '',
                'total_steps' => $request->total_steps,
                'current_step' => 0,
                'percentage' => 0,
                'status' => 'started',
                'category' => $request->category ?? 'general',
                'user_id' => $request->user_id ?? auth()->id(),
                'metadata' => $request->metadata ?? [],
                'steps' => [],
                'logs' => [],
                'errors' => [],
                'warnings' => [],
                'started_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
                'estimated_completion' => null,
                'elapsed_time' => 0,
            ];

            // Store in cache for 1 hour
            Cache::put($trackerId, $progressData, 3600);

            // Broadcast to WebSocket if enabled
            $this->broadcastProgress($trackerId, $progressData);

            return response()->json([
                'success' => true,
                'data' => $progressData,
                'tracker_id' => $trackerId,
                'message' => 'Progress tracker created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create progress tracker: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create progress tracker',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update progress tracker.
     */
    public function update(Request $request, string $trackerId): JsonResponse
    {
        try {
            $validator = validator($request->all(), [
                'current_step' => 'sometimes|integer|min:0',
                'status' => 'sometimes|string|in:started,in_progress,completed,failed,paused,cancelled',
                'step_description' => 'sometimes|string|max:500',
                'log_message' => 'sometimes|string|max:1000',
                'error_message' => 'sometimes|string|max:1000',
                'warning_message' => 'sometimes|string|max:1000',
                'metadata' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $progressData = Cache::get($trackerId);

            if (!$progressData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Progress tracker not found'
                ], 404);
            }

            $startTime = strtotime($progressData['started_at']);
            $currentTime = time();
            $elapsedTime = $currentTime - $startTime;

            // Update current step
            if ($request->has('current_step')) {
                $progressData['current_step'] = min($request->current_step, $progressData['total_steps']);
                $progressData['percentage'] = round(($progressData['current_step'] / $progressData['total_steps']) * 100, 2);
            }

            // Update status
            if ($request->has('status')) {
                $progressData['status'] = $request->status;
                
                if ($request->status === 'completed') {
                    $progressData['current_step'] = $progressData['total_steps'];
                    $progressData['percentage'] = 100;
                    $progressData['completed_at'] = now()->toISOString();
                } elseif ($request->status === 'failed') {
                    $progressData['failed_at'] = now()->toISOString();
                } elseif ($request->status === 'cancelled') {
                    $progressData['cancelled_at'] = now()->toISOString();
                }
            }

            // Add step description
            if ($request->has('step_description')) {
                $progressData['steps'][] = [
                    'step' => $progressData['current_step'],
                    'description' => $request->step_description,
                    'timestamp' => now()->toISOString(),
                ];
            }

            // Add log message
            if ($request->has('log_message')) {
                $progressData['logs'][] = [
                    'message' => $request->log_message,
                    'timestamp' => now()->toISOString(),
                    'level' => 'info',
                ];
            }

            // Add error message
            if ($request->has('error_message')) {
                $progressData['errors'][] = [
                    'message' => $request->error_message,
                    'timestamp' => now()->toISOString(),
                    'step' => $progressData['current_step'],
                ];
            }

            // Add warning message
            if ($request->has('warning_message')) {
                $progressData['warnings'][] = [
                    'message' => $request->warning_message,
                    'timestamp' => now()->toISOString(),
                    'step' => $progressData['current_step'],
                ];
            }

            // Update metadata
            if ($request->has('metadata')) {
                $progressData['metadata'] = array_merge($progressData['metadata'], $request->metadata);
            }

            // Calculate estimated completion
            if ($progressData['current_step'] > 0 && $progressData['status'] === 'in_progress') {
                $avgTimePerStep = $elapsedTime / $progressData['current_step'];
                $remainingSteps = $progressData['total_steps'] - $progressData['current_step'];
                $estimatedRemainingTime = $remainingSteps * $avgTimePerStep;
                $progressData['estimated_completion'] = date('c', $currentTime + $estimatedRemainingTime);
            }

            $progressData['elapsed_time'] = $elapsedTime;
            $progressData['updated_at'] = now()->toISOString();

            // Update cache
            Cache::put($trackerId, $progressData, 3600);

            // Broadcast to WebSocket
            $this->broadcastProgress($trackerId, $progressData);

            return response()->json([
                'success' => true,
                'data' => $progressData,
                'message' => 'Progress updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update progress tracker {$trackerId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress tracker',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get progress tracker details.
     */
    public function show(string $trackerId): JsonResponse
    {
        try {
            $progressData = Cache::get($trackerId);

            if (!$progressData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Progress tracker not found'
                ], 404);
            }

            // Calculate current elapsed time
            $startTime = strtotime($progressData['started_at']);
            $currentTime = time();
            $progressData['elapsed_time'] = $currentTime - $startTime;

            return response()->json([
                'success' => true,
                'data' => $progressData,
                'message' => 'Progress retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve progress tracker {$trackerId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve progress tracker',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active progress trackers.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = validator($request->all(), [
                'category' => 'nullable|string',
                'user_id' => 'nullable|integer',
                'status' => 'nullable|string|in:started,in_progress,completed,failed,paused,cancelled',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $pattern = 'progress_*';
            $keys = Cache::getRedis()->keys($pattern);
            $trackers = [];

            foreach ($keys as $key) {
                $data = Cache::get($key);
                if ($data) {
                    // Apply filters
                    if ($request->category && $data['category'] !== $request->category) {
                        continue;
                    }
                    
                    if ($request->user_id && $data['user_id'] !== $request->user_id) {
                        continue;
                    }
                    
                    if ($request->status && $data['status'] !== $request->status) {
                        continue;
                    }

                    // Calculate current elapsed time
                    $startTime = strtotime($data['started_at']);
                    $currentTime = time();
                    $data['elapsed_time'] = $currentTime - $startTime;

                    $trackers[] = $data;
                }
            }

            // Sort by updated_at desc
            usort($trackers, function ($a, $b) {
                return strtotime($b['updated_at']) - strtotime($a['updated_at']);
            });

            // Apply limit
            $limit = $request->limit ?? 50;
            $trackers = array_slice($trackers, 0, $limit);

            return response()->json([
                'success' => true,
                'data' => $trackers,
                'count' => count($trackers),
                'message' => 'Progress trackers retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve progress trackers: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve progress trackers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete progress tracker.
     */
    public function delete(string $trackerId): JsonResponse
    {
        try {
            $progressData = Cache::get($trackerId);

            if (!$progressData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Progress tracker not found'
                ], 404);
            }

            Cache::forget($trackerId);

            // Broadcast deletion
            $this->broadcastProgress($trackerId, ['status' => 'deleted']);

            return response()->json([
                'success' => true,
                'message' => 'Progress tracker deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete progress tracker {$trackerId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete progress tracker',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cleanup expired progress trackers.
     */
    public function cleanup(): JsonResponse
    {
        try {
            $pattern = 'progress_*';
            $keys = Cache::getRedis()->keys($pattern);
            $deleted = 0;
            $cutoffTime = now()->subHours(24);

            foreach ($keys as $key) {
                $data = Cache::get($key);
                if ($data && isset($data['updated_at'])) {
                    $updatedAt = \Carbon\Carbon::parse($data['updated_at']);
                    
                    // Delete if older than 24 hours and completed/failed/cancelled
                    if ($updatedAt->lt($cutoffTime) && in_array($data['status'], ['completed', 'failed', 'cancelled'])) {
                        Cache::forget($key);
                        $deleted++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'deleted_count' => $deleted,
                'message' => "Cleaned up {$deleted} expired progress trackers"
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup progress trackers: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup progress trackers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get progress statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $pattern = 'progress_*';
            $keys = Cache::getRedis()->keys($pattern);
            $stats = [
                'total' => 0,
                'by_status' => [],
                'by_category' => [],
                'by_user' => [],
                'average_completion_time' => 0,
                'success_rate' => 0,
            ];

            $completionTimes = [];
            $totalCompleted = 0;
            $totalSuccess = 0;

            foreach ($keys as $key) {
                $data = Cache::get($key);
                if ($data) {
                    $stats['total']++;
                    
                    // Count by status
                    $status = $data['status'];
                    $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;
                    
                    // Count by category
                    $category = $data['category'];
                    $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;
                    
                    // Count by user
                    $userId = $data['user_id'] ?? 'unknown';
                    $stats['by_user'][$userId] = ($stats['by_user'][$userId] ?? 0) + 1;
                    
                    // Calculate completion times
                    if (isset($data['completed_at'])) {
                        $startTime = strtotime($data['started_at']);
                        $endTime = strtotime($data['completed_at']);
                        $completionTimes[] = $endTime - $startTime;
                        $totalCompleted++;
                        
                        if ($status === 'completed') {
                            $totalSuccess++;
                        }
                    }
                }
            }

            // Calculate average completion time
            if (!empty($completionTimes)) {
                $stats['average_completion_time'] = round(array_sum($completionTimes) / count($completionTimes), 2);
            }

            // Calculate success rate
            if ($totalCompleted > 0) {
                $stats['success_rate'] = round(($totalSuccess / $totalCompleted) * 100, 2);
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Progress statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get progress statistics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get progress statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a batch progress tracker.
     */
    public function createBatch(Request $request): JsonResponse
    {
        try {
            $validator = validator($request->all(), [
                'operation' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'batch_items' => 'required|array|min:1',
                'category' => 'nullable|string|max:50',
                'user_id' => 'nullable|integer',
                'metadata' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $batchId = 'batch_' . Str::uuid();
            $batchItems = $request->batch_items;
            $itemTrackers = [];

            // Create individual trackers for each batch item
            foreach ($batchItems as $index => $item) {
                $itemTrackerId = "batch_item_{$batchId}_{$index}";
                
                $itemProgressData = [
                    'id' => $itemTrackerId,
                    'batch_id' => $batchId,
                    'batch_index' => $index,
                    'operation' => $item['operation'] ?? $request->operation,
                    'description' => $item['description'] ?? '',
                    'total_steps' => $item['total_steps'] ?? 1,
                    'current_step' => 0,
                    'percentage' => 0,
                    'status' => 'pending',
                    'category' => $request->category ?? 'batch',
                    'user_id' => $request->user_id ?? auth()->id(),
                    'metadata' => array_merge($request->metadata ?? [], $item['metadata'] ?? []),
                    'steps' => [],
                    'logs' => [],
                    'errors' => [],
                    'warnings' => [],
                    'started_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ];

                Cache::put($itemTrackerId, $itemProgressData, 3600);
                $itemTrackers[] = $itemTrackerId;
            }

            // Create batch tracker
            $batchProgressData = [
                'id' => $batchId,
                'operation' => $request->operation,
                'description' => $request->description ?? '',
                'total_items' => count($batchItems),
                'completed_items' => 0,
                'failed_items' => 0,
                'pending_items' => count($batchItems),
                'percentage' => 0,
                'status' => 'started',
                'category' => $request->category ?? 'batch',
                'user_id' => $request->user_id ?? auth()->id(),
                'metadata' => $request->metadata ?? [],
                'item_trackers' => $itemTrackers,
                'batch_logs' => [],
                'started_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            Cache::put($batchId, $batchProgressData, 3600);

            return response()->json([
                'success' => true,
                'data' => $batchProgressData,
                'batch_id' => $batchId,
                'item_trackers' => $itemTrackers,
                'message' => 'Batch progress tracker created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create batch progress tracker: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create batch progress tracker',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update batch progress.
     */
    public function updateBatch(string $batchId): JsonResponse
    {
        try {
            $batchData = Cache::get($batchId);

            if (!$batchData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch progress tracker not found'
                ], 404);
            }

            $completedItems = 0;
            $failedItems = 0;
            $pendingItems = 0;

            // Check status of all item trackers
            foreach ($batchData['item_trackers'] as $itemTrackerId) {
                $itemData = Cache::get($itemTrackerId);
                if ($itemData) {
                    switch ($itemData['status']) {
                        case 'completed':
                            $completedItems++;
                            break;
                        case 'failed':
                            $failedItems++;
                            break;
                        default:
                            $pendingItems++;
                            break;
                    }
                }
            }

            // Update batch data
            $batchData['completed_items'] = $completedItems;
            $batchData['failed_items'] = $failedItems;
            $batchData['pending_items'] = $pendingItems;
            $batchData['percentage'] = round(($completedItems / $batchData['total_items']) * 100, 2);

            // Update batch status
            if ($completedItems + $failedItems === $batchData['total_items']) {
                $batchData['status'] = $failedItems === 0 ? 'completed' : 'completed_with_errors';
                $batchData['completed_at'] = now()->toISOString();
            } elseif ($completedItems > 0 || $failedItems > 0) {
                $batchData['status'] = 'in_progress';
            }

            $batchData['updated_at'] = now()->toISOString();

            // Update cache
            Cache::put($batchId, $batchData, 3600);

            // Broadcast update
            $this->broadcastProgress($batchId, $batchData);

            return response()->json([
                'success' => true,
                'data' => $batchData,
                'message' => 'Batch progress updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update batch progress {$batchId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update batch progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Broadcast progress update via WebSocket.
     */
    private function broadcastProgress(string $trackerId, array $data): void
    {
        try {
            // If using Laravel Echo/Pusher
            if (class_exists('\Pusher\Pusher')) {
                broadcast(new \App\Events\ProgressUpdated($trackerId, $data));
            }

            // If using Redis for WebSocket
            if (class_exists('\Predis\Client')) {
                Redis::publish('progress_updates', json_encode([
                    'tracker_id' => $trackerId,
                    'data' => $data,
                    'timestamp' => now()->toISOString(),
                ]));
            }
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast progress update: " . $e->getMessage());
        }
    }

    /**
     * Get real-time progress updates (SSE endpoint).
     */
    public function stream(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $trackerId = $request->query('tracker_id');
        
        return response()->stream(function () use ($trackerId) {
            $lastUpdate = 0;
            
            while (true) {
                $data = Cache::get($trackerId);
                
                if ($data && strtotime($data['updated_at']) > $lastUpdate) {
                    echo "data: " . json_encode($data) . "\n\n";
                    ob_flush();
                    flush();
                    
                    $lastUpdate = strtotime($data['updated_at']);
                    
                    // Stop streaming if completed, failed, or cancelled
                    if (in_array($data['status'], ['completed', 'failed', 'cancelled'])) {
                        break;
                    }
                }
                
                sleep(1); // Poll every second
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
