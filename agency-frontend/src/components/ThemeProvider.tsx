import React from 'react'
import { useThemeStore } from '../store/themeStore'

interface ThemeProviderProps {
  children: React.ReactNode
}

export const ThemeProvider: React.FC<ThemeProviderProps> = ({ children }) => {
  const { initializeTheme } = useThemeStore()

  React.useEffect(() => {
    initializeTheme()
  }, [initializeTheme])

  return <>{children}</>
}