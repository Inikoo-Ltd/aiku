/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { createTooltip, destroyTooltip } from "floating-vue"

/**
 * floating-vue mounts one tooltip component per v-tooltip, even if it is never hovered: a 50-row
 * chat inbox with 29 per row is ~1,450 of them on every open (INI-145). An element with a
 * data-tooltip attribute instead gets the same tooltip only while the pointer or focus is on it,
 * and loses it when the element leaves the page (a row removed by a realtime update).
 */
export const startSharedTooltip = (): void => {
	let current: HTMLElement | null = null
	const removal = new MutationObserver(() => {
		if (current && !current.isConnected) hide()
	})

	const hide = () => {
		if (current) destroyTooltip(current)
		current = null
		removal.disconnect()
	}

	const showFor = (target: EventTarget | null) => {
		const element = (target as Element | null)?.closest?.("[data-tooltip]") as HTMLElement | null
		if (element === current) return
		hide()
		if (!element?.dataset.tooltip) return

		current = element
		createTooltip(element, { content: element.dataset.tooltip, shown: true }, {})
		removal.observe(document.body, { childList: true, subtree: true })
	}

	const hideOnLeave = (event: MouseEvent | FocusEvent) => {
		if (current && !current.contains(event.relatedTarget as Node | null)) hide()
	}

	document.addEventListener("mouseover", (event) => showFor(event.target))
	document.addEventListener("mouseout", hideOnLeave)
	document.addEventListener("focusin", (event) => showFor(event.target))
	document.addEventListener("focusout", hideOnLeave)
}
