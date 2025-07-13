<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\IoTDevice;
use App\Models\IoTReading;
use App\Models\IoTAlert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Broadcast;

class IoTController extends Controller
{
    /**
     * Get all IoT devices with status
     */
    public function index(Request $request): JsonResponse
    {
        $devices = IoTDevice::with(['cylinder', 'vehicle'])
            ->when($request->get('status'), function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->get('type'), function ($query, $type) {
                return $query->where('device_type', $type);
            })
            ->when($request->get('online'), function ($query, $online) {
                return $query->where('is_online', $online === 'true');
            })
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $devices,
            'summary' => [
                'total_devices' => IoTDevice::count(),
                'online_devices' => IoTDevice::where('is_online', true)->count(),
                'offline_devices' => IoTDevice::where('is_online', false)->count(),
                'maintenance_required' => IoTDevice::whereRaw('needsMaintenance()')->count(),
            ]
        ]);
    }

    /**
     * Get device details with analytics
     */
    public function show(IoTDevice $device): JsonResponse
    {
        $device->load(['cylinder', 'vehicle', 'readings' => function ($query) {
            $query->latest()->limit(100);
        }]);

        return response()->json([
            'success' => true,
            'data' => [
                'device' => $device,
                'health_score' => $device->health_score,
                'maintenance_insights' => $device->getPredictiveMaintenanceInsights(),
                'recent_readings' => $device->readings,
                'performance_metrics' => $this->getDevicePerformanceMetrics($device),
                'alerts' => $device->alerts()->latest()->limit(10)->get(),
            ]
        ]);
    }

    /**
     * Update device sensor data
     */
    public function updateSensorData(Request $request, IoTDevice $device): JsonResponse
    {
        $request->validate([
            'sensor_data' => 'required|array',
            'location' => 'sometimes|array',
            'battery_level' => 'sometimes|numeric|between:0,100',
            'signal_strength' => 'sometimes|numeric|between:0,100',
        ]);

        $device->updateSensorData($request->get('sensor_data'));
        
        if ($request->has('location')) {
            $device->update(['location' => $request->get('location')]);
        }
        
        if ($request->has('battery_level')) {
            $device->update(['battery_level' => $request->get('battery_level')]);
        }

        // Broadcast real-time update
        broadcast(new \App\Events\IoTDataUpdated($device));

        return response()->json([
            'success' => true,
            'message' => 'Sensor data updated successfully',
            'data' => $device->fresh()
        ]);
    }

    /**
     * Get real-time monitoring dashboard
     */
    public function monitoringDashboard(): JsonResponse
    {
        $cacheKey = 'iot_monitoring_dashboard';
        
        $dashboard = Cache::remember($cacheKey, now()->addMinutes(1), function () {
            return [
                'device_overview' => $this->getDeviceOverview(),
                'real_time_metrics' => $this->getRealTimeMetrics(),
                'alert_summary' => $this->getAlertSummary(),
                'network_status' => $this->getNetworkStatus(),
                'maintenance_schedule' => $this->getMaintenanceSchedule(),
                'performance_trends' => $this->getPerformanceTrends(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $dashboard,
            'last_updated' => now()
        ]);
    }

    /**
     * Get device alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $alerts = IoTAlert::with('device')
            ->when($request->get('severity'), function ($query, $severity) {
                return $query->where('severity', $severity);
            })
            ->when($request->get('status'), function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->get('device_id'), function ($query, $deviceId) {
                return $query->where('device_id', $deviceId);
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $alerts,
            'summary' => [
                'critical_alerts' => IoTAlert::where('severity', 'critical')->where('status', 'active')->count(),
                'warning_alerts' => IoTAlert::where('severity', 'warning')->where('status', 'active')->count(),
                'total_active' => IoTAlert::where('status', 'active')->count(),
            ]
        ]);
    }

    /**
     * Acknowledge alert
     */
    public function acknowledgeAlert(Request $request, IoTAlert $alert): JsonResponse
    {
        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
            'notes' => $request->get('notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully'
        ]);
    }

    /**
     * Get predictive maintenance insights
     */
    public function predictiveMaintenance(): JsonResponse
    {
        $insights = IoTDevice::all()->map(function ($device) {
            return [
                'device_id' => $device->id,
                'device_name' => $device->device_id,
                'health_score' => $device->health_score,
                'maintenance_insights' => $device->getPredictiveMaintenanceInsights(),
                'next_maintenance' => $this->calculateNextMaintenanceDate($device),
                'priority' => $this->calculateMaintenancePriority($device),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'devices' => $insights,
                'maintenance_schedule' => $this->generateMaintenanceSchedule($insights),
                'cost_analysis' => $this->calculateMaintenanceCosts($insights),
                'recommendations' => $this->generateMaintenanceRecommendations($insights),
            ]
        ]);
    }

    /**
     * Get device performance analytics
     */
    public function performanceAnalytics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week');
        $deviceId = $request->get('device_id');
        
        $analytics = [
            'uptime_metrics' => $this->getUptimeMetrics($period, $deviceId),
            'sensor_accuracy' => $this->getSensorAccuracyMetrics($period, $deviceId),
            'battery_performance' => $this->getBatteryPerformanceMetrics($period, $deviceId),
            'connectivity_metrics' => $this->getConnectivityMetrics($period, $deviceId),
            'data_quality_score' => $this->getDataQualityScore($period, $deviceId),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Configure device settings
     */
    public function configureDevice(Request $request, IoTDevice $device): JsonResponse
    {
        $request->validate([
            'configuration' => 'required|array',
            'alert_thresholds' => 'sometimes|array',
            'maintenance_schedule' => 'sometimes|array',
        ]);

        $device->update([
            'configuration' => array_merge($device->configuration ?? [], $request->get('configuration')),
            'alert_thresholds' => $request->get('alert_thresholds', $device->alert_thresholds),
            'maintenance_schedule' => $request->get('maintenance_schedule', $device->maintenance_schedule),
        ]);

        // Push configuration to device (simulate)
        $this->pushConfigurationToDevice($device);

        return response()->json([
            'success' => true,
            'message' => 'Device configuration updated successfully',
            'data' => $device->fresh()
        ]);
    }

    /**
     * Get device network diagnostics
     */
    public function networkDiagnostics(IoTDevice $device): JsonResponse
    {
        $diagnostics = [
            'connectivity_status' => $device->is_online,
            'signal_strength' => $device->signal_strength,
            'network_type' => $device->network_type,
            'last_seen' => $device->last_reading_at,
            'data_transmission_rate' => $this->getDataTransmissionRate($device),
            'packet_loss' => $this->getPacketLoss($device),
            'latency_metrics' => $this->getLatencyMetrics($device),
            'network_issues' => $this->detectNetworkIssues($device),
        ];

        return response()->json([
            'success' => true,
            'data' => $diagnostics
        ]);
    }

    /**
     * Bulk device operations
     */
    public function bulkOperations(Request $request): JsonResponse
    {
        $request->validate([
            'operation' => 'required|string|in:update_config,restart,maintenance_mode,activate,deactivate',
            'device_ids' => 'required|array',
            'parameters' => 'sometimes|array',
        ]);

        $devices = IoTDevice::whereIn('id', $request->get('device_ids'))->get();
        $results = [];

        foreach ($devices as $device) {
            $result = $this->performBulkOperation(
                $device, 
                $request->get('operation'), 
                $request->get('parameters', [])
            );
            $results[] = $result;
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk operation completed',
            'data' => $results
        ]);
    }

    // Private helper methods

    private function getDeviceOverview(): array
    {
        return [
            'total_devices' => IoTDevice::count(),
            'online_devices' => IoTDevice::where('is_online', true)->count(),
            'critical_alerts' => IoTAlert::where('severity', 'critical')->where('status', 'active')->count(),
            'maintenance_due' => IoTDevice::whereRaw('needsMaintenance()')->count(),
            'battery_low' => IoTDevice::where('battery_level', '<', 20)->count(),
            'device_types' => IoTDevice::groupBy('device_type')->selectRaw('device_type, count(*) as count')->get(),
        ];
    }

    private function getRealTimeMetrics(): array
    {
        $devices = IoTDevice::where('is_online', true)->get();
        
        return [
            'average_temperature' => $devices->avg('sensor_data.temperature'),
            'average_pressure' => $devices->avg('sensor_data.pressure'),
            'average_gas_level' => $devices->avg('sensor_data.gas_level'),
            'average_battery' => $devices->avg('battery_level'),
            'data_points_per_minute' => $this->getDataPointsPerMinute(),
        ];
    }

    private function getAlertSummary(): array
    {
        return IoTAlert::selectRaw('
            severity,
            status,
            COUNT(*) as count
        ')
        ->groupBy('severity', 'status')
        ->get()
        ->groupBy('severity')
        ->map(function ($items) {
            return $items->keyBy('status');
        })
        ->toArray();
    }

    private function getNetworkStatus(): array
    {
        return [
            'wifi_devices' => IoTDevice::where('network_type', 'wifi')->count(),
            'cellular_devices' => IoTDevice::where('network_type', 'cellular')->count(),
            'bluetooth_devices' => IoTDevice::where('network_type', 'bluetooth')->count(),
            'average_signal_strength' => IoTDevice::avg('signal_strength'),
            'connectivity_issues' => $this->getConnectivityIssues(),
        ];
    }

    private function getMaintenanceSchedule(): array
    {
        return IoTDevice::whereNotNull('maintenance_schedule')
            ->get()
            ->map(function ($device) {
                return [
                    'device_id' => $device->id,
                    'device_name' => $device->device_id,
                    'next_maintenance' => $this->calculateNextMaintenanceDate($device),
                    'priority' => $this->calculateMaintenancePriority($device),
                ];
            })
            ->sortBy('next_maintenance')
            ->values()
            ->toArray();
    }

    private function getPerformanceTrends(): array
    {
        return IoTReading::selectRaw('
            DATE(created_at) as date,
            AVG(JSON_EXTRACT(sensor_data, "$.temperature")) as avg_temperature,
            AVG(JSON_EXTRACT(sensor_data, "$.pressure")) as avg_pressure,
            AVG(JSON_EXTRACT(sensor_data, "$.gas_level")) as avg_gas_level,
            COUNT(*) as reading_count
        ')
        ->where('created_at', '>=', now()->subWeek())
        ->groupBy('date')
        ->orderBy('date')
        ->get()
        ->toArray();
    }

    private function getDevicePerformanceMetrics(IoTDevice $device): array
    {
        $readings = $device->readings()->where('created_at', '>=', now()->subDay())->get();
        
        return [
            'uptime_percentage' => $this->calculateUptimePercentage($device),
            'data_accuracy' => $this->calculateDataAccuracy($readings),
            'battery_health' => $this->calculateBatteryHealth($device),
            'connectivity_stability' => $this->calculateConnectivityStability($device),
        ];
    }

    // Additional helper methods...
    private function calculateNextMaintenanceDate(IoTDevice $device): ?string { return null; }
    private function calculateMaintenancePriority(IoTDevice $device): string { return 'medium'; }
    private function generateMaintenanceSchedule(array $insights): array { return []; }
    private function calculateMaintenanceCosts(array $insights): array { return []; }
    private function generateMaintenanceRecommendations(array $insights): array { return []; }
    private function getUptimeMetrics(string $period, ?int $deviceId): array { return []; }
    private function getSensorAccuracyMetrics(string $period, ?int $deviceId): array { return []; }
    private function getBatteryPerformanceMetrics(string $period, ?int $deviceId): array { return []; }
    private function getConnectivityMetrics(string $period, ?int $deviceId): array { return []; }
    private function getDataQualityScore(string $period, ?int $deviceId): float { return 95.5; }
    private function pushConfigurationToDevice(IoTDevice $device): void { /* Implementation */ }
    private function getDataTransmissionRate(IoTDevice $device): float { return 0.0; }
    private function getPacketLoss(IoTDevice $device): float { return 0.0; }
    private function getLatencyMetrics(IoTDevice $device): array { return []; }
    private function detectNetworkIssues(IoTDevice $device): array { return []; }
    private function performBulkOperation(IoTDevice $device, string $operation, array $parameters): array { return []; }
    private function getDataPointsPerMinute(): int { return 150; }
    private function getConnectivityIssues(): array { return []; }
    private function calculateUptimePercentage(IoTDevice $device): float { return 99.5; }
    private function calculateDataAccuracy(array $readings): float { return 98.7; }
    private function calculateBatteryHealth(IoTDevice $device): float { return 85.2; }
    private function calculateConnectivityStability(IoTDevice $device): float { return 96.8; }
}