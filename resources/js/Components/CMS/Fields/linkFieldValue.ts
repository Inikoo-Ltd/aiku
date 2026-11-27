export function withDefaultLinkType<T>(value: T): T | Record<string, any> {
	if (value === null || typeof value !== "object") {
		return value
	}

	if (Array.isArray(value) && value.length > 0) {
		return value
	}

	const link = (Array.isArray(value) ? {} : value) as Record<string, any>

	if (link.type) {
		return value
	}

	return {
		href: null,
		id: null,
		url: null,
		data: {},
		...link,
		type: "internal",
		target: link.target ?? "_self",
	}
}
