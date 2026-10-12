/**
 * Static-export note (see docs/decisions.md): these used to read
 * cookies() server-side to pre-render the user's saved theme
 * preference and avoid a flash on first paint. There is no per-request
 * server under static export, so that's no longer possible - these now
 * just return the configured defaults at build time. The *real* saved
 * preference is picked up client-side after mount by
 * SettingsProvider's useObjectCookie (src/@core/contexts/settingsContext.tsx)
 * and MUI's InitColorSchemeScript, exactly as before; the only change
 * is a brief flash of the default theme on first load instead of none,
 * which is an inherent tradeoff of static hosting with no server.
 *
 * Function names/signatures are kept async and unchanged so every
 * existing call site (layout.tsx, Providers.tsx, and ~25 page.tsx
 * files that do `const mode = await getMode()`) needs no changes.
 */

// Type Imports
import type { Settings } from '@core/contexts/settingsContext'
import type { SystemMode } from '@core/types'

// Config Imports
import themeConfig from '@configs/themeConfig'

export const getSettingsFromCookie = async (): Promise<Settings> => {
  return {}
}

export const getMode = async () => {
  return themeConfig.mode
}

export const getSystemMode = async (): Promise<SystemMode> => {
  const mode = await getMode()

  // 'system' can't be resolved without reading the client's OS preference,
  // which isn't available at build time - fall back to light, same as the
  // client-side InitColorSchemeScript does before it corrects itself.
  return (mode === 'system' ? 'light' : mode) || 'light'
}

export const getServerMode = async () => {
  const mode = await getMode()
  const systemMode = await getSystemMode()

  return mode === 'system' ? systemMode : mode
}

export const getSkin = async () => {
  const settingsCookie = await getSettingsFromCookie()

  return settingsCookie.skin || 'default'
}
