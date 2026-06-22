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

interface Language {
    id: number
    code: string
    name: string
    flag: string
    native_name: string
}

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
    currentQuery: {} as {[key: string]: string},
    currentPlatform: "", // string

    family_page: {
        productInBasket: {
            isLoading: false,
            list: {} as { [key: number]: { quantity_ordered: number|null, quantity_ordered_new: number|null, transactions_id: number|null } }[]  // list of quantity_ordered from each products
        }
    },
    iris: {
        currency: {
            code: '',
        },
        website_i18n: {
            current_language: {} as Language,
            shop_language: {} as Language,
            language_options: {} as { [key: string]: Language },
        }
    },
    iris_variables: {
        cart_count: 0,
        cart_amount: 0,
        cart_amount_gross: 0,
    },
    leftSidebar: {
        show: true,
    },
    navigation: {
        grp: {} as grpNavigation,
        org: {} as { [key: string]: orgNavigation } | { [key: string]: Navigation } | Navigation
    },
    offer_meters: [] as {
        is_gift: boolean,
        information: string,
        label: string,
        label_got: string,
        metadata: {
            current: number,
            target: number,
        }
    }[],
    rightbasket: {
        show: false,
        products: [] as any[],
    },
    // rightSidebar: {
    //     activeUsers: {
    //         users: [],
    //         count: 0,
    //         show: false
    //     },
    //     language: {
    //         show: false
    //     }
    // },
    retina: {
        type: '' as string,  // 'dropshipping' | 'fulfilment' | 'ecom'
        organisation: {
            slug: ''
        }
    },
    root_active: null as string | null,
    stackedComponents: [] as StackedComponent[],
    user: {} as {
        id: number,
        avatar_thumbnail: Image,
        email: string,
        customer_id: number,
        username: string,
        notifications: Notification[]
        customerSalesChannels : any
    },
    reload_handle: null as null | (() => void)
}