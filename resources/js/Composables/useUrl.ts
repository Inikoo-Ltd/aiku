import { usePage } from "@inertiajs/vue3"
import { inject } from "vue"

// from "http://app.aiku.test/org/sk/inventory" to "/org/sk/inventory"
export const removeDomain = (fullUrl: string, domain: string) => {
    if(!fullUrl) return ''

    const domainRegex = new RegExp(`https?://${domain}`, 'i')

    return fullUrl.replace(domainRegex, '')
}

// Check if current route is same as the given route
export const isRouteSameAsCurrentUrl = (expectedRoute: string) => {
    return usePage().url.includes(removeDomain(expectedRoute, route().v().route.domain))
}

// routeRoot: the route.root to indicates a group of Navigation ('grp.org.fulfilments.show.operations.pallets.current.index' is exist in 'grp.org.fulfilments.show.operations.')
export const isNavigationActive = (layoutRoute: string, routeRoot: string | undefined) => {
    const injectLayout = inject('layout')
    
    if(!routeRoot) return false

    const isCurrentRouteIncludesRouteRoot = layoutRoute.includes(routeRoot)
    const isRootActiveIncludesNavRoot = injectLayout?.root_active ? injectLayout?.root_active?.includes(routeRoot) : false

    console.log('2 isRootActiveIncludesNavRoot', layoutRoute, injectLayout?.root_active, isRootActiveIncludesNavRoot)
    return isCurrentRouteIncludesRouteRoot || isRootActiveIncludesNavRoot
}