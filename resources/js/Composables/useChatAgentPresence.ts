/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import axios from 'axios'

type PresenceStatus = 'online' | 'away' | 'offline'

// pointermove is in here on purpose: somebody reading a long conversation without clicking
// anything is still at their desk, and a hand resting on the mouse is the only signal they give.
const ACTIVITY_EVENTS = ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart'] as const

// Starting values only. The server answers every heartbeat with the timings from config/chat.php,
// which stays the single place these are tuned.
const timings = {
    heartbeat: 120_000,
    away: 3_600_000,
    abandon: 14_400_000,
}

const MIN_SEND_GAP = 5_000

let heartbeatTimer: ReturnType<typeof setInterval> | null = null
let lastActivityAt = Date.now()
let lastSentAt = 0
let lastSentStatus: PresenceStatus | null = null
let running = false

const schedule = () => {
    if (heartbeatTimer) {
        clearInterval(heartbeatTimer)
    }

    heartbeatTimer = setInterval(beat, timings.heartbeat)
}

const applyTimings = (payload: Record<string, number> | undefined) => {
    if (!payload) {
        return
    }

    const heartbeat = payload.heartbeat_seconds * 1000

    timings.away = payload.away_after_seconds * 1000
    timings.abandon = payload.abandon_after_seconds * 1000

    if (heartbeat !== timings.heartbeat) {
        timings.heartbeat = heartbeat

        if (running) {
            schedule()
        }
    }
}

const send = (status: PresenceStatus) => {
    lastSentStatus = status
    lastSentAt = Date.now()

    axios
        .post(route('grp.chat.presence.track'), { status })
        .then(({ data }) => applyTimings(data?.timings))
        .catch(() => {
            // A missed ping is not worth surfacing: the next one recovers it, and the window the
            // server allows is many pings wide, so nobody goes offline over one failed request.
            lastSentStatus = null
        })
}

const beat = () => {
    const idleFor = Date.now() - lastActivityAt

    // Past this point the tab is furniture, not a person: it stops pinging so a laptop left open
    // overnight expires instead of reporting for duty in the morning. Real activity resumes it.
    if (idleFor > timings.abandon) {
        lastSentStatus = null

        return
    }

    send(idleFor > timings.away ? 'away' : 'online')
}

const onActivity = () => {
    lastActivityAt = Date.now()

    // Only a status that needs correcting is worth a request here, and never faster than the
    // gap: a moving mouse fires constantly, and a failing endpoint clears lastSentStatus, so
    // without the gap an outage would turn every twitch into another doomed request.
    if (lastSentStatus !== 'online' && Date.now() - lastSentAt > MIN_SEND_GAP) {
        beat()
    }
}

const onVisibilityChange = () => {
    if (document.hidden) {
        return
    }

    // Coming back to the tab counts as being here. A hidden tab on its own does not mean away,
    // agents keep the inbox in a background tab all day.
    onActivity()
}

export const useChatAgentPresence = () => {
    const start = () => {
        if (running || typeof window === 'undefined') {
            return
        }

        running = true
        lastActivityAt = Date.now()

        ACTIVITY_EVENTS.forEach(event => window.addEventListener(event, onActivity, { passive: true }))
        document.addEventListener('visibilitychange', onVisibilityChange)

        beat()
        schedule()
    }

    const stop = () => {
        if (!running) {
            return
        }

        running = false
        lastSentStatus = null

        if (heartbeatTimer) {
            clearInterval(heartbeatTimer)
            heartbeatTimer = null
        }

        ACTIVITY_EVENTS.forEach(event => window.removeEventListener(event, onActivity))
        document.removeEventListener('visibilitychange', onVisibilityChange)
    }

    return { start, stop }
}
