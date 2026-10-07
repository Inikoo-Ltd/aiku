export interface StaffTaskEtaProposal {
    due_at: string
    previous_due_at: string | null
    reason: string
    by_id: number
    by_name: string
    at: string
}

export interface StaffTaskDueAccess {
    can_set: boolean
    can_suggest: boolean
}
