export const resolveInternalLinkRoute = (routeGetInternalLink?: routeType): string | null => {
	if (routeGetInternalLink?.name) {
		return route(routeGetInternalLink.name, routeGetInternalLink.parameters ?? {})
	}

	const { organisation, website, shop, fulfilment } = route().params
	if (!website) {
		return null
	}

	return fulfilment
		? route("grp.org.fulfilments.show.web.webpages.index", { organisation, website, fulfilment })
		: route("grp.org.shops.show.web.webpages.index", { organisation, website, shop })
}
