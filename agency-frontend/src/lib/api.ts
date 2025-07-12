import axios, { AxiosInstance, AxiosResponse, AxiosError, InternalAxiosRequestConfig } from 'axios';

// API Configuration
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';

// Create axios instance
const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Add auth token if available
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    // Add request timestamp
    (config as any).metadata = { startTime: new Date() };
    
    return config;
  },
  (error: AxiosError) => {
    return Promise.reject(error);
  }
);

// Response interceptor
api.interceptors.response.use(
  (response: AxiosResponse) => {
    // Calculate request duration
    const endTime = new Date();
    const startTime = (response.config as any).metadata?.startTime;
    const duration = startTime ? endTime.getTime() - startTime.getTime() : 0;
    
    // Log successful requests in development
    if (process.env.NODE_ENV === 'development') {
      console.log(`✅ ${response.config.method?.toUpperCase()} ${response.config.url} - ${duration}ms`);
    }
    
    return response;
  },
  (error: AxiosError) => {
    const { response, request, message } = error;
    
    // Handle different error types
    if (response) {
      // Server responded with error status
      const status = response.status;
      const data = response.data as any;
      
      switch (status) {
        case 401:
          // Unauthorized - redirect to login
          localStorage.removeItem('auth_token');
          localStorage.removeItem('user_data');
          window.location.href = '/login';
          console.error('Session expired. Please login again.');
          break;
        case 403:
          console.error('Access denied. You do not have permission to perform this action.');
          break;
        case 404:
          console.error('Resource not found.');
          break;
        case 422:
          // Validation errors
          const errors = data.errors || {};
          const errorMessages = Object.values(errors).flat();
          console.error(errorMessages.join(', ') || 'Validation failed');
          break;
        case 429:
          console.error('Too many requests. Please try again later.');
          break;
        case 500:
          console.error('Internal server error. Please try again later.');
          break;
        default:
          console.error(data.message || 'An error occurred');
      }
    } else if (request) {
      // Network error
      console.error('Network error. Please check your connection.');
    } else {
      // Other error
      console.error(message || 'An unexpected error occurred');
    }
    
    return Promise.reject(error);
  }
);

