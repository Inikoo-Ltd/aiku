import type { Image } from "@/types/Image"

export type BlogPost = {
	id: number
	title: string
	image_src?: Image
	image_alt?: string
	third_party_image_preview?: string
	url?: string
	published_at?: string
}
