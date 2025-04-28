import { routeType } from '@/types/route'

export interface Address {
    id?: number | null
    address_line_1?: string
    address_line_2?: string
    sorting_code?: string
    postal_code?: number
    locality?: string
    dependent_locality?: string
    administrative_area?: string
    country_code?: string
    country_id: number | null
    label: string | null
    checksum: string
    created_at: string | null
    updated_at:string
    country?: {
        code: string
        iso3: string
        name: string
    }
    formatted_address?: string
    can_edit: boolean | null
    can_delete: boolean | null
}

export interface AddressOptions {
    countriesAddressData: {
        [key: string]: {
            administrativeAreas: []
            fields: {
                address_line_1: {
                    label: string
                    required: boolean
                }
                address_line_2: {
                    label: string
                    required?: boolean
                }
                locality: {
                    label: string
                    required?: boolean
                }
                postal_code: {
                    label: string
                    required?: boolean
                }
            }
            label: string  // "Bangladesh (BD)"
        }
    }
}

export interface AddressManagement {
    value: Address
    options: AddressOptions
    address_list: {
        data: Address[]
    }
    pinned_address_id: number
    home_address_id: number
    current_selected_address_id: number
    isCannotSelect: boolean 
    selected_delivery_addresses_id: number[]
    routes_list: {
        switch_route: routeType
        pinned_route: routeType
        delete_route: routeType
        store_route: routeType
    }
}