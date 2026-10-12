import 'server-only'

// Type Imports
import type { Locale } from '@configs/i18n'

const dictionaries = {
  en: () => import('@/data/dictionaries/en.json').then(module => module.default),
  // PLACEHOLDER - see src/data/dictionaries/ur.json's _translation_status field.
  ur: () => import('@/data/dictionaries/ur.json').then(module => module.default)
}

export const getDictionary = async (locale: Locale) => dictionaries[locale]()
