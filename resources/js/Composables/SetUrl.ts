export const resolveMigrationLink = (
	href?: string,
	migration_redirect?: MigrationRedirect | null
): string | undefined => {
	if (!href || !migration_redirect?.need_changes_url || !migration_redirect?.to_url) return href

	try {
		const current = new URL(href, migration_redirect.need_changes_url[0])

		for (const fromUrl of migration_redirect.need_changes_url) {
			const from = new URL(fromUrl)
			const to = new URL(migration_redirect.to_url)

			if (current.origin === from.origin) {
				return to.origin + current.pathname + current.search + current.hash
			}
		}
	} catch {
		return href
	}

	return href
}
export const resolveMigrationHrefInHTML = (
	html?: string,
	migration_redirect?: MigrationRedirect | null
): string => {
	if (!html || !migration_redirect?.need_changes_url || !migration_redirect.to_url) return html

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

