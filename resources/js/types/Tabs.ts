export interface Navigation {
    [key: string]: {
        title?: string
        icon?: string | string[]
        type?: string
        align?: string
        number?: number
        icon_rotation: '90' | '180' | '270'
        iconClass?: string
        colorScheme?: string  // Paints the whole tab in one colour, active or not. See Tabs.vue
        indicator?: boolean  // A blue dot indicator in Tabs
    }
}

export interface Tabs {
    current: string
    navigation: Navigation
}