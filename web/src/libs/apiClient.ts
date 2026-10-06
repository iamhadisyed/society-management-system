/**
 * Thin fetch wrapper for the Laravel API (see docs/decisions.md - "Route
 * handlers: deleted. All data fetching goes to the Laravel API via a
 * shared apiClient using NEXT_PUBLIC_API_BASE_URL"). Plain fetch rather
 * than axios - no extra dependency needed for this.
 *
 * Attaches the bearer token (set by AuthContext) to every request and
 * throws an ApiError with the parsed response body on non-2xx so
 * callers can read e.g. Laravel's validation `errors` object.
 */

const TOKEN_STORAGE_KEY = 'auth_token'

export class ApiError extends Error {
  status: number
  body: unknown

  constructor(status: number, body: unknown) {
    const message =
      (typeof body === 'object' && body !== null && 'message' in body && typeof (body as any).message === 'string'
        ? (body as any).message
        : null) || `Request failed with status ${status}`

    super(message)
    this.status = status
    this.body = body
  }
}

export const getStoredToken = (): string | null => {
  if (typeof window === 'undefined') return null

  return window.localStorage.getItem(TOKEN_STORAGE_KEY)
}

export const setStoredToken = (token: string | null) => {
  if (typeof window === 'undefined') return

  if (token) {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, token)
  } else {
    window.localStorage.removeItem(TOKEN_STORAGE_KEY)
  }
}

const getBaseUrl = () => {
  const baseUrl = process.env.NEXT_PUBLIC_API_BASE_URL

  if (!baseUrl) {
    throw new Error('NEXT_PUBLIC_API_BASE_URL is not set - see web/.env.example')
  }

  return baseUrl.replace(/\/+$/, '')
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  token?: string | null
}

async function request<T>(path: string, { method = 'GET', body, token }: RequestOptions = {}): Promise<T> {
  const authToken = token ?? getStoredToken()

  const res = await fetch(`${getBaseUrl()}${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(authToken ? { Authorization: `Bearer ${authToken}` } : {})
    },
    body: body !== undefined ? JSON.stringify(body) : undefined
  })

  const isJson = res.headers.get('content-type')?.includes('application/json')
  const data = isJson ? await res.json().catch(() => null) : null

  if (!res.ok) {
    throw new ApiError(res.status, data)
  }

  return data as T
}

export const apiClient = {
  get: <T>(path: string, options?: Omit<RequestOptions, 'method' | 'body'>) =>
    request<T>(path, { ...options, method: 'GET' }),
  post: <T>(path: string, body?: unknown, options?: Omit<RequestOptions, 'method' | 'body'>) =>
    request<T>(path, { ...options, method: 'POST', body }),
  put: <T>(path: string, body?: unknown, options?: Omit<RequestOptions, 'method' | 'body'>) =>
    request<T>(path, { ...options, method: 'PUT', body }),
  delete: <T>(path: string, options?: Omit<RequestOptions, 'method' | 'body'>) =>
    request<T>(path, { ...options, method: 'DELETE' })
}
