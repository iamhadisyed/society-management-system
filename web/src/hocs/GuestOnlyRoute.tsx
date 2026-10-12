'use client'

// React Imports
import { useEffect } from 'react'

// Next Imports
import { useParams, useRouter } from 'next/navigation'

// Type Imports
import type { ChildrenType } from '@core/types'

// Config Imports
import themeConfig from '@configs/themeConfig'

// Context Imports
import { useAuth } from '@/contexts/AuthContext'

// Util Imports
import { getLocalizedUrl } from '@/utils/i18n'

/**
 * Static-export note (see docs/decisions.md): same change as AuthGuard -
 * the getServerSession() check moved client-side. Guest-only pages
 * (login/register/forgot-password) briefly render while the initial
 * auth check runs, then redirect away if a valid session is found.
 */
const GuestOnlyRoute = ({ children }: ChildrenType) => {
  const { isAuthenticated, isLoading } = useAuth()
  const router = useRouter()
  const { lang } = useParams()

  useEffect(() => {
    if (!isLoading && isAuthenticated) {
      router.replace(getLocalizedUrl(themeConfig.homePageUrl, lang as string))
    }
  }, [isLoading, isAuthenticated, lang, router])

  if (isLoading || isAuthenticated) {
    return null
  }

  return <>{children}</>
}

export default GuestOnlyRoute
