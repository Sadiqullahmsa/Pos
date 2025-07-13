<?php

namespace App\Events;

use App\Models\IoTDevice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IoTDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $device;
    public $sensorData;
    public $alertStatus;

    /**
     * Create a new event instance.
     */
    public function __construct(IoTDevice $device)
    {
        $this->device = $device;
        $this->sensorData = $device->sensor_data;
        $this->alertStatus = $this->checkAlertStatus($device);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('iot-monitoring'),
            new PrivateChannel('device.' . $this->device->id),
            new PrivateChannel('cylinder.' . $this->device->cylinder_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->device->id,
            'device_name' => $this->device->device_id,
            'sensor_data' => $this->sensorData,
            'battery_level' => $this->device->battery_level,
            'signal_strength' => $this->device->signal_strength,
            'location' => $this->device->location,
            'health_score' => $this->device->health_score,
            'alert_status' => $this->alertStatus,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'iot.data.updated';
    }

    private function checkAlertStatus(IoTDevice $device): array
    {
        $alerts = [];
        
        // Check battery level
        if ($device->battery_level < 20) {
            $alerts[] = [
                'type' => 'battery_low',
                'severity' => $device->battery_level < 10 ? 'critical' : 'warning',
                'message' => "Battery level is {$device->battery_level}%"
            ];
        }
        
        // Check sensor data for anomalies
        $sensorData = $device->sensor_data ?? [];
        
        if (isset($sensorData['temperature']) && $sensorData['temperature'] > 50) {
            $alerts[] = [
                'type' => 'temperature_high',
                'severity' => 'warning',
                'message' => "High temperature detected: {$sensorData['temperature']}°C"
            ];
        }
        
        if (isset($sensorData['pressure']) && $sensorData['pressure'] > 200) {
            $alerts[] = [
                'type' => 'pressure_high',
                'severity' => 'critical',
                'message' => "High pressure detected: {$sensorData['pressure']} PSI"
            ];
        }
        
        return $alerts;
    }
}