// Next Imports
import type { Metadata } from 'next'

// Component Imports
import VerifyEmail from '@views/VerifyEmail'

// Server Action Imports
import { getServerMode } from '@core/utils/serverHelpers'

export const metadata: Metadata = {
  title: 'Verify Email',
  description: 'Verify your email address'
}

const VerifyEmailPage = async () => {
  // Vars
  const mode = await getServerMode()

  return <VerifyEmail mode={mode} />
}

export default VerifyEmailPage
