/**
 * Author: Vika Aqordi
 * Created on: 27-06-2025-13h-50m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { routeType } from "../route"
import { Icon } from "../Utils/Icon"

export interface StatsBoxTS {
    id: number
    label: string
    value: number
    change: number
    changeType: string
    icon: string
    color: string
    backgroundColor?: string
    is_negative?: boolean
    route: {
        name: string
        parameters: {}
    }
    metaRight?: {
        count: number
        icon: Icon
        route?: routeType
        tooltip: string
    }
    metas?: {
        count: number
        icon: Icon
        route?: routeType
        tooltip: string
    }[]
}