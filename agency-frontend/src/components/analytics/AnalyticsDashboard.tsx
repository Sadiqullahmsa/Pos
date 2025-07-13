import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import {
  LineChart,
  Line,
  AreaChart,
  Area,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend
} from 'recharts';
import { 
  TrendingUp, 
  TrendingDown, 
  Users, 
  Package, 
  DollarSign, 
  Activity,
  AlertTriangle,
  Target,
  Brain,
  Zap
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../ui/tabs';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import { Progress } from '../ui/progress';
import { useRealTimeAnalytics } from '../../lib/websocket';
import { useQuery } from '@tanstack/react-query';
import axios from 'axios';

// Types
interface AnalyticsData {
  overview: {
    total_revenue: { current: number; previous: number };
    total_orders: { current: number; previous: number };
    new_customers: { current: number; previous: number };
    average_order_value: { current: number; previous: number };
  };
  sales_trends: Array<{
    date: string;
    orders: number;
    revenue: number;
  }>;
  customer_analytics: {
    acquisition_rate: number;
    retention_rate: number;
    lifetime_value: number;
    satisfaction_scores: number[];
  };
  delivery_performance: {
    success_rate: number;
    avg_delivery_time: number;
    total_deliveries: number;
  };
  inventory_status: Array<{
    status: string;
    count: number;
    percentage: number;
  }>;
  predictive_insights: {
    demand_forecast: any[];
    inventory_optimization: any[];
    price_optimization: any[];
  };
  real_time_metrics: {
    active_orders: number;
    pending_deliveries: number;
    online_devices: number;
    active_drivers: number;
    system_load: {
      cpu_usage: number;
      memory_usage: number;
      active_connections: number;
      queue_size: number;
    };
  };
}

interface BusinessInsights {
  insights: Record<string, any>;
  recommendations: Record<string, any>;
  market_opportunities: any;
  risk_analysis: any;
}

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

const AnalyticsDashboard: React.FC = () => {
  const [selectedPeriod, setSelectedPeriod] = useState('week');
  const [refreshInterval, setRefreshInterval] = useState(30000); // 30 seconds
  const realTimeData = useRealTimeAnalytics();

  // Fetch analytics data
  const { data: analyticsData, refetch } = useQuery<AnalyticsData>({
    queryKey: ['analytics', selectedPeriod],
    queryFn: async () => {
      const response = await axios.get(`/api/analytics/dashboard?period=${selectedPeriod}`);
      return response.data.data;
    },
    refetchInterval: refreshInterval,
  });

  // Fetch business insights
  const { data: insights } = useQuery<BusinessInsights>({
    queryKey: ['business-insights'],
    queryFn: async () => {
      const response = await axios.get('/api/analytics/business-insights');
      return response.data.data;
    },
    refetchInterval: 300000, // 5 minutes
  });

  // Calculate percentage change
  const calculateChange = (current: number, previous: number) => {
    if (previous === 0) return 0;
    return ((current - previous) / previous) * 100;
  };

  // Format currency
  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
    }).format(amount);
  };

  // Format number with K, M suffixes
  const formatNumber = (num: number) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
  };

  // Real-time updates effect
  useEffect(() => {
    if (realTimeData) {
      // Update specific metrics based on real-time data
      refetch();
    }
  }, [realTimeData, refetch]);

  if (!analyticsData) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Analytics Dashboard</h1>
          <p className="text-gray-600 mt-1">Real-time insights and AI-powered analytics</p>
        </div>
        <div className="flex gap-4">
          <div className="flex gap-2">
            {['today', 'week', 'month', 'quarter'].map((period) => (
              <Button
                key={period}
                variant={selectedPeriod === period ? 'default' : 'outline'}
                size="sm"
                onClick={() => setSelectedPeriod(period)}
                className="capitalize"
              >
                {period}
              </Button>
            ))}
          </div>
          <Badge variant="outline" className="flex items-center gap-1">
            <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            Live
          </Badge>
        </div>
      </div>

      {/* Key Metrics Overview */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
        >
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Total Revenue</p>
                  <p className="text-2xl font-bold text-gray-900">
                    {formatCurrency(analyticsData.overview.total_revenue.current)}
                  </p>
                  <div className="flex items-center mt-1">
                    {calculateChange(
                      analyticsData.overview.total_revenue.current,
                      analyticsData.overview.total_revenue.previous
                    ) >= 0 ? (
                      <TrendingUp className="h-4 w-4 text-green-500" />
                    ) : (
                      <TrendingDown className="h-4 w-4 text-red-500" />
                    )}
                    <span className={`text-sm ml-1 ${
                      calculateChange(
                        analyticsData.overview.total_revenue.current,
                        analyticsData.overview.total_revenue.previous
                      ) >= 0 ? 'text-green-500' : 'text-red-500'
                    }`}>
                      {Math.abs(calculateChange(
                        analyticsData.overview.total_revenue.current,
                        analyticsData.overview.total_revenue.previous
                      )).toFixed(1)}%
                    </span>
                  </div>
                </div>
                <DollarSign className="h-8 w-8 text-green-500" />
              </div>
            </CardContent>
          </Card>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Total Orders</p>
                  <p className="text-2xl font-bold text-gray-900">
                    {formatNumber(analyticsData.overview.total_orders.current)}
                  </p>
                  <div className="flex items-center mt-1">
                    {calculateChange(
                      analyticsData.overview.total_orders.current,
                      analyticsData.overview.total_orders.previous
                    ) >= 0 ? (
                      <TrendingUp className="h-4 w-4 text-green-500" />
                    ) : (
                      <TrendingDown className="h-4 w-4 text-red-500" />
                    )}
                    <span className={`text-sm ml-1 ${
                      calculateChange(
                        analyticsData.overview.total_orders.current,
                        analyticsData.overview.total_orders.previous
                      ) >= 0 ? 'text-green-500' : 'text-red-500'
                    }`}>
                      {Math.abs(calculateChange(
                        analyticsData.overview.total_orders.current,
                        analyticsData.overview.total_orders.previous
                      )).toFixed(1)}%
                    </span>
                  </div>
                </div>
                <Package className="h-8 w-8 text-blue-500" />
              </div>
            </CardContent>
          </Card>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
        >
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">New Customers</p>
                  <p className="text-2xl font-bold text-gray-900">
                    {formatNumber(analyticsData.overview.new_customers.current)}
                  </p>
                  <div className="flex items-center mt-1">
                    {calculateChange(
                      analyticsData.overview.new_customers.current,
                      analyticsData.overview.new_customers.previous
                    ) >= 0 ? (
                      <TrendingUp className="h-4 w-4 text-green-500" />
                    ) : (
                      <TrendingDown className="h-4 w-4 text-red-500" />
                    )}
                    <span className={`text-sm ml-1 ${
                      calculateChange(
                        analyticsData.overview.new_customers.current,
                        analyticsData.overview.new_customers.previous
                      ) >= 0 ? 'text-green-500' : 'text-red-500'
                    }`}>
                      {Math.abs(calculateChange(
                        analyticsData.overview.new_customers.current,
                        analyticsData.overview.new_customers.previous
                      )).toFixed(1)}%
                    </span>
                  </div>
                </div>
                <Users className="h-8 w-8 text-purple-500" />
              </div>
            </CardContent>
          </Card>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
        >
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Avg Order Value</p>
                  <p className="text-2xl font-bold text-gray-900">
                    {formatCurrency(analyticsData.overview.average_order_value.current)}
                  </p>
                  <div className="flex items-center mt-1">
                    {calculateChange(
                      analyticsData.overview.average_order_value.current,
                      analyticsData.overview.average_order_value.previous
                    ) >= 0 ? (
                      <TrendingUp className="h-4 w-4 text-green-500" />
                    ) : (
                      <TrendingDown className="h-4 w-4 text-red-500" />
                    )}
                    <span className={`text-sm ml-1 ${
                      calculateChange(
                        analyticsData.overview.average_order_value.current,
                        analyticsData.overview.average_order_value.previous
                      ) >= 0 ? 'text-green-500' : 'text-red-500'
                    }`}>
                      {Math.abs(calculateChange(
                        analyticsData.overview.average_order_value.current,
                        analyticsData.overview.average_order_value.previous
                      )).toFixed(1)}%
                    </span>
                  </div>
                </div>
                <Target className="h-8 w-8 text-orange-500" />
              </div>
            </CardContent>
          </Card>
        </motion.div>
      </div>

      {/* Real-time Status */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Activity className="h-5 w-5" />
            Real-time Operations
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div className="text-center">
              <p className="text-2xl font-bold text-blue-600">
                {analyticsData.real_time_metrics.active_orders}
              </p>
              <p className="text-sm text-gray-600">Active Orders</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-bold text-orange-600">
                {analyticsData.real_time_metrics.pending_deliveries}
              </p>
              <p className="text-sm text-gray-600">Pending Deliveries</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-bold text-green-600">
                {analyticsData.real_time_metrics.online_devices}
              </p>
              <p className="text-sm text-gray-600">Online Devices</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-bold text-purple-600">
                {analyticsData.real_time_metrics.active_drivers}
              </p>
              <p className="text-sm text-gray-600">Active Drivers</p>
            </div>
          </div>
          
          {/* System Load */}
          <div className="mt-6 p-4 bg-gray-50 rounded-lg">
            <h4 className="font-medium mb-3">System Performance</h4>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div>
                <div className="flex justify-between text-sm mb-1">
                  <span>CPU Usage</span>
                  <span>{analyticsData.real_time_metrics.system_load.cpu_usage.toFixed(1)}%</span>
                </div>
                <Progress value={analyticsData.real_time_metrics.system_load.cpu_usage} />
              </div>
              <div>
                <div className="flex justify-between text-sm mb-1">
                  <span>Memory</span>
                  <span>{analyticsData.real_time_metrics.system_load.memory_usage.toFixed(1)}%</span>
                </div>
                <Progress value={analyticsData.real_time_metrics.system_load.memory_usage} />
              </div>
              <div>
                <div className="flex justify-between text-sm mb-1">
                  <span>Connections</span>
                  <span>{analyticsData.real_time_metrics.system_load.active_connections}</span>
                </div>
                <Progress value={Math.min(analyticsData.real_time_metrics.system_load.active_connections / 1000 * 100, 100)} />
              </div>
              <div>
                <div className="flex justify-between text-sm mb-1">
                  <span>Queue</span>
                  <span>{analyticsData.real_time_metrics.system_load.queue_size}</span>
                </div>
                <Progress value={Math.min(analyticsData.real_time_metrics.system_load.queue_size / 100 * 100, 100)} />
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Charts and Analytics */}
      <Tabs defaultValue="trends" className="w-full">
        <TabsList className="grid w-full grid-cols-5">
          <TabsTrigger value="trends">Sales Trends</TabsTrigger>
          <TabsTrigger value="customers">Customers</TabsTrigger>
          <TabsTrigger value="delivery">Delivery</TabsTrigger>
          <TabsTrigger value="inventory">Inventory</TabsTrigger>
          <TabsTrigger value="insights">AI Insights</TabsTrigger>
        </TabsList>

        <TabsContent value="trends">
          <Card>
            <CardHeader>
              <CardTitle>Sales Trends</CardTitle>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={400}>
                <AreaChart data={analyticsData.sales_trends}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="date" />
                  <YAxis yAxisId="left" />
                  <YAxis yAxisId="right" orientation="right" />
                  <Tooltip 
                    formatter={(value, name) => [
                      name === 'revenue' ? formatCurrency(Number(value)) : value,
                      name === 'revenue' ? 'Revenue' : 'Orders'
                    ]}
                  />
                  <Legend />
                  <Area yAxisId="right" type="monotone" dataKey="revenue" stackId="1" stroke="#8884d8" fill="#8884d8" fillOpacity={0.3} />
                  <Line yAxisId="left" type="monotone" dataKey="orders" stroke="#82ca9d" strokeWidth={3} />
                </AreaChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="customers">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card>
              <CardHeader>
                <CardTitle>Customer Metrics</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div>
                    <div className="flex justify-between text-sm mb-1">
                      <span>Acquisition Rate</span>
                      <span>{analyticsData.customer_analytics.acquisition_rate.toFixed(1)}%</span>
                    </div>
                    <Progress value={analyticsData.customer_analytics.acquisition_rate} />
                  </div>
                  <div>
                    <div className="flex justify-between text-sm mb-1">
                      <span>Retention Rate</span>
                      <span>{analyticsData.customer_analytics.retention_rate.toFixed(1)}%</span>
                    </div>
                    <Progress value={analyticsData.customer_analytics.retention_rate} />
                  </div>
                  <div className="pt-4">
                    <p className="text-sm text-gray-600">Customer Lifetime Value</p>
                    <p className="text-2xl font-bold">
                      {formatCurrency(analyticsData.customer_analytics.lifetime_value)}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
            
            <Card>
              <CardHeader>
                <CardTitle>Satisfaction Scores</CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={200}>
                  <BarChart data={analyticsData.customer_analytics.satisfaction_scores.map((score, index) => ({
                    rating: `${index + 1} Star${index === 0 ? '' : 's'}`,
                    count: score,
                  }))}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="rating" />
                    <YAxis />
                    <Tooltip />
                    <Bar dataKey="count" fill="#8884d8" />
                  </BarChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="delivery">
          <Card>
            <CardHeader>
              <CardTitle>Delivery Performance</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="text-center">
                  <p className="text-3xl font-bold text-green-600">
                    {analyticsData.delivery_performance.success_rate.toFixed(1)}%
                  </p>
                  <p className="text-sm text-gray-600">Success Rate</p>
                </div>
                <div className="text-center">
                  <p className="text-3xl font-bold text-blue-600">
                    {analyticsData.delivery_performance.avg_delivery_time.toFixed(1)}h
                  </p>
                  <p className="text-sm text-gray-600">Avg Delivery Time</p>
                </div>
                <div className="text-center">
                  <p className="text-3xl font-bold text-purple-600">
                    {analyticsData.delivery_performance.total_deliveries}
                  </p>
                  <p className="text-sm text-gray-600">Total Deliveries</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="inventory">
          <Card>
            <CardHeader>
              <CardTitle>Inventory Status</CardTitle>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={400}>
                <PieChart>
                  <Pie
                    data={analyticsData.inventory_status}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ status, percentage }) => `${status}: ${percentage.toFixed(1)}%`}
                    outerRadius={120}
                    fill="#8884d8"
                    dataKey="count"
                  >
                    {analyticsData.inventory_status.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="insights">
          <div className="space-y-6">
            {insights && (
              <>
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Brain className="h-5 w-5" />
                      AI-Powered Business Insights
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                        <h4 className="font-medium mb-3">Key Recommendations</h4>
                        <div className="space-y-2">
                          {Object.entries(insights.recommendations).slice(0, 5).map(([key, value]) => (
                            <div key={key} className="p-3 bg-blue-50 rounded-lg">
                              <p className="text-sm font-medium text-blue-900">{key.replace('_', ' ').toUpperCase()}</p>
                              <p className="text-sm text-blue-700">{JSON.stringify(value)}</p>
                            </div>
                          ))}
                        </div>
                      </div>
                      <div>
                        <h4 className="font-medium mb-3">Market Opportunities</h4>
                        <div className="space-y-2">
                          {Object.entries(insights.market_opportunities || {}).slice(0, 3).map(([key, value]) => (
                            <div key={key} className="p-3 bg-green-50 rounded-lg">
                              <p className="text-sm font-medium text-green-900">{key.replace('_', ' ').toUpperCase()}</p>
                              <p className="text-sm text-green-700">{JSON.stringify(value)}</p>
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <AlertTriangle className="h-5 w-5" />
                      Risk Analysis
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      {Object.entries(insights.risk_analysis || {}).map(([key, value]) => (
                        <div key={key} className="p-4 border rounded-lg">
                          <h5 className="font-medium text-gray-900 mb-2">
                            {key.replace('_', ' ').toUpperCase()}
                          </h5>
                          <p className="text-sm text-gray-600">{JSON.stringify(value)}</p>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              </>
            )}
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
};

export default AnalyticsDashboard;