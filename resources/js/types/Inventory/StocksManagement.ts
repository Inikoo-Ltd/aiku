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
    organisation_slug: string
    organisation_name: string
    warehouse_slug: string
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
        max_stock: number
        replenishment_stock: number
    },
    created_at: string
    updated_at: string
    location: Location
    default_wholesale_picking_location: boolean
    default_dropshipping_picking_location: boolean
    enabled_on_dropshipping: boolean
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
    stock_cost : {
        cost_stock_price_per_unit : number
        cost_stock_price_outer : number
        cost_current_price_per_unit : number
        cost_current_price_outer : number
    }
    locations: StockLocation[]
    qty_in_location: number
}