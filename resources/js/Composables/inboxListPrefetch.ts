/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import axios, { type AxiosResponse } from "axios"
import type { router as inertiaRouter } from "@inertiajs/vue3"

const STORAGE_KEY = "inbox-first-list"
const FRESH_MS = 10_000
const INBOX_PATH = /^\/org\/([^/]+)\/chat\/(inbox|supervision)(?:\/[0-9A-HJKMNP-TV-Z]{26})?$/

type Request = { url: string, params: Record<string, unknown> }

let pending: { key: string, startedAt: number, response: Promise<AxiosResponse> } | null = null

export const listKey = (url: string, params: Record<string, unknown>): string => url + JSON.stringify(params)

/** The organisation and the page (inbox or supervision) an inbox address is for. */
const slotOf = (path: string): string | null => {
	const match = path.match(INBOX_PATH)

	return match ? `${STORAGE_KEY}:${match[1]}:${match[2]}` : null
}

/** What the inbox asked for when it last opened on this page of this organisation, so the next visit can ask for it straight away. */
export const rememberFirstList = (url: string, params: Record<string, unknown>) => {
	const slot = slotOf(window.location.pathname)

	if (!slot) {
		return
	}

	try {
		window.localStorage.setItem(slot, JSON.stringify({ url, params }))
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

/**
 * The inbox list is its own request, and asked for from the page it could only leave once the page
 * was drawn (INI-187). Going to the inbox asks for the list it opened with last time as the visit
 * starts, so both answers travel together. Partial reloads and hover prefetches go nowhere.
 */
export const prefetchInboxListOnVisit = (router: typeof inertiaRouter) => {
	router.on("before", (event) => {
		const visit = event.detail.visit

		if (visit.only?.length || visit.except?.length || visit.prefetch || visit.method !== "get") {
			return
		}

		const slot = slotOf(visit.url.pathname)
		const remembered = slot ? rememberedIn(slot) : null

		if (!remembered?.url || !remembered.params) {
			return
		}

		const response = axios.get(remembered.url, { params: remembered.params })
		response.catch(() => {})
		pending = { key: listKey(remembered.url, remembered.params), startedAt: performance.now(), response }
	})

	window.addEventListener("popstate", () => {
		pending = null
	})
}

/** The answer already on its way, if it is for exactly this list; handed out once. */
export const takePrefetchedList = (url: string, params: Record<string, unknown>): Promise<AxiosResponse> | null => {
	const found = pending
	pending = null

	if (!found || found.key !== listKey(url, params) || performance.now() - found.startedAt > FRESH_MS) {
		return null
	}

	return found.response
}
