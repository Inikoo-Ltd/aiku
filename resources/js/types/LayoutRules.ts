
import { Image } from "@/types/Image"
import { routeType } from "@/types/route"
import type { Component } from "vue"

export interface OrganisationsData {
    id: number
    slug: string
    code: string
    label: string
    logo: Image
    route: routeType
    currency: Currency
    authorised_shops: Shop[]
    authorised_warehouses: {
        id: number
        slug: string
        code: string
        label: string
        state: string
        route: routeType
    }[]
    authorised_fulfilments: {
        id: number
        slug: string
        code: string
        label: string
        state: string
        type: string
        route: routeType
    }[]
    authorised_productions: {
        id: number
        slug: string
        code: string
        label: string
    }[]
}

export interface Group {
    logo: Image
    slug: string
    label: string
    currency: Currency
}

// Each organisation have their own state
export interface OrganisationState {
    [key: string] : string  // 'currentShop' | 'currentWarehouse' | 'currentFulfilment'
}

export interface Currency {
    id: number
    code: string  // 'GBP', 'USD', 'IDR'
    name: string
    symbol: string
    fraction_digits: string
    status: boolean
    store_historic_data: boolean
    historic_data_since: Date
    data: []
    created_at: Date
    updated_at: Date
}

export interface StackedComponent {
    component: Component
    data?: {
        currentTab?: string
    }
}

export interface Shop {
	id: number
	slug: string
	code: string
	label: string
	state: string
	type: string
	route: routeType
}