import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { Customer, Connection, CustomerFilter, PaginatedResponse } from '../types/lpg'

interface CustomerState {
  customers: Customer[]
  selectedCustomer: Customer | null
  connections: Connection[]
  isLoading: boolean
  error: string | null
  filter: CustomerFilter
  pagination: {
    page: number
    limit: number
    total: number
    totalPages: number
  }
}

interface CustomerActions {
  // Customer CRUD operations
  fetchCustomers: (filter?: CustomerFilter) => Promise<void>
  getCustomerById: (id: string) => Promise<Customer | null>
  createCustomer: (customer: Omit<Customer, 'id'>) => Promise<Customer>
  updateCustomer: (id: string, updates: Partial<Customer>) => Promise<Customer>
  deleteCustomer: (id: string) => Promise<void>
  setSelectedCustomer: (customer: Customer | null) => void
  
  // Connection management
  getCustomerConnections: (customerId: string) => Promise<Connection[]>
  createConnection: (connection: Omit<Connection, 'id'>) => Promise<Connection>
  updateConnection: (id: string, updates: Partial<Connection>) => Promise<Connection>
  deactivateConnection: (id: string) => Promise<void>
  
  // KYC operations
  updateKycStatus: (customerId: string, status: 'pending' | 'verified' | 'rejected') => Promise<void>
  uploadDocument: (customerId: string, documentType: string, file: File) => Promise<string>
  
  // Search and filter
  setFilter: (filter: Partial<CustomerFilter>) => void
  clearFilter: () => void
  searchCustomers: (query: string) => Promise<Customer[]>
  
  // Utility functions
  setLoading: (loading: boolean) => void
  setError: (error: string | null) => void
  clearError: () => void
  
  // Customer analytics
  getCustomerStats: (customerId: string) => Promise<any>
  getTopCustomers: (limit?: number) => Promise<Customer[]>
  getCustomersByArea: (area: string) => Promise<Customer[]>
}

