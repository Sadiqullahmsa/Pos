import React, { useState, useEffect } from 'react';
import { useRealTimeIoTData, useRealTimeAlerts } from '../lib/websocket';

interface IoTDevice {
  id: number;
  device_id: string;
  device_type: string;
  status: string;
  battery_level: number;
  signal_strength: number;
  is_online: boolean;
  sensor_data: {
    temperature?: number;
    pressure?: number;
    gas_level?: number;
    vibration?: number;
  };
  health_score: number;
  location?: {
    latitude: number;
    longitude: number;
  };
}

interface Alert {
  id: number;
  device_id: number;
  type: string;
  severity: 'info' | 'warning' | 'critical';
  message: string;
  timestamp: string;
  status: string;
}

const IoTMonitoring: React.FC = () => {
  const [devices, setDevices] = useState<IoTDevice[]>([]);
  const [selectedDevice, setSelectedDevice] = useState<IoTDevice | null>(null);
  const [filter, setFilter] = useState<string>('all');
  const [alerts, setAlerts] = useState<Alert[]>([]);

  const { data: realTimeData, isConnected } = useRealTimeIoTData();
  const realTimeAlerts = useRealTimeAlerts();

  // Mock data for demonstration
  useEffect(() => {
    const mockDevices: IoTDevice[] = [
      {
        id: 1,
        device_id: 'IOT-001',
        device_type: 'cylinder_sensor',
        status: 'active',
        battery_level: 85,
        signal_strength: 92,
        is_online: true,
        sensor_data: {
          temperature: 25.5,
          pressure: 145.2,
          gas_level: 75.3,
          vibration: 0.1
        },
        health_score: 95,
        location: { latitude: 19.0760, longitude: 72.8777 }
      },
      {
        id: 2,
        device_id: 'IOT-002',
        device_type: 'vehicle_tracker',
        status: 'active',
        battery_level: 45,
        signal_strength: 78,
        is_online: true,
        sensor_data: {
          temperature: 32.1,
          vibration: 0.3
        },
        health_score: 88,
        location: { latitude: 19.0760, longitude: 72.8777 }
      },
      {
        id: 3,
        device_id: 'IOT-003',
        device_type: 'pressure_monitor',
        status: 'maintenance',
        battery_level: 12,
        signal_strength: 45,
        is_online: false,
        sensor_data: {
          pressure: 198.7,
          temperature: 28.9
        },
        health_score: 65
      }
    ];
    setDevices(mockDevices);
  }, []);

  // Update devices with real-time data
  useEffect(() => {
    if (realTimeData) {
      setDevices(prev => prev.map(device => 
        device.id === realTimeData.device_id 
          ? { ...device, sensor_data: realTimeData.sensor_data, battery_level: realTimeData.battery_level }
          : device
      ));
    }
  }, [realTimeData]);

  // Handle real-time alerts
  useEffect(() => {
    if (realTimeAlerts.length > 0) {
      setAlerts(realTimeAlerts.slice(0, 10)); // Show last 10 alerts
    }
  }, [realTimeAlerts]);

  const filteredDevices = devices.filter(device => {
    if (filter === 'online') return device.is_online;
    if (filter === 'offline') return !device.is_online;
    if (filter === 'low_battery') return device.battery_level < 20;
    return true;
  });

  const getStatusColor = (status: string, isOnline: boolean) => {
    if (!isOnline) return 'text-red-500 bg-red-100';
    switch (status) {
      case 'active': return 'text-green-500 bg-green-100';
      case 'maintenance': return 'text-yellow-500 bg-yellow-100';
      case 'error': return 'text-red-500 bg-red-100';
      default: return 'text-gray-500 bg-gray-100';
    }
  };

  const getBatteryColor = (level: number) => {
    if (level > 50) return 'text-green-500';
    if (level > 20) return 'text-yellow-500';
    return 'text-red-500';
  };

  const getSeverityColor = (severity: string) => {
    switch (severity) {
      case 'critical': return 'text-red-500 bg-red-100 border-red-300';
      case 'warning': return 'text-yellow-500 bg-yellow-100 border-yellow-300';
      case 'info': return 'text-blue-500 bg-blue-100 border-blue-300';
      default: return 'text-gray-500 bg-gray-100 border-gray-300';
    }
  };

  return (
    <div className="p-6 bg-gray-50 min-h-screen">
      {/* Header */}
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">IoT Device Monitoring</h1>
          <p className="text-gray-600 mt-1">Real-time monitoring and management of IoT devices</p>
        </div>
        <div className="flex items-center gap-4">
          <div className={`flex items-center gap-2 px-3 py-1 rounded-full text-sm ${
            isConnected ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
          }`}>
            <div className={`w-2 h-2 rounded-full ${
              isConnected ? 'bg-green-500 animate-pulse' : 'bg-red-500'
            }`}></div>
            {isConnected ? 'Connected' : 'Disconnected'}
          </div>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Total Devices</p>
              <p className="text-2xl font-bold text-gray-900">{devices.length}</p>
            </div>
            <div className="h-8 w-8 bg-blue-100 rounded-lg flex items-center justify-center">
              <span className="text-blue-600">📱</span>
            </div>
          </div>
        </div>
        
        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Online Devices</p>
              <p className="text-2xl font-bold text-green-600">
                {devices.filter(d => d.is_online).length}
              </p>
            </div>
            <div className="h-8 w-8 bg-green-100 rounded-lg flex items-center justify-center">
              <span className="text-green-600">✅</span>
            </div>
          </div>
        </div>
        
        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Low Battery</p>
              <p className="text-2xl font-bold text-red-600">
                {devices.filter(d => d.battery_level < 20).length}
              </p>
            </div>
            <div className="h-8 w-8 bg-red-100 rounded-lg flex items-center justify-center">
              <span className="text-red-600">🔋</span>
            </div>
          </div>
        </div>
        
        <div className="bg-white p-6 rounded-lg shadow-sm border">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Avg Health Score</p>
              <p className="text-2xl font-bold text-blue-600">
                {devices.length > 0 ? Math.round(devices.reduce((acc, d) => acc + d.health_score, 0) / devices.length) : 0}%
              </p>
            </div>
            <div className="h-8 w-8 bg-blue-100 rounded-lg flex items-center justify-center">
              <span className="text-blue-600">💚</span>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Device List */}
        <div className="lg:col-span-2">
          <div className="bg-white rounded-lg shadow-sm border">
            <div className="p-6 border-b">
              <div className="flex justify-between items-center">
                <h2 className="text-xl font-semibold text-gray-900">Device Status</h2>
                <select 
                  value={filter} 
                  onChange={(e) => setFilter(e.target.value)}
                  className="px-3 py-1 border border-gray-300 rounded-md text-sm"
                >
                  <option value="all">All Devices</option>
                  <option value="online">Online Only</option>
                  <option value="offline">Offline Only</option>
                  <option value="low_battery">Low Battery</option>
                </select>
              </div>
            </div>
            <div className="p-6">
              <div className="space-y-4">
                {filteredDevices.map(device => (
                  <div 
                    key={device.id}
                    className={`p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors ${
                      selectedDevice?.id === device.id ? 'ring-2 ring-blue-500 bg-blue-50' : ''
                    }`}
                    onClick={() => setSelectedDevice(device)}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-4">
                        <div className={`w-3 h-3 rounded-full ${
                          device.is_online ? 'bg-green-500 animate-pulse' : 'bg-red-500'
                        }`}></div>
                        <div>
                          <h3 className="font-medium text-gray-900">{device.device_id}</h3>
                          <p className="text-sm text-gray-500 capitalize">
                            {device.device_type.replace('_', ' ')}
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center gap-4 text-sm">
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(device.status, device.is_online)}`}>
                          {device.status}
                        </span>
                        <span className={`font-medium ${getBatteryColor(device.battery_level)}`}>
                          🔋 {device.battery_level}%
                        </span>
                        <span className="text-gray-600">
                          📶 {device.signal_strength}%
                        </span>
                      </div>
                    </div>
                    
                    {device.sensor_data && (
                      <div className="mt-3 grid grid-cols-4 gap-4 text-sm">
                        {device.sensor_data.temperature && (
                          <div>
                            <span className="text-gray-500">Temp:</span>
                            <span className="ml-1 font-medium">{device.sensor_data.temperature}°C</span>
                          </div>
                        )}
                        {device.sensor_data.pressure && (
                          <div>
                            <span className="text-gray-500">Pressure:</span>
                            <span className="ml-1 font-medium">{device.sensor_data.pressure} PSI</span>
                          </div>
                        )}
                        {device.sensor_data.gas_level && (
                          <div>
                            <span className="text-gray-500">Gas:</span>
                            <span className="ml-1 font-medium">{device.sensor_data.gas_level}%</span>
                          </div>
                        )}
                        <div>
                          <span className="text-gray-500">Health:</span>
                          <span className="ml-1 font-medium">{device.health_score}%</span>
                        </div>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Device Details & Alerts */}
        <div className="space-y-6">
          {/* Selected Device Details */}
          {selectedDevice && (
            <div className="bg-white rounded-lg shadow-sm border">
              <div className="p-6 border-b">
                <h2 className="text-xl font-semibold text-gray-900">Device Details</h2>
              </div>
              <div className="p-6">
                <div className="space-y-4">
                  <div>
                    <h3 className="font-medium text-gray-900">{selectedDevice.device_id}</h3>
                    <p className="text-sm text-gray-500 capitalize">
                      {selectedDevice.device_type.replace('_', ' ')}
                    </p>
                  </div>
                  
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span className="text-gray-500">Status:</span>
                      <span className={`ml-2 px-2 py-1 rounded text-xs ${getStatusColor(selectedDevice.status, selectedDevice.is_online)}`}>
                        {selectedDevice.status}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-500">Health Score:</span>
                      <span className="ml-2 font-medium">{selectedDevice.health_score}%</span>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-500">Battery Level</span>
                      <span className={getBatteryColor(selectedDevice.battery_level)}>
                        {selectedDevice.battery_level}%
                      </span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        className={`h-2 rounded-full ${selectedDevice.battery_level > 50 ? 'bg-green-500' : selectedDevice.battery_level > 20 ? 'bg-yellow-500' : 'bg-red-500'}`}
                        style={{ width: `${selectedDevice.battery_level}%` }}
                      ></div>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-500">Signal Strength</span>
                      <span>{selectedDevice.signal_strength}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        className="bg-blue-500 h-2 rounded-full"
                        style={{ width: `${selectedDevice.signal_strength}%` }}
                      ></div>
                    </div>
                  </div>

                  {selectedDevice.location && (
                    <div>
                      <span className="text-gray-500 text-sm">Location:</span>
                      <p className="text-sm font-medium">
                        {selectedDevice.location.latitude.toFixed(4)}, {selectedDevice.location.longitude.toFixed(4)}
                      </p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Recent Alerts */}
          <div className="bg-white rounded-lg shadow-sm border">
            <div className="p-6 border-b">
              <h2 className="text-xl font-semibold text-gray-900">Recent Alerts</h2>
            </div>
            <div className="p-6">
              {alerts.length > 0 ? (
                <div className="space-y-3">
                  {alerts.map((alert, index) => (
                    <div key={index} className={`p-3 border rounded-lg ${getSeverityColor(alert.severity)}`}>
                      <div className="flex justify-between items-start">
                        <div>
                          <p className="font-medium text-sm">{alert.type.replace('_', ' ').toUpperCase()}</p>
                          <p className="text-sm mt-1">{alert.message}</p>
                        </div>
                        <span className="text-xs text-gray-500">
                          {new Date(alert.timestamp).toLocaleTimeString()}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8">
                  <div className="text-gray-400 text-4xl mb-2">🔔</div>
                  <p className="text-gray-500">No recent alerts</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default IoTMonitoring;