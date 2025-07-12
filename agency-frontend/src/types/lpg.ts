// Core LPG Business Types and Interfaces

export interface Customer {
  id: string
  customerId: string // Unique customer ID (e.g., LPG001234)
  name: string
  email: string
  phone: string
  address: {
    street: string
    area: string
    city: string
    state: string
    pincode: string
    landmark?: string
  }
  documents: {
    aadhar: string
    pan?: string
    rationCard?: string
    photo: string
  }
  connections: Connection[]
  isActive: boolean
  kycStatus: 'pending' | 'verified' | 'rejected'
  registrationDate: string
  lastOrderDate?: string
  totalOrders: number
  outstandingAmount: number
  loyaltyPoints: number
}

export interface Connection {
  id: string
  connectionId: string // Unique connection ID
  customerId: string
  type: 'domestic' | 'commercial'
  cylinderType: 'regular' | 'jumbo'
  status: 'active' | 'inactive' | 'blocked' | 'surrendered'
  subsidyEligible: boolean
  depositAmount: number
  paidDeposit: boolean
  createdDate: string
  lastRefillDate?: string
  nextDueDate?: string
}

export interface Cylinder {
  id: string
  cylinderId: string // Physical cylinder number
  type: 'regular' | 'jumbo' // 14.2kg or 19kg
  status: 'filled' | 'empty' | 'in_transit' | 'damaged' | 'maintenance'
  location: 'warehouse' | 'customer' | 'delivery' | 'supplier'
  customerId?: string // If with customer
  lastFillDate?: string
  expiryDate: string
  qrCode: string
  weight: number
  safetyTestDate: string
  supplierId: string
}

export interface Order {
  id: string
  orderId: string // Unique order number
  customerId: string
  connectionId: string
  cylinderType: 'regular' | 'jumbo'
  quantity: number
  orderDate: string
  deliveryDate?: string
  status: 'pending' | 'confirmed' | 'out_for_delivery' | 'delivered' | 'cancelled'
  priority: 'normal' | 'urgent' | 'emergency'
  amount: number
  subsidyAmount: number
  finalAmount: number
  paymentStatus: 'pending' | 'paid' | 'partial' | 'refunded'
  paymentMethod?: 'cash' | 'online' | 'card' | 'upi'
  deliveryAddress: string
  driverId?: string
  notes?: string
  estimatedDeliveryTime?: string
  actualDeliveryTime?: string
}

export interface Delivery {
  id: string
  deliveryId: string
  orderId: string
  driverId: string
  vehicleId: string
  route: string[]
  startTime?: string
  endTime?: string
  status: 'assigned' | 'in_progress' | 'completed' | 'cancelled'
  currentLocation?: {
    lat: number
    lng: number
    timestamp: string
  }
  proofOfDelivery?: {
    signature: string
    photo: string
    timestamp: string
  }
  distanceCovered?: number
  fuelUsed?: number
}

export interface Driver {
  id: string
  employeeId: string
  name: string
  phone: string
  email?: string
  licenseNumber: string
  licenseExpiry: string
  address: string
  joiningDate: string
  status: 'active' | 'inactive' | 'on_leave'
  rating: number
  totalDeliveries: number
  currentVehicle?: string
  emergencyContact: {
    name: string
    phone: string
    relation: string
  }
}

export interface Vehicle {
  id: string
  vehicleNumber: string
  type: 'two_wheeler' | 'three_wheeler' | 'tempo' | 'truck'
  capacity: number // Number of cylinders
  status: 'available' | 'in_use' | 'maintenance' | 'out_of_service'
  fuelType: 'petrol' | 'diesel' | 'cng' | 'electric'
  insurance: {
    policyNumber: string
    expiryDate: string
    provider: string
  }
  fitness: {
    certificateNumber: string
    expiryDate: string
  }
  currentDriverId?: string
  lastMaintenanceDate: string
  nextMaintenanceDate: string
}

export interface Payment {
  id: string
  paymentId: string
  orderId: string
  customerId: string
  amount: number
  method: 'cash' | 'online' | 'card' | 'upi' | 'net_banking'
  status: 'pending' | 'success' | 'failed' | 'refunded'
  transactionId?: string
  gatewayResponse?: any
  timestamp: string
  collectedBy?: string // Employee ID for cash payments
}

