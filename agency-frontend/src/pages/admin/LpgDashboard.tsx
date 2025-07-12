import React, { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { useAuthStore } from '../../store/authStore'
import { useCustomerStore } from '../../store/customerStore'
import { useOrderStore } from '../../store/orderStore'
import { DashboardStats, SalesAnalytics } from '../../types/lpg'

const LpgDashboard: React.FC = () => {
  const { user } = useAuthStore()
  const { customers } = useCustomerStore()
  const { orders, todaysOrders, pendingOrders, urgentOrders } = useOrderStore()
  const [stats, setStats] = useState<DashboardStats>({
    totalCustomers: 0,
    activeConnections: 0,
    todaysOrders: 0,
    pendingDeliveries: 0,
    todaysRevenue: 0,
    monthlyRevenue: 0,
    cylindersInStock: 0,
    lowStockAlerts: 0,
    pendingComplaints: 0,
    newCustomersThisMonth: 0,
  })
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    loadDashboardData()
  }, [])

  const loadDashboardData = async () => {
    setIsLoading(true)
    try {
      // Load data from stores
      await Promise.all([
        useCustomerStore.getState().fetchCustomers(),
        useOrderStore.getState().getTodaysOrders(),
        useOrderStore.getState().getPendingOrders(),
        useOrderStore.getState().fetchUrgentOrders(),
      ])

      // Mock stats - replace with actual API calls
      setStats({
        totalCustomers: customers.length,
        activeConnections: customers.filter(c => c.isActive).length,
        todaysOrders: todaysOrders.length,
        pendingDeliveries: pendingOrders.filter(o => o.status === 'out_for_delivery').length,
        todaysRevenue: todaysOrders.reduce((sum, order) => sum + order.finalAmount, 0),
        monthlyRevenue: 125000, // Mock data
        cylindersInStock: 850,
        lowStockAlerts: 3,
        pendingComplaints: 5,
        newCustomersThisMonth: customers.filter(c => {
          const registrationDate = new Date(c.registrationDate)
          const now = new Date()
          return registrationDate.getMonth() === now.getMonth() && 
                 registrationDate.getFullYear() === now.getFullYear()
        }).length,
      })
    } catch (error) {
      console.error('Failed to load dashboard data:', error)
    } finally {
      setIsLoading(false)
    }
  }

  const quickActions = [
    { title: 'New Order', action: () => {}, icon: '📦', color: 'bg-blue-500' },
    { title: 'Add Customer', action: () => {}, icon: '👥', color: 'bg-green-500' },
    { title: 'Stock Update', action: () => {}, icon: '📊', color: 'bg-yellow-500' },
    { title: 'Delivery Route', action: () => {}, icon: '🚚', color: 'bg-purple-500' },
    { title: 'Payment Collect', action: () => {}, icon: '💰', color: 'bg-indigo-500' },
    { title: 'Generate Report', action: () => {}, icon: '📄', color: 'bg-red-500' },
  ]

  const recentOrders = todaysOrders.slice(0, 5)
  const urgentOrdersToday = urgentOrders.slice(0, 3)

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-primary mx-auto"></div>
          <p className="mt-4 text-muted-foreground">Loading dashboard...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">LPG Agency Dashboard</h1>
          <p className="text-muted-foreground">
            Welcome back, {user?.name}! Here's what's happening today.
          </p>
        </div>
        <div className="flex space-x-2">
          <Button onClick={loadDashboardData} disabled={isLoading}>
            {isLoading ? 'Refreshing...' : 'Refresh'}
          </Button>
          <Button variant="outline">View Reports</Button>
        </div>
      </div>

      {/* Key Metrics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Customers</CardTitle>
            <span className="text-2xl">👥</span>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.totalCustomers.toLocaleString()}</div>
            <p className="text-xs text-muted-foreground">
              +{stats.newCustomersThisMonth} new this month
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Today's Orders</CardTitle>
            <span className="text-2xl">📦</span>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.todaysOrders}</div>
            <p className="text-xs text-muted-foreground">
              {pendingOrders.length} pending delivery
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Today's Revenue</CardTitle>
            <span className="text-2xl">💰</span>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">₹{stats.todaysRevenue.toLocaleString()}</div>
            <p className="text-xs text-muted-foreground">
              Monthly: ₹{stats.monthlyRevenue.toLocaleString()}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Cylinders in Stock</CardTitle>
            <span className="text-2xl">🏭</span>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.cylindersInStock}</div>
            <p className="text-xs text-destructive">
              {stats.lowStockAlerts} low stock alerts
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <Card>
        <CardHeader>
          <CardTitle>Quick Actions</CardTitle>
          <CardDescription>Frequently used operations</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            {quickActions.map((action, index) => (
              <Button
                key={index}
                variant="outline"
                className="h-20 flex-col space-y-2"
                onClick={action.action}
              >
                <span className="text-2xl">{action.icon}</span>
                <span className="text-xs">{action.title}</span>
              </Button>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent Orders */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Recent Orders</CardTitle>
            <CardDescription>Latest orders placed today</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {recentOrders.length > 0 ? (
                recentOrders.map((order) => (
                  <div key={order.id} className="flex items-center justify-between p-3 border rounded-lg">
                    <div className="flex items-center space-x-3">
                      <div className={`w-3 h-3 rounded-full ${
                        order.priority === 'emergency' ? 'bg-red-500' :
                        order.priority === 'urgent' ? 'bg-yellow-500' : 'bg-green-500'
                      }`}></div>
                      <div>
                        <p className="font-medium">{order.orderId}</p>
                        <p className="text-sm text-muted-foreground">
                          {order.cylinderType} × {order.quantity}
                        </p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="font-medium">₹{order.finalAmount}</p>
                      <p className="text-sm text-muted-foreground capitalize">{order.status}</p>
                    </div>
                  </div>
                ))
              ) : (
                <p className="text-center text-muted-foreground py-8">No orders today</p>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Urgent Orders & Alerts */}
        <div className="space-y-6">
          {/* Urgent Orders */}
          <Card>
            <CardHeader>
              <CardTitle className="text-red-600">Urgent Orders</CardTitle>
              <CardDescription>Orders requiring immediate attention</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {urgentOrdersToday.length > 0 ? (
                  urgentOrdersToday.map((order) => (
                    <div key={order.id} className="p-3 border border-red-200 rounded-lg bg-red-50">
                      <div className="flex justify-between items-start">
                        <div>
                          <p className="font-medium text-red-800">{order.orderId}</p>
                          <p className="text-sm text-red-600">{order.priority}</p>
                        </div>
                        <Button size="sm" variant="destructive">
                          View
                        </Button>
                      </div>
                    </div>
                  ))
                ) : (
                  <p className="text-center text-muted-foreground py-4">No urgent orders</p>
                )}
              </div>
            </CardContent>
          </Card>

          {/* System Alerts */}
          <Card>
            <CardHeader>
              <CardTitle className="text-yellow-600">System Alerts</CardTitle>
              <CardDescription>Important notifications</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {stats.lowStockAlerts > 0 && (
                  <div className="p-3 border border-yellow-200 rounded-lg bg-yellow-50">
                    <p className="font-medium text-yellow-800">Low Stock Alert</p>
                    <p className="text-sm text-yellow-600">
                      {stats.lowStockAlerts} items running low
                    </p>
                  </div>
                )}
                
                {stats.pendingComplaints > 0 && (
                  <div className="p-3 border border-orange-200 rounded-lg bg-orange-50">
                    <p className="font-medium text-orange-800">Pending Complaints</p>
                    <p className="text-sm text-orange-600">
                      {stats.pendingComplaints} customer complaints pending
                    </p>
                  </div>
                )}

                {stats.lowStockAlerts === 0 && stats.pendingComplaints === 0 && (
                  <p className="text-center text-muted-foreground py-4">No alerts</p>
                )}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Business Module Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="hover:shadow-lg transition-shadow cursor-pointer">
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <span>👥</span>
              <span>Customer Management</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground mb-4">
              Manage customer profiles, KYC, and connections
            </p>
            <Button className="w-full" size="sm">
              Open Module
            </Button>
          </CardContent>
        </Card>

        <Card className="hover:shadow-lg transition-shadow cursor-pointer">
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <span>📦</span>
              <span>Order Management</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground mb-4">
              Process orders, track deliveries, and payments
            </p>
            <Button className="w-full" size="sm">
              Open Module
            </Button>
          </CardContent>
        </Card>

        <Card className="hover:shadow-lg transition-shadow cursor-pointer">
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <span>🏭</span>
              <span>Inventory Control</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground mb-4">
              Track cylinders, stock levels, and suppliers
            </p>
            <Button className="w-full" size="sm">
              Open Module
            </Button>
          </CardContent>
        </Card>

        <Card className="hover:shadow-lg transition-shadow cursor-pointer">
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <span>🚚</span>
              <span>Delivery Management</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground mb-4">
              Route planning, driver tracking, and delivery proof
            </p>
            <Button className="w-full" size="sm">
              Open Module
            </Button>
          </CardContent>
        </Card>

        <Card className="hover:shadow-lg transition-shadow cursor-pointer">
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <span>💰</span>
              <span>Financial Management</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground mb-4">
              Billing, payments, subsidies, and accounting
            </p>
            <Button className="w-full" size="sm">
              Open Module
            </Button>
          </CardContent>
        </Card>

        <Card className="hover:shadow-lg transition-shadow cursor-pointer">
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <span>📊</span>
              <span>Reports & Analytics</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground mb-4">
              Business intelligence and performance metrics
            </p>
            <Button className="w-full" size="sm">
              Open Module
            </Button>
          </CardContent>
        </Card>

        <Card className="hover:shadow-lg transition-shadow cursor-pointer">
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <span>🔧</span>
              <span>System Settings</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground mb-4">
              Configure pricing, notifications, and security
            </p>
            <Button className="w-full" size="sm">
              Open Module
            </Button>
          </CardContent>
        </Card>

        <Card className="hover:shadow-lg transition-shadow cursor-pointer">
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <span>📞</span>
              <span>Support & Complaints</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground mb-4">
              Handle customer support and complaint resolution
            </p>
            <Button className="w-full" size="sm">
              Open Module
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

export default LpgDashboard