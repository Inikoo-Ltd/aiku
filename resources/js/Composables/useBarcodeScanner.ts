/*
 *  Author: Vika Aqordi <aqordivika@yahoo.co.id>
 *  Github: aqordeon
 *  Created: Mon, 10 August 2026 10:00:00 Bali, Indonesia
 *  Copyright (c) 2026, Vika Aqordi
 */

import { onBeforeUnmount, onMounted, ref } from "vue"
import { debounce } from "lodash-es"

// A keyboard wedge scanner types the whole code in a few milliseconds. Anything typed faster than
// this threshold is treated as machine input, which is what lets us auto submit codes coming from
// scanners configured without an Enter suffix without also submitting half typed manual entries.
const MACHINE_KEYSTROKE_INTERVAL_MS = 40
const IDLE_SUBMIT_DELAY_MS = 120
const MIN_MACHINE_CODE_LENGTH = 3

/**
 * Turns the whole page into a scan target: keystrokes are captured wherever the operator is looking
 * as long as they are not typing into something else, and each completed code is handed to onCode.
 */
export const useBarcodeScanner = (onCode: (code: string) => void) => {
	const buffer = ref("")
	const inputElement = ref<HTMLInputElement | null>(null)
	const isListening = ref(true)

	let lastKeystrokeAt = 0
	let looksLikeMachineInput = false

	// Keystrokes are only hijacked while the operator is not interacting with something else, so the
	// scanner works without ever clicking the field but the table search, modals and buttons still
	// behave normally.
	const shouldIgnoreKeydown = (element: EventTarget | null) => {
		const node = element as HTMLElement | null

		if (!node || node === inputElement.value) {
			return false
		}

		if (document.querySelector(".p-dialog, [role='dialog']")) {
			return true
		}

		return (
			node.tagName === "INPUT" ||
			node.tagName === "TEXTAREA" ||
			node.tagName === "SELECT" ||
			node.tagName === "BUTTON" ||
			node.tagName === "A" ||
			node.getAttribute("role") === "button" ||
			node.isContentEditable
		)
	}

	// A scanner configured without an Enter suffix never terminates the code, so the quiet gap right
	// after a burst of machine speed keystrokes is what marks the code as complete.
	const submitWhenScannerWentQuiet = debounce(() => {
		if (looksLikeMachineInput && buffer.value.trim().length >= MIN_MACHINE_CODE_LENGTH) {
			flushBuffer()
		}
	}, IDLE_SUBMIT_DELAY_MS)

	const registerKeystroke = () => {
		const now = performance.now()
		looksLikeMachineInput = now - lastKeystrokeAt < MACHINE_KEYSTROKE_INTERVAL_MS
		lastKeystrokeAt = now

		submitWhenScannerWentQuiet()
	}

	const clearBuffer = () => {
		submitWhenScannerWentQuiet.cancel()
		buffer.value = ""
		looksLikeMachineInput = false
	}

	const flushBuffer = () => {
		submitWhenScannerWentQuiet.cancel()

		const code = buffer.value.trim()
		buffer.value = ""
		looksLikeMachineInput = false

		if (!code) {
			return
		}

		onCode(code)
	}

	const onKeydown = (event: KeyboardEvent) => {
		if (!isListening.value) {
			return
		}

		if (event.target === inputElement.value) {
			return
		}

		if (shouldIgnoreKeydown(event.target) || event.ctrlKey || event.metaKey || event.altKey) {
			return
		}

		if (event.key === "Enter") {
			if (buffer.value) {
				event.preventDefault()
				flushBuffer()
			}
			return
		}

		if (event.key === "Escape") {
			clearBuffer()
			return
		}

		if (event.key.length !== 1) {
			return
		}

		event.preventDefault()
		buffer.value += event.key
		registerKeystroke()
		inputElement.value?.focus()
	}

	onMounted(() => {
		window.addEventListener("keydown", onKeydown)
		inputElement.value?.focus()
	})

	onBeforeUnmount(() => {
		window.removeEventListener("keydown", onKeydown)
		submitWhenScannerWentQuiet.cancel()
	})

	return { buffer, inputElement, isListening, registerKeystroke, clearBuffer, flushBuffer }
}

/**
 * Scans arrive faster than the server can answer, so they are queued and sent one at a time: a
 * quantity is always resolved against what the previous scan already wrote.
 */
export const useScanQueue = <T>(submit: (scan: T) => Promise<void>) => {
	const queuedCount = ref(0)
	const pendingScans: T[] = []
	let isDraining = false

	const drainQueue = async () => {
		if (isDraining) {
			return
		}

		isDraining = true

		while (pendingScans.length) {
			const scan = pendingScans.shift() as T
			queuedCount.value = pendingScans.length
			await submit(scan)
		}

		isDraining = false
	}

	const enqueueScan = (scan: T) => {
		pendingScans.push(scan)
		queuedCount.value = pendingScans.length
		drainQueue()
	}

	return { queuedCount, enqueueScan }
}
