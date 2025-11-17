/**
 * Author: Vika Aqordi
 * Created on: 22-07-2025-15h-36m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { Image } from '@/types/Image'


export interface AnnouncementFields {

}

export interface AnnouncementData {
    ulid: string
    code: string
    source: Image
    fields: AnnouncementFields
    container_properties: BlockProperties
    compiled_layout?: string
    template_code: string
}


export interface BlockProperties {
    position: {
        type: string
        x: string
        y: string
    }
    dimension: {
        height: {
            value: number
            unit: string
        }
        width: {
            value: number
            unit: string
        }
    }
    text: {
        color: string
        fontFamily: string
    }
    padding: {
        unit: string
        top: {
            value: number
        }
        bottom: {
            value: number
        }
        right: {
            value: number
        }
        left: {
            value: number
        }
    }
    margin: {
        unit: string
        top: {
            value: number
        }
        bottom: {
            value: number
        }
        right: {
            value: number
        }
        left: {
            value: number
        }
    }
    border: {
        unit: string
        color: string
        top: {
            value: number
        }
        bottom: {
            value: number
        }
        right: {
            value: number
        }
        left: {
            value: number
        }
        rounded: {
            unit: string
            topright: {
                value: number
            }
            bottomright: {
                value: number
            }
            bottomleft: {
                value: number
            }
            topleft: {
                value: number
            }
        }
    }
    isCenterHorizontal: boolean
    background: {
        type: string
        color: string
        image: Image
    }
    additional_style: {} // "display: flex; align-items: center; justify-content: space-between"
}

export interface LinkProperties {
    href: string
    target: string
}