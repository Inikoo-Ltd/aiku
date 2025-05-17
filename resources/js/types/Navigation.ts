import { routeType } from "@/types/route"

export interface DropdownLink {
    label?: string
    tooltip: string
    icon: string[]
    route: {
        all: string[]
        selected: string[]
    }
}

export interface SubSection {
    label: string
    icon?: string | string[]
    tooltip?: string
    root: string
    route: routeType
}

export interface Navigation {
    root?: string  // For Navigation active state purpose
    label: string
    tooltip?: string
    scope?: string
    icon?: string[] | string
    route?: routeType
    right_label?: {
        label: string
        icon?: string[]
        icon_rotation?: number
        is_important?: boolean
        class?: string
        tooltip?: string
    }
    topMenu?: {
        subSections?: SubSection[]
        dropdown?: {
            links: DropdownLink[]
        }
    }
}

export interface grpNavigation {
    [key: string]: Navigation
}

export interface orgNavigation {
    [orgKey: string]: {
        [shopKey: string]: {
            [navShopKey: string]: {
                type?: string  // 'shop' || 'dropshipping'
                subNavigation: Navigation
            }
        }
    }
}