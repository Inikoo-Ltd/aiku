/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Aug 2026 21:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { ref, onMounted, onUnmounted, computed } from "vue"

export const LIVE_VISITOR_WINDOW = 300

// A basket touched inside this window means the visitor is putting an order together right now,
// as opposed to the tens of thousands of baskets that simply sit in 'creating' forever.
export const ORDERING_WINDOW = 900

export interface LiveVisitor {
    session_id: string
    x: number
    y: number
    vx: number
    vy: number
    radius: number
    country: string
    city?: string
    region?: string
    page?: string
    page_title?: string
    url?: string
    last_active: number
    logged_in: boolean
    device?: string
    browser?: string
    os?: string
    search_engine?: string
    search_term?: string
    customer_id?: string
    customer_name?: string
    basket_amount?: number
    basket_touched_at?: number
    currency_code?: string
    agent?: string
    department?: string
    status: string
    flash_until: number
}

export const liveVisitorColors: Record<string, string> = {
    ordering: "#f59e0b",
    assigned: "#65a30d",
    customer: "#10b981",
    active: "#0ea5e9",
    idle: "#94a3b8",
    bot: "#a855f7",
}

export const liveVisitorStatusLabels: Record<string, string> = {
    ordering: "Ordering",
    assigned: "Chatting",
    customer: "Customer",
    active: "Browsing",
    idle: "Idle",
    bot: "Bot",
}

/**
 * The storefront mints a new session id for the same signed-in person many times an hour, so a
 * customer turns up once per session, each carrying the basket total as it was at that session's
 * last hit. Collapsing to the newest session per customer is what makes both the list and the
 * basket total describe people instead of sessions.
 */
export const dedupeByCustomer = (visitors: LiveVisitor[]): LiveVisitor[] => {
    const byKey = new Map<string, LiveVisitor>()

    visitors.forEach(v => {
        const key = v.customer_id ? `c:${v.customer_id}` : `s:${v.session_id}`
        const seen = byKey.get(key)
        if (!seen || v.last_active > seen.last_active) {
            byKey.set(key, v)
        }
    })

    return Array.from(byKey.values())
}

export const sumBaskets = (visitors: LiveVisitor[]): number =>
    dedupeByCustomer(visitors).reduce((sum, v) => sum + (v.basket_amount ?? 0), 0)

/**
 * Page titles are long and get truncated to nothing useful in a narrow column, so the last
 * segment of the url path is shown instead; the full url stays available as a tooltip.
 */
export const pagePath = (v: Pick<LiveVisitor, "url" | "page">): string => {
    const raw = v.url ?? ""

    try {
        const path = new URL(raw).pathname.replace(/\/+$/, "")
        const last = path.split("/").filter(Boolean).pop()

        return last ?? "/"
    } catch {
        return v.page || "/"
    }
}

/**
 * /app/basket and /app/checkout are the last two steps before an order is placed, so visitors
 * sitting on them get their own panes. Matched on whole path segments: a catalogue page such as
 * "boxes-trays-baskets" contains the word but is not the basket.
 */
export const funnelStage = (v: Pick<LiveVisitor, "url">): "basket" | "checkout" | null => {
    try {
        const segments = new URL(v.url ?? "").pathname.split("/").filter(Boolean)

        if (segments.includes("checkout")) {
            return "checkout"
        }

        return segments[segments.length - 1] === "basket" ? "basket" : null
    } catch {
        return null
    }
}

export const liveVisitorStatus = (v: Pick<LiveVisitor, "last_active"> & Partial<LiveVisitor>): string => {
    const now = Date.now() / 1000

    if ((v.device ?? "").toLowerCase() === "bot") return "bot"
    if (v.agent) return "assigned"
    if (v.basket_touched_at && now - v.basket_touched_at < ORDERING_WINDOW) return "ordering"
    if (now - v.last_active > 60) return "idle"

    return v.logged_in ? "customer" : "active"
}

/**
 * Holds the live visitor set for a website, seeded from the server and kept current by soketi.
 * Positions and velocities live on the visitor objects so the canvas can animate them in place
 * across updates rather than restarting the layout on every hit.
 */
