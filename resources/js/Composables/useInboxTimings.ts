/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { nextTick } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { takeVisitStart } from "@/Composables/visitTiming"

type Measure = "inbox_open" | "inbox_page" | "conversation_open" | "conversation_fetch" | "reply_sent" | "freeze" | "slow_frame"

type Sample = {
	measure: Measure
	duration_ms: number
	channel?: string | null
	is_full_load?: boolean | null
	render_ms?: number | null
	source?: string | null
	page?: string | null
}

type FrameScript = {
	invoker: string
	sourceURL: string
	sourceFunctionName: string
	sourceCharPosition: number
	duration: number
}

type SlowFrame = PerformanceEntry & { blockingDuration?: number, styleAndLayoutStart?: number, scripts?: FrameScript[] }

const FREEZE_MS = 200
const FLUSH_EVERY_MS = 30_000
const GIVE_UP_MS = 30_000
const LONGEST_MS = 120_000
const RESPONSES_KEPT = 20

let hasStartedInThisTab = false
let lastHiddenAt = document.visibilityState === "hidden" ? performance.now() : -1
let queue: Sample[] = []
let isFlushing = false
let isRecordingFreezes = false
let currentPage: () => string | null = () => null
const finishedResponses: { path: string, at: number }[] = []

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

/** One queue per tab, sent every 30 s and when the tab is hidden or left. */
const keepFlushing = () => {
	if (isFlushing) {
		return
	}

	isFlushing = true
	setInterval(flush, FLUSH_EVERY_MS)
	document.addEventListener("visibilitychange", () => {
		if (document.visibilityState === "hidden") {
			flush()
		}
	})
}

/** A sample longer than two minutes is a measuring accident, and the server would refuse the whole batch over it. */
const record = (sample: Sample, startedAt: number) => {
	if (!Number.isFinite(sample.duration_ms) || sample.duration_ms < 0 || sample.duration_ms > LONGEST_MS || wasHiddenSince(startedAt)) {
		return
	}

	queue.push({ ...sample, duration_ms: Math.round(sample.duration_ms), page: currentPage()?.slice(0, 100) ?? null })

	if (queue.length >= 50) {
		flush()
	}
}

