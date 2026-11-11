/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import axios, { type AxiosResponse } from "axios"
import type { router as inertiaRouter } from "@inertiajs/vue3"

const STORAGE_KEY = "inbox-first-list"
const FRESH_MS = 10_000
const INBOX_PATH = /^\/org\/([^/]+)\/(?:shops\/([^/]+)\/)?chat\/(inbox|supervision)(\/[0-9A-HJKMNP-TV-Z]{26})?$/
const CHANNELS = ["whatsapp", "website"]
const HOME_SLOT = `${STORAGE_KEY}:home`
const PLAIN_INBOX_SLOT = new RegExp(`^${STORAGE_KEY}:[^:]+:inbox$`)

type Request = { url: string, params: Record<string, unknown> }

let pending: { key: string, startedAt: number, response: Promise<AxiosResponse> } | null = null

export const listKey = (url: string, params: Record<string, unknown>): string => url + JSON.stringify(params)

/**
 * Each inbox address opens on its own list: the organisation, inbox or supervision, the shop it
 * was opened from, a conversation's own address (its shop, channel and status pick the list) and
 * the channel a notification link asks for. /chat/inbox redirects to the organisation's inbox, so
 * it opens on the list an organisation's inbox opened with last.
 */
const slotOf = (address: URL): string | null => {
	if (address.pathname === "/chat/inbox") {
		return HOME_SLOT
	}

	const match = address.pathname.match(INBOX_PATH)

	if (!match) {
		return null
	}

	const channel = address.searchParams.get("channel")

	return [STORAGE_KEY, match[1], match[3], match[2] && `shop:${match[2]}`, match[4] && "conversation", CHANNELS.includes(channel ?? "") && channel]
		.filter(Boolean)
		.join(":")
}

/** What the inbox asked for when it last opened on this address, so the next visit there can ask for it straight away. */
export const rememberFirstList = (url: string, params: Record<string, unknown>, address: string = window.location.href) => {
	const slot = slotOf(new URL(address, window.location.href))

	if (!slot) {
		return
	}

	try {
		window.localStorage.setItem(slot, JSON.stringify({ url, params }))

		if (PLAIN_INBOX_SLOT.test(slot)) {
			window.localStorage.setItem(HOME_SLOT, JSON.stringify({ url, params }))
		}
	} catch {
		return
	}
}

const rememberedIn = (slot: string): Request | null => {
	try {
		return JSON.parse(window.localStorage.getItem(slot) ?? "null")
	} catch {
		return null
	}
}

const askForListRememberedAt = (address: URL) => {
	const slot = slotOf(address)
	const remembered = slot ? rememberedIn(slot) : null

	if (!remembered?.url || !remembered.params) {
		return
	}

	const response = axios.get(remembered.url, { params: remembered.params })
	response.catch(() => {})
	pending = { key: listKey(remembered.url, remembered.params), startedAt: performance.now(), response }
}

/**
 * The inbox list is its own request, and asked for from the page it could only leave once the page
 * was drawn (INI-187). Going to the inbox asks for the list it opened with last time as the visit
 * starts, so both answers travel together. Partial reloads and hover prefetches go nowhere. Back and
 * Forward restore the page from history without a visit, so they drop any answer asked for before
 * and ask for the list of the page they return to: its address as history keeps it, since the
 * inbox writes the open conversation into the address bar.
 */
export const prefetchInboxListOnVisit = (router: typeof inertiaRouter) => {
	router.on("before", (event) => {
		const visit = event.detail.visit

		if (visit.only?.length || visit.except?.length || visit.prefetch || visit.method !== "get") {
			return
		}

		askForListRememberedAt(visit.url)
	})

	window.addEventListener("popstate", (event: PopStateEvent) => {
		pending = null

		if (event.state?.page) {
			askForListRememberedAt(new URL(event.state.page.url ?? window.location.href, window.location.href))
		}
	})
}

/**
 * The answer already on its way, if it is for exactly this list; handed out once. outcome says
 * whether one was there to use (prefetched), there for another list or too old (missed), or never
 * asked for (fetched), so production shows why the inbox had to ask.
 */
export const takePrefetchedList = (url: string, params: Record<string, unknown>): { response: Promise<AxiosResponse> | null, outcome: "prefetched" | "missed" | "fetched" } => {
	const found = pending
	pending = null

	if (!found) {
		return { response: null, outcome: "fetched" }
	}

	if (found.key !== listKey(url, params) || performance.now() - found.startedAt > FRESH_MS) {
		return { response: null, outcome: "missed" }
	}

	return { response: found.response, outcome: "prefetched" }
}
