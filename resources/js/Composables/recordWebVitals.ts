/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 00:30:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { onCLS, onFCP, onINP, onLCP, onTTFB, type Metric } from "web-vitals"

let isRecording = false

/**
 * Measures the page the visitor landed on, as Google does: LCP, FCP and TTFB only exist for a full
 * page load, and CLS and INP keep growing until the page is hidden, so everything is sent once,
 * when the visitor leaves or switches away.
 */
export const recordWebVitals = (webpageId: number | null) => {
	if (isRecording) {
		return
	}

	isRecording = true

	const measured: Record<string, number> = {}
	let isSent = false

	const keep = (metric: Metric) => {
		measured[metric.name.toLowerCase()] = metric.name === "CLS" ? Math.round(metric.value * 1000) / 1000 : Math.round(metric.value)
	}

	onLCP(keep)
	onINP(keep)
	onCLS(keep)
	onFCP(keep)
	onTTFB(keep)

	const send = (event: Event) => {
		if (isSent || (event.type === "visibilitychange" && document.visibilityState !== "hidden") || !Object.keys(measured).length) {
			return
		}

		isSent = true

		const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? "")

		fetch("/analytics/web-vitals", {
			method: "POST",
			keepalive: true,
			headers: { "Content-Type": "application/json", Accept: "application/json", "X-XSRF-TOKEN": token },
			body: JSON.stringify({
				webpage_id: webpageId,
				device: window.innerWidth < 768 ? "phone" : "desktop",
				...measured,
			}),
		}).catch(() => {})
	}

	addEventListener("visibilitychange", send)
	addEventListener("pagehide", send)
}
