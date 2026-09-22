<script setup lang='ts'>
import { inject, computed, onBeforeUnmount, onMounted, ref } from 'vue'
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
import { faChevronRight, faChevronLeft, faWarehouseAlt, faTimes, faArrowRight } from '@fal'
library.add(faChevronRight, faChevronLeft, faWarehouseAlt, faTimes, faArrowRight)

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

const themeColor = computed(() => layout.app?.theme?.[0] || '#4f46e5')

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

const findEntityBySlug = (slug: string) =>
    layout.organisations.data.find((org: any) => org.slug === slug) ||
    layout.agents.data.find((agent: any) => agent.slug === slug)

const resolveRouteOrFallback = (routeName: string, routeParams: Record<string, string>, fallbackHref: string): string => {
    try {
        return route(routeName, routeParams)
    } catch {
        return fallbackHref
    }
}

const isOrgRouteNeedingPermissionCheck = (currentRoute: string, currentRouteParams: Record<string, string>): boolean =>
    !currentRouteParams.shop &&
    !currentRouteParams.fulfilment &&
    !currentRouteParams.warehouse &&
    (currentRoute.includes('grp.org.') || currentRoute.includes('grp.agent.'))

const getOrgHref = (slug?: string): string | undefined => {
    if (!slug) return undefined

    const currentRoute = layout.currentRoute || route().current() || ''
    const currentRouteParams = layout.currentParams || { ...route().params }
    const dashboardHref = route('grp.org.dashboard.show', { organisation: slug })
    const targetEntity = findEntityBySlug(slug) as any
    const orgState = layout.organisationsState?.[slug]

    if (currentRouteParams.shop) {
        const rememberedShop = orgState?.currentShop
        const shopIsValid = targetEntity?.authorised_shops?.find(
            (s: any) => s.slug === rememberedShop && s.state !== 'closed'
        )
        return rememberedShop && shopIsValid
            ? resolveRouteOrFallback(currentRoute, { ...currentRouteParams, organisation: slug, shop: rememberedShop }, dashboardHref)
            : dashboardHref
    }

    if (currentRouteParams.fulfilment) {
        const rememberedFulfilment = orgState?.currentFulfilment
        const fulfilmentIsValid = targetEntity?.authorised_fulfilments?.find(
            (f: any) => f.slug === rememberedFulfilment
        )
        return rememberedFulfilment && fulfilmentIsValid
            ? resolveRouteOrFallback(currentRoute, { ...currentRouteParams, organisation: slug, fulfilment: rememberedFulfilment }, dashboardHref)
            : dashboardHref
    }

    if (currentRouteParams.warehouse) {
        const rememberedWarehouse = orgState?.currentWarehouse
        const warehouseIsValid = targetEntity?.authorised_warehouses?.find(
            (w: any) => w.slug === rememberedWarehouse
        )
        return rememberedWarehouse && warehouseIsValid
            ? resolveRouteOrFallback(currentRoute, { ...currentRouteParams, organisation: slug, warehouse: rememberedWarehouse }, dashboardHref)
            : dashboardHref
    }

    if (!isOrgRouteNeedingPermissionCheck(currentRoute, currentRouteParams)) {
        return dashboardHref
    }

    return resolveRouteOrFallback(currentRoute, { ...currentRouteParams, organisation: slug }, dashboardHref)
}