export interface Inventory {
  id: string
  cylinderId: string
  type: 'regular' | 'jumbo'
  status: 'available' | 'booked' | 'out_of_stock'
  location: string
  lastUpdated: string
  reorderLevel: number
  currentStock: number
  supplierPrice: number
  sellingPrice: number
  subsidyAmount: number
}

export interface Supplier {
  id: string
  name: string
  code: string
  contactPerson: string
  phone: string
  email: string
  address: string
  priceStructure: {
    regularCylinder: number
    jumboCylinder: number
  }
  paymentTerms: string
  status: 'active' | 'inactive'
  lastSupplyDate?: string
  outstandingAmount: number
}

export interface PriceStructure {
  id: string
  cylinderType: 'regular' | 'jumbo'
  basePrice: number
  subsidyAmount: number
  sellingPrice: number
  commercialPrice: number
  effectiveDate: string
  expiryDate?: string
  isActive: boolean
}

export interface Complaint {
  id: string
  complaintId: string
  customerId: string
  orderId?: string
  type: 'delivery_delay' | 'quality_issue' | 'billing_issue' | 'staff_behavior' | 'other'
  description: string
  status: 'open' | 'in_progress' | 'resolved' | 'closed'
  priority: 'low' | 'medium' | 'high' | 'critical'
  assignedTo?: string
  createdDate: string
  resolvedDate?: string
  resolution?: string
  customerSatisfaction?: number // 1-5 rating
}

export interface Notification {
  id: string
  type: 'order_confirmation' | 'delivery_update' | 'payment_reminder' | 'cylinder_due' | 'emergency_alert'
  recipient: string // Customer ID or Employee ID
  recipientType: 'customer' | 'employee'
  title: string
  message: string
  channel: 'sms' | 'email' | 'whatsapp' | 'push'
  status: 'pending' | 'sent' | 'delivered' | 'failed'
  createdDate: string
  sentDate?: string
  orderId?: string
}

export interface Report {
  id: string
  type: 'sales' | 'inventory' | 'delivery' | 'customer' | 'financial'
  period: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'custom'
  startDate: string
  endDate: string
  data: any
  generatedBy: string
  generatedDate: string
}

export interface Employee {
  id: string
  employeeId: string
  name: string
  role: 'admin' | 'manager' | 'operator' | 'driver' | 'accountant'
  phone: string
  email: string
  address: string
  joiningDate: string
  salary: number
  status: 'active' | 'inactive' | 'terminated'
  permissions: string[]
  lastLogin?: string
}

export interface BusinessSettings {
  id: string
  companyName: string
  companyCode: string
  gstNumber: string
  address: string
  phone: string
  email: string
  website?: string
  operatingHours: {
    start: string
    end: string
    workingDays: string[]
  }
  deliveryRadius: number // in kilometers
  emergencyContact: string
  subsidyProvider: string
  paymentMethods: string[]
  notificationSettings: {
    sms: boolean
    email: boolean
    whatsapp: boolean
  }
}

// API Response Types
export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
  error?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  total: number
  page: number
  limit: number
  totalPages: number
}

// Filter Types
export interface CustomerFilter {
  search?: string
  status?: string
  kycStatus?: string
  area?: string
  registrationDateFrom?: string
  registrationDateTo?: string
}

export interface OrderFilter {
  search?: string
  status?: string
  priority?: string
  dateFrom?: string
  dateTo?: string
  customerId?: string
  driverId?: string
}

export interface InventoryFilter {
  type?: string
  status?: string
  location?: string
  lowStock?: boolean
}

// Dashboard Analytics Types
export interface DashboardStats {
  totalCustomers: number
  activeConnections: number
  todaysOrders: number
  pendingDeliveries: number
  todaysRevenue: number
  monthlyRevenue: number
  cylindersInStock: number
  lowStockAlerts: number
  pendingComplaints: number
  newCustomersThisMonth: number
}

export interface SalesAnalytics {
  dailySales: { date: string; amount: number; orders: number }[]
  monthlySales: { month: string; amount: number; orders: number }[]
  topCustomers: { customerId: string; name: string; totalAmount: number }[]
  popularAreas: { area: string; orderCount: number }[]
  paymentMethodBreakdown: { method: string; amount: number; percentage: number }[]
}