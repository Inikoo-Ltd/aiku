export interface TicketBadgeRow {
    label: string
    count: number
    elements: Record<string, string>
}

export interface TicketRecentUpdate {
    id: string
    title: string
    body: string
    route: string
    read: boolean
    created_at: string
}

export interface TicketBadges {
    mine: Record<string, TicketBadgeRow>
    recent?: TicketRecentUpdate[]
    queue: Record<string, TicketBadgeRow> | null
}
