export type WebsiteDialogDisplayFrequency = 'every_page_view' | 'once_per_session' | 'once' | 'once_per_customer'

export interface IrisWebsiteDialog {
    ulid: string
    version?: string | null
    template_code?: string | null
    component?: string | null
    fields?: Record<string, any>
    container_properties?: Record<string, any>
    schedule_at?: string | null
    schedule_finish_at?: string | null
    resumes_at?: string | null
    show_pages?: string[]
    hide_pages?: string[]
    settings?: {
        trigger?: 'automatic' | 'on_click'
        target_users?: {
            auth_state?: 'all' | 'logged_in' | 'logged_out'
        }
        display_frequency?: WebsiteDialogDisplayFrequency
        delay_seconds?: number
    }
}

export interface DismissalStores {
    session: Pick<Storage, 'getItem' | 'setItem'> | null
    local: Pick<Storage, 'getItem' | 'setItem'> | null
    customer?: Record<string, string | null> | null
}

// Method: remembered on the customer account, so it stays closed on every device; guests fall back to this browser
export const isOncePerCustomer = (dialog: IrisWebsiteDialog): boolean =>
    dialog.settings?.display_frequency === 'once_per_customer'

const dismissalKey = (dialog: IrisWebsiteDialog) => `iris_website_dialog:${dialog.ulid}`

const dismissalStore = (dialog: IrisWebsiteDialog, stores: DismissalStores) => {
    const frequency = dialog.settings?.display_frequency ?? 'once_per_session'

    if (frequency === 'once' || frequency === 'once_per_customer') return stores.local
    if (frequency === 'once_per_session') return stores.session
    return null
}

const readStore = (store: DismissalStores['local'], key: string): string | null => {
    try {
        return store?.getItem(key) ?? null
    } catch {
        return null
    }
}

// Method: 'once' remembers the published version, so publishing the dialog again shows it to everybody again
export const isWebsiteDialogDismissed = (dialog: IrisWebsiteDialog, stores: DismissalStores): boolean => {
    if (isOncePerCustomer(dialog) && stores.customer && dialog.ulid in stores.customer) {
        if ((stores.customer[dialog.ulid] ?? '') === (dialog.version ?? '')) return true
    }

    const store = dismissalStore(dialog, stores)
    if (!store) return false

    return readStore(store, dismissalKey(dialog)) === (dialog.version ?? '')
}

export const rememberWebsiteDialogDismissal = (dialog: IrisWebsiteDialog, stores: DismissalStores): void => {
    const store = dismissalStore(dialog, stores)
    if (!store) return

    try {
        store.setItem(dismissalKey(dialog), dialog.version ?? '')
    } catch {
        return
    }
}

export const WEBSITE_DIALOG_HASH_PREFIX = 'website-dialog-'

export const isAutomaticWebsiteDialog = (dialog: IrisWebsiteDialog): boolean =>
    (dialog.settings?.trigger ?? 'automatic') === 'automatic'

// Method: '#website-dialog-{ulid}' (also at the end of a full URL) → ulid, anything else → null
export const websiteDialogUlidFromHref = (href?: string | null): string | null => {
    const hashIndex = (href ?? '').indexOf('#')
    if (hashIndex < 0) return null

    const hash = (href ?? '').slice(hashIndex + 1)

    return hash.startsWith(WEBSITE_DIALOG_HASH_PREFIX) && hash.length > WEBSITE_DIALOG_HASH_PREFIX.length
        ? hash.slice(WEBSITE_DIALOG_HASH_PREFIX.length)
        : null
}

// Method: newest visible dialog the visitor has not dismissed yet; only one dialog pops up at a time
export const pickWebsiteDialog = <T extends IrisWebsiteDialog>(
    dialogs: T[],
    isVisible: (dialog: T) => boolean,
    stores: DismissalStores
): T | null => {
    return dialogs.find(dialog =>
        !!dialog?.component
        && isAutomaticWebsiteDialog(dialog)
        && isVisible(dialog)
        && !isWebsiteDialogDismissed(dialog, stores)
    ) ?? null
}

export const browserDismissalStores = (): DismissalStores => {
    const safely = (getStore: () => Storage) => {
        try {
            return getStore()
        } catch {
            return null
        }
    }

    if (typeof window === 'undefined') {
        return { session: null, local: null }
    }

    return {
        session: safely(() => window.sessionStorage),
        local: safely(() => window.localStorage),
    }
}
