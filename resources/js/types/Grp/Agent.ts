
/**
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 12-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

import { Address } from '@/types/PureComponent/Address'
import { Image } from '@/types/Image'

interface Currency {
    id: number
    code: string
    name: string
    symbol: string
}

export interface Agent {
    code: string
    name: string
    slug: string
    location: string[]
    email: string
    phone: string
    company?: string
    contact?: string
    website?: string
    currency?: Currency
    created_at?: string
    address?: Address
    photo?: Image
}