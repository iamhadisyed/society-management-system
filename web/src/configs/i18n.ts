export const i18n = {
  defaultLocale: 'en',
  locales: ['en', 'ur'],
  langDirection: {
    en: 'ltr',
    ur: 'rtl'
  }
} as const

export type Locale = (typeof i18n)['locales'][number]
