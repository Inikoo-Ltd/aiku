/**
 *  author: Vika Aqordi
 *  created on: 18-10-2024
 *  github: https://github.com/aqordeon
 *  copyright: 2024
*/

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 00:33:39 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */
// import { Navigation, grpNavigation, orgNavigation } from "@/types/Navigation"
// import { useColorTheme } from '@/Composables/useStockList'


import { defineStore } from "pinia"
import { Image } from "@/types/Image"
import { Colors } from "@/types/Color"
import { OrganisationsData, Group, OrganisationState, StackedComponent} from '@/types/LayoutRules'
import { ref } from "vue";
import { useColorTheme } from "@/Composables/useStockList"

interface User {
    id: number
    avatar_thumbnail: Image
    email: string
    username: string
}

interface App {
    name: string
    color: unknown | Colors
    theme: string[]
    url: string | null
    environment: string | null
}

const getLocalStorage = () => {
	let storageIris = {}
	if (typeof window !== "undefined") {
		storageIris = JSON.parse(localStorage.getItem("iris") || "{}") // Get layout from localStorage
		return storageIris
	}
    
    return storageIris
}

export const useIrisLayoutStore = defineStore('irisLayout', () => {
    const user = ref<User | null>(null)
    const app = ref<App>({
        name: "",  // For styling navigation depend on which App
        color: null,  // Styling layout color
        theme: useColorTheme[3],  // For styling app color (same as Retina)
        url: null, // For url on logo top left
        environment: null // 'local' | 'staging'
    })

    const iris_varnish = { 
        isFetching : false
    }
    const iris = {
        is_logged_in : getLocalStorage().is_logged_in || false
    }
    const iris_variables = getLocalStorage().iris_variables || {}
    const offer_meters = getLocalStorage().offer_meters || {}
    const currentRoute = ref<string | undefined>("iris.login") // Define value to avoid route null at the first load
    const currentParams = ref<{[key: string]: string}>({})
    const currentQuery = ref<{[key: string]: string}>({})

    return { user, app, currentRoute, currentParams, currentQuery, iris_varnish, iris_variables, offer_meters, iris }
})