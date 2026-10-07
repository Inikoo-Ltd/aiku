import type { BlockProperties, LinkProperties } from "@/types/Announcement"

export interface WebsiteDialogTemplateData {
    template_code?: string | null
    component?: string | null
    fields: {
        accent?: string
        eyebrow?: {
            text: string
        }
        note?: {
            text: string
        }
        subscribe?: {
            placeholder?: string
            success_text?: string
        }
        coupon?: {
            label?: string
            code?: string
        }
        image?: {
            source?: any
            alt?: string
        }
        title?: {
            text: string
        }
        description?: {
            text: string
        }
        button?: {
            text: string
            link?: LinkProperties
            container?: {
                properties: BlockProperties
            }
        }
    }
    container_properties: BlockProperties
}

export interface WebsiteDialogSettings {
    trigger?: 'automatic' | 'on_click'
    target_pages?: {
        type: 'all' | 'specific'
        specific: { will: 'show' | 'hide', when: 'contain', url: string }[]
    }
    target_users?: {
        auth_state: 'all' | 'logged_in' | 'logged_out'
    }
    display_frequency?: 'every_page_view' | 'once_per_session' | 'once'
    delay_seconds?: number
}

export interface WebsiteDialogData extends WebsiteDialogTemplateData {
    id: number
    ulid: string
    name: string
    settings: WebsiteDialogSettings
    state: 'in-process' | 'ready'
    status: 'active' | 'inactive'
    is_dirty: boolean
    is_published: boolean
    schedule_at?: string | null
    schedule_finish_at?: string | null
    paused_until?: string | null
    paused_by?: string | null
    live_at?: string | null
    closed_at?: string | null
    ready_at?: string | null
    created_at: string
    published_message?: string | null
    display_frequency: string
    publisher?: {
        contact_name: string
        username: string
    } | null
}
