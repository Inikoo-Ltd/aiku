export type DraggedImage = Record<string, any> & { id: number | string }

export type ImageDropAction =
	| { type: "attach"; image: DraggedImage }
	| { type: "upload"; files: File[] }
	| { type: "none" }

function parseDraggedImage(raw: string | undefined | null): DraggedImage | null {
	if (!raw) {
		return null
	}

	try {
		const parsed = JSON.parse(raw)

		return parsed?.id ? parsed : null
	} catch {
		return null
	}
}

/**
 * Browsers may expose an internally dragged image as a file as well, so the
 * dragged payload always takes precedence over the dropped files.
 */
export function resolveImageDropAction(
	dataTransfer: DataTransfer | null | undefined
): ImageDropAction {
	const image = parseDraggedImage(dataTransfer?.getData("application/json"))

	if (image) {
		return { type: "attach", image }
	}

	const files = Array.from(dataTransfer?.files ?? [])

	return files.length ? { type: "upload", files } : { type: "none" }
}
