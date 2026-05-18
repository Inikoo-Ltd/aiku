<script setup lang='ts'>
import { inject, computed, ref } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useTruncate } from '@/Composables/useTruncate'
import { trans } from 'laravel-vue-i18n'
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { MenuItem } from '@headlessui/vue'
import Image from "@common/Components/Image.vue";
import { Image as ImageTS } from '@/types/Image'
import axios from 'axios'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faChevronRight, faWarehouseAlt } from '@fal'
library.add(faChevronRight, faWarehouseAlt)

const props = defineProps<{
    menuItems: {
        slug?: string
        logo?: ImageTS
        label: string
    }[]
    menuKey?: string  // 'group'
    imageSkeleton: {
        [key: string]: boolean
    }
    label: string
    icon: string | string[]
    closeMenu?: () => void
}>()

const layout = inject('layout', layoutStructure)

// Computed property untuk mengurutkan menuItems berdasarkan alphabet
const sortedMenuItems = computed(() => {
    if (!props.menuItems || !Array.isArray(props.menuItems)) return []

    // Jika menuKey adalah 'group', tidak perlu sort karena biasanya cuma 1 item
    if (props.menuKey === 'group') {
        return props.menuItems
    }

    // Sort berdasarkan label secara alphabetical untuk organisasi
    return [...props.menuItems].sort((a, b) => {
        const labelA = (a.label || '').toLowerCase()
        const labelB = (b.label || '').toLowerCase()
        return labelA.localeCompare(labelB, 'en', { numeric: true, sensitivity: 'base' })
    })
})

// Method: on click Organisation/Agents/Digital Agency
const onClickOrg = async (slug?: string) => {
    if (!slug) return

    const currentRoute = layout.currentRoute || route().current() || ''
    const currentRouteParams = layout.currentParams || { ...route().params }

    // If user currently in Shop page
    if (currentRouteParams.shop) {
        const rememberedShop = layout.organisationsState?.[slug]?.currentShop
        const targetEntity = layout.organisations.data.find((org: any) => org.slug === slug) ||
                            layout.agents.data.find((agent: any) => agent.slug === slug)
        const shopIsValid = (targetEntity as any)?.authorised_shops?.find(
            (s: any) => s.slug === rememberedShop && s.state !== 'closed'
        )

        if (rememberedShop && shopIsValid) {
            router.visit(route(currentRoute, { ...currentRouteParams, organisation: slug, shop: rememberedShop }))
        } else {
            router.visit(route('grp.org.dashboard.show', { organisation: slug }))
        }
        return
    }

    // If user currently in Fulfilment page
    if (currentRouteParams.fulfilment) {
        const rememberedFulfilment = layout.organisationsState?.[slug]?.currentFulfilment
        const targetEntity = layout.organisations.data.find((org: any) => org.slug === slug) ||
                            layout.agents.data.find((agent: any) => agent.slug === slug)
        const fulfilmentIsValid = (targetEntity as any)?.authorised_fulfilments?.find(
            (f: any) => f.slug === rememberedFulfilment
        )

        if (rememberedFulfilment && fulfilmentIsValid) {
            router.visit(route(currentRoute, { ...currentRouteParams, organisation: slug, fulfilment: rememberedFulfilment }))
        } else {
            router.visit(route('grp.org.dashboard.show', { organisation: slug }))
        }
        return
    }

    // If user currently in Warehouse page
    if (currentRouteParams.warehouse) {
        const rememberedWarehouse = layout.organisationsState?.[slug]?.currentWarehouse
        const targetEntity = layout.organisations.data.find((org: any) => org.slug === slug) ||
                            layout.agents.data.find((agent: any) => agent.slug === slug)
        const warehouseIsValid = (targetEntity as any)?.authorised_warehouses?.find(
            (w: any) => w.slug === rememberedWarehouse
        )

        if (rememberedWarehouse && warehouseIsValid) {
            router.visit(route(currentRoute, { ...currentRouteParams, organisation: slug, warehouse: rememberedWarehouse }))
        } else {
            router.visit(route('grp.org.dashboard.show', { organisation: slug }))
        }
        return
    }


    try {
        console.log('topbar dropdown scope 12')
        if (!currentRoute.includes('grp.org.') && !currentRoute.includes('grp.agent.')) {
            throw new Error('Redirect to dashboard')
        }

        const response = await axios.get(route('grp.profile.can_visit'))

        if (!!response.data) {
            try {
                router.visit(route(currentRoute, { ...currentRouteParams, organisation: slug }))
            } catch {
                router.visit(route('grp.org.dashboard.show', { organisation: slug }))
            }
        } else {
            throw new Error('Redirect to dashboard')
        }
    } catch (error) {
        console.error(error)
        router.visit(route('grp.org.dashboard.show', { organisation: slug }))
    }
}







