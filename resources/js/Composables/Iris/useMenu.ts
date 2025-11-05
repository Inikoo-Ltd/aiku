/**
 * Author: Vika Aqordi
 * Created on 17-10-2025-13h-12m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/


export interface ProductCategoryMenu {
    name: string
    url: string
    collections: {
        id: string
        url: string
        name: string
    }[]
    sub_departments: {
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
    }[]
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




// Method: Convert structure menu Product Categories to Menu structure
export const menuCategoriesToMenuStructure = (categories: ProductCategoryMenu[]) => {

    if (!categories || categories.length === 0) {
        return []
    }

    const aaa = categories.map((department) => {
        return {
            id: `menu_dept_${department.name.toLowerCase().replace(/\s+/g, '_')}`,
            icon: {},
            type: department.sub_departments.length ? 'multiple' : 'single',
            label: department.name,
            link: {
                href: department.url,
                target: "_self",
                type: "internal",
            },
            collections: department.collections,
            subnavs: department.sub_departments.length ? department.sub_departments.map((subDept) => {
                return {
                    id: `menu_subdept_${subDept.name.toLowerCase().replace(/\s+/g, '_')}`,
                    link: {
                        id: null,
                        href: subDept.url,
                        type: "internal",
                        target: "_self",
                        workshop: null
                    },
                    collections: subDept.collections,
                    links: subDept.families.length ? subDept.families.map((family) => {
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
                    title: subDept.name
                }
            }) : undefined
        }
    })

    return aaa
}
