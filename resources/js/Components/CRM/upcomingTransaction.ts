import { routeType } from "@/types/route"

export type UpcomingTransactionType = "gift" | "follow_on"

export interface UpcomingTransaction {
    id: number
    customer_id: number
    product_id: number
    product_code: string
    product_name: string
    quantity: number
    public_notes: string | null
    private_notes: string | null
    type: UpcomingTransactionType
    state: string
    update: routeType
    delete: routeType
}

export interface UpcomingTransactionRoutes {
    index: routeType
    store: routeType
}

interface TypeMeta {
    label: string
    description: string
    icon: string
    selectedClass: string
    iconClass: string
    iconWrapperClass: string
    badgeClass: string
}

export const upcomingTransactionTypeMeta: Record<UpcomingTransactionType, TypeMeta> = {
    gift: {
        label: "Is gift",
        description: "Send the item for free on the next order",
        icon: "fal fa-gift",
        selectedClass: "border-rose-500 bg-rose-50 text-rose-700 font-semibold",
        iconClass: "text-rose-500",
        iconWrapperClass: "bg-rose-50 ring-rose-100",
        badgeClass: "bg-rose-50 text-rose-600 ring-rose-200",
    },
    follow_on: {
        label: "Follow on",
        description: "Add the item to the customer's next order",
        icon: "fal fa-repeat",
        selectedClass: "border-sky-500 bg-sky-50 text-sky-700 font-semibold",
        iconClass: "text-sky-500",
        iconWrapperClass: "bg-sky-50 ring-sky-100",
        badgeClass: "bg-sky-50 text-sky-600 ring-sky-200",
    },
}

export const upcomingTransactionTypes = Object.entries(upcomingTransactionTypeMeta).map(
    ([value, meta]) => ({ value: value as UpcomingTransactionType, ...meta })
)
