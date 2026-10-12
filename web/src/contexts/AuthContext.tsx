'use client'

// React Imports
import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'

// Util Imports
import { apiClient, getStoredToken, setStoredToken } from '@/libs/apiClient'

/**
 * Backend account types - see backend/app/Http/Controllers/Api/V1/Auth/AuthController.php.
 * Residents and society staff share the `User` model (`user_type`);
 * platform admins are a separate table/guard.
 */
export type AuthUser = {
  id: number
  name: string
  email: string | null
  user_type?: 'society_staff' | 'resident'
  society_id?: number | null
  [key: string]: unknown
}

export type LoginKind = 'staff' | 'resident' | 'platform'

type LoginPayload =
  | { kind: 'staff'; email: string; password: string }
  | { kind: 'resident'; society_code: string; reference_number: string; password: string }
  | { kind: 'platform'; email: string; password: string }

type AuthContextValue = {
  user: AuthUser | null
  token: string | null
  isAuthenticated: boolean
  /** True until the initial token-from-localStorage check (and /me refresh) completes. */
  isLoading: boolean
  permissions: string[]
  login: (payload: LoginPayload) => Promise<AuthUser>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

const loginEndpoint: Record<LoginKind, string> = {
  staff: '/auth/staff/login',
  resident: '/auth/resident/login',
  platform: '/auth/platform/login'
}

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [token, setToken] = useState<string | null>(null)
  const [permissions, setPermissions] = useState<string[]>([])
  const [isLoading, setIsLoading] = useState(true)

  // On mount: pick up a token saved from a previous session and confirm it's
  // still valid by re-fetching /me. There is no server here to pre-render
  // this - every page starts logged-out until this effect resolves.
  useEffect(() => {
    const storedToken = getStoredToken()

    if (!storedToken) {
      setIsLoading(false)

      return
    }

    setToken(storedToken)

    apiClient
      .get<{ user: AuthUser; permissions: string[] }>('/me', { token: storedToken })
      .then(data => {
        setUser(data.user)
        setPermissions(data.permissions ?? [])
      })
      .catch(() => {
        // Token expired/invalid - clear it rather than leaving a stale session.
        setStoredToken(null)
        setToken(null)
        setUser(null)
      })
      .finally(() => setIsLoading(false))
  }, [])

  const login = async (payload: LoginPayload): Promise<AuthUser> => {
    const { kind, ...body } = payload

    const data = await apiClient.post<{ token: string; user: AuthUser }>(loginEndpoint[kind], body, { token: null })

    setStoredToken(data.token)
    setToken(data.token)
    setUser(data.user)

    // /me also returns the resolved permission list; fetch it once so
    // route/menu guards have it immediately after login.
    try {
      const me = await apiClient.get<{ permissions: string[] }>('/me', { token: data.token })

      setPermissions(me.permissions ?? [])
    } catch {
      setPermissions([])
    }

    return data.user
  }

  const logout = async () => {
    try {
      await apiClient.post('/auth/logout', undefined, { token })
    } catch {
      // Best-effort - clear local state regardless of whether the API call succeeded.
    } finally {
      setStoredToken(null)
      setToken(null)
      setUser(null)
      setPermissions([])
    }
  }

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      isAuthenticated: Boolean(token && user),
      isLoading,
      permissions,
      login,
      logout
    }),
    [user, token, isLoading, permissions]
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export const useAuth = (): AuthContextValue => {
  const ctx = useContext(AuthContext)

  if (!ctx) {
    throw new Error('useAuth must be used within an AuthProvider')
  }

  return ctx
}
