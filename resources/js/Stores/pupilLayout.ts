/**
 * Author: Vika Aqordi
 * Created on: 18-03-2025-09h-38m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { useColorTheme } from '@/Composables/useStockList'

import { defineStore } from "pinia"
import { Image } from "@/types/Image"
// import { routeType } from "@/types/route"
import { Colors } from "@/types/Color"
import { ref } from 'vue'
import { StackedComponent } from '@/types/LayoutRules'

// interface LiveUsers {
//     enabled?: boolean
// }

export const useLayoutStore = defineStore("pupilLayout", () => {
    const app = ref({
        name: "",  // For styling navigation depend on which App
        color: null as unknown | Colors,  // Styling layout color
        theme: ['#14b8a6', '#f3f4f6', '#0d9488', '#fde047', '#14b8a6', '#f3f4f6', '#fde047', '#374151'] as string[],  // For styling app color
        url: '#', // Homepage links
        environment: null as string | null // 'local' | 'staging'
    })
    const currentModule = ref("")
    const currentRoute = ref<string | undefined>("pupil.home") // Define value to avoid route null at first load
    const currentParams = ref<{[key: string]: string}>({})
    const currentPlatform = ref({})

    // group: null as Group | null,
    const leftSidebar = ref({
        show: true,
    })
    // const liveUsers = ref<LiveUsers | null>({
    //     enabled: false as boolean
    // })
    // const navigation = ref({
    //     // grp: {} as grpNavigation,
    //     // org: {} as { [key: string]: orgNavigation } | { [key: string]: Navigation } | Navigation
    // })
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
    

    return { root_active, stackedComponents, app, currentModule, currentRoute, currentParams, leftSidebar, currentPlatform, rightSidebar, user }
});