// Method: on click Organisation/Agents/Digital Agency
const onClickOrg = async (event: MouseEvent, slug?: string) => {
    if (!slug) return

    const isOpeningInNewTabOrWindow = event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
    if (isOpeningInNewTabOrWindow) return

    event.preventDefault()

    const currentRoute = layout.currentRoute || route().current() || ''
    const currentRouteParams = layout.currentParams || { ...route().params }
    const dashboardHref = route('grp.org.dashboard.show', { organisation: slug })
    const targetHref = getOrgHref(slug) ?? dashboardHref

    if (!isOrgRouteNeedingPermissionCheck(currentRoute, currentRouteParams)) {
        router.visit(targetHref)
        return
    }

    try {
        const response = await axios.get(route('grp.profile.can_visit'))
        router.visit(response.data ? targetHref : dashboardHref)
    } catch (error) {
        console.error(error)
        router.visit(dashboardHref)
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
const mobileQuery = globalThis.window?.matchMedia?.('(max-width: 1023px)')
const isMobile = ref(mobileQuery?.matches ?? false)
const onMobileQueryChange = (event: MediaQueryListEvent) => (isMobile.value = event.matches)
onMounted(() => mobileQuery?.addEventListener('change', onMobileQueryChange))
onBeforeUnmount(() => mobileQuery?.removeEventListener('change', onMobileQueryChange))

const currentPlaceLabel = computed(() => {
    const params = layout.currentParams ?? {}
    const entity: any = params.organisation ? findEntityBySlug(params.organisation) : null
    if (params.shop) return entity?.authorised_shops?.find((shop: any) => shop.slug === params.shop)?.label ?? params.shop
    if (params.fulfilment) return entity?.authorised_fulfilments?.find((fulfilment: any) => fulfilment.slug === params.fulfilment)?.label ?? params.fulfilment
    if (params.warehouse) return entity?.authorised_warehouses?.find((warehouse: any) => warehouse.slug === params.warehouse)?.label ?? params.warehouse
    return null
})

const panelOrg = computed(() => (hoveredOrgSlug.value ? findEntityBySlug(hoveredOrgSlug.value) as any : null))

const closePanel = () => {
    isFlyoutVisible.value = false
    hoveredOrgSlug.value = null
}

const onOrgRowClick = (event: MouseEvent, item: { slug?: string }) => {
    if (!isMobile.value) {
        onClickOrg(event, item.slug)
        return
    }
    event.preventDefault()
    if (hasShopsOrFulfilments(item)) {
        hoveredOrgSlug.value = item.slug ?? null
        isFlyoutVisible.value = true
        return
    }
    onClickOrg(event, item.slug)
    props.closeMenu?.()
}

const openPanelOrg = (event: MouseEvent) => {
    const slug = hoveredOrgSlug.value
    closePanel()
    onClickOrg(event, slug ?? undefined)
    props.closeMenu?.()
}

const selectSubOrg = (sub: any, typeSub: string) => {
    navigateToSubOrg(sub, typeSub)
    if (isMobile.value) {
        closePanel()
        props.closeMenu?.()
    }
}

const showFlyout = (item: { slug?: string }, event: MouseEvent) => {
    if (isMobile.value || !hasShopsOrFulfilments(item)) return
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
    if (isMobile.value) return
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

    const visitNormally = () => {
        router.visit(route(sub.route?.name, sub.route?.parameters))
    }

    const paramsLength = Object.keys(layout.currentParams || route().routeParams || {}).length

    if (layout.currentParams?.organisation && paramsLength === 1) { // ✅
        visitNormally()
    } else if (paramsLength === 2) {
        if (layout.currentParams?.organisation && typeSub === 'fulfilment') {
            try {
                router.visit(route(layout.currentRoute, { organisation: sub.org_slug, fulfilment: sub.slug }))
            } catch {
                visitNormally()
            }
        } else if (layout.currentParams?.organisation && typeSub === 'warehouse') {
            try {
                router.visit(route(layout.currentRoute, { organisation: sub.org_slug, warehouse: sub.slug }))
            } catch (e) {
                console.log('cathch', e)
                visitNormally()
            }
        } else if (layout.currentParams?.organisation && typeSub === 'shop') {
            try {
                router.visit(route(layout.currentRoute, { organisation: sub.org_slug, shop: sub.slug }))
            } catch {
                visitNormally()
            }
        } else { // ✅
            visitNormally()
        }

    } else if (paramsLength === 3 && layout.currentParams?.website) {
        if (layout.currentParams?.organisation && typeSub === 'fulfilment') {
            try {
                router.visit(route(layout.currentRoute, { organisation: sub.org_slug, fulfilment: sub.slug, website: sub.website_slug }))
            } catch {
                visitNormally()
            }
        } else if (layout.currentParams?.organisation && typeSub === 'warehouse') {
            try {
                router.visit(route(layout.currentRoute, { organisation: sub.org_slug, warehouse: sub.slug, website: sub.website_slug }))
            } catch {
                visitNormally()
            }
        } else if (layout.currentParams?.organisation && typeSub === 'shop') {
            try {
                router.visit(route(layout.currentRoute, { organisation: sub.org_slug, shop: sub.slug, website: sub.website_slug }))
            } catch {
                visitNormally()
            }
        } else { // ✅
            visitNormally()
        }
    } else if (paramsLength > 2) {
        if (layout.currentParams?.organisation && layout.currentParams?.shop && typeSub === 'shop') {
            try {
                router.visit(route(layout.currentRoute, { organisation: sub.org_slug, shop: sub.slug }))
            } catch {
                visitNormally()
            }
        } else if (layout.currentParams?.organisation && layout.currentParams?.warehouse && typeSub === 'warehouse') {
            try {
                router.visit(route(layout.currentRoute, { organisation: sub.org_slug, warehouse: sub.slug }))
            } catch {
                visitNormally()
            }
        } else if (layout.currentParams?.organisation && layout.currentParams?.fulfilment && typeSub === 'fulfilment') {
            try {
                router.visit(route(layout.currentRoute, { organisation: sub.org_slug, fulfilment: sub.slug }))
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
            <FontAwesomeIcon :icon="icon" class="text-gray-400 text-xxs" fixed-width aria-hidden="true" />
            <span class="text-[9px] leading-none text-gray-400 whitespace-nowrap">{{ label }}</span>
            <hr class="w-full rounded-full border-slate-300">
        </div>

        <div class="space-y-1.5 lg:max-h-52 lg:overflow-y-auto">
            <template v-if="menuKey === 'group'">
                <MenuItem v-slot="{ active }">
                <div @click="() => router.visit(route('grp.dashboard.show'))" :class="[
                    sortedMenuItems[0].slug == layout.currentParams?.organisation ? 'bg-slate-300 text-slate-600' : 'text-slate-600 hover:bg-slate-200/75 hover:text-indigo-600',
                    'group flex gap-x-2 w-full justify-start items-center rounded pl-2 pr-4 py-2 text-sm cursor-pointer',
                ]">
                    <FontAwesomeIcon icon="fal fa-city" class="" fixed-width aria-hidden="true" />
                    <div class="space-x-1 whitespace-nowrap">
                        <span class="font-semibold">{{ layout.group?.label }}</span>
                        <span class="text-[9px] leading-none text-gray-400">({{ trans("Group") }})</span>
                    </div>
                </div>
                </MenuItem>
            </template>

            <template v-else>
                <component :is="isMobile ? 'div' : MenuItem" v-for="(item, index) in sortedMenuItems" :key="item.slug || index">
                <a
                    :href="getOrgHref(item.slug)"
                    @mouseenter="(e) => showFlyout(item, e as MouseEvent)"
                    @mouseleave="hideFlyout"
                    @click="(e) => onOrgRowClick(e as MouseEvent, item)"
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
                                backgroundColor: `color-mix(in srgb, ${themeColor} 40%, transparent)`,
                                color: `color-mix(in srgb, ${themeColor} 80%, black)`,
                            }
                            : hoveredOrgSlug === item.slug && hasShopsOrFulfilments(item)
                                ? {
                                    backgroundColor: `color-mix(in srgb, ${themeColor} 15%, transparent)`,
                                    color: `color-mix(in srgb, ${themeColor} 80%, black)`,
                                    }
                                : {}"
                >
                    <div class="flex items-center gap-x-2 flex-1 min-w-0">
                        <div class="h-5 aspect-square rounded-full overflow-hidden ring-1 ring-slate-200 bg-slate-50 flex-shrink-0">
                            <Image v-show="!imageSkeleton[item.slug]" :src="item.logo"
                                @onLoadImage="() => imageSkeleton[item.slug] = false" />
                            <div v-show="imageSkeleton[item.slug]" class="skeleton w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold whitespace-nowrap">{{ useTruncate(item.label, 20) }}</div>
                            <div v-if="isMobile && item.slug == layout.currentParams?.organisation && currentPlaceLabel" class="truncate text-xs font-normal opacity-80">{{ currentPlaceLabel }}</div>
                        </div>
                    </div>
                    <FontAwesomeIcon
                        v-if="hasShopsOrFulfilments(item)"
                        icon="fal fa-chevron-right"
                        class="text-xs flex-shrink-0 transition-colors"
                        :style="hoveredOrgSlug === item.slug && item.slug != layout.currentParams?.organisation ? { color: themeColor } : {}"
                        :class="item.slug == layout.currentParams?.organisation ? 'opacity-70' : hoveredOrgSlug === item.slug ? '' : 'text-gray-400'"
                        fixed-width aria-hidden="true"
                    />
                </a>
                </component>
            </template>
        </div>

        <Teleport to="body" :disabled="isMobile">
            <Transition
                :enter-active-class="isMobile ? 'transition duration-200 ease-out' : ''"
                :enter-from-class="isMobile ? '-translate-x-full' : ''"
                :leave-active-class="isMobile ? 'transition duration-150 ease-in' : ''"
                :leave-to-class="isMobile ? '-translate-x-full' : ''">
            <div
                v-if="isFlyoutVisible && hoveredOrgSlug"
                :style="isMobile ? {} : { top: flyoutStyle.top, left: flyoutStyle.left }"
                class="fixed z-[200] bg-white overflow-y-auto"
                :class="isMobile ? 'inset-y-0 left-0 w-72 max-w-[85vw] px-2 py-3 shadow-xl' : 'w-56 rounded-lg shadow-lg ring-1 ring-black/5 p-2 max-h-96'"
                @mouseenter="keepFlyout"
                @mouseleave="hideFlyout"
            >
                <div v-if="isMobile" class="mb-3 flex items-center gap-x-1 border-b border-gray-200 pb-3">
                    <button type="button" class="shrink-0 rounded p-2 text-gray-400 hover:text-gray-700" :aria-label="trans('Back')" @click="closePanel">
                        <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                    </button>
                    <a
                        :href="getOrgHref(hoveredOrgSlug)"
                        class="flex min-w-0 flex-1 items-center gap-x-2 rounded px-2 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                        @click="(e) => openPanelOrg(e as MouseEvent)"
                    >
                        <div class="h-6 aspect-square shrink-0 overflow-hidden rounded-full bg-slate-50 ring-1 ring-slate-200">
                            <Image :src="panelOrg?.logo" />
                        </div>
                        <span class="truncate">{{ panelOrg?.label }}</span>
                        <FontAwesomeIcon icon="fal fa-arrow-right" class="ml-auto shrink-0 text-xs text-gray-400" fixed-width aria-hidden="true" />
                    </a>
                </div>
                <!-- Section: Shops list -->
                <template v-if="getShopsForOrg(hoveredOrgSlug).length">
                    <div class="flex items-center gap-x-1.5 px-1 mb-1">
                        <FontAwesomeIcon icon="fal fa-store-alt" class="text-gray-400 text-xxs" fixed-width aria-hidden="true" />
                        <span class="text-[9px] leading-none text-gray-400 whitespace-nowrap">{{ trans('Shops') }}</span>
                        <hr class="w-full rounded-full border-slate-300">
                    </div>
                    <div
                        v-for="shop in getShopsForOrg(hoveredOrgSlug)"
                        :key="shop.id"
                        @click="selectSubOrg(shop, 'shop')"
                        :class="[
                            'flex gap-x-2 w-full min-h-[3rem] justify-between items-center rounded pl-2 pr-2 py-1.5 text-sm cursor-pointer transition-colors',
                            shop.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentShop && layout.organisationsState?.[hoveredOrgSlug]?.currentType === 'shop'
                                ? ''
                                : 'text-slate-600 hover:bg-slate-200/75',
                        ]"
                        :style="shop.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentShop && layout.organisationsState?.[hoveredOrgSlug]?.currentType === 'shop' ? {
                            backgroundColor: `color-mix(in srgb, ${themeColor} 20%, transparent)`,
                            color: `color-mix(in srgb, ${themeColor} 80%, black)`,
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
                        <FontAwesomeIcon icon="fal fa-hand-holding-box" class="text-gray-400 text-xxs" fixed-width aria-hidden="true" />
                        <span class="text-[9px] leading-none text-gray-400 whitespace-nowrap">{{ trans('Fulfilments') }}</span>
                        <hr class="w-full rounded-full border-slate-300">
                    </div>
                    <div
                        v-for="fulfilment in getFulfilmentsForOrg(hoveredOrgSlug)"
                        :key="fulfilment.id"
                        @click="selectSubOrg(fulfilment, 'fulfilment')"
                        :class="[
                            'flex gap-x-2 w-full justify-between items-center rounded pl-2 pr-2 py-1.5 text-sm cursor-pointer transition-colors',
                            fulfilment.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentFulfilment && layout.organisationsState?.[hoveredOrgSlug]?.currentType === 'fulfilment'
                                ? ''
                                : 'text-slate-600 hover:bg-slate-200/75',
                        ]"
                        :style="fulfilment.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentFulfilment && layout.organisationsState?.[hoveredOrgSlug]?.currentType === 'fulfilment' ? {
                            backgroundColor: `color-mix(in srgb, ${themeColor} 20%, transparent)`,
                            color: `color-mix(in srgb, ${themeColor} 80%, black)`,
                        } : {}"
                    >
                        <div class="font-semibold">{{ fulfilment.label }}</div>
                    </div>
                </template>

                <!-- Section: Warehouse list -->
                <template v-if="getWarehousesForOrg(hoveredOrgSlug).length">
                    <div class="flex items-center gap-x-1.5 px-1 mb-1" :class="getShopsForOrg(hoveredOrgSlug).length || getFulfilmentsForOrg(hoveredOrgSlug).length ? 'mt-2' : ''">
                        <FontAwesomeIcon icon="fal fa-warehouse-alt" class="text-gray-400 text-xxs" fixed-width aria-hidden="true" />
                        <span class="text-[9px] leading-none text-gray-400 whitespace-nowrap">{{ trans('Warehouses') }}</span>
                        <hr class="w-full rounded-full border-slate-300">
                    </div>
                    <div
                        v-for="warehouse in getWarehousesForOrg(hoveredOrgSlug)"
                        :key="warehouse.id"
                        @click="selectSubOrg(warehouse, 'warehouse')"
                        :class="[
                            'flex gap-x-2 w-full justify-between items-center rounded pl-2 pr-2 py-1.5 text-sm cursor-pointer transition-colors',
                            warehouse.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentWarehouse
                                ? ''
                                : 'text-slate-600 hover:bg-slate-200/75',
                        ]"
                        :style="warehouse.slug === layout.organisationsState?.[hoveredOrgSlug]?.currentWarehouse ? {
                            backgroundColor: `color-mix(in srgb, ${themeColor} 20%, transparent)`,
                            color: `color-mix(in srgb, ${themeColor} 80%, black)`,
                        } : {}"
                    >
                        <div class="font-semibold">{{ warehouse.label }}</div>
                    </div>
                </template>
            </div>
            </Transition>
        </Teleport>
    </div>
</template>