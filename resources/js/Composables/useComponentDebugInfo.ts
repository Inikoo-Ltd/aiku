/*
 * Exposes window.component in the browser console (local only, gated by the server-side phpComponent prop) with the PHP action
 * and the Vue page of the currently rendered Inertia page.
 */

import { usePage } from '@inertiajs/vue3'

declare global {
    interface Window {
        component: {
            php: string
            vue: string
        }
    }
}

export const setComponentDebugInfo = () => {
    if (typeof window === 'undefined') {
        return
    }

    const page = usePage()
    const phpComponent = page.props?.phpComponent as string | null | undefined

    if (!phpComponent) {
        delete (window as Partial<Window>).component
        return
    }

    window.component = {
        php: phpComponent,
        vue: page.component ?? ''
    }
}