/** The file part of a script address, so a page address never brings its query (searches, filters) along. */
const fileOf = (address: string | undefined): string => (address ?? "").split(/[?#]/)[0].split("/").pop() ?? ""

/**
 * The browser names a request handler only as XMLHttpRequest.onloadend, so the requests that
 * finished inside the frame are added in front (path only, never the query): that says whether
 * it was the inbox list, the staff rail or something else.
 */
const requestsDuring = (frame: SlowFrame): string => {
	if (!frame.scripts?.some((script) => script.invoker.startsWith("XMLHttpRequest"))) {
		return ""
	}

	const paths = finishedResponses
		.filter((response) => response.at >= frame.startTime && response.at <= frame.startTime + frame.duration)
		.map((response) => response.path)

	// Capped so the scripts after it, the main finding, always fit the 255-character column.
	return paths.length ? `req ${[...new Set(paths)].join(" ")}`.slice(0, 100) + " | " : ""
}

/**
 * What a freeze was made of, so production says which code to fix: the longest scripts in the
 * frame (file and position in the built chunk, as function names are minified) and how long
 * style and layout took at its end. Vue's re-renders show as its scheduler in the vendor chunk.
 */
const describeSlowFrame = (frame: SlowFrame): Sample => ({
	measure: "slow_frame",
	duration_ms: frame.duration,
	render_ms: frame.styleAndLayoutStart ? Math.max(0, Math.round(frame.startTime + frame.duration - frame.styleAndLayoutStart)) : null,
	source: (requestsDuring(frame) + [...(frame.scripts ?? [])]
		.sort((a, b) => b.duration - a.duration)
		.slice(0, 3)
		.map((script) => `${fileOf(script.invoker)} ${fileOf(script.sourceURL)}:${script.sourceCharPosition} ${script.sourceFunctionName ?? ""} ${Math.round(script.duration)}`)
		.join(" | "))
		.slice(0, 255) || null,
})

const rememberResponse = (url: string | undefined) => {
	try {
		finishedResponses.push({ path: new URL(url ?? "", window.location.href).pathname, at: performance.now() })
	} catch {
		return
	}

	if (finishedResponses.length > RESPONSES_KEPT) {
		finishedResponses.shift()
	}
}

/**
 * Every freeze of 200 ms or more on any grp page, with the page it happened on, as the staff rail,
 * the badges and the dock run on all of them (INI-145). Started once per tab by the app; a same-origin
 * frame inside the page (the chat pane) is already seen by the page around it, so it is not counted twice.
 * Costs two performance observers and one short POST at most every 30 s while something froze.
 */
export const startFreezeRecording = (pageName: () => string | null) => {
	if (isRecordingFreezes || window.self !== window.top || typeof PerformanceObserver === "undefined") {
		return
	}

	isRecordingFreezes = true
	currentPage = pageName
	keepFlushing()

	axios.interceptors.response.use(
		(response) => {
			rememberResponse(response.config?.url)
			return response
		},
		(error) => {
			rememberResponse(error?.config?.url)
			return Promise.reject(error)
		},
	)
	// Inertia visits go through Inertia's own http client, not axios.
	router.on("finish", (event) => {
		rememberResponse(event.detail.visit.url.href)
	})

	if (PerformanceObserver.supportedEntryTypes?.includes("longtask")) {
		new PerformanceObserver((list) => {
			list.getEntries()
				.filter((entry) => entry.duration >= FREEZE_MS)
				.forEach((entry) => record({ measure: "freeze", duration_ms: entry.duration }, entry.startTime))
		}).observe({ type: "longtask" })
	}

	// A minimised or covered tab reports 2–4 s frames with no script and nothing blocked: the
	// browser was waiting to paint, not busy, so only frames that blocked the page count.
	if (PerformanceObserver.supportedEntryTypes?.includes("long-animation-frame")) {
		new PerformanceObserver((list) => {
			(list.getEntries() as SlowFrame[])
				.filter((entry) => entry.duration >= FREEZE_MS && (entry.blockingDuration ?? 0) > 0)
				.forEach((entry) => record(describeSlowFrame(entry), entry.startTime))
		}).observe({ type: "long-animation-frame" })
	}
}

/**
 * How long the Customer Inbox makes the person in front of it wait (INI-145). The server's share
 * is in NightOwl; this adds what only the browser sees: downloading and starting the page, painting
 * the list and the conversation. Freezes are recorded app-wide by startFreezeRecording. Samples are
 * kept and sent in batches, and whatever is left goes when the tab is hidden or the page is left.
 */
export const useInboxTimings = () => {
	let stopWaitingForConversation: (() => void) | null = null

	const painted = async () => {
		await nextTick()
		await new Promise<void>((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve())))
	}

	/**
	 * Where the wait for the inbox began: the click that started the visit when coming from another
	 * page (redirects included), or the start of the page when the whole app was loaded, which only
	 * the first inbox in a tab can be. Back restores the inbox without a visit, and the chat pane is
	 * an inbox inside another page, so neither is timed: nothing is recorded rather than a guess.
	 */
	const inboxStart = (): { at: number, isFullLoad: boolean } | null => {
		const visitStartedAt = takeVisitStart()

		if (window.self !== window.top) {
			return null
		}

		if (visitStartedAt !== null) {
			return { at: visitStartedAt, isFullLoad: false }
		}

		const navigation = performance.getEntriesByType("navigation")[0] as PerformanceNavigationTiming | undefined

		if (!hasStartedInThisTab && navigation && new URL(navigation.name).pathname === window.location.pathname) {
			return { at: 0, isFullLoad: true }
		}

		return null
	}

	/** Called on mount, before anything else changes the address. */
	const start = () => {
		const opened = inboxStart()
		hasStartedInThisTab = true

		keepFlushing()

		// The page's own share of the wait (request, download, first render), so the list's share
		// is what is left of inbox_open.
		if (opened) {
			record({ measure: "inbox_page", duration_ms: performance.now() - opened.at, is_full_load: opened.isFullLoad }, opened.at)
		}

		return {
			/** False when nothing new was asked for: Back restored the page, or it is the chat pane. */
			isVisit: !!opened,
			/** source says where the list came from, e.g. asked for as the visit started (inboxListPrefetch). */
			listPainted: async (isListLoaded: boolean, source: string | null = null) => {
				if (!opened || !isListLoaded) {
					return
				}

				await painted()
				record({ measure: "inbox_open", duration_ms: performance.now() - opened.at, is_full_load: opened.isFullLoad, source }, opened.at)
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

			record({ measure: "conversation_fetch", duration_ms: loaded.responseEnd - loaded.startTime, channel }, openedAt)
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
		flush()
	}

	return { start, conversationOpening, replySending, stop }
}
