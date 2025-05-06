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


export const useLayoutStore = defineStore("pupilLayout", () => {
    const app = ref({
        name: "",  // For styling navigation depend on which App
        color: null as null | Colors,  // Styling layout color
        theme: useColorTheme[3],  // For styling app color
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
