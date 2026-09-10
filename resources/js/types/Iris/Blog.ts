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

export type BlogCategoryContent = {
	label?: string
	description?: string
	image?: Image
	image_alt?: string
}

export type BlogCategory = {
	value: string
	label: string
	custom_label?: string
	description: string
	url: string
	icon: string
	fallback_image?: string
	count: number
	image_src?: Image
	image_alt?: string
	third_party_image_preview?: string
}
