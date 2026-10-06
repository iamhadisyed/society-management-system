import type { NextConfig } from 'next'

// Static export (see docs/decisions.md): this builds to plain HTML/CSS/JS
// in `out/`, uploaded directly to shared hosting with no Node runtime.
// `redirects()` is unsupported under `output: 'export'` and has been
// removed - the root `/` -> `/en/dashboards/...` behavior it used to
// provide now needs to be a client-side redirect instead (e.g. a small
// client component in src/app/page.tsx), once the real landing page is
// decided.
const nextConfig: NextConfig = {
  output: 'export',
  basePath: process.env.BASEPATH,
  images: {
    // next/image's built-in optimizer needs a server; unused today (no
    // next/image usage anywhere) but set for when it is used.
    unoptimized: true
  }
}

export default nextConfig
