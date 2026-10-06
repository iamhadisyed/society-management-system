// Third-party Imports
import { configureStore } from '@reduxjs/toolkit'

// The chat/calendar/kanban/email demo slices were removed along with
// their demo apps (see docs/decisions.md). Kept the store/Provider
// scaffolding in place (ReduxProvider still wraps the app in
// Providers.tsx) in case a real feature needs client-side shared state
// later - add its slice to this reducer map when that happens.
export const store = configureStore({
  reducer: {},
  middleware: getDefaultMiddleware => getDefaultMiddleware({ serializableCheck: false })
})

export type RootState = ReturnType<typeof store.getState>
export type AppDispatch = typeof store.dispatch
