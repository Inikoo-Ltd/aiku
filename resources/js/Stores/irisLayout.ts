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

export const useIrisLayoutStore = defineStore('irisLayout', () => {
    const user = ref<User | null>(null)
    const app = ref<App>({
        name: "",  // For styling navigation depend on which App
        color: null,  // Styling layout color
        theme: useColorTheme[3],  // For styling app color (same as Retina)
        url: null, // For url on logo top left
        environment: null // 'local' | 'staging'
    })

    return { user, app }
})