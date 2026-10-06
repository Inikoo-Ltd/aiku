/**
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 12-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/
import { routeType } from '@/types/route'

export interface Upload {
    event: string
    channel: string
    required_fields: string[]
    column_aliases?: Record<string, string[]>
    template: {
        label: string
    }
    route: {
        upload: routeType
        history: routeType
        download: routeType
    }
}