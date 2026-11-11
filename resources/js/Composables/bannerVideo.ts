export type BannerVideo = { kind: "file" | "iframe", src: string }

const youtubeId = (url: URL): string | null => {
	const host = url.hostname.replace(/^(www|m)\./, "")
	if (host === "youtu.be") {
		return url.pathname.split("/")[1] || null
	}
	if (host === "youtube.com" || host === "youtube-nocookie.com") {
		return url.searchParams.get("v") ?? url.pathname.match(/^\/(?:embed|shorts|live)\/([^/?#]+)/)?.[1] ?? null
	}

	return null
}

const vimeoVideo = (url: URL): { id: string, hash: string | null } | null => {
	if (!url.hostname.replace(/^www\./, "").endsWith("vimeo.com")) {
		return null
	}
	const match = url.pathname.match(/\/(\d{5,})(?:\/([0-9a-f]{6,}))?/)

	return match ? { id: match[1], hash: match[2] ?? url.searchParams.get("h") } : null
}

export const bannerVideo = (rawUrl?: string | null): BannerVideo | null => {
	if (!rawUrl?.trim()) {
		return null
	}

	let url: URL
	try {
		url = new URL(rawUrl.trim())
	} catch {
		return null
	}

	if (/\.(mp4|webm|ogg|mov)$/i.test(url.pathname)) {
		return { kind: "file", src: url.href }
	}

	const youtube = youtubeId(url)
	if (youtube) {
		return { kind: "iframe", src: `https://www.youtube.com/embed/${youtube}?autoplay=1&mute=1&loop=1&playlist=${youtube}&controls=0&playsinline=1` }
	}

	const vimeo = vimeoVideo(url)
	if (vimeo) {
		return { kind: "iframe", src: `https://player.vimeo.com/video/${vimeo.id}?${vimeo.hash ? `h=${vimeo.hash}&` : ""}background=1` }
	}

	return null
}
