/*
 * Exposes window.component in the browser console (local only) with the PHP action
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
    if (import.meta.env.VITE_APP_ENV !== 'local') {
        return
    }

    const page = usePage()

    window.component = {
        php: (page.props?.phpComponent as string) ?? '',
        vue: page.component ?? ''
    }
}