// Generic API methods
export const apiClient = {
  get: <T>(url: string, params?: any): Promise<AxiosResponse<T>> => 
    api.get(url, { params }),
  
  post: <T>(url: string, data?: any): Promise<AxiosResponse<T>> => 
    api.post(url, data),
  
  put: <T>(url: string, data?: any): Promise<AxiosResponse<T>> => 
    api.put(url, data),
  
  patch: <T>(url: string, data?: any): Promise<AxiosResponse<T>> => 
    api.patch(url, data),
  
  delete: <T>(url: string): Promise<AxiosResponse<T>> => 
    api.delete(url),
  
  upload: <T>(url: string, formData: FormData): Promise<AxiosResponse<T>> => 
    api.post(url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
};

// Authentication API
export const authApi = {
  login: (credentials: { email: string; password: string }) =>
    apiClient.post('/auth/login', credentials),
  
  register: (userData: any) =>
    apiClient.post('/auth/register', userData),
  
  logout: () =>
    apiClient.post('/auth/logout'),
  
  getUser: () =>
    apiClient.get('/user'),
  
  refreshToken: () =>
    apiClient.post('/auth/refresh'),
};

// Customer API
export const customerApi = {
  getAll: (params?: any) =>
    apiClient.get('/customers', params),
  
  getById: (id: string) =>
    apiClient.get(`/customers/${id}`),
  
  create: (data: any) =>
    apiClient.post('/customers', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/customers/${id}`, data),
  
  delete: (id: string) =>
    apiClient.delete(`/customers/${id}`),
  
  uploadDocuments: (id: string, formData: FormData) =>
    apiClient.upload(`/customers/${id}/upload-documents`, formData),
  
  updateKycStatus: (id: string, data: { kyc_status: string; kyc_notes?: string }) =>
    apiClient.patch(`/customers/${id}/kyc-status`, data),
  
  getOrders: (id: string, params?: any) =>
    apiClient.get(`/customers/${id}/orders`, params),
  
  getConnections: (id: string) =>
    apiClient.get(`/customers/${id}/connections`),
};

// Connection API
export const connectionApi = {
  getAll: (params?: any) =>
    apiClient.get('/connections', params),
  
  getById: (id: string) =>
    apiClient.get(`/connections/${id}`),
  
  create: (data: any) =>
    apiClient.post('/connections', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/connections/${id}`, data),
  
  delete: (id: string) =>
    apiClient.delete(`/connections/${id}`),
  
  updateStatus: (id: string, status: string) =>
    apiClient.patch(`/connections/${id}/status`, { status }),
  
  resetQuota: (id: string) =>
    apiClient.post(`/connections/${id}/quota-reset`),
};

// Order API
export const orderApi = {
  getAll: (params?: any) =>
    apiClient.get('/orders', params),
  
  getById: (id: string) =>
    apiClient.get(`/orders/${id}`),
  
  create: (data: any) =>
    apiClient.post('/orders', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/orders/${id}`, data),
  
  delete: (id: string) =>
    apiClient.delete(`/orders/${id}`),
  
  updateStatus: (id: string, status: string) =>
    apiClient.patch(`/orders/${id}/status`, { status }),
  
  cancel: (id: string, reason?: string) =>
    apiClient.post(`/orders/${id}/cancel`, { reason }),
  
  confirm: (id: string) =>
    apiClient.post(`/orders/${id}/confirm`),
  
  track: (id: string) =>
    apiClient.get(`/orders/${id}/track`),
};

// Delivery API
export const deliveryApi = {
  getAll: (params?: any) =>
    apiClient.get('/deliveries', params),
  
  getById: (id: string) =>
    apiClient.get(`/deliveries/${id}`),
  
  create: (data: any) =>
    apiClient.post('/deliveries', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/deliveries/${id}`, data),
  
  updateStatus: (id: string, status: string) =>
    apiClient.patch(`/deliveries/${id}/status`, { status }),
  
  complete: (id: string, data: any) =>
    apiClient.post(`/deliveries/${id}/complete`, data),
  
  markFailed: (id: string, reason: string) =>
    apiClient.post(`/deliveries/${id}/failed`, { reason }),
  
  track: (id: string) =>
    apiClient.get(`/deliveries/${id}/track`),
};

// Driver API
export const driverApi = {
  getAll: (params?: any) =>
    apiClient.get('/drivers', params),
  
  getById: (id: string) =>
    apiClient.get(`/drivers/${id}`),
  
  create: (data: any) =>
    apiClient.post('/drivers', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/drivers/${id}`, data),
  
  delete: (id: string) =>
    apiClient.delete(`/drivers/${id}`),
  
  updateAvailability: (id: string, isAvailable: boolean) =>
    apiClient.patch(`/drivers/${id}/availability`, { is_available: isAvailable }),
  
  getDeliveries: (id: string, params?: any) =>
    apiClient.get(`/drivers/${id}/deliveries`, params),
  
  getAvailable: () =>
    apiClient.get('/drivers/available'),
};

// Vehicle API
export const vehicleApi = {
  getAll: (params?: any) =>
    apiClient.get('/vehicles', params),
  
  getById: (id: string) =>
    apiClient.get(`/vehicles/${id}`),
  
  create: (data: any) =>
    apiClient.post('/vehicles', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/vehicles/${id}`, data),
  
  delete: (id: string) =>
    apiClient.delete(`/vehicles/${id}`),
  
  updateStatus: (id: string, status: string) =>
    apiClient.patch(`/vehicles/${id}/status`, { status }),
  
  addMaintenance: (id: string, data: any) =>
    apiClient.post(`/vehicles/${id}/maintenance`, data),
  
  getAvailable: () =>
    apiClient.get('/vehicles/available'),
};

// Payment API
export const paymentApi = {
  getAll: (params?: any) =>
    apiClient.get('/payments', params),
  
  getById: (id: string) =>
    apiClient.get(`/payments/${id}`),
  
  create: (data: any) =>
    apiClient.post('/payments', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/payments/${id}`, data),
  
  refund: (id: string, data: any) =>
    apiClient.post(`/payments/${id}/refund`, data),
  
  getReceipt: (id: string) =>
    apiClient.get(`/payments/${id}/receipt`),
};

// Cylinder API
export const cylinderApi = {
  getAll: (params?: any) =>
    apiClient.get('/cylinders', params),
  
  getById: (id: string) =>
    apiClient.get(`/cylinders/${id}`),
  
  create: (data: any) =>
    apiClient.post('/cylinders', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/cylinders/${id}`, data),
  
  delete: (id: string) =>
    apiClient.delete(`/cylinders/${id}`),
  
  updateStatus: (id: string, status: string) =>
    apiClient.patch(`/cylinders/${id}/status`, { status }),
  
  addMaintenance: (id: string, data: any) =>
    apiClient.post(`/cylinders/${id}/maintenance`, data),
  
  track: (serialNumber: string) =>
    apiClient.get(`/cylinders/tracking/${serialNumber}`),
};

// Supplier API
export const supplierApi = {
  getAll: (params?: any) =>
    apiClient.get('/suppliers', params),
  
  getById: (id: string) =>
    apiClient.get(`/suppliers/${id}`),
  
  create: (data: any) =>
    apiClient.post('/suppliers', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/suppliers/${id}`, data),
  
  delete: (id: string) =>
    apiClient.delete(`/suppliers/${id}`),
  
  updateStatus: (id: string, status: string) =>
    apiClient.patch(`/suppliers/${id}/status`, { status }),
  
  getOrders: (id: string, params?: any) =>
    apiClient.get(`/suppliers/${id}/orders`, params),
};

// Complaint API
export const complaintApi = {
  getAll: (params?: any) =>
    apiClient.get('/complaints', params),
  
  getById: (id: string) =>
    apiClient.get(`/complaints/${id}`),
  
  create: (data: any) =>
    apiClient.post('/complaints', data),
  
  update: (id: string, data: any) =>
    apiClient.put(`/complaints/${id}`, data),
  
  delete: (id: string) =>
    apiClient.delete(`/complaints/${id}`),
  
  updateStatus: (id: string, status: string) =>
    apiClient.patch(`/complaints/${id}/status`, { status }),
  
  resolve: (id: string, data: any) =>
    apiClient.post(`/complaints/${id}/resolve`, data),
  
  escalate: (id: string, reason: string) =>
    apiClient.post(`/complaints/${id}/escalate`, { reason }),
};

// Dashboard API
export const dashboardApi = {
  getStats: () =>
    apiClient.get('/dashboard/stats'),
  
  getRecentOrders: () =>
    apiClient.get('/dashboard/recent-orders'),
  
  getPendingDeliveries: () =>
    apiClient.get('/dashboard/pending-deliveries'),
  
  getLowStock: () =>
    apiClient.get('/dashboard/low-stock'),
};

// Reports API
export const reportsApi = {
  sales: (params?: any) =>
    apiClient.get('/reports/sales', params),
  
  inventory: (params?: any) =>
    apiClient.get('/reports/inventory', params),
  
  customers: (params?: any) =>
    apiClient.get('/reports/customers', params),
  
  deliveries: (params?: any) =>
    apiClient.get('/reports/deliveries', params),
};

// File Upload API
export const fileApi = {
  uploadImage: (file: File) => {
    const formData = new FormData();
    formData.append('image', file);
    return apiClient.upload('/upload/image', formData);
  },
  
  uploadDocument: (file: File) => {
    const formData = new FormData();
    formData.append('document', file);
    return apiClient.upload('/upload/document', formData);
  },
};

// Health Check API
export const healthApi = {
  check: () =>
    apiClient.get('/health'),
};

// Export the main API instance
export default api;