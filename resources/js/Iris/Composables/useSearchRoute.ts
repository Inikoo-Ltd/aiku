export const searchRoute = (name: 'catalogue' | 'catalogue_page' | 'click' | 'featured_items'): string => {
    const irisRouteName = `iris.json.search.${name}`

    return route().has(irisRouteName as any) ? irisRouteName : `retina.json.search.${name}`
}
