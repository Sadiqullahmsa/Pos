import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { Order, Delivery, OrderFilter, PaginatedResponse } from '../types/lpg'

interface OrderState {
  orders: Order[]
  selectedOrder: Order | null
  deliveries: Delivery[]
  isLoading: boolean
  error: string | null
  filter: OrderFilter
  pagination: {
    page: number
    limit: number
    total: number
    totalPages: number
  }
  todaysOrders: Order[]
  pendingOrders: Order[]
  urgentOrders: Order[]
}

interface OrderActions {
  // Order CRUD operations
  fetchOrders: (filter?: OrderFilter) => Promise<void>
  getOrderById: (id: string) => Promise<Order | null>
  createOrder: (order: Omit<Order, 'id' | 'orderId'>) => Promise<Order>
  updateOrder: (id: string, updates: Partial<Order>) => Promise<Order>
  cancelOrder: (id: string, reason: string) => Promise<void>
  setSelectedOrder: (order: Order | null) => void
  
  // Order status management
  confirmOrder: (id: string) => Promise<void>
  assignDriver: (orderId: string, driverId: string) => Promise<void>
  markOutForDelivery: (id: string) => Promise<void>
  markDelivered: (id: string, proofOfDelivery?: any) => Promise<void>
  
  // Delivery management
  createDelivery: (delivery: Omit<Delivery, 'id'>) => Promise<Delivery>
  updateDeliveryStatus: (deliveryId: string, status: string) => Promise<void>
  updateDeliveryLocation: (deliveryId: string, location: { lat: number; lng: number }) => Promise<void>
  getDeliveryByOrderId: (orderId: string) => Promise<Delivery | null>
  
  // Emergency and priority orders
  markAsUrgent: (orderId: string) => Promise<void>
  markAsEmergency: (orderId: string) => Promise<void>
  fetchUrgentOrders: () => Promise<void>
  
  // Payment operations
  updatePaymentStatus: (orderId: string, status: string, paymentMethod?: string) => Promise<void>
  recordPayment: (orderId: string, amount: number, method: string) => Promise<void>
  
  // Analytics and reporting
  getTodaysOrders: () => Promise<void>
  getPendingOrders: () => Promise<void>
  getOrderStats: (dateRange?: { from: string; to: string }) => Promise<any>
  getDeliveryPerformance: (driverId?: string) => Promise<any>
  
  // Customer order history
  getCustomerOrders: (customerId: string) => Promise<Order[]>
  getOrderHistory: (customerId: string, limit?: number) => Promise<Order[]>
  
  // Bulk operations
  bulkUpdateStatus: (orderIds: string[], status: string) => Promise<void>
  bulkAssignDriver: (orderIds: string[], driverId: string) => Promise<void>
  
  // Search and filter
  setFilter: (filter: Partial<OrderFilter>) => void
  clearFilter: () => void
  searchOrders: (query: string) => Promise<Order[]>
  
  // Utility functions
  setLoading: (loading: boolean) => void
  setError: (error: string | null) => void
  clearError: () => void
  
  // Order validation
  validateOrder: (order: Partial<Order>) => Promise<{ valid: boolean; errors: string[] }>
  checkDeliverySlot: (date: string, timeSlot: string) => Promise<boolean>
  calculateOrderAmount: (cylinderType: string, quantity: number, customerId: string) => Promise<{ amount: number; subsidyAmount: number; finalAmount: number }>
}

