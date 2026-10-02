export interface TaskBadgeRow {
    label: string
    count: number
    filter: Record<string, string | number>
    status: string
}

export interface TaskRecentUpdate {
    id: string
    title: string
    body: string
    route: string
    read: boolean
    created_at: string
}

export interface TaskBadges {
    mine: Record<string, TaskBadgeRow>
    today: { done: number; open: number }
    recent: TaskRecentUpdate[]
}
