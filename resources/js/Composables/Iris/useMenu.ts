/**
 * Author: Vika Aqordi
 * Created on 17-10-2025-13h-12m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

export interface ProductCategoryMenuSub {
    name: string
    url: string
    collections: {
        id: string
        url: string
        name: string
    }[]
    families: {
        name: string
        url: string
    }[]
}

export interface ProductCategoryMenu {
    name: string
    url: string
    collections: {
        id: string
        url: string
        name: string
        families?: {
            name: string
            url: string
        }[]
    }[]
    sub_departments: ProductCategoryMenuSub[]
}

interface CustomMenu {
    id: string
    icon: {}
    type: string  // 'multiple' | 'single'
    label: string
    subnavs: {
        id: string
        link: {
            id: string | null
            href: string | null
            type: "internal" | "external"
            target: "_self" | "_blank"
            workshop: string | null
        }
        links: {
            id: string
            icon: {}
            link: {
                id: string | null
                href: string | null
                type: "internal" | "external"
                target: "_self" | "_blank"
                workshop: string | null
            }
            label: string
        }[]
        title: string
    }[]
}




const byName = (a: { name?: string }, b: { name?: string }) =>
    (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' })

// Method: Convert structure menu Product Categories to Menu structure
// Mirrors the sidebar panels (IrisSidebar.vue): departments sorted, second level
// is sub_departments merged with collections, third level is sorted families
export const menuCategoriesToMenuStructure = (categories: ProductCategoryMenu[]) => {

    if (!categories || categories.length === 0) {
        return []
    }

    return [...categories].sort(byName).map((department) => {
        const children = [
            ...(department.sub_departments || []),
            ...(department.collections || [])
        ].filter((child) => child?.name).sort(byName)

        return {
            id: `menu_dept_${department.name.toLowerCase().replace(/\s+/g, '_')}`,
            icon: {},
            type: children.length ? 'multiple' : 'single',
            label: department.name,
            link: {
                href: department.url,
                target: "_self",
                type: "internal",
            },
            collections: department.collections,
            subnavs: children.length ? children.map((child) => {
                return {
                    id: `menu_subdept_${child.name.toLowerCase().replace(/\s+/g, '_')}`,
                    link: {
                        id: null,
                        href: child.url,
                        type: "internal",
                        target: "_self",
                        workshop: null
                    },
                    collections: 'collections' in child ? child.collections : undefined,
                    links: child.families?.length ? [...child.families].sort(byName).map((family) => {
                        return {
                            id: `menu_family_${family.name.toLowerCase().replace(/\s+/g, '_')}`,
                            icon: {},
                            link: {
                                id: null,
                                href: family.url,
                                type: "internal",
                                target: "_self",
                                workshop: null
                            },
                            label: family.name
                        }
                    }) : undefined,
                    title: child.name
                }
            }) : undefined
        }
    })
}
