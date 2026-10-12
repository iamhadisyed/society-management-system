// React Imports
import type { SVGAttributes } from 'react'

/**
 * Brand mark: a flat, minimal rounded-square tile with a simple house
 * pictogram (housing-society theme), in the product's purple. No
 * gradients/shading - see docs/decisions.md ("Branding" entry) for the
 * design brief this was built against (wordmark + "Modern minimal/flat" +
 * "Purple/white").
 */
const Logo = (props: SVGAttributes<SVGElement>) => {
  return (
    <svg width='28' height='28' viewBox='0 0 32 32' fill='none' xmlns='http://www.w3.org/2000/svg' {...props}>
      <rect width='32' height='32' rx='8' fill='var(--mui-palette-primary-main)' />
      <path d='M16 7L25 15H23.5V24H8.5V15H7L16 7Z' fill='white' />
      <rect x='13.5' y='18.5' width='5' height='5.5' rx='1' fill='var(--mui-palette-primary-main)' />
    </svg>
  )
}

export default Logo
