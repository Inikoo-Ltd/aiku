/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

type TicketsScopeParameters = { organisation?: string; shop?: string }

const ticketsScope = (): { prefix: string; parameters: TicketsScopeParameters } => {
    const { organisation, shop } = (route().params ?? {}) as TicketsScopeParameters

    if (organisation && shop) {
        return { prefix: "grp.org.shops.show.tickets", parameters: { organisation, shop } }
    }

    if (organisation) {
        return { prefix: "grp.org.tickets", parameters: { organisation } }
    }

    return { prefix: "grp.tickets", parameters: {} }
}

export const ticketsRouteObject = (suffix: string, parameters: Record<string, any> = {}) => {
    const scope = ticketsScope()

    return { name: `${scope.prefix}.${suffix}`, parameters: { ...scope.parameters, ...parameters } }
}

export const ticketsRoute = (suffix: string, parameters: Record<string, any> = {}): string => {
    const { name, parameters: routeParameters } = ticketsRouteObject(suffix, parameters)

    return route(name, routeParameters)
}

export const ticketRoute = (reference: string): string => ticketsRoute("show", { ticket: reference })
