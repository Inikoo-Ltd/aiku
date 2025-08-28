/**
 * Author: Vika Aqordi
 * Created on: 28-08-2025-08h-50m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

export interface Dashboard {
    id: string  //  organisation_dashboard_tab
    super_blocks: {
        settings: Settings
        intervals: Intervals
        interval_options?: Array<{ label: string; value: string }>
        table?: {}[]
        total?: {}[]
        widgets?: {}[]
        currency_code?: string
        current?: string
        total_tooltip?:{}[]
    }[]
}

export interface Settings {
    [key: string]: {  // 'data_display_type' || 'model_state_type' || 'currency_type'
        align: string
        id: string
        options: {
            label: string
            value: string
            tooltip?: string
        }[]
        type: string
        value: string
    }
}

export interface Intervals {
    options: {
        label: string
        value: string
        labelShort: string
    }[]
    value: string
}