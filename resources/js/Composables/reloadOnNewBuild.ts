import type { Router } from "@inertiajs/core"

export function reloadOnNewBuild(router: Router): void {
	router.on("location", (event) => {
		if (!event.detail.versionChange
			|| document.activeElement?.closest("input, textarea, select, [contenteditable], [role=combobox], iframe")
			|| document.querySelector("[role=dialog]")) {
			return
		}
		try {
			if (Date.now() - Number(sessionStorage.getItem("aikuVersionReloadAt") || 0) < 60000) {
				return
			}
			sessionStorage.setItem("aikuVersionReloadAt", String(Date.now()))
			sessionStorage.setItem("inertiaLocationVisit", JSON.stringify({ preserveScroll: true }))
		} catch {
			return
		}
		event.preventDefault()
		const url = event.detail.url
		if (url.href.split("#")[0] === window.location.href.split("#")[0]) {
			window.location.reload()
		} else {
			window.location.href = url.href
		}
	})
}
