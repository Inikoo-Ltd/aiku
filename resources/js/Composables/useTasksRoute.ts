/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

type TasksScopeParameters = { organisation?: string; shop?: string }

const tasksScope = (): { prefix: string; parameters: TasksScopeParameters } => {
    const { organisation, shop } = (route().params ?? {}) as TasksScopeParameters

    if (organisation && shop) {
        return { prefix: "grp.org.shops.show.tasks", parameters: { organisation, shop } }
    }

    if (organisation) {
        return { prefix: "grp.org.tasks", parameters: { organisation } }
    }

    return { prefix: "grp.tasks", parameters: {} }
}

export const tasksRouteObject = (suffix: string, parameters: Record<string, any> = {}) => {
    const scope = tasksScope()

    return { name: `${scope.prefix}.${suffix}`, parameters: { ...scope.parameters, ...parameters } }
}

export const tasksRoute = (suffix: string, parameters: Record<string, any> = {}): string => {
    const { name, parameters: routeParameters } = tasksRouteObject(suffix, parameters)

    return route(name, routeParameters)
}

export const taskRoute = (reference: string): string => tasksRoute("show", { staffTask: reference })
