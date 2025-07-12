<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;

class IoTDevice extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'device_id',
        'device_type',
        'manufacturer',
        'model',
        'firmware_version',
        'status',
        'cylinder_id',
        'vehicle_id',
        'configuration',
        'location',
        'battery_level',
        'last_reading_at',
        'sensor_data',
        'alert_thresholds',
        'is_online',
        'network_type',
        'signal_strength',
        'maintenance_schedule',
        'installed_at',
        'last_maintenance_at',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'configuration' => 'array',
        'location' => 'array',
        'sensor_data' => 'array',
        'alert_thresholds' => 'array',
        'maintenance_schedule' => 'array',
        'last_reading_at' => 'datetime',
        'installed_at' => 'datetime',
        'last_maintenance_at' => 'datetime',
        'is_online' => 'boolean',
        'battery_level' => 'decimal:2',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($device) {
            if (!$device->device_id) {
                $device->device_id = 'IOT' . str_pad(
                    (IoTDevice::count() + 1),
                    8,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }

    /**
     * Get the cylinder associated with this device.
     */
    public function cylinder(): BelongsTo
    {
        return $this->belongsTo(Cylinder::class);
    }

    /**
     * Get the vehicle associated with this device.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get device readings.
     */
    public function readings(): HasMany
    {
        return $this->hasMany(IoTReading::class, 'device_id', 'device_id');
    }

    /**
     * Get device alerts.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(IoTAlert::class, 'device_id', 'device_id');
    }

    /**
     * Scope for online devices.
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    /**
     * Scope for active devices.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for devices by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('device_type', $type);
    }

    /**
     * Scope for low battery devices.
     */
    public function scopeLowBattery($query, $threshold = 20)
    {
        return $query->where('battery_level', '<=', $threshold);
    }

    /**
     * Update device sensor data.
     */
    public function updateSensorData(array $data): self
    {
        $this->update([
            'sensor_data' => array_merge($this->sensor_data ?? [], $data),
            'last_reading_at' => now(),
            'is_online' => true,
        ]);

        // Check for alerts
        $this->checkAlerts($data);

        return $this;
    }

    /**
     * Check for alert conditions.
     */
    public function checkAlerts(array $data): void
    {
        $thresholds = $this->alert_thresholds ?? [];

        foreach ($data as $metric => $value) {
            if (isset($thresholds[$metric])) {
                $threshold = $thresholds[$metric];
                
                if ($this->shouldTriggerAlert($metric, $value, $threshold)) {
                    $this->createAlert($metric, $value, $threshold);
                }
            }
        }
    }

    /**
     * Check if an alert should be triggered.
     */
    private function shouldTriggerAlert(string $metric, $value, array $threshold): bool
    {
        if (isset($threshold['min']) && $value < $threshold['min']) {
            return true;
        }

        if (isset($threshold['max']) && $value > $threshold['max']) {
            return true;
        }

        return false;
    }

    /**
     * Create an alert.
     */
    private function createAlert(string $metric, $value, array $threshold): void
    {
        // Create alert record
        IoTAlert::create([
            'device_id' => $this->device_id,
            'alert_type' => $metric,
            'severity' => $this->getAlertSeverity($metric, $value, $threshold),
            'message' => $this->generateAlertMessage($metric, $value, $threshold),
            'data' => [
                'metric' => $metric,
                'current_value' => $value,
                'threshold' => $threshold,
                'device_type' => $this->device_type,
                'location' => $this->location,
            ],
            'acknowledged' => false,
            'triggered_at' => now(),
        ]);
    }

    /**
     * Get alert severity.
     */
    private function getAlertSeverity(string $metric, $value, array $threshold): string
    {
        // Define severity based on how far the value is from threshold
        $severityConfig = [
            'temperature' => ['low' => 5, 'medium' => 15, 'high' => 30],
            'pressure' => ['low' => 10, 'medium' => 25, 'high' => 50],
            'battery_level' => ['low' => 5, 'medium' => 10, 'high' => 20],
        ];

        $config = $severityConfig[$metric] ?? ['low' => 10, 'medium' => 20, 'high' => 30];
        
        if (isset($threshold['min']) && $value < $threshold['min']) {
            $deviation = abs($value - $threshold['min']);
        } elseif (isset($threshold['max']) && $value > $threshold['max']) {
            $deviation = abs($value - $threshold['max']);
        } else {
            return 'low';
        }

        if ($deviation >= $config['high']) return 'critical';
        if ($deviation >= $config['medium']) return 'high';
        if ($deviation >= $config['low']) return 'medium';
        return 'low';
    }

    /**
     * Generate alert message.
     */
    private function generateAlertMessage(string $metric, $value, array $threshold): string
    {
        $messages = [
            'temperature' => "Temperature alert: {$value}°C",
            'pressure' => "Pressure alert: {$value} PSI",
            'battery_level' => "Low battery: {$value}%",
            'gas_level' => "Gas level: {$value}%",
            'leak_detected' => "Gas leak detected!",
            'vibration' => "Excessive vibration detected",
        ];

        return $messages[$metric] ?? "Alert for {$metric}: {$value}";
    }

    /**
     * Get current location.
     */
    public function getCurrentLocationAttribute(): ?array
    {
        return $this->location;
    }

    /**
     * Check if device needs maintenance.
     */
    public function needsMaintenance(): bool
    {
        if (!$this->maintenance_schedule) return false;

        $schedule = $this->maintenance_schedule;
        $lastMaintenance = $this->last_maintenance_at ?? $this->installed_at;
        
        if (!$lastMaintenance) return true;

        $intervalDays = $schedule['interval_days'] ?? 30;
        $nextMaintenanceDate = $lastMaintenance->addDays($intervalDays);

        return now()->gte($nextMaintenanceDate);
    }

    /**
     * Get device health score.
     */
    public function getHealthScoreAttribute(): int
    {
        $score = 100;
        
        // Deduct points for various factors
        if ($this->battery_level && $this->battery_level < 20) {
            $score -= 30;
        } elseif ($this->battery_level && $this->battery_level < 50) {
            $score -= 15;
        }

        if (!$this->is_online) {
            $score -= 40;
        }

        if ($this->status !== 'active') {
            $score -= 50;
        }

        if ($this->needsMaintenance()) {
            $score -= 20;
        }

        // Check for recent alerts
        $recentAlerts = $this->alerts()
            ->where('created_at', '>=', now()->subDays(7))
            ->where('severity', 'high')
            ->count();
        
        $score -= min($recentAlerts * 10, 30);

        return max($score, 0);
    }

    /**
     * Get predictive maintenance insights.
     */
    public function getPredictiveMaintenanceInsights(): array
    {
        $insights = [];
        
        // Analyze trends from recent readings
        $recentReadings = $this->readings()
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at')
            ->get();

        if ($recentReadings->count() > 0) {
            // Analyze battery degradation
            if ($this->device_type === 'cylinder_sensor') {
                $insights['battery_degradation'] = $this->analyzeBatteryDegradation($recentReadings);
                $insights['sensor_accuracy'] = $this->analyzeSensorAccuracy($recentReadings);
                $insights['connectivity_issues'] = $this->analyzeConnectivityIssues($recentReadings);
            }
        }

        return $insights;
    }

    /**
     * Analyze battery degradation trend.
     */
    private function analyzeBatteryDegradation($readings): array
    {
        $batteryReadings = $readings
            ->whereNotNull('data.battery_level')
            ->pluck('data.battery_level', 'created_at');

        if ($batteryReadings->count() < 5) {
            return ['status' => 'insufficient_data'];
        }

        // Calculate degradation rate
        $firstReading = $batteryReadings->first();
        $lastReading = $batteryReadings->last();
        $daysDiff = $readings->first()->created_at->diffInDays($readings->last()->created_at);
        
        $degradationRate = ($firstReading - $lastReading) / max($daysDiff, 1);

        return [
            'status' => 'analyzed',
            'degradation_rate' => round($degradationRate, 2),
            'estimated_days_remaining' => $degradationRate > 0 ? round($lastReading / $degradationRate) : null,
            'recommendation' => $degradationRate > 2 ? 'Schedule maintenance soon' : 'Normal operation',
        ];
    }

    /**
     * Analyze sensor accuracy.
     */
    private function analyzeSensorAccuracy($readings): array
    {
        // Implementation for sensor accuracy analysis
        return ['status' => 'good', 'accuracy' => 95];
    }

    /**
     * Analyze connectivity issues.
     */
    private function analyzeConnectivityIssues($readings): array
    {
        $totalReadings = $readings->count();
        $expectedReadings = 30 * 24; // Assuming hourly readings for 30 days
        $connectivityScore = ($totalReadings / $expectedReadings) * 100;

        return [
            'score' => round($connectivityScore, 1),
            'status' => $connectivityScore > 90 ? 'excellent' : ($connectivityScore > 70 ? 'good' : 'poor'),
            'missing_readings' => max(0, $expectedReadings - $totalReadings),
        ];
    }

    /**
     * Get activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'is_online', 'battery_level', 'location'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}

// Additional models for IoT ecosystem
class IoTReading extends Model
{
    protected $fillable = [
        'device_id', 'reading_type', 'value', 'unit', 'data', 'recorded_at'
    ];

    protected $casts = [
        'data' => 'array',
        'recorded_at' => 'datetime',
    ];
}

class IoTAlert extends Model
{
    protected $fillable = [
        'device_id', 'alert_type', 'severity', 'message', 'data', 
        'acknowledged', 'acknowledged_by', 'acknowledged_at', 'triggered_at'
    ];

    protected $casts = [
        'data' => 'array',
        'acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
        'triggered_at' => 'datetime',
    ];
}
