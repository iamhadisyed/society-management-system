'use client'

// React Imports
import { useEffect } from 'react'

// Next Imports
import { useRouter } from 'next/navigation'

// Config Imports
import { i18n } from '@configs/i18n'
import themeConfig from '@configs/themeConfig'

// Util Imports
import { getLocalizedUrl } from '@/utils/i18n'

/**
 * Replaces the `redirects()` rules in next.config.ts that used to send
 * `/` and `/{lang}` -> `/{lang}/dashboards/crm` - unsupported under
 * `output: 'export'`, so it's now a client-side redirect instead (see
 * docs/decisions.md). AuthGuard/GuestOnlyRoute take over from here to
 * send the visitor to login or the real home page based on auth state.
 */
export default function RootPage() {
  const router = useRouter()

  useEffect(() => {
    router.replace(getLocalizedUrl(themeConfig.homePageUrl, i18n.defaultLocale))
  }, [router])

  return null
}