export const useLiveVisitors = (websiteId: number, fallbackCurrency?: string | null, enabled = true) => {
    const visitors = ref<Map<string, LiveVisitor>>(new Map())
    const channel = `website.${websiteId}.analytics`

    const toVisitor = (data: any, existing?: LiveVisitor): LiveVisitor => {
        const visitor: LiveVisitor = existing ?? {
            session_id: data.session_id,
            x: Math.random() * 600,
            y: Math.random() * 300,
            vx: (Math.random() - 0.5) * 1.5,
            vy: (Math.random() - 0.5) * 1.5,
            radius: 13,
            country: "XX",
            last_active: 0,
            logged_in: false,
            status: "active",
            flash_until: 0,
        }

        // Broadcast payloads carry only what the tracker writes to Redis. Chat assignment is
        // resolved separately on page load, so keys absent from a payload must keep their previous
        // value — otherwise a visitor in a chat drops out of "in chat" on their very next hit.
        Object.assign(visitor, {
            country: data.country ?? visitor.country ?? "XX",
            city: data.city ?? visitor.city,
            region: data.region ?? visitor.region,
            page: data.page ?? visitor.page,
            page_title: data.page_title ?? visitor.page_title,
            url: data.url ?? visitor.url,
            last_active: Number(data.last_active) || Date.now() / 1000,
            logged_in: data.logged_in === "1" || data.logged_in === true,
            device: data.device ?? visitor.device,
            browser: data.browser ?? visitor.browser,
            os: data.os ?? visitor.os,
            search_engine: data.search_engine ?? visitor.search_engine,
            search_term: data.search_term ?? visitor.search_term,
            customer_id: data.customer_id ?? visitor.customer_id,
            customer_name: data.customer_name ?? visitor.customer_name,
            basket_amount: data.basket_amount === undefined ? visitor.basket_amount : Number(data.basket_amount),
            basket_touched_at: data.basket_touched_at === undefined ? visitor.basket_touched_at : Number(data.basket_touched_at),
            currency_code: data.currency_code ?? visitor.currency_code ?? fallbackCurrency ?? undefined,
            agent: data.agent ?? visitor.agent,
            department: data.department ?? visitor.department,
        })
        visitor.status = liveVisitorStatus(visitor)

        return visitor
    }

    const syncFromServer = (rows: any[]) => {
        const seen = new Set<string>()
        rows.forEach(row => {
            seen.add(row.session_id)
            visitors.value.set(row.session_id, toVisitor(row, visitors.value.get(row.session_id)))
        })
        visitors.value.forEach((_, sessionId) => {
            if (!seen.has(sessionId)) {
                visitors.value.delete(sessionId)
            }
        })
    }

    // Redis holds the authoritative totals; the local map is capped, so it is only the fallback
    // until the first count broadcast lands.
    const serverCounts = ref<{ logged_in: number; logged_out: number } | null>(null)

    const counts = computed(() => {
        if (serverCounts.value) {
            return serverCounts.value
        }

        let loggedIn = 0
        visitors.value.forEach(v => {
            if (v.logged_in) loggedIn++
        })

        return { logged_in: loggedIn, logged_out: visitors.value.size - loggedIn }
    })

    onMounted(() => {
        if (!enabled) {
            return
        }

        window.Echo.private(channel)
            .listen(".App\\Events\\Web\\WebsiteVisitorCountUpdated", (e: any) => {
                serverCounts.value = { logged_in: e.logged_in_count, logged_out: e.logged_out_count }
            })
            .listen(".App\\Events\\Web\\WebsiteVisitorHit", (e: any) => {
                const visitor = toVisitor(e, visitors.value.get(e.session_id))
                visitor.flash_until = Date.now() + 1200
                visitor.vx += (Math.random() - 0.5) * 5
                visitor.vy += (Math.random() - 0.5) * 5
                visitors.value.set(e.session_id, visitor)
            })
    })

    onUnmounted(() => {
        if (enabled) {
            window.Echo.leave(channel)
        }
    })

    return { visitors, counts, serverCounts, toVisitor, syncFromServer }
}
