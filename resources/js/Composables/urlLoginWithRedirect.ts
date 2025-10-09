/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 00:14:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

// SSR-safe utility to generate login URL with optional redirect back to current page
// Usage: import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
// Then call urlLoginWithRedirect() to get a string URL.

export function urlLoginWithRedirect(): string {
  // Detect the current route via Ziggy if available; avoid throwing in SSR
  const isAuthRoute = (globalThis as any)?.route?.current?.() === 'retina.login.show'
    || (globalThis as any)?.route?.current?.() === 'retina.register'

  // Build redirect only on the client where a window exists and not on auth pages
  if (typeof window !== 'undefined' && !isAuthRoute) {
    const path = window.location?.pathname ?? ''
    const search = window.location?.search ?? ''
    const ref = `${encodeURIComponent(path)}${search ? encodeURIComponent(search) : ''}`
    return `/app/login?ref=${ref}`
  }

  return '/app/login'
}
