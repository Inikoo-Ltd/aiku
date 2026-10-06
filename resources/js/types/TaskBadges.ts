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

export interface CreatedTask {
    id: number
    reference: string
    subject: string
    status: string
    status_icon: any
    due_at: string | null
    assignee: string | null
    has_eta_proposal: boolean
    asked_for_help: boolean
    route: string
}

export interface TaskBadges {
    mine: Record<string, TaskBadgeRow>
    today: { done: number; open: number }
    created?: {
        open: number
        needs_answer: number
        tasks: CreatedTask[]
        sections?: { eta_change: CreatedTask[]; help_request: CreatedTask[]; recent: CreatedTask[] }
    }
    recent: TaskRecentUpdate[]
}
