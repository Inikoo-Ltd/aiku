export type VideoCarouselSource = {
	platform: "upload" | "instagram" | "tiktok"
	video_url: string | null
	poster_url: string | null
	permalink: string | null
	tiktok_id?: string
}

export type VideoCarouselItem = {
	source: VideoCarouselSource
	link?: { href?: string; target?: string; type?: string; canonical_url?: string } | null
	link_label?: string | null
}

export type VideoCarouselFieldValue = {
	id?: string
	text?: string
	container?: { properties?: any }
	tile?: { properties?: any }
	settings?: {
		slidesPerView?: { desktop?: number; tablet?: number; mobile?: number }
		spaceBetween?: number
		autoplay?: boolean
		loop?: boolean
		arrows?: boolean
	}
	videos?: VideoCarouselItem[]
	button?: {
		text?: string
		show?: boolean
		link?: { href?: string; target?: string }
		container?: { properties?: any }
	}
}
