export interface TicketBadgeRow {
    label: string
    count: number
    elements: Record<string, string>
}

export interface TicketBadges {
    mine: Record<string, TicketBadgeRow>
    queue: Record<string, TicketBadgeRow> | null
}