// Section: Second popover (shops and fulfilment) on hover Organisation
const hoveredOrgSlug = ref<string | null>(null)
const isFlyoutVisible = ref(false)
const flyoutStyle = ref<{ top: string; left: string }>({ top: '0px', left: '0px' })
let hideTimeout: ReturnType<typeof setTimeout> | null = null
const hasShopsOrFulfilments = (item: { slug?: string }): boolean => {
    if (!item.slug) return false
    const org = layout.organisations.data.find((org: any) => org.slug === item.slug)
    const agent = layout.agents.data.find((agent: any) => agent.slug === item.slug)
    const shops = org?.authorised_shops || agent?.authorised_shops || []
    const fulfilments = org?.authorised_fulfilments || agent?.authorised_fulfilments || []
    const warehouses = org?.authorised_warehouses || agent?.authorised_warehouses || []
    return shops.length > 0 || fulfilments.length > 0 || warehouses.length > 0
}
const getShopsForOrg = (slug: string): any[] => {
    const org = layout.organisations.data.find((org: any) => org.slug === slug)
    const agent = layout.agents.data.find((agent: any) => agent.slug === slug)
    const shops = org?.authorised_shops || agent?.authorised_shops || []
    return shops.filter((shop: any) => shop.state !== 'closed')
}
const getFulfilmentsForOrg = (slug: string): any[] => {
    const org = layout.organisations.data.find((org: any) => org.slug === slug)
    const agent = layout.agents.data.find((agent: any) => agent.slug === slug)
    return org?.authorised_fulfilments || agent?.authorised_fulfilments || []
}
const getWarehousesForOrg = (slug: string): any[] => {
    const org = layout.organisations.data.find((org: any) => org.slug === slug)
    const agent = layout.agents.data.find((agent: any) => agent.slug === slug)
    return org?.authorised_warehouses || agent?.authorised_warehouses || []
}
const showFlyout = (item: { slug?: string }, event: MouseEvent) => {
    if (!hasShopsOrFulfilments(item)) return
    if (hideTimeout) clearTimeout(hideTimeout)
    const target = event.currentTarget as HTMLElement
    const rect = target.getBoundingClientRect()
    flyoutStyle.value = {
        top: `${rect.top}px`,
        left: `${rect.right + 4}px`,
    }
    hoveredOrgSlug.value = item.slug ?? null
    isFlyoutVisible.value = true
}
const hideFlyout = () => {
    hideTimeout = setTimeout(() => {
        isFlyoutVisible.value = false
        hoveredOrgSlug.value = null
    }, 150)
}
const keepFlyout = () => {
    if (hideTimeout) clearTimeout(hideTimeout)
}

// Method: on click side popover (shops/fulfilments/warehouses)
const navigateToSubOrg = (sub: typeof sortedShowareList.value[number], typeSub: string) => {
    console.log('topbar dropdown scope', sub)
    const visitNormally = () => {
        router.visit(route(sub.route?.name, sub.route?.parameters))
    }

    const paramsLength = Object.keys(layout.currentParams || route().routeParams || {}).length

    if (layout.currentParams?.organisation && paramsLength === 1) { // ✅
        visitNormally()
    } else if (paramsLength === 2) {
        if (layout.currentParams?.organisation && layout.currentParams?.shop && typeSub === 'shop') { // ✅
            router.visit(route(layout.currentRoute, { organisation: hoveredOrgSlug.value, shop: sub.slug }))
        } else if (layout.currentParams?.organisation && layout.currentParams?.warehouse && typeSub === 'warehouse') { // ✅
            router.visit(route(layout.currentRoute, { organisation: hoveredOrgSlug.value, warehouse: sub.slug }))
        } else if (layout.currentParams?.organisation && layout.currentParams?.fulfilment && typeSub === 'fulfilment') { // ✅
            router.visit(route(layout.currentRoute, { organisation: hoveredOrgSlug.value, fulfilment: sub.slug }))
        } else { // ✅
            visitNormally()
        }
    } else if (paramsLength > 2) {
        if (layout.currentParams?.organisation && layout.currentParams?.shop && typeSub === 'shop') {
            try {
                router.visit(route(layout.currentRoute, { organisation: hoveredOrgSlug.value, shop: sub.slug }))
            } catch {
                visitNormally()
            }
        } else if (layout.currentParams?.organisation && layout.currentParams?.warehouse && typeSub === 'warehouse') {
            try {
                router.visit(route(layout.currentRoute, { organisation: hoveredOrgSlug.value, warehouse: sub.slug }))
            } catch {
                visitNormally()
            }
        } else if (layout.currentParams?.organisation && layout.currentParams?.fulfilment && typeSub === 'fulfilment') {
            try {
                router.visit(route(layout.currentRoute, { organisation: hoveredOrgSlug.value, fulfilment: sub.slug }))
            } catch {
                visitNormally()
            }
        } else {
            visitNormally()
        }
    } else {
        visitNormally()
    }
}
</script>

