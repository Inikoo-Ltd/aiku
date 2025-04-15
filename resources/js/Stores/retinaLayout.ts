/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 00:33:39 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */
// import { Navigation, grpNavigation, orgNavigation } from "@/types/Navigation"
import { useColorTheme } from '@/Composables/useStockList'

import { defineStore } from "pinia"
import { Image } from "@/types/Image"
// import { routeType } from "@/types/route"
import { Colors } from "@/types/Color"
import { ref } from 'vue'
import { StackedComponent } from '@/types/LayoutRules'

export const useLayoutStore = defineStore("retinaLayout", () => {
    const app = ref({
        name: "",  // For styling navigation depend on which App
        color: null as unknown | Colors,  // Styling layout color
        theme: useColorTheme[3] as string[],  // For styling app color
        url: '#', // Homepage links
        environment: null as string | null // 'local' | 'staging'
    })
    const currentModule = ref("")
    const currentRoute = ref<string | undefined>("retina.dashboard.show") // Define value to avoid route null at first load
    const currentParams = ref<{[key: string]: string}>({})
    const currentPlatform = ref({})

    // group: null as Group | null,
    const leftSidebar = ref({
        show: true,
    })
    const navigation = ref({
        // grp: {} as grpNavigation,
        // org: {} as { [key: string]: orgNavigation } | { [key: string]: Navigation } | Navigation
    })
    const rightSidebar = ref({
        activeUsers: {
            users: [],
            count: 0,
            show: false
        },
        language: {
            show: false
        }
    })

    const root_active = ref<string | null>(null)
    const stackedComponents = ref<StackedComponent[]>([])

    const user = ref<{ id: number, avatar_thumbnail: Image, email: string, username: string } | {}>({})


    return { root_active, stackedComponents, app, currentModule, currentRoute, currentParams, leftSidebar, navigation, currentPlatform, rightSidebar, user }
});
