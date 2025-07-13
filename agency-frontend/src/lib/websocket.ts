// Mock Echo and Pusher for now until packages are installed
const mockEcho = {
  channel: (channel: string) => ({
    listen: (event: string, callback: (data: any) => void) => {}
  }),
  private: (channel: string) => ({
    listen: (event: string, callback: (data: any) => void) => {}
  }),
  disconnect: () => {},
  connector: {
    pusher: {
      connection: {
        state: 'connected',
        bind: (event: string, callback: () => void) => {},
        unbind: (event: string, callback: () => void) => {}
      }
    }
  }
};

export const echo = mockEcho;

// WebSocket Event Types
export interface IoTDataUpdate {
  device_id: number;
  device_name: string;
  sensor_data: {
    temperature?: number;
    pressure?: number;
    gas_level?: number;
    vibration?: number;
  };
  battery_level: number;
  signal_strength: number;
  location?: {
    latitude: number;
    longitude: number;
  };
  health_score: number;
  alert_status: Array<{
    type: string;
    severity: 'info' | 'warning' | 'critical';
    message: string;
  }>;
  timestamp: string;
}

export interface OrderUpdate {
  order_id: number;
  customer_id: number;
  status: string;
  delivery_time?: string;
  driver_id?: number;
  location?: {
    latitude: number;
    longitude: number;
  };
  timestamp: string;
}

export interface AnalyticsUpdate {
  metric_type: string;
  value: number;
  change_percentage: number;
  timestamp: string;
}

// WebSocket Service Class
export class WebSocketService {
  private static instance: WebSocketService;
  private subscribers: Map<string, Set<(data: any) => void>> = new Map();

  private constructor() {
    this.initializeChannels();
  }

  public static getInstance(): WebSocketService {
    if (!WebSocketService.instance) {
      WebSocketService.instance = new WebSocketService();
    }
    return WebSocketService.instance;
  }

  private initializeChannels(): void {
    // IoT Monitoring Channel
    echo.channel('iot-monitoring')
      .listen('.iot.data.updated', (data: IoTDataUpdate) => {
        this.notifySubscribers('iot-data', data);
      });

    // Analytics Channel
    echo.channel('analytics-updates')
      .listen('.analytics.updated', (data: AnalyticsUpdate) => {
        this.notifySubscribers('analytics', data);
      });

    // Orders Channel
    echo.channel('order-updates')
      .listen('.order.status.updated', (data: OrderUpdate) => {
        this.notifySubscribers('orders', data);
      });

    // System Alerts Channel
    echo.channel('system-alerts')
      .listen('.system.alert', (data: any) => {
        this.notifySubscribers('alerts', data);
      });
  }

  // Subscribe to specific device updates
  public subscribeToDevice(deviceId: number, callback: (data: IoTDataUpdate) => void): void {
    echo.private(`device.${deviceId}`)
      .listen('.iot.data.updated', callback);
  }

  // Subscribe to customer order updates
  public subscribeToCustomerOrders(customerId: number, callback: (data: OrderUpdate) => void): void {
    echo.private(`customer.${customerId}`)
      .listen('.order.status.updated', callback);
  }

  // Generic subscription method
  public subscribe(channel: string, callback: (data: any) => void): () => void {
    if (!this.subscribers.has(channel)) {
      this.subscribers.set(channel, new Set());
    }
    this.subscribers.get(channel)!.add(callback);

    // Return unsubscribe function
    return () => {
      this.subscribers.get(channel)?.delete(callback);
    };
  }

  private notifySubscribers(channel: string, data: any): void {
    const channelSubscribers = this.subscribers.get(channel);
    if (channelSubscribers) {
      channelSubscribers.forEach(callback => callback(data));
    }
  }

  // Disconnect from all channels
  public disconnect(): void {
    echo.disconnect();
  }

  // Check connection status
  public isConnected(): boolean {
    return echo.connector.pusher.connection.state === 'connected';
  }

  // Get connection state
  public getConnectionState(): string {
    return echo.connector.pusher.connection.state;
  }
}

// Real-time Hooks for React Components
export function useRealTimeIoTData(deviceId?: number) {
  const [data, setData] = React.useState<IoTDataUpdate | null>(null);
  const [connectionState, setConnectionState] = React.useState<string>('connecting');

  React.useEffect(() => {
    const wsService = WebSocketService.getInstance();
    
    // Update connection state
    const updateConnectionState = () => {
      setConnectionState(wsService.getConnectionState());
    };

    // Subscribe to connection state changes
    echo.connector.pusher.connection.bind('state_change', updateConnectionState);

    let unsubscribe: (() => void) | undefined;

    if (deviceId) {
      // Subscribe to specific device
      wsService.subscribeToDevice(deviceId, setData);
    } else {
      // Subscribe to all IoT data
      unsubscribe = wsService.subscribe('iot-data', setData);
    }

    return () => {
      unsubscribe?.();
      echo.connector.pusher.connection.unbind('state_change', updateConnectionState);
    };
  }, [deviceId]);

  return { data, connectionState, isConnected: connectionState === 'connected' };
}

export function useRealTimeAnalytics() {
  const [data, setData] = React.useState<AnalyticsUpdate | null>(null);

  React.useEffect(() => {
    const wsService = WebSocketService.getInstance();
    const unsubscribe = wsService.subscribe('analytics', setData);
    return unsubscribe;
  }, []);

  return data;
}

export function useRealTimeOrders(customerId?: number) {
  const [data, setData] = React.useState<OrderUpdate | null>(null);

  React.useEffect(() => {
    const wsService = WebSocketService.getInstance();
    
    let unsubscribe: (() => void) | undefined;

    if (customerId) {
      wsService.subscribeToCustomerOrders(customerId, setData);
    } else {
      unsubscribe = wsService.subscribe('orders', setData);
    }

    return unsubscribe;
  }, [customerId]);

  return data;
}

export function useRealTimeAlerts() {
  const [alerts, setAlerts] = React.useState<any[]>([]);

  React.useEffect(() => {
    const wsService = WebSocketService.getInstance();
    
    const handleAlert = (alert: any) => {
      setAlerts((prev: any[]) => [alert, ...prev.slice(0, 49)]); // Keep last 50 alerts
    };

    const unsubscribe = wsService.subscribe('alerts', handleAlert);
    return unsubscribe;
  }, []);

  return alerts;
}

// Import React for hooks
import React from 'react';

export default WebSocketService;