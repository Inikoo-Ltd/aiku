export interface Navigation {
    [key: string]: {
        title?: string
        icon?: string | string[]
        icon_badge?: string  // A small second icon pinned to the bottom right of the icon, e.g. a clock on an upload icon for upload history
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