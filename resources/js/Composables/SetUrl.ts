export const resolveMigrationLink = (
	href?: string,
	migration_redirect?: [string, string] | null
): string | undefined => {
	if (!href || !migration_redirect || !migration_redirect[0] || !migration_redirect[1]) return href

	try {
		const from = new URL(migration_redirect[0])
		const to = new URL(migration_redirect[1])
		const current = new URL(href, from.origin)

		// Only replace origin if matched
		if (current.origin === from.origin) {
			console.log('ddd')
			return to.origin + current.pathname + current.search + current.hash
		}
	} catch {
		// Ignore parsing errors and return original href
		return href
	}

	return href
}

export const resolveMigrationHrefInHTML = (
	html?: string,
	migration_redirect?: [string, string] | null
): string => {
	if (!html || !migration_redirect || !migration_redirect[0] || !migration_redirect[1]) return html

	const tempEl = document.createElement('div')
	tempEl.innerHTML = html

	const anchors = tempEl.querySelectorAll('a[href]')
	anchors.forEach((a) => {
		const originalHref = a.getAttribute('href') || ''
		const updatedHref = resolveMigrationLink(originalHref, migration_redirect)
		if (updatedHref) a.setAttribute('href', updatedHref)
	})

	return tempEl.innerHTML
}


