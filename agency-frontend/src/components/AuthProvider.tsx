import React from 'react'
import { useAuthStore } from '../store/authStore'

interface AuthProviderProps {
  children: React.ReactNode
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const { setLoading } = useAuthStore()

  React.useEffect(() => {
    // Initialize auth state from localStorage
    const token = localStorage.getItem('auth_token')
    if (token) {
      // You could validate the token here
      setLoading(false)
    } else {
      setLoading(false)
    }
  }, [setLoading])

  return <>{children}</>
}