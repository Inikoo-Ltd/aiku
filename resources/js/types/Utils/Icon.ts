/**
 * Author: Vika Aqordi
 * Created on: 16-07-2024-10h-02m
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

export interface Icon {
    icon: string | string[]
    tooltip?: string
    class?: string
    icon_rotation?: number
}

export interface StateIcon {
    tooltip?: string
    icon: string | string[]
    class?: string
    color?: string
    app?: {
        name: string
        type: string
    }
}