import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api } from '../api/client'
import type { PermissionCode, User } from '../types'

type AuthContextValue = {
  user: User | null
  loading: boolean
  login: (user: string, password: string) => Promise<void>
  logout: () => Promise<void>
  can: (permission: PermissionCode | string) => boolean
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('docssalud_token')
    if (!token) {
      setLoading(false)
      return
    }

    api
      .get<{ user: User }>('/auth/me')
      .then((response) => setUser(response.data.user))
      .catch(() => localStorage.removeItem('docssalud_token'))
      .finally(() => setLoading(false))
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      login: async (user, password) => {
        const response = await api.post<{ token: string; user: User }>('/auth/login', {
          user,
          password,
          device_name: 'DocsSalud Web',
        })
        localStorage.setItem('docssalud_token', response.data.token)
        setUser(response.data.user)
      },
      logout: async () => {
        try {
          await api.post('/auth/logout')
        } finally {
          localStorage.removeItem('docssalud_token')
          setUser(null)
        }
      },
      can: (permission) => user?.role?.code === 'ADMIN' || user?.permissions.includes(permission) || false,
    }),
    [loading, user],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth debe usarse dentro de AuthProvider')
  }
  return context
}