export const useOrderStore = create<OrderState & OrderActions>()(
  persist(
    (set, get) => ({
      // Initial state
      orders: [],
      selectedOrder: null,
      deliveries: [],
      isLoading: false,
      error: null,
      filter: {},
      pagination: {
        page: 1,
        limit: 20,
        total: 0,
        totalPages: 0,
      },
      todaysOrders: [],
      pendingOrders: [],
      urgentOrders: [],

      // Order CRUD operations
      fetchOrders: async (filter = {}) => {
        set({ isLoading: true, error: null })
        try {
          const queryParams = Object.entries(filter)
            .filter(([_, value]) => value !== undefined)
            .reduce((acc, [key, value]) => ({ ...acc, [key]: String(value) }), {})
          
          const response = await fetch(`/api/orders?${new URLSearchParams(queryParams)}`)
          const data: PaginatedResponse<Order> = await response.json()
          
          set({
            orders: data.data,
            pagination: {
              page: data.page,
              limit: data.limit,
              total: data.total,
              totalPages: data.totalPages,
            },
            isLoading: false,
          })
        } catch (error) {
          set({ error: 'Failed to fetch orders', isLoading: false })
        }
      },

      getOrderById: async (id: string) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch(`/api/orders/${id}`)
          const order: Order = await response.json()
          set({ selectedOrder: order, isLoading: false })
          return order
        } catch (error) {
          set({ error: 'Failed to fetch order', isLoading: false })
          return null
        }
      },

      createOrder: async (orderData) => {
        set({ isLoading: true, error: null })
        try {
          // Generate order ID
          const orderId = `ORD${Date.now()}`
          const newOrderData = { ...orderData, orderId }
          
          const response = await fetch('/api/orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(newOrderData),
          })
          const newOrder: Order = await response.json()
          
          set(state => ({
            orders: [newOrder, ...state.orders],
            isLoading: false,
          }))
          
          return newOrder
        } catch (error) {
          set({ error: 'Failed to create order', isLoading: false })
          throw error
        }
      },

      updateOrder: async (id: string, updates) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch(`/api/orders/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updates),
          })
          const updatedOrder: Order = await response.json()
          
          set(state => ({
            orders: state.orders.map(o => o.id === id ? updatedOrder : o),
            selectedOrder: state.selectedOrder?.id === id ? updatedOrder : state.selectedOrder,
            isLoading: false,
          }))
          
          return updatedOrder
        } catch (error) {
          set({ error: 'Failed to update order', isLoading: false })
          throw error
        }
      },

      cancelOrder: async (id: string, reason: string) => {
        await get().updateOrder(id, { status: 'cancelled', notes: reason })
      },

      setSelectedOrder: (order) => {
        set({ selectedOrder: order })
      },

      // Order status management
      confirmOrder: async (id: string) => {
        await get().updateOrder(id, { status: 'confirmed' })
      },

      assignDriver: async (orderId: string, driverId: string) => {
        await get().updateOrder(orderId, { driverId })
      },

      markOutForDelivery: async (id: string) => {
        await get().updateOrder(id, { status: 'out_for_delivery' })
      },

      markDelivered: async (id: string, proofOfDelivery) => {
        const updates: Partial<Order> = {
          status: 'delivered',
          actualDeliveryTime: new Date().toISOString(),
        }
        
        if (proofOfDelivery) {
          updates.notes = `Delivered with proof: ${JSON.stringify(proofOfDelivery)}`
        }
        
        await get().updateOrder(id, updates)
      },

      // Delivery management
      createDelivery: async (deliveryData) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch('/api/deliveries', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(deliveryData),
          })
          const newDelivery: Delivery = await response.json()
          
          set(state => ({
            deliveries: [newDelivery, ...state.deliveries],
            isLoading: false,
          }))
          
          return newDelivery
        } catch (error) {
          set({ error: 'Failed to create delivery', isLoading: false })
          throw error
        }
      },

      updateDeliveryStatus: async (deliveryId: string, status: string) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch(`/api/deliveries/${deliveryId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status }),
          })
          const updatedDelivery: Delivery = await response.json()
          
          set(state => ({
            deliveries: state.deliveries.map(d => d.id === deliveryId ? updatedDelivery : d),
            isLoading: false,
          }))
        } catch (error) {
          set({ error: 'Failed to update delivery status', isLoading: false })
        }
      },

      updateDeliveryLocation: async (deliveryId: string, location) => {
        try {
          await fetch(`/api/deliveries/${deliveryId}/location`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ location: { ...location, timestamp: new Date().toISOString() } }),
          })
        } catch (error) {
          console.error('Failed to update delivery location:', error)
        }
      },

      getDeliveryByOrderId: async (orderId: string) => {
        try {
          const response = await fetch(`/api/deliveries/order/${orderId}`)
          return await response.json()
        } catch (error) {
          console.error('Failed to fetch delivery:', error)
          return null
        }
      },

      // Emergency and priority orders
      markAsUrgent: async (orderId: string) => {
        await get().updateOrder(orderId, { priority: 'urgent' })
      },

      markAsEmergency: async (orderId: string) => {
        await get().updateOrder(orderId, { priority: 'emergency' })
      },

      fetchUrgentOrders: async () => {
        try {
          const response = await fetch('/api/orders?priority=urgent&priority=emergency')
          const urgentOrders: Order[] = await response.json()
          set({ urgentOrders })
        } catch (error) {
          console.error('Failed to fetch urgent orders:', error)
        }
      },

      // Payment operations
      updatePaymentStatus: async (orderId: string, status: string, paymentMethod) => {
        const updates: Partial<Order> = { paymentStatus: status as any }
        if (paymentMethod) {
          updates.paymentMethod = paymentMethod as any
        }
        await get().updateOrder(orderId, updates)
      },

      recordPayment: async (orderId: string, amount: number, method: string) => {
        try {
          await fetch('/api/payments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              orderId,
              amount,
              method,
              timestamp: new Date().toISOString(),
            }),
          })
          
          await get().updatePaymentStatus(orderId, 'paid', method)
        } catch (error) {
          console.error('Failed to record payment:', error)
          throw error
        }
      },

      // Analytics and reporting
      getTodaysOrders: async () => {
        try {
          const today = new Date().toISOString().split('T')[0]
          const response = await fetch(`/api/orders?date=${today}`)
          const todaysOrders: Order[] = await response.json()
          set({ todaysOrders })
        } catch (error) {
          console.error('Failed to fetch today\'s orders:', error)
        }
      },

      getPendingOrders: async () => {
        try {
          const response = await fetch('/api/orders?status=pending&status=confirmed')
          const pendingOrders: Order[] = await response.json()
          set({ pendingOrders })
        } catch (error) {
          console.error('Failed to fetch pending orders:', error)
        }
      },

      getOrderStats: async (dateRange) => {
        try {
          let url = '/api/orders/stats'
          if (dateRange) {
            url += `?from=${dateRange.from}&to=${dateRange.to}`
          }
          const response = await fetch(url)
          return await response.json()
        } catch (error) {
          console.error('Failed to fetch order stats:', error)
          return null
        }
      },

      getDeliveryPerformance: async (driverId) => {
        try {
          let url = '/api/deliveries/performance'
          if (driverId) {
            url += `?driverId=${driverId}`
          }
          const response = await fetch(url)
          return await response.json()
        } catch (error) {
          console.error('Failed to fetch delivery performance:', error)
          return null
        }
      },

      // Customer order history
      getCustomerOrders: async (customerId: string) => {
        try {
          const response = await fetch(`/api/orders/customer/${customerId}`)
          return await response.json()
        } catch (error) {
          console.error('Failed to fetch customer orders:', error)
          return []
        }
      },

      getOrderHistory: async (customerId: string, limit = 10) => {
        try {
          const response = await fetch(`/api/orders/customer/${customerId}/history?limit=${limit}`)
          return await response.json()
        } catch (error) {
          console.error('Failed to fetch order history:', error)
          return []
        }
      },

      // Bulk operations
      bulkUpdateStatus: async (orderIds: string[], status: string) => {
        set({ isLoading: true, error: null })
        try {
          await fetch('/api/orders/bulk-update', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ orderIds, updates: { status } }),
          })
          
          // Update local state
          set(state => ({
            orders: state.orders.map(order => 
              orderIds.includes(order.id) ? { ...order, status: status as any } : order
            ),
            isLoading: false,
          }))
        } catch (error) {
          set({ error: 'Failed to bulk update orders', isLoading: false })
          throw error
        }
      },

      bulkAssignDriver: async (orderIds: string[], driverId: string) => {
        set({ isLoading: true, error: null })
        try {
          await fetch('/api/orders/bulk-assign', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ orderIds, driverId }),
          })
          
          // Update local state
          set(state => ({
            orders: state.orders.map(order => 
              orderIds.includes(order.id) ? { ...order, driverId } : order
            ),
            isLoading: false,
          }))
        } catch (error) {
          set({ error: 'Failed to bulk assign driver', isLoading: false })
          throw error
        }
      },

      // Search and filter
      setFilter: (newFilter) => {
        set(state => ({ filter: { ...state.filter, ...newFilter } }))
      },

      clearFilter: () => {
        set({ filter: {} })
      },

      searchOrders: async (query: string) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch(`/api/orders/search?q=${encodeURIComponent(query)}`)
          const orders: Order[] = await response.json()
          set({ isLoading: false })
          return orders
        } catch (error) {
          set({ error: 'Failed to search orders', isLoading: false })
          return []
        }
      },

      // Utility functions
      setLoading: (loading) => {
        set({ isLoading: loading })
      },

      setError: (error) => {
        set({ error })
      },

      clearError: () => {
        set({ error: null })
      },

      // Order validation
      validateOrder: async (order) => {
        try {
          const response = await fetch('/api/orders/validate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(order),
          })
          return await response.json()
        } catch (error) {
          return { valid: false, errors: ['Validation failed'] }
        }
      },

      checkDeliverySlot: async (date: string, timeSlot: string) => {
        try {
          const response = await fetch(`/api/delivery-slots/check?date=${date}&slot=${timeSlot}`)
          const { available } = await response.json()
          return available
        } catch (error) {
          console.error('Failed to check delivery slot:', error)
          return false
        }
      },

      calculateOrderAmount: async (cylinderType: string, quantity: number, customerId: string) => {
        try {
          const response = await fetch('/api/orders/calculate-amount', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cylinderType, quantity, customerId }),
          })
          return await response.json()
        } catch (error) {
          console.error('Failed to calculate order amount:', error)
          return { amount: 0, subsidyAmount: 0, finalAmount: 0 }
        }
      },
    }),
    {
      name: 'order-storage',
      partialize: (state) => ({
        selectedOrder: state.selectedOrder,
        filter: state.filter,
      }),
    }
  )
)