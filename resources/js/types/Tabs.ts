export interface Navigation {
    [key: string]: {
        title?: string
        icon?: string | string[]
        type?: string
        align?: string
        number?: number
        icon_rotation: '90' | '180' | '270'
        iconClass?: string
        indicator?: boolean  // A blue dot indicator in Tabs
    }
}

export interface Tabs {
    current: string
    navigation: Navigation
}