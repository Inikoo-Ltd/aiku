/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 02:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import type { router as inertiaRouter } from "@inertiajs/vue3"

let lastVisitStartedAt: number | null = null

/**
 * When the visit to the page now opening began, so a page can say how long it kept somebody
 * waiting: the browser's own list of requests stops recording after 250, which a grp tab passes
 * within minutes, and does not follow redirects. Partial reloads and prefetches are not someone
 * going to a page. Back and Forward put a page back without a visit, so they leave nothing to time.
 */
export const trackVisitStarts = (router: typeof inertiaRouter) => {
	router.on("before", (event) => {
		const visit = event.detail.visit

		if (visit.only?.length || visit.except?.length || visit.prefetch) {
			return
		}

		lastVisitStartedAt = performance.now()
	})

	window.addEventListener("popstate", () => {
		lastVisitStartedAt = null
	})
}

/** Handed out once: the next page to ask did not come from that visit. */
export const takeVisitStart = (): number | null => {
	const startedAt = lastVisitStartedAt
	lastVisitStartedAt = null

	return startedAt
}
