# Modern React Agency Frontend

A complete, production-ready React application built with the latest technologies and best practices. This project includes all modern features you'd expect in a professional React application.

## 🚀 Features

### Core Technologies
- **React 19** - Latest React with concurrent features
- **TypeScript** - Full type safety and IntelliSense
- **Tailwind CSS** - Utility-first CSS framework
- **Vite** - Fast build tool and dev server

### UI & Design
- **Radix UI** - Accessible, unstyled UI components
- **shadcn/ui** - Beautiful, customizable components
- **Framer Motion** - Smooth animations and transitions
- **Lucide React** - Beautiful SVG icons
- **Dark Mode** - System preference detection with manual toggle

### State Management
- **Zustand** - Lightweight state management
- **React Query** - Server state management and caching
- **Persistent Storage** - State persistence across sessions

### Routing & Navigation
- **React Router v6** - Modern routing with data loading
- **Protected Routes** - Authentication-based route protection
- **Lazy Loading** - Code splitting for better performance

### Forms & Validation
- **React Hook Form** - Performant forms with minimal re-renders
- **Zod** - TypeScript-first schema validation
- **Form Validation** - Real-time validation with error handling

### HTTP & API
- **Axios** - HTTP client with interceptors
- **Request/Response Interceptors** - Global error handling
- **API Error Handling** - Centralized error management
- **File Upload** - Progress tracking and error handling

### Authentication
- **JWT-based Auth** - Secure token-based authentication
- **Route Protection** - Automatic redirects for protected routes
- **Auth Store** - Persistent authentication state
- **Login/Register** - Complete authentication flow

### Development Tools
- **ESLint** - Code linting with modern rules
- **Prettier** - Code formatting
- **TypeScript** - Static type checking
- **React DevTools** - Component inspection
- **React Query DevTools** - Query debugging

### Performance
- **Code Splitting** - Route-based code splitting
- **Lazy Loading** - Component lazy loading
- **Memoization** - Optimized re-renders
- **Bundle Optimization** - Tree shaking and minification

### Testing
- **Jest** - Unit testing framework
- **React Testing Library** - Component testing
- **User Event** - User interaction testing

## 📦 Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd agency-frontend
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Set up environment variables**
   ```bash
   cp .env.example .env.local
   ```
   
   Update the environment variables in `.env.local`:
   ```
   REACT_APP_API_URL=http://localhost:3001/api
   ```

4. **Start the development server**
   ```bash
   npm start
   ```

## 🛠️ Available Scripts

- `npm start` - Start development server
- `npm run build` - Build for production
- `npm test` - Run tests
- `npm run lint` - Run ESLint
- `npm run lint:fix` - Fix ESLint errors
- `npm run format` - Format code with Prettier
- `npm run type-check` - Check TypeScript types

## 📁 Project Structure

```
src/
├── components/          # Reusable UI components
│   ├── ui/             # Base UI components (Button, Card, etc.)
│   ├── Layout.tsx      # Main layout component
│   ├── ThemeProvider.tsx
│   └── AuthProvider.tsx
├── pages/              # Page components
│   ├── HomePage.tsx
│   ├── LoginPage.tsx
│   ├── RegisterPage.tsx
│   ├── DashboardPage.tsx
│   ├── ProfilePage.tsx
│   └── SettingsPage.tsx
├── store/              # State management
│   ├── authStore.ts    # Authentication state
│   └── themeStore.ts   # Theme state
├── lib/                # Utility functions
│   ├── api.ts          # API client and methods
│   └── utils.ts        # Helper functions
├── hooks/              # Custom React hooks
├── types/              # TypeScript type definitions
└── App.tsx             # Main app component
```

## 🎨 UI Components

The application uses a modern component library built on top of Radix UI:

- **Button** - Various styles and states
- **Card** - Content containers with header/footer
- **Form Components** - Inputs, labels, validation
- **Navigation** - Responsive navigation bar
- **Loading States** - Spinners and skeletons
- **Toast Notifications** - Success/error messages

## 🔐 Authentication

The app includes a complete authentication system:

- **Login/Register** - User authentication forms
- **JWT Tokens** - Secure token-based auth
- **Protected Routes** - Automatic route protection
- **Auth Store** - Persistent authentication state
- **Auto-logout** - Automatic logout on token expiry

## 🎯 State Management

### Zustand Stores

1. **Auth Store** - User authentication state
2. **Theme Store** - Dark/light mode preferences

### React Query

- **Server State** - API data caching and synchronization
- **Background Updates** - Automatic data refetching
- **Error Handling** - Centralized error management
- **Loading States** - Built-in loading indicators

## 🌙 Dark Mode

The application supports three theme modes:

- **Light** - Light color scheme
- **Dark** - Dark color scheme  
- **System** - Follows system preference

Theme preference is automatically saved and restored.

## 🔧 Configuration

### Tailwind CSS

The project uses a custom Tailwind configuration with:

- **CSS Variables** - Dynamic theming support
- **Custom Colors** - Brand color palette
- **Responsive Design** - Mobile-first approach
- **Animations** - Custom keyframes and transitions

### TypeScript

Strict TypeScript configuration with:

- **Strict Mode** - Maximum type safety
- **Path Mapping** - Clean import paths
- **Type Checking** - Compile-time error detection

## 🚀 Deployment

### Build for Production

```bash
npm run build
```

This creates an optimized build in the `build` folder.

### Environment Variables

Set the following environment variables for production:

```
REACT_APP_API_URL=https://your-api-url.com/api
```

### Deployment Options

- **Vercel** - Zero-config deployment
- **Netlify** - Static site hosting
- **AWS S3** - Static website hosting
- **Docker** - Containerized deployment

## 📱 PWA Features

The application includes Progressive Web App features:

- **Service Worker** - Offline functionality
- **App Manifest** - Install as native app
- **Responsive Design** - Works on all devices
- **Performance** - Optimized loading and caching

## 🧪 Testing

Run tests with:

```bash
npm test
```

The project includes:

- **Unit Tests** - Component testing
- **Integration Tests** - User flow testing
- **Accessibility Tests** - A11y compliance
- **Performance Tests** - Core Web Vitals

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests and linting
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License.

## 🔗 Links

- [React Documentation](https://react.dev/)
- [TypeScript Documentation](https://www.typescriptlang.org/)
- [Tailwind CSS Documentation](https://tailwindcss.com/)
- [Radix UI Documentation](https://www.radix-ui.com/)
- [React Query Documentation](https://tanstack.com/query/latest)
- [Zustand Documentation](https://github.com/pmndrs/zustand)

---

Built with ❤️ using modern React technologies.
