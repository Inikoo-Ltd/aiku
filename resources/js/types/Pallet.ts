// PDR = Pallet Delivery/Return

import { Timeline } from "@/types/Timeline"
import { routeType } from "@/types/route"
import { AddressManagement } from "@/types/PureComponent/Address"
import { Icon, StateIcon } from '@/types/Utils/Icon'


export interface Pallet {
    id: number
    slug?: string
    reference: string
    customer_reference: string
    fulfilment_customer_name?: string
    fulfilment_customer_slug?: string
    fulfilment_customer_id: number
    notes: string
    state: string
    state_label: string
    state_icon: StateIcon
    status: string
    status_label: string
    status_icon: StateIcon
    location: string
    location_code: string
    location_slug: string
    location_id: number
    stored_items: any[]
    stored_items_quantity: number
    updateRoute: routeType
    deleteRoute: routeType
    deleteFromDeliveryRoute: routeType
    deleteFromReturnRoute: routeType
    notReceivedRoute: routeType
    undoNotReceivedRoute: routeType
    bookInRoute: routeType
    undoBookInRoute: routeType
    updateLocationRoute: routeType
    storeStoredItemRoute: routeType
    index: number
    editingIndicator: {
        loading: boolean
        isSucces: boolean
        isFailed: boolean
    }
}

export interface PalletCustomer {
    slug: string
    reference: string
    name: string
    contact_name: string
    company_name?: string
    email?: string
    phone?: string
    created_at: Date
    updated_at: Date
    shop?: string
    shop_slug?: string
    number_current_customer_clients?: number
}

export interface PieCustomer {
    label: string
    count: number
    cases: {
        value: string
        count: number
        label: string
        icon: Icon
    }[]
}

export interface FulfilmentCustomerStats {
    label: string
    state: {
        [key: string]: {
            count: number
            icon: Icon
        }
    }
    description: string
    count: number
    cases: {
        value: string
        count: number
        label: string
        icon: Icon
    }[]
}

export interface PalletDelivery {
    id: number
    customer_name: string
    customer_reference: string | null
    number_pallets: number
    number_boxes: number
    number_oversizes: number
    number_services: number
    number_physical_goods: number
    reference: string
    state: string
    timeline: Timeline[]
    estimated_delivery_date: string
}


export interface PalletReturn {
    id: number
    delivery_address: {
        formatted_address?: string
    }
    reference: string
    state: string
    timeline: {
        [key: string]: Timeline
    }
    number_pallets: number
    number_stored_items: number
    number_services: number
    number_physical_goods: number
    is_collection?: boolean  // if true then no delivery address
}


export interface FieldOrderSummary {
    label: string
    label_class?: string
    quantity: number
    price_base?: number
    price_total: number | string
    price_total_class?: string
    information_icon?: string
    information?: string
    currency: {
        code: string
        symbol: string
    }
}


// Box Stats in Pallet Delivery
export interface BoxStats {
    delivery_state: Icon
    is_platform: boolean
    platform_customer: object
    fulfilment_customer: {
        address: AddressManagement
        customer: {
            slug: string
            reference: string
            name: string
            contact_name: string
            company_name: string
            location: string[]
            email: string
            phone: string
            created_at: string
        }
        fulfilment: {
            name: string
            slug: string
        }
        slug: string
        number_pallet_deliveries: number
        number_pallet_returns: number
        number_pallets: number
        number_pallets_state_received: number
        number_stored_items: number
        radioTabs: {
            dropshipping: boolean
            items_storage: boolean
            pallets_storage: boolean
        }
    }
    invoice:{
        reference: string,
        route: routeType
    }
    order_summary: FieldOrderSummary[][]
}


// Pallet Delivery and Return notes
export interface PDRNotes {
    label: string
    note: string
    editable?: boolean
    bgColor?: string
    textColor?: string
    color?: string
    lockMessage?: string
    field: string  // customer_notes, public_notes, internal_notes
}


export interface UploadPallet {
    event: string
    channel: string
    required_fields: string[]
    template: {
        label: string
    }
    route: {
        upload: routeType
        history: routeType
        download: routeType
    }
}
