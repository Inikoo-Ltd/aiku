/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 00:33:39 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */
import { useColorTheme } from '@/Composables/useStockList'
import { defineStore } from "pinia"
import { Image } from "@/types/Image"
import { Colors } from "@/types/Color"
import { ref } from 'vue'
import { StackedComponent } from '@/types/LayoutRules'

const getLocalStorage = () => {
	let storageIris = {}
	if (typeof window !== "undefined") {
		storageIris = JSON.parse(localStorage.getItem("iris") || "{}") // Get layout from localStorage
		return storageIris
	}
    
    return storageIris
}

export const useLayoutStore = defineStore("retinaLayout", () => {
    const app = ref({
        name: "",  // For styling navigation depend on which App
        color: null as null | Colors,  // Styling layout color
        theme: useColorTheme[3],  // For styling app color
        url: '#', // Homepage links
        environment: null as string | null // 'local' | 'staging'
    })
    const currentModule = ref("")
    const currentRoute = ref<string | undefined>("retina.dashboard.show") // Define value to avoid route null at the first load
    const currentParams = ref<{[key: string]: string}>({})
    const currentQuery = ref<{[key: string]: string}>({})
    const currentPlatform = ref({})

    const leftSidebar = ref({
        show: true,
    })
    const navigation = ref({})
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

    const user = ref<{ id: number, avatar_thumbnail: Image, email: string, username: string } | null>(null)
    const iris_varnish = { 
        isFetching : false
    }
    const iris = {
        is_logged_in : getLocalStorage().is_logged_in || false
    }
    const iris_variables = getLocalStorage().iris_variables || {}


    return { currentQuery, root_active, stackedComponents, app, currentModule, currentRoute, currentParams, leftSidebar, navigation, currentPlatform, rightSidebar, user, iris_varnish, iris_variables, iris }
});
