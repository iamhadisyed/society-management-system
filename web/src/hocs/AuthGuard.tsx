'use client'

// React Imports
import { useEffect } from 'react'

// Next Imports
import { useParams, usePathname, useRouter } from 'next/navigation'

// Type Imports
import type { ChildrenType } from '@core/types'

// Context Imports
import { useAuth } from '@/contexts/AuthContext'

// Util Imports
import { getLocalizedUrl } from '@/utils/i18n'

/**
 * Static-export note (see docs/decisions.md): this used to be an async
 * Server Component checking getServerSession() per request. There's no
 * server to do that under static export, so route protection now
 * happens client-side after mount: render nothing (or a spinner) while
 * the AuthContext's initial token check is in flight, then redirect to
 * login if it turns out there's no valid session.
 */
export default function AuthGuard({ children }: ChildrenType) {
  const { isAuthenticated, isLoading } = useAuth()
  const router = useRouter()
  const pathname = usePathname()
  const { lang } = useParams()

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      const loginUrl = getLocalizedUrl(`/login?redirectTo=${pathname}`, lang as string)

      router.replace(loginUrl)
    }
  }, [isLoading, isAuthenticated, pathname, lang, router])

  if (isLoading || !isAuthenticated) {
    return null
  }

  return <>{children}</>
}