<template>
    <div>
        <div class="flex items-center gap-x-1.5 px-1 mb-1">
            <FontAwesomeIcon :icon="icon" class="text-gray-400 text-xxs" aria-hidden="true" />
            <span class="text-[9px] leading-none text-gray-400 whitespace-nowrap">{{ label }}</span>
            <hr class="w-full rounded-full border-slate-300">
        </div>

        <div class="max-h-52 overflow-y-auto space-y-1.5">
            <template v-if="menuKey === 'group'">
                <MenuItem v-slot="{ active }">
                <div @click="() => router.visit(route('grp.dashboard.show'))" :class="[
                    sortedMenuItems[0].slug == layout.currentParams?.organisation ? 'bg-slate-300 text-slate-600' : 'text-slate-600 hover:bg-slate-200/75 hover:text-indigo-600',
                    'group flex gap-x-2 w-full justify-start items-center rounded pl-2 pr-4 py-2 text-sm cursor-pointer',
                ]">
                    <FontAwesomeIcon icon="fal fa-city" class="" ariaa-hidden="true" />
                    <div class="space-x-1 whitespace-nowrap">
                        <span class="font-semibold">{{ layout.group?.label }}</span>
                        <span class="text-[9px] leading-none text-gray-400">({{ trans("Group") }})</span>
                    </div>
                </div>
                </MenuItem>
            </template>

            <template v-else>
                <MenuItem v-for="(item, index) in sortedMenuItems" :key="item.slug || index" v-slot="{ active }">
                <div
                    @mouseenter="(e) => showFlyout(item, e as MouseEvent)"
                    @mouseleave="hideFlyout"
                    :class="[
                        item.slug == layout.currentParams?.organisation
                            ? 'bg-slate-300 text-slate-600'
                            : hoveredOrgSlug === item.slug && hasShopsOrFulfilments(item)
                                ? ''
                                : 'text-slate-600 hover:bg-slate-200/75',
                    ]"
                    class="group flex gap-x-2 w-full justify-between items-center rounded pl-2 pr-2 py-2 text-sm cursor-pointer"
                    :style="item.slug == layout.currentParams?.organisation
                            ? {
                                backgroundColor: 'color-mix(in srgb, var(--theme-color-0) 40%, transparent)',
                                color: 'color-mix(in srgb, var(--theme-color-0) 80%, black)',
                            }
                            : hoveredOrgSlug === item.slug && hasShopsOrFulfilments(item)
                                ? {
                                    backgroundColor: 'color-mix(in srgb, var(--theme-color-0) 15%, transparent)',
                                    color: 'color-mix(in srgb, var(--theme-color-0) 80%, black)',
                                    }
                                : {}"
                >
                    <div @click="() => onClickOrg(item.slug)" class="flex items-center gap-x-2 flex-1 min-w-0">
                        <div class="h-5 aspect-square rounded-full overflow-hidden ring-1 ring-slate-200 bg-slate-50 flex-shrink-0">
                            <Image v-show="!imageSkeleton[item.slug]" :src="item.logo"
                                @onLoadImage="() => imageSkeleton[item.slug] = false" />
                            <div v-show="imageSkeleton[item.slug]" class="skeleton w-5 h-5" />
                        </div>
                        <div class="font-semibold whitespace-nowrap">{{ useTruncate(item.label, 20) }}</div>
                    </div>
                    <FontAwesomeIcon
                        v-if="hasShopsOrFulfilments(item)"
                        icon="fal fa-chevron-right"
                        class="text-xs flex-shrink-0 transition-colors"
                        :style="hoveredOrgSlug === item.slug ? { color: 'var(--theme-color-0)' } : {}"
                        :class="hoveredOrgSlug === item.slug ? '' : 'text-gray-400'"
                        aria-hidden="true"
                    />
                </div>
                </MenuItem>
            </template>
        </div>

        <Teleport to="body">
            <div
                v-if="isFlyoutVisible && hoveredOrgSlug"
                :style="{ top: flyoutStyle.top, left: flyoutStyle.left }"
                class="fixed z-[200] w-56 bg-white rounded-lg shadow-lg ring-1 ring-black/5 p-2 max-h-96 overflow-y-auto"
                @mouseenter="keepFlyout"
                @mouseleave="hideFlyout"
            >
                <!-- Section: Shops list -->
                <template v-if="getShopsForOrg(hoveredOrgSlug).length">
                    <div class="flex items-center gap-x-1.5 px-1 mb-1">
                        <FontAwesomeIcon icon="fal fa-store-alt" class="text-gray-400 text-xxs" aria-hidden="true" />
                        <span class="text-[9px] leading-none text-gray-400 whitespace-nowrap">{{ trans('Shops') }}</span>
                        <hr class="w-full rounded-full border-slate-300">
                    </div>
                    <div
                        v-for="shop in getShopsForOrg(hoveredOrgSlug)"
                        :key="shop.id"
                        @click="navigateToSubOrg(shop, 'shop')"
                        :class="[
                            'flex gap-x-2 w-full justify-between items-center rounded pl-2 pr-2 py-1.5 text-sm cursor-pointer transition-colors',
                            shop.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentShop && layout.organisationsState?.[hoveredOrgSlug]?.currentType === 'shop'
                                ? ''
                                : 'text-slate-600 hover:bg-slate-200/75',
                        ]"
                        :style="shop.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentShop && layout.organisationsState?.[hoveredOrgSlug]?.currentType === 'shop' ? {
                            backgroundColor: 'color-mix(in srgb, var(--theme-color-0) 20%, transparent)',
                            color: 'color-mix(in srgb, var(--theme-color-0) 80%, black)',
                        } : {}"
                    >
                        <div class="flex flex-col">
                            <span class="font-semibold">{{ shop.label }}</span>
                            <span v-if="shop.website_domain" class="text-xs opacity-60 italic">{{ shop.website_domain }}</span>
                        </div>
                    </div>
                </template>

                <!-- Section: Fulfilment list -->
                <template v-if="getFulfilmentsForOrg(hoveredOrgSlug).length">
                    <div class="flex items-center gap-x-1.5 px-1 mb-1" :class="getShopsForOrg(hoveredOrgSlug).length ? 'mt-2' : ''">
                        <FontAwesomeIcon icon="fal fa-hand-holding-box" class="text-gray-400 text-xxs" aria-hidden="true" />
                        <span class="text-[9px] leading-none text-gray-400 whitespace-nowrap">{{ trans('Fulfilments') }}</span>
                        <hr class="w-full rounded-full border-slate-300">
                    </div>
                    <div
                        v-for="fulfilment in getFulfilmentsForOrg(hoveredOrgSlug)"
                        :key="fulfilment.id"
                        @click="navigateToSubOrg(fulfilment, 'fulfilment')"
                        :class="[
                            'flex gap-x-2 w-full justify-between items-center rounded pl-2 pr-2 py-1.5 text-sm cursor-pointer transition-colors',
                            fulfilment.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentFulfilment && layout.organisationsState?.[hoveredOrgSlug]?.currentType === 'fulfilment'
                                ? ''
                                : 'text-slate-600 hover:bg-slate-200/75',
                        ]"
                        :style="fulfilment.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentFulfilment && layout.organisationsState?.[hoveredOrgSlug]?.currentType === 'fulfilment' ? {
                            backgroundColor: 'color-mix(in srgb, var(--theme-color-0) 20%, transparent)',
                            color: 'color-mix(in srgb, var(--theme-color-0) 80%, black)',
                        } : {}"
                    >
                        <div class="font-semibold">{{ fulfilment.label }}</div>
                    </div>
                </template>

                <!-- Section: Warehouse list -->
                <template v-if="getWarehousesForOrg(hoveredOrgSlug).length">
                    <div class="flex items-center gap-x-1.5 px-1 mb-1" :class="getShopsForOrg(hoveredOrgSlug).length || getFulfilmentsForOrg(hoveredOrgSlug).length ? 'mt-2' : ''">
                        <FontAwesomeIcon icon="fal fa-warehouse-alt" class="text-gray-400 text-xxs" aria-hidden="true" />
                        <span class="text-[9px] leading-none text-gray-400 whitespace-nowrap">{{ trans('Warehouses') }}</span>
                        <hr class="w-full rounded-full border-slate-300">
                    </div>
                    <div
                        v-for="warehouse in getWarehousesForOrg(hoveredOrgSlug)"
                        :key="warehouse.id"
                        @click="navigateToSubOrg(warehouse, 'warehouse')"
                        :class="[
                            'flex gap-x-2 w-full justify-between items-center rounded pl-2 pr-2 py-1.5 text-sm cursor-pointer transition-colors',
                            warehouse.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentWarehouse
                                ? ''
                                : 'text-slate-600 hover:bg-slate-200/75',
                        ]"
                        :style="warehouse.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentWarehouse ? {
                            backgroundColor: 'color-mix(in srgb, var(--theme-color-0) 20%, transparent)',
                            color: 'color-mix(in srgb, var(--theme-color-0) 80%, black)',
                        } : {}"
                    >
                        <div class="font-semibold">{{ warehouse.label }}</div>
                    </div>
                </template>
            </div>
        </Teleport>
    </div>
</template>