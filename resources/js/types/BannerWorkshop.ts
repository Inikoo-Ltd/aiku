import { Icon } from "@/types/Utils/Icon"
import { Images } from "@/types/Images"

export type BannerScreenView = "desktop" | "tablet" | "mobile"

export interface CornerData {
    data: {
        button_color?: string
        ribbon_color?: string
        text: string
        target: string
    }
    temporaryData?: {
        linkButton: {
            button_color: string
            target: string
            text: string
        }
    }
    type: string
}

export interface CornersData {
    topLeft?: CornerData
    topRight?: CornerData
    bottomLeft?: CornerData
    bottomRight?: CornerData
}

export interface CentralStageData {
    style: {
        color?: string
        fontFamily?: string
        fontSize?: {
            fontSubtitle: string
            fontTitle: string
        }
        textShadow?: boolean
    }
    subtitle?: string
    textAlign?: string
    title?: string
    linkOfText?: string
}

// Slide Data
export interface SlideWorkshopData {
    id?: number
    ulid: string
    // image_id: number
    // image_source: string
    layout: {
        link?: string,
        centralStage?: CentralStageData
        imageAlt: string
        corners?: CornersData
        background: {
            desktop: string
            tablet?: string
            mobile?: string
        }
        backgroundType: {
            desktop: string
            tablet?: string
            mobile?: string
        }
    }
    image: {
        desktop: Images | {}
        tablet?: Images | {}
        mobile?: Images | {}
    }
    visibility: boolean
    corners?: CornersData
    // imageAlt?: string
    link?: string
    user?: string
}

export interface CommonData {
    centralStage?: CentralStageData
    corners?: CornersData
    user?: string
}

export interface BannerNavigation {
    colorNav?: string
    sideNav?: {
        value: boolean
        type: string
    }
    bottomNav?: {
        value: boolean
        type: string | {
            value: string
        }
    }
}

// Full Banner Data
export interface BannerWorkshop {
    common: CommonData
    components: SlideWorkshopData[]
    delay: number
    type: string
    navigation?: BannerNavigation
    published_hash?: string
}

// Banner model as served to the workshop (BannerResourceForWorkshop)
export interface BannerWorkshopResource {
    id: number
    ulid: string
    slug: string
    name: string
    type: string
    state: string
    state_value: string
    state_label: string
    state_icon: Icon
    ratio: string
    compiled_layout: BannerWorkshop
    created_at: string
    updated_at: string
    last_saved_at: string
    delivery_url: string
    views?: number
}