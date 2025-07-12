# LPG Gas Agency Management System

A comprehensive, modern web application for managing LPG Gas Agency operations with Laravel backend and React frontend.

## 🚀 Project Overview

This is a full-stack LPG Gas Agency Management System that streamlines operations for gas agencies, from customer management to delivery tracking. The system is built with modern technologies and follows best practices for scalability and maintainability.

### 🏗️ Architecture

```
├── agency-backend/          # Laravel API Backend
│   ├── app/
│   │   ├── Models/          # Eloquent Models
│   │   ├── Http/Controllers/API/  # API Controllers
│   │   └── ...
│   ├── database/
│   │   ├── migrations/      # Database Migrations
│   │   └── ...
│   └── routes/
│       ├── api.php          # API Routes
│       └── ...
├── agency-frontend/         # React Frontend
│   ├── src/
│   │   ├── components/      # React Components
│   │   ├── pages/           # Page Components
│   │   ├── store/           # Zustand State Management
│   │   ├── lib/             # Utilities & API Client
│   │   └── types/           # TypeScript Types
│   └── ...
└── README.md
```

## 🛠️ Tech Stack

### Backend (Laravel)
- **Framework**: Laravel 12
- **Database**: MySQL/PostgreSQL
- **Authentication**: Laravel Sanctum
- **File Storage**: Local/S3
- **Additional Packages**:
  - Spatie Laravel Permission (Role-based access)
  - Spatie Laravel Activity Log (Audit trails)
  - Intervention Image (Image processing)

### Frontend (React)
- **Framework**: React 18 with TypeScript
- **Styling**: Tailwind CSS
- **State Management**: Zustand
- **Routing**: React Router v6
- **API Client**: Axios
- **Forms**: React Hook Form + Zod validation
- **UI Components**: Radix UI
- **Notifications**: Built-in toast system

## 🎯 Features

### Core Business Modules

#### 1. Customer Management
- **Registration & KYC**: Complete customer onboarding with document verification
- **Profile Management**: Personal details, address, contact information
- **Credit Management**: Credit limits, outstanding balance tracking
- **Document Storage**: Secure document upload and management
- **Search & Filters**: Advanced filtering by location, status, KYC status

#### 2. Connection Management
- **Connection Types**: Residential, Commercial, Industrial
- **Status Tracking**: Active, Inactive, Suspended, Terminated
- **Quota Management**: Monthly quota tracking and reset
- **Subsidy Management**: Subsidy card integration and tracking
- **Security Deposit**: Deposit management and tracking

#### 3. Order Management
- **Order Types**: Regular, Emergency, Exchange, New Connection
- **Order Processing**: Confirmation, processing, dispatch workflow
- **Pricing**: Dynamic pricing with discount and subsidy calculations
- **Payment Integration**: Multiple payment methods support
- **Order Tracking**: Real-time order status updates

#### 4. Inventory & Cylinder Management
- **Cylinder Tracking**: Individual cylinder tracking with serial numbers
- **Status Management**: Available, Dispatched, Delivered, Maintenance
- **Maintenance Logs**: Complete maintenance history
- **Capacity Management**: Different cylinder types and capacities
- **Location Tracking**: Current location and movement history

#### 5. Delivery Management
- **Route Planning**: Optimized delivery route calculation
- **Driver Assignment**: Automatic driver and vehicle assignment
- **GPS Tracking**: Real-time delivery tracking
- **Delivery Confirmation**: Digital signature and photo capture
- **Failed Delivery Handling**: Reason tracking and retry mechanism

#### 6. Driver & Vehicle Management
- **Driver Profiles**: Complete driver information and documents
- **License Management**: License validation and expiry tracking
- **Availability Tracking**: Real-time driver availability
- **Performance Metrics**: Delivery success rates and ratings
- **Vehicle Management**: Vehicle registration, maintenance, and tracking

#### 7. Financial Management
- **Payment Processing**: Multiple payment gateways integration
- **Billing**: Automated billing and invoice generation
- **Refund Management**: Automated refund processing
- **Revenue Tracking**: Daily, monthly, yearly revenue reports
- **Outstanding Management**: Credit and recovery tracking

#### 8. Reports & Analytics
- **Sales Reports**: Detailed sales analysis and trends
- **Inventory Reports**: Stock levels and movement reports
- **Customer Reports**: Customer analysis and segmentation
- **Delivery Reports**: Delivery performance and efficiency
- **Financial Reports**: Revenue, profit, and expense analysis

#### 9. Complaint Management
- **Complaint Registration**: Multiple channels (phone, email, app)
- **Priority Management**: Low, Medium, High, Urgent
- **Assignment**: Automatic assignment to relevant teams
- **Resolution Tracking**: Complete resolution workflow
- **Customer Feedback**: Satisfaction ratings and feedback

#### 10. System Administration
- **User Management**: Role-based access control
- **Settings Management**: System-wide configuration
- **Security**: Activity logging and audit trails
- **Backup & Recovery**: Automated backup systems
- **Notifications**: Email, SMS, and push notifications

