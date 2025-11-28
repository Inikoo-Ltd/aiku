/**
 * Author: Vika Aqordi
 * Created on 26-11-2025-11h-19m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { Icon as IconTS } from '@/types/Utils/Icon'
import { routeType } from '../route'

export interface Location {
    id: number
    slug: string
    code: string
    stock_value: string
    stock_commercial_value: string
    allow_stocks: boolean
    allow_fulfilment: boolean
    allow_dropshipping: boolean
    has_stock_slots: boolean
    has_fulfilment: boolean
    has_dropshipping_slots: boolean
    organisation_slug: null
    organisation_name: null
    warehouse_slug: null
    max_weight: number
    max_volume: number
}

export interface StockLocation {
    id: number
    code: string
    quantity: string | number
    value: string
    audited_at: string
    commercial_value: string
    type: string
    picking_priority: number
    notes: string | null
    data: []
    settings: {
        min_stock: number
    },
    created_at: string
    updated_at: string
    location: Location
}

export interface StockManagementRoutes {
    location_route: routeType
    associate_location_route: routeType
    disassociate_location_route: routeType
    audit_route: routeType
    move_location_route: routeType
}

export interface StocksManagementTS {
    routes: StockManagementRoutes
    summary: {
        [key: string]: {
            icon_state: IconTS
            value: number
        }
    }
    locations: StockLocation[]
}