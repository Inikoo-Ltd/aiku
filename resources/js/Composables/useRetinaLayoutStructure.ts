/**
 * Author: Vika Aqordi
 * Created on: 06-03-2025-09h-52m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { useColorTheme } from '@/Composables/useStockList'

import { StackedComponent} from '@/types/LayoutRules'
import { Colors } from "@/types/Color"
import { Navigation, grpNavigation, orgNavigation } from "@/types/Navigation"
import { Image } from "@/types/Image"
import { Notification } from '@/types/Notification'


export const retinaLayoutStructure = {
    app: {
        name: "",  // For styling navigation depend on which App
        color: null as unknown | Colors,  // Styling layout color
        theme: useColorTheme[3] as string[],  // For styling app color
        url: null as string | null, // For url on logo top left
        environment: null as string | null, // 'local' | 'staging' 
    },

    currentModule: "",
    currentRoute: "grp.dashboard.show", // Define value to avoid route null at first load
    currentParams: {} as {[key: string]: string},
    currentPlatform: "", // string

    leftSidebar: {
        show: true,
    },
    navigation: {
        grp: {} as grpNavigation,
        org: {} as { [key: string]: orgNavigation } | { [key: string]: Navigation } | Navigation
    },

    rightSidebar: {
        activeUsers: {
            users: [],
            count: 0,
            show: false
        },
        language: {
            show: false
        }
    },
    retina: {
        type: '' as string  // 'dropshipping' | 'fulfilment' | 'ecom'
    },
    root_active: null as string | null,
    stackedComponents: [] as StackedComponent[],
    user: {} as {
        id: number,
        avatar_thumbnail: Image,
        email: string,
        username: string,
        notifications: Notification[]
    },
    
}