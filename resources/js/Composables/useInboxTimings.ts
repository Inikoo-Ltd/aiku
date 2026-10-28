/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { nextTick } from "vue"

type Measure = "inbox_open" | "conversation_open" | "reply_sent" | "freeze"

type Sample = {
	measure: Measure
	duration_ms: number
	channel?: string | null
	is_full_load?: boolean | null
}

const FREEZE_MS = 200
const FLUSH_EVERY_MS = 30_000
const GIVE_UP_MS = 30_000
const LONGEST_MS = 120_000
const VISIT_JUST_ARRIVED_MS = 5_000

let hasStartedInThisTab = false
let lastHiddenAt = document.visibilityState === "hidden" ? performance.now() : -1

document.addEventListener("visibilitychange", () => {
	if (document.visibilityState === "hidden") {
		lastHiddenAt = performance.now()
	}
})

/**
 * A tab in the background does not paint, so a wait that included one measures how long the
 * agent was elsewhere, not the inbox. The browser's own record of the tab being hidden covers the
 * time before this file was loaded.
 */
const wasHiddenSince = (since: number): boolean => {
	if (document.visibilityState === "hidden" || lastHiddenAt >= since) {
		return true
	}

	return typeof PerformanceObserver !== "undefined" && PerformanceObserver.supportedEntryTypes?.includes("visibility-state")
		? performance.getEntriesByType("visibility-state").some((entry) => entry.name === "hidden" && entry.startTime >= since)
		: false
}

/**
 * How long the Customer Inbox makes the person in front of it wait (INI-145). The server's share
 * is in NightOwl; this adds what only the browser sees: downloading and starting the page, painting
 * the list and the conversation, and the moments the page is frozen. Samples are kept and sent in
 * batches, and whatever is left goes when the tab is hidden or the page is left.
 */
export const useInboxTimings = () => {
	let queue: Sample[] = []
	let flushTimer: ReturnType<typeof setInterval> | null = null
	let freezeObserver: PerformanceObserver | null = null
	let stopWaitingForConversation: (() => void) | null = null

	const painted = async () => {
		await nextTick()
		await new Promise<void>((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve())))
	}

	/** A sample longer than two minutes is a measuring accident, and the server would refuse the whole batch over it. */
	const record = (sample: Sample, startedAt: number) => {
		if (!Number.isFinite(sample.duration_ms) || sample.duration_ms < 0 || sample.duration_ms > LONGEST_MS || wasHiddenSince(startedAt)) {
			return
		}

		queue.push({ ...sample, duration_ms: Math.round(sample.duration_ms) })

		if (queue.length >= 50) {
			flush()
		}
	}

	const flush = () => {
		if (!queue.length) {
			return
		}

		const samples = queue
		queue = []
		const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? "")

		fetch(route("grp.chat.inbox_timings.store"), {
			method: "POST",
			keepalive: true,
			headers: { "Content-Type": "application/json", Accept: "application/json", "X-XSRF-TOKEN": token },
			body: JSON.stringify({ samples }),
		}).catch(() => {})
	}

	const onHidden = () => {
		if (document.visibilityState === "hidden") {
			flush()
		}
	}

	/**
	 * Where the wait for the inbox began: the start of the page when the whole app was loaded,
	 * or the request that fetched the inbox when coming from another page, which must have just
	 * arrived. Back restores the inbox without asking the server, and a full list of requests keeps
	 * only old ones, so an older request for the same address is not this visit. Only the first
	 * inbox in a tab can be the full load; when neither is found nothing is recorded, not a guess.
	 */
	const inboxStart = (): { at: number, isFullLoad: boolean } | null => {
		const path = window.location.pathname
		const visit = (performance.getEntriesByType("resource") as PerformanceResourceTiming[])
			.filter((entry) => entry.initiatorType === "xmlhttprequest" && new URL(entry.name, window.location.origin).pathname === path)
			.pop()

		if (visit && visit.responseEnd >= performance.now() - VISIT_JUST_ARRIVED_MS) {
			return { at: visit.startTime, isFullLoad: false }
		}

		if (visit) {
			return null
		}

		const navigation = performance.getEntriesByType("navigation")[0] as PerformanceNavigationTiming | undefined

		if (!hasStartedInThisTab && navigation && new URL(navigation.name).pathname === path) {
			return { at: 0, isFullLoad: true }
		}

		return null
	}

	/** Called on mount, before anything else changes the address. */
	const start = () => {
		const opened = inboxStart()
		hasStartedInThisTab = true

		flushTimer = setInterval(flush, FLUSH_EVERY_MS)
		document.addEventListener("visibilitychange", onHidden)

		if (typeof PerformanceObserver !== "undefined" && PerformanceObserver.supportedEntryTypes?.includes("longtask")) {
			freezeObserver = new PerformanceObserver((list) => {
				list.getEntries()
					.filter((entry) => entry.duration >= FREEZE_MS)
					.forEach((entry) => record({ measure: "freeze", duration_ms: entry.duration }, entry.startTime))
			})
			freezeObserver.observe({ type: "longtask" })
		}

		return {
			listPainted: async () => {
				if (!opened) {
					return
				}

				await painted()
				record({ measure: "inbox_open", duration_ms: performance.now() - opened.at, is_full_load: opened.isFullLoad }, opened.at)
			},
		}
	}

	/**
	 * Opening a conversation ends when its messages are on screen. The thread fetches them itself,
	 * so this waits for that request (website, email and WhatsApp alike end in
	 * sessions/{ulid}/messages) and for the paint after it. Opening another one first drops this one,
	 * and one that failed to load is not a time to open.
	 */
	const conversationOpening = (ulid: string, channel: string | null) => {
		stopWaitingForConversation?.()

		if (typeof PerformanceObserver === "undefined") {
			return
		}

		const openedAt = performance.now()
		const observer = new PerformanceObserver((list) => {
			const loaded = (list.getEntries() as PerformanceResourceTiming[])
				.find((entry) => entry.startTime >= openedAt && entry.name.includes(`sessions/${ulid}/messages`))

			if (!loaded) {
				return
			}

			stop()

			if (((loaded as PerformanceResourceTiming & { responseStatus?: number }).responseStatus ?? 0) >= 400) {
				return
			}

			painted().then(() => record({ measure: "conversation_open", duration_ms: performance.now() - openedAt, channel }, openedAt))
		})
		const giveUp = setTimeout(() => stop(), GIVE_UP_MS)
		const stop = () => {
			observer.disconnect()
			clearTimeout(giveUp)

			if (stopWaitingForConversation === stop) {
				stopWaitingForConversation = null
			}
		}

		stopWaitingForConversation = stop
		observer.observe({ type: "resource" })
	}

	/** From pressing Send until the server took the reply. */
	const replySending = (channel: string | null) => {
		const sentAt = performance.now()

		return () => record({ measure: "reply_sent", duration_ms: performance.now() - sentAt, channel }, sentAt)
	}

	const stop = () => {
		stopWaitingForConversation?.()
		freezeObserver?.disconnect()

		if (flushTimer) {
			clearInterval(flushTimer)
		}

		document.removeEventListener("visibilitychange", onHidden)
		flush()
	}

	return { start, conversationOpening, replySending, stop }
}
