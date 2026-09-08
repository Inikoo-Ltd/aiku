export interface Timeline {
    key?: string
    index?: number
    label: string
    icon?: string | string[]
    tooltip?: string
    timestamp?: string | Date
    timestamp_icon?: string | string[]
    timestamp_tooltip?: string
    current?: boolean
    sub_label?: string
    format_time?: string
}