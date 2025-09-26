/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Mar 2023 08:26:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { router } from "@inertiajs/vue3"
import { Ref } from 'vue'

export const useTabChange = (tabSlug: string, currentTab: Ref<string>) => {
    if (tabSlug === currentTab.value) {
        return
    }

    // Advantage: reload 1 props (faster, example load: 826 B)
    // Disadvantage: backward-forward browser isn't open the tab, query is stay on change Tab (will throw error)
    router.reload(
        {
            data: { tab: tabSlug },  // Sent to url parameter (?tab=showcase, ?tab=menu)
            only: [tabSlug],  // Only reload the props with dynamic name tabSlug (i.e props.showcase, props.menu)
            onSuccess: () => {
                currentTab.value = tabSlug;
            },
            onError: (e) => {
                // console.log('eeerr', e)
            }
        }
    )

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