export const useCustomerStore = create<CustomerState & CustomerActions>()(
  persist(
    (set, get) => ({
      // Initial state
      customers: [],
      selectedCustomer: null,
      connections: [],
      isLoading: false,
      error: null,
      filter: {},
      pagination: {
        page: 1,
        limit: 20,
        total: 0,
        totalPages: 0,
      },

      // Customer CRUD operations
      fetchCustomers: async (filter = {}) => {
        set({ isLoading: true, error: null })
        try {
          // Filter out undefined values for URLSearchParams
          const queryParams = Object.entries(filter)
            .filter(([_, value]) => value !== undefined)
            .reduce((acc, [key, value]) => ({ ...acc, [key]: String(value) }), {})
          
          const response = await fetch(`/api/customers?${new URLSearchParams(queryParams)}`)
          const data: PaginatedResponse<Customer> = await response.json()
          
          set({
            customers: data.data,
            pagination: {
              page: data.page,
              limit: data.limit,
              total: data.total,
              totalPages: data.totalPages,
            },
            isLoading: false,
          })
        } catch (error) {
          set({ error: 'Failed to fetch customers', isLoading: false })
        }
      },

      getCustomerById: async (id: string) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch(`/api/customers/${id}`)
          const customer: Customer = await response.json()
          set({ selectedCustomer: customer, isLoading: false })
          return customer
        } catch (error) {
          set({ error: 'Failed to fetch customer', isLoading: false })
          return null
        }
      },

      createCustomer: async (customerData) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch('/api/customers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(customerData),
          })
          const newCustomer: Customer = await response.json()
          
          set(state => ({
            customers: [newCustomer, ...state.customers],
            isLoading: false,
          }))
          
          return newCustomer
        } catch (error) {
          set({ error: 'Failed to create customer', isLoading: false })
          throw error
        }
      },

      updateCustomer: async (id: string, updates) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch(`/api/customers/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updates),
          })
          const updatedCustomer: Customer = await response.json()
          
          set(state => ({
            customers: state.customers.map(c => c.id === id ? updatedCustomer : c),
            selectedCustomer: state.selectedCustomer?.id === id ? updatedCustomer : state.selectedCustomer,
            isLoading: false,
          }))
          
          return updatedCustomer
        } catch (error) {
          set({ error: 'Failed to update customer', isLoading: false })
          throw error
        }
      },

      deleteCustomer: async (id: string) => {
        set({ isLoading: true, error: null })
        try {
          await fetch(`/api/customers/${id}`, { method: 'DELETE' })
          
          set(state => ({
            customers: state.customers.filter(c => c.id !== id),
            selectedCustomer: state.selectedCustomer?.id === id ? null : state.selectedCustomer,
            isLoading: false,
          }))
        } catch (error) {
          set({ error: 'Failed to delete customer', isLoading: false })
          throw error
        }
      },

      setSelectedCustomer: (customer) => {
        set({ selectedCustomer: customer })
      },

      // Connection management
      getCustomerConnections: async (customerId: string) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch(`/api/customers/${customerId}/connections`)
          const connections: Connection[] = await response.json()
          set({ connections, isLoading: false })
          return connections
        } catch (error) {
          set({ error: 'Failed to fetch connections', isLoading: false })
          return []
        }
      },

      createConnection: async (connectionData) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch('/api/connections', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(connectionData),
          })
          const newConnection: Connection = await response.json()
          
          set(state => ({
            connections: [newConnection, ...state.connections],
            isLoading: false,
          }))
          
          return newConnection
        } catch (error) {
          set({ error: 'Failed to create connection', isLoading: false })
          throw error
        }
      },

      updateConnection: async (id: string, updates) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch(`/api/connections/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updates),
          })
          const updatedConnection: Connection = await response.json()
          
          set(state => ({
            connections: state.connections.map(c => c.id === id ? updatedConnection : c),
            isLoading: false,
          }))
          
          return updatedConnection
        } catch (error) {
          set({ error: 'Failed to update connection', isLoading: false })
          throw error
        }
      },

      deactivateConnection: async (id: string) => {
        await get().updateConnection(id, { status: 'inactive' })
      },

      // KYC operations
      updateKycStatus: async (customerId: string, status) => {
        await get().updateCustomer(customerId, { kycStatus: status })
      },

      uploadDocument: async (customerId: string, documentType: string, file: File) => {
        set({ isLoading: true, error: null })
        try {
          const formData = new FormData()
          formData.append('file', file)
          formData.append('documentType', documentType)
          
          const response = await fetch(`/api/customers/${customerId}/documents`, {
            method: 'POST',
            body: formData,
          })
          
          const { url } = await response.json()
          set({ isLoading: false })
          return url
        } catch (error) {
          set({ error: 'Failed to upload document', isLoading: false })
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

      searchCustomers: async (query: string) => {
        set({ isLoading: true, error: null })
        try {
          const response = await fetch(`/api/customers/search?q=${encodeURIComponent(query)}`)
          const customers: Customer[] = await response.json()
          set({ isLoading: false })
          return customers
        } catch (error) {
          set({ error: 'Failed to search customers', isLoading: false })
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

      // Customer analytics
      getCustomerStats: async (customerId: string) => {
        try {
          const response = await fetch(`/api/customers/${customerId}/stats`)
          return await response.json()
        } catch (error) {
          console.error('Failed to fetch customer stats:', error)
          return null
        }
      },

      getTopCustomers: async (limit = 10) => {
        try {
          const response = await fetch(`/api/customers/top?limit=${limit}`)
          return await response.json()
        } catch (error) {
          console.error('Failed to fetch top customers:', error)
          return []
        }
      },

      getCustomersByArea: async (area: string) => {
        try {
          const response = await fetch(`/api/customers/by-area?area=${encodeURIComponent(area)}`)
          return await response.json()
        } catch (error) {
          console.error('Failed to fetch customers by area:', error)
          return []
        }
      },
    }),
    {
      name: 'customer-storage',
      partialize: (state) => ({
        selectedCustomer: state.selectedCustomer,
        filter: state.filter,
      }),
    }
  )
)