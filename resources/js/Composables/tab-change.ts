/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Mar 2023 08:26:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { router, usePage } from "@inertiajs/vue3"
import { Ref, ref, watch } from "vue"

export const useTabChange = (tabSlug: string, currentTab: Ref<string>) => {
    if (tabSlug === currentTab.value) {
        return
    }

    const currentUrl = new URL(window.location.href)
    const isTabChanged = currentUrl.searchParams.get('tab') !== tabSlug

    // Section: remove other parameter if ?tab= has changed
    let targetUrl: string
    if (isTabChanged) {
        targetUrl = `${currentUrl.pathname}?tab=${encodeURIComponent(tabSlug)}`
    } else {
        currentUrl.searchParams.set('tab', tabSlug)
        targetUrl = `${currentUrl.pathname}?${currentUrl.searchParams.toString()}`
    }

    router.get(
        targetUrl,
        {},
        {
            only: [tabSlug],  // Only reload the props with dynamic name tabSlug (i.e props.showcase, props.menu)
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                currentTab.value = tabSlug;
            },
            onError: (e) => {
                // console.log('eeerr', e)
            }
        }
    )

    // Advantage: reload 1 props (faster, example load: 826 B)
    // router.reload(
    //     {
    //         data: { tab: tabSlug },  // Sent to url parameter (?tab=showcase, ?tab=menu)
    //         only: [tabSlug],  // Only reload the props with dynamic name tabSlug (i.e props.showcase, props.menu)
    //         onSuccess: () => {
    //             currentTab.value = tabSlug;
    //         },
    //         onError: (e) => {
    //             // console.log('eeerr', e)
    //         }
    //     }
    // )

    // Advantage: clear query on change tab, backward-forward browser will open correct tab
    // Disadvantage: reload 2 props (a little bit slower, example load: 993 B)
    // router.visit(route(route().v().name, {...route().routeParams, tab: tabSlug}),
    //     {
    //         only: [tabSlug, 'tabs'],  // Only reload the props with dynamic name tabSlug (i.e props.showcase, props.menu)
    //         onError: (e) => {
    //             // console.log('eeerr', e)
    //         }
    //     }
    // )
}

/**
 * useTabChange does a partial visit, so props.tabs.current keeps the value of the
 * first load: the tab has to be read from the page url or going back in the browser
 * restores the stale one.
 */
export const useCurrentTab = (fallback: string): Ref<string> => {
    const tabOf = (url: string) => new URLSearchParams(url.split('?')[1] ?? '').get('tab')

    const page = usePage()
    const currentTab = ref(tabOf(page.url) ?? fallback)

    watch(() => usePage().url, (url) => {
        currentTab.value = tabOf(url) ?? fallback
    })

    return currentTab
}