## 📦 Installation & Setup

### Prerequisites
- PHP 8.1 or higher
- Composer
- Node.js 18 or higher
- MySQL 8.0 or higher
- Redis (optional, for caching)

### Backend Setup (Laravel)

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd lpg-gas-agency
   ```

2. **Install PHP dependencies**
   ```bash
   cd agency-backend
   composer install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database in `.env`**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=lpg_agency
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

5. **Run database migrations**
   ```bash
   php artisan migrate
   ```

6. **Start the development server**
   ```bash
   php artisan serve
   ```

### Frontend Setup (React)

1. **Install Node.js dependencies**
   ```bash
   cd agency-frontend
   npm install
   ```

2. **Environment configuration**
   ```bash
   cp .env.example .env
   ```

3. **Configure API URL in `.env`**
   ```env
   REACT_APP_API_URL=http://localhost:8000/api
   ```

4. **Start the development server**
   ```bash
   npm start
   ```

## 🔧 Configuration

### Environment Variables

#### Backend (.env)
```env
APP_NAME="LPG Gas Agency"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lpg_agency
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

# Optional: File Storage
FILESYSTEM_DISK=local
# For S3: FILESYSTEM_DISK=s3
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=
# AWS_BUCKET=
```

#### Frontend (.env)
```env
REACT_APP_API_URL=http://localhost:8000/api
REACT_APP_NAME="LPG Gas Agency"
REACT_APP_VERSION=1.0.0
```

## 🔐 Authentication & Security

### API Authentication
- **Laravel Sanctum**: Token-based authentication
- **Role-based Access Control**: Using Spatie Laravel Permission
- **Rate Limiting**: API rate limiting to prevent abuse
- **CORS**: Cross-Origin Resource Sharing configuration

### Security Features
- **Input Validation**: Comprehensive validation on all inputs
- **SQL Injection Prevention**: Using Eloquent ORM
- **XSS Protection**: Built-in Laravel protection
- **CSRF Protection**: Cross-Site Request Forgery protection
- **Activity Logging**: Complete audit trail

## 📱 API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication Headers
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Sample API Endpoints

#### Authentication
```http
POST /api/auth/login
POST /api/auth/register
POST /api/auth/logout
GET  /api/user
```

#### Customers
```http
GET    /api/customers
POST   /api/customers
GET    /api/customers/{id}
PUT    /api/customers/{id}
DELETE /api/customers/{id}
PATCH  /api/customers/{id}/kyc-status
POST   /api/customers/{id}/upload-documents
```

#### Orders
```http
GET    /api/orders
POST   /api/orders
GET    /api/orders/{id}
PUT    /api/orders/{id}
PATCH  /api/orders/{id}/status
POST   /api/orders/{id}/confirm
POST   /api/orders/{id}/cancel
GET    /api/orders/{id}/track
```

## 🧪 Testing

### Backend Testing
```bash
cd agency-backend
php artisan test
```

### Frontend Testing
```bash
cd agency-frontend
npm test
```

## 🚀 Deployment

### Production Setup

#### Backend Deployment
1. **Server Requirements**
   - PHP 8.1+
   - MySQL 8.0+
   - Nginx/Apache
   - SSL Certificate

2. **Environment Configuration**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Database Migration**
   ```bash
   php artisan migrate --force
   ```

#### Frontend Deployment
1. **Build for production**
   ```bash
   npm run build
   ```

2. **Deploy to web server**
   ```bash
   # Copy build folder to web server
   cp -r build/* /var/www/html/
   ```

### Docker Support
```dockerfile
# Backend Dockerfile
FROM php:8.1-fpm
# ... configuration

# Frontend Dockerfile
FROM node:18-alpine
# ... configuration
```

## 📊 Performance Optimization

### Backend Optimizations
- **Database Indexing**: Proper indexing on frequently queried columns
- **Eager Loading**: N+1 query problem prevention
- **Caching**: Redis caching for frequently accessed data
- **Queue Jobs**: Background job processing for heavy tasks

### Frontend Optimizations
- **Code Splitting**: Lazy loading of components
- **Image Optimization**: Optimized image loading
- **Bundle Optimization**: Webpack optimizations
- **Service Worker**: Offline functionality

## 🔍 Monitoring & Logging

### Backend Monitoring
- **Laravel Telescope**: Development debugging
- **Log Management**: Structured logging
- **Error Tracking**: Error monitoring and reporting
- **Performance Monitoring**: Response time tracking

### Frontend Monitoring
- **Error Boundary**: React error handling
- **Performance Metrics**: Core Web Vitals tracking
- **User Analytics**: User behavior tracking

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📞 Support

For support and questions:
- **Email**: support@lpgagency.com
- **Documentation**: [Documentation Link]
- **Issue Tracker**: [GitHub Issues]

## 🎉 Acknowledgments

- Laravel Community
- React Community
- All contributors and testers

---

**Built with ❤️ for LPG Gas Agency Management**