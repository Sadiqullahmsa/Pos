import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export type Theme = 'light' | 'dark' | 'system'

interface ThemeState {
  theme: Theme
  isDarkMode: boolean
}

interface ThemeActions {
  setTheme: (theme: Theme) => void
  toggleTheme: () => void
  initializeTheme: () => void
}

export const useThemeStore = create<ThemeState & ThemeActions>()(
  persist(
    (set, get) => ({
      theme: 'system',
      isDarkMode: false,

      setTheme: (theme: Theme) => {
        set({ theme })
        applyTheme(theme)
      },

      toggleTheme: () => {
        const { theme } = get()
        const newTheme = theme === 'light' ? 'dark' : 'light'
        set({ theme: newTheme })
        applyTheme(newTheme)
      },

      initializeTheme: () => {
        const { theme } = get()
        applyTheme(theme)
      },
    }),
    {
      name: 'theme-storage',
      partialize: (state) => ({
        theme: state.theme,
      }),
    }
  )
)

function applyTheme(theme: Theme) {
  const root = document.documentElement
  
  if (theme === 'system') {
    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    root.classList.toggle('dark', systemTheme === 'dark')
    useThemeStore.setState({ isDarkMode: systemTheme === 'dark' })
  } else {
    root.classList.toggle('dark', theme === 'dark')
    useThemeStore.setState({ isDarkMode: theme === 'dark' })
  }
}

// Listen for system theme changes
if (typeof window !== 'undefined') {
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    const { theme } = useThemeStore.getState()
    if (theme === 'system') {
      applyTheme('system')
    }
  })
}