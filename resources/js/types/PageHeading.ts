import { Button } from '@/types/Button'
import { routeType } from '@/types/route'
import { Action } from '@/types/Action'
import { Icon } from '@/types/Utils/Icon'

export interface PageHeading {
    actions: Action[]
    actionActualMethod?: string
    afterTitle?: {
        label: string
        class?: string
    }
    container: {
        icon: string | string[]
        label: string
        href?: routeType
        tooltip: string
    }
    edit: {
        route: routeType
    }
    noCapitalise?: boolean  // Off capitalize in 'title'
    meta?: {
        key: string
        label?: string
        number?: number | string
        leftIcon?: Icon
        route?: routeType
    }[]
    model: string  // Define the type page ('Pallet Delivery' or 'Pallet Returns', etc)
    icon: {
        icon: string | string[]
        title: string
        tooltip?: string
    }
    iconRight?: {
        tooltip: string
        icon: string
        class: string
    }
    title: string,
    subNavigation?: any
    wrapped_actions: Action[]
}