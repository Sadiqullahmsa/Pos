import axios, { AxiosError, AxiosRequestConfig, AxiosResponse } from 'axios'

// API Configuration
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:3001/api'

// Create axios instance
export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
})

// Request interceptor
api.interceptors.request.use(
  (config) => {
    // Add auth token if available
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor
api.interceptors.response.use(
  (response: AxiosResponse) => {
    return response
  },
  (error: AxiosError) => {
    // Handle common errors
    if (error.response?.status === 401) {
      // Unauthorized - clear token and redirect to login
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
    }
    
    if (error.response?.status === 403) {
      // Forbidden
      console.error('Access forbidden')
    }
    
    if (error.response?.status >= 500) {
      // Server error
      console.error('Server error:', error.response.data)
    }
    
    return Promise.reject(error)
  }
)

// API Types
export interface ApiResponse<T = any> {
  data: T
  message?: string
  success: boolean
}

export interface PaginatedResponse<T = any> {
  data: T[]
  total: number
  page: number
  limit: number
  totalPages: number
}

export interface ApiError {
  message: string
  code?: string
  details?: any
}

// Generic API methods
export const apiClient = {
  get: <T = any>(url: string, config?: AxiosRequestConfig) =>
    api.get<ApiResponse<T>>(url, config),
  
  post: <T = any>(url: string, data?: any, config?: AxiosRequestConfig) =>
    api.post<ApiResponse<T>>(url, data, config),
  
  put: <T = any>(url: string, data?: any, config?: AxiosRequestConfig) =>
    api.put<ApiResponse<T>>(url, data, config),
  
  patch: <T = any>(url: string, data?: any, config?: AxiosRequestConfig) =>
    api.patch<ApiResponse<T>>(url, data, config),
  
  delete: <T = any>(url: string, config?: AxiosRequestConfig) =>
    api.delete<ApiResponse<T>>(url, config),
}

// Auth API
export const authApi = {
  login: (credentials: { email: string; password: string }) =>
    apiClient.post<{ token: string; user: any }>('/auth/login', credentials),
  
  register: (userData: { email: string; password: string; name: string }) =>
    apiClient.post<{ token: string; user: any }>('/auth/register', userData),
  
  logout: () => apiClient.post('/auth/logout'),
  
  refreshToken: () => apiClient.post<{ token: string }>('/auth/refresh'),
  
  forgotPassword: (email: string) =>
    apiClient.post('/auth/forgot-password', { email }),
  
  resetPassword: (token: string, password: string) =>
    apiClient.post('/auth/reset-password', { token, password }),
  
  verifyEmail: (token: string) =>
    apiClient.post('/auth/verify-email', { token }),
}

// User API
export const userApi = {
  getProfile: () => apiClient.get<any>('/user/profile'),
  
  updateProfile: (data: any) =>
    apiClient.put<any>('/user/profile', data),
  
  changePassword: (data: { currentPassword: string; newPassword: string }) =>
    apiClient.post('/user/change-password', data),
  
  deleteAccount: () => apiClient.delete('/user/account'),
  
  uploadAvatar: (file: File) => {
    const formData = new FormData()
    formData.append('avatar', file)
    return apiClient.post('/user/avatar', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
  },
}

// Generic CRUD operations
export const createCrudApi = <T = any>(endpoint: string) => ({
  getAll: (params?: any) =>
    apiClient.get<PaginatedResponse<T>>(`/${endpoint}`, { params }),
  
  getById: (id: string | number) =>
    apiClient.get<T>(`/${endpoint}/${id}`),
  
  create: (data: Partial<T>) =>
    apiClient.post<T>(`/${endpoint}`, data),
  
  update: (id: string | number, data: Partial<T>) =>
    apiClient.put<T>(`/${endpoint}/${id}`, data),
  
  delete: (id: string | number) =>
    apiClient.delete(`/${endpoint}/${id}`),
})

// File upload helper
export const uploadFile = async (file: File, endpoint: string = '/upload') => {
  const formData = new FormData()
  formData.append('file', file)
  
  return apiClient.post<{ url: string; filename: string }>(endpoint, formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    onUploadProgress: (progressEvent) => {
      const percentCompleted = Math.round(
        (progressEvent.loaded * 100) / (progressEvent.total || 1)
      )
      console.log(`Upload Progress: ${percentCompleted}%`)
    },
  })
}

// Error handler helper
export const handleApiError = (error: AxiosError): string => {
  if (error.response?.data) {
    const errorData = error.response.data as any
    return errorData.message || errorData.error || 'An error occurred'
  }
  
  if (error.request) {
    return 'Network error. Please check your connection.'
  }
  
  return error.message || 'An unexpected error occurred'
}

export default api