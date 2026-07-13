let autocompletePromise: Promise<void> | null = null

export const loadLuigiAutocomplete = (): Promise<void> => {
	if (!autocompletePromise) {
		autocompletePromise = new Promise((resolve, reject) => {
			if (!document.querySelector('link[href="https://cdn.luigisbox.tech/autocomplete.css"]')) {
				const link = document.createElement("link")
				link.rel = "stylesheet"
				link.href = "https://cdn.luigisbox.tech/autocomplete.css"
				document.head.appendChild(link)
			}

			if (typeof (window as any).AutoComplete === "function") {
				resolve()
				return
			}

			const existing = document.querySelector<HTMLScriptElement>('script[src="https://cdn.luigisbox.tech/autocomplete.js"]')
			if (existing) {
				existing.addEventListener("load", () => resolve(), { once: true })
				existing.addEventListener("error", () => reject(new Error("Failed to load Luigi autocomplete script")), { once: true })
				return
			}

			const script = document.createElement("script")
			script.src = "https://cdn.luigisbox.tech/autocomplete.js"
			script.async = true
			script.onload = () => resolve()
			script.onerror = () => reject(new Error("Failed to load Luigi autocomplete script"))
			document.head.appendChild(script)
		})
	}

	return autocompletePromise
}

export const onFirstInteractionOrIdle = (callback: () => void, idleDelayMs = 3500): void => {
	let fired = false
	const events = ["pointerdown", "keydown", "touchstart", "scroll"]

	const trigger = () => {
		if (fired) return
		fired = true
		events.forEach((e) => window.removeEventListener(e, trigger))
		callback()
	}

	events.forEach((e) => window.addEventListener(e, trigger, { once: true, passive: true }))

	if (document.readyState === "complete") {
		window.setTimeout(trigger, idleDelayMs)
	} else {
		window.addEventListener("load", () => window.setTimeout(trigger, idleDelayMs), { once: true })
	}
}
