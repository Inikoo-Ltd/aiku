import { routeType } from './route';
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 19:13:19 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

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
    platform?:{
        icon: string | string[],
        title: string,
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
    noCapitalise?: boolean  // Off capitalizing in 'title'
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
    image?: {
        tooltip: string
        src: string
        alt: string
        class: string
        width: string
        height: string
        style: string
    }
    iconRight?: {
        tooltip: string
        icon: string
        class: string
        url: routeType
        icon_rotation:  90 | 180 | 270 | '90' | '180' | '270'
    }
    icon_rotation:  90 | 180 | 270 | '90' | '180' | '270'
    titleRight?: string
    title: string,
    subNavigation?: any
    wrapped_actions: Action[]
    parentTag?: ParentTag[]
}


interface ParentTag {
    label: string
    route: routeType
    icon: string | string[]
    tooltip: string
    length: number
}