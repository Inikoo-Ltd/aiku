export type ProductMediaImage = {
	source: any
	thumbnail?: any
	zoom?: any
	alt?: string
}

export type ProductMediaItem =
	| { type: "image"; image: ProductMediaImage; imageIndex: number }
	| { type: "video" }

const videoPosition = 1

export function buildProductMediaItems(
	images: ProductMediaImage[] | null | undefined,
	video?: string | null
): ProductMediaItem[] {
	const items: ProductMediaItem[] = (images ?? []).map((image, imageIndex) => ({
		type: "image",
		image,
		imageIndex,
	}))

	if (video) {
		items.splice(Math.min(videoPosition, items.length), 0, { type: "video" })
	}

	return items
}
