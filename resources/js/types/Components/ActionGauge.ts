import { routeType } from "@/types/route"

export interface ActionGaugeTS {
    key: string
    label: string
    hint: string
    value: number
    percentage: number
    total: number
    color: string
    route: routeType
    secondary?: {
        tooltip: string
        icon: string
        value: number
        route: routeType
    }
}
