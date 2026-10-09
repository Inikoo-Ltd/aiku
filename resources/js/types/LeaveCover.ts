export interface LeaveCover {
    id: number
    employee_name: string
    type_label: string
    start_date: string
    end_date: string
    is_ongoing: boolean
    has_permissions: boolean
    job_positions: string[]
    open_work: { key: string; label: string; count: number }[]
}
