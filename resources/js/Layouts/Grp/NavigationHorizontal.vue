<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 08:27:43 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang='ts'>
import NavigationSimple from '@/Layouts/Grp/NavigationSimple.vue'
import { Navigation } from '@/types/Navigation'
import { Link } from '@inertiajs/vue3'
import { isNavigationActive } from '@/Composables/useUrl'
import { generateCurrentString } from '@/Composables/useConvertString'
import { inject, ref } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronLeft, faChevronRight } from '@fas'
import { faParachuteBox } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { capitalize } from '@/Composables/capitalize'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { set } from 'lodash-es'
library.add(faChevronLeft, faChevronRight, faParachuteBox)


const props = defineProps<{
    orgNav: {
        fulfilments_navigation: {
            label: string
            icon: string
            navigation: {
                [key: string]: {
                    type?: string
                    subNavigation: Navigation[]
                }
            }
        }
        shops_navigation: {
            label: string
            icon: string
            navigation: {
                [key: string]: {
                    type?: string  // 'dropshipping' || 'shop'
                    subNavigation: Navigation[]
                }
            }
        }
    }
    numberOptions?: number  // number of the Shops that open or Fulfilments that open
    // icon: string

}>()

interface MergeNavigation {
    key: string  // 'uk', 'awd'
    value: {
        type?: string
        subNavigation: Navigation[]
    }
    type: string  // 'shop', 'fulfilment
    root: string  // 'grp.org.fulfilments.show.'
}

const layout = inject('layout', layoutStructure)

// console.log('org', layout.organisationsState[layout.currentParams.organisation][`current${capitalize(layout.organisationsState[layout.currentParams.organisation].currentType)}`])

// Navigation Fulfilment, Shop, and Merged
const shopsOpenSlugs = layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_shops.filter(shop => shop.state !== 'closed').map(shop => shop.slug)
const fulfilmentsOpenSlugs = layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_fulfilments.filter(fulfilment => fulfilment.state !== 'closed').map(fulfilment => fulfilment.slug)
const fulfilmentsNav: () => MergeNavigation[] = () => {
    const filterFulfilmentsOpen = Object.entries(props.orgNav.fulfilments_navigation.navigation).filter(([key, subNavList]) => fulfilmentsOpenSlugs?.includes(key))

    return filterFulfilmentsOpen.map(([key, subNavObject]) => {
        return {
            key: key,
            value: {
                type: subNavObject.type,
                subNavigation: subNavObject.subNavigation
            },
            type: 'fulfilment',
            root: 'grp.org.fulfilments.'
        }
    })
}
const shopsNav: () => MergeNavigation[] = () => {
    const filterShopsOpen = Object.entries(props.orgNav.shops_navigation.navigation).filter(([key, subNavList]) => shopsOpenSlugs?.includes(key))

    return filterShopsOpen.map(([key, subNavObject]) => {
        return {
            key: key,
            value: {
                type: subNavObject.type,
                subNavigation: subNavObject.subNavigation
            },
            type: 'shop',
            root: 'grp.org.shops.'
        }
    })
}

const mergeNavigations = [...shopsNav(), ...fulfilmentsNav()]

const currentNavigation = () => {
    // { product: {...}, website: {...}, crm: {...} }
    const curre = mergeNavigations.find(mergeNav => {
        return mergeNav.key == layout.organisationsState?.[layout.currentParams.organisation]?.[generateCurrentString(activeNav())]
    })

    return curre
}

const isSomeSubnavActive = () => {
    return Object.values(currentNavigation() || {}).some(nav => (isNavigationActive(layout.currentRoute, currentNavigation()?.root)))
}

// Method: detect the active Nav is 'fulfilment' or 'shop'
const activeNav = () => {
    if (layout.currentRoute.includes('grp.org.fulfilments')) return 'fulfilment'
    if (layout.currentRoute.includes('grp.org.shops')) return 'shop'

    return layout.organisationsState[layout.currentParams.organisation].currentType
}

// Method: to get current slug depend on the type ('AWF')
const currentTypeSlug = () => {
    const currentType = layout.organisationsState[layout.currentParams.organisation].currentType  // 'shop' || 'fulfilment'
    return layout.organisationsState[layout.currentParams.organisation][`current${capitalize(currentType)}`]
}

// Route for arrow chevron
const previousNavigation = () => {
    const keySlug = currentTypeSlug()
    const indexNavigation = mergeNavigations.findIndex(navigation => navigation.key === keySlug)  // -1, 0, 2, 3

    return mergeNavigations[indexNavigation-1] || undefined
}
const nextNavigation = () => {
    const keySlug = currentTypeSlug()
    const indexNavigation = mergeNavigations.findIndex(navigation => navigation.key === keySlug)  // -1, 0, 2, 3

    return mergeNavigations[indexNavigation+1] || undefined
}
const routeArrow = (nav?: MergeNavigation) => {
    // console.log('routeArrow', nav)
    if(!nav) return '#'

    if (nav.type === 'fulfilment') {
        return route('grp.org.fulfilments.show.operations.dashboard', {
            organisation: layout.currentParams.organisation,
            fulfilment: nav.key
        })
    } else if (nav.type === 'shop') {
        return route('grp.org.shops.show.dashboard.show', {
            organisation: layout.currentParams.organisation,
            shop: nav.key
        })
    }

}

// Show this Horizontal depends on:
// - If shop is the latest, and selected shop is open
// - If fulfilment is the latest, and selected fulfilment is open
const isShowHorizontal = () => {
    const isShopOpen = activeNav() == 'shop' && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_shops.find(shop => shop.slug === layout.organisationsState[layout.currentParams.organisation]?.currentShop)?.state === 'open'
    const isFulfilmentOpen = activeNav() == 'fulfilment' && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_fulfilments.find(fulfilment => fulfilment.slug === layout.organisationsState[layout.currentParams.organisation]?.currentFulfilment)?.state === 'open'

    return (true || isShopOpen || isFulfilmentOpen)
}

// Route for label 'UK (Shop)'
const routeLabelHorizontal = () => {
    return '#'
    // if(currentNavigation()?.type === 'fulfilment') {
    //     return route('grp.org.fulfilments.show.operations.dashboard', [layout.currentParams.organisation, currentNavigation()?.key])
    // } else if (currentNavigation()?.type === 'shop'){
    //     return route('grp.org.shops.show', [layout.currentParams.organisation, currentNavigation()?.key])
    // } else {
    //     return '#'
    // }
}


const isLoadingNavigation = ref<string | boolean>(false)
</script>

<template>
    <div v-if="isShowHorizontal()" class="relative isolate ring-1 ring-white/20 rounded transition-all"
        :class="layout.leftSidebar.show ? 'px-1' : 'px-0'"
        :style="{ 'box-shadow': `0 0 0 1px ${layout.app.theme[1]}55` }">
        <span v-if="false" class="text-white">
            {{ previousNavigation() }}
        </span>

        <!-- Label: Icon shops/warehouses and slug -->
        <div v-if="!!currentNavigation()" class="relative w-full flex justify-between items-end pt-2 pl-2 pr-0.5 pb-2"
            :style="{ color: layout.app.theme[1] + '99' }">

            <!-- Label: 'UK (Shop)' -->
            <div :href="routeLabelHorizontal()" class="relative flex gap-x-1.5 items-center pt-1 select-none cursor-default">

                <Transition name="slide-to-left">
                    <div v-if="layout.leftSidebar.show" class="flex items-end gap-x-0.5">
                        <Transition name="spin-to-down">
                            <span :key="currentNavigation()?.key" class="text-base leading-[8px] uppercase">
                                {{ currentNavigation()?.key }}
                            </span>
                        </Transition>
                        <!-- <Transition name="spin-to-down">
                            <span :key="currentNavigation()?.value.type" class="text-xxs capitalize leading-3">
                                ({{ currentNavigation()?.value.type || currentNavigation()?.type}})
                            </span>
                        </Transition> -->
                    </div>
                </Transition>

                <Transition name="spin-to-down">
                    <FontAwesomeIcon v-if="currentNavigation()?.value.type === 'b2b'" icon="fal fa-fax" class='text-xs' fixed-width aria-hidden='true' v-tooltip="trans('E-commerce')" />
                    <FontAwesomeIcon v-else-if="currentNavigation()?.value.type === 'fulfilment'" icon="fal fa-hand-holding-box" class='text-xs' fixed-width aria-hidden='true' v-tooltip="trans('Fulfilment')" />
                    <FontAwesomeIcon v-else-if="currentNavigation()?.value.type === 'dropshipping'" icon="fal fa-parachute-box " class='text-xs' fixed-width aria-hidden='true' v-tooltip="trans('Dropshipping')" />
                    <FontAwesomeIcon v-else-if="currentNavigation()?.type === 'shop'" icon="fal fa-store-alt " class='text-xs' fixed-width aria-hidden='true' v-tooltip="trans('Shop')" />
                </Transition>

                <Transition name="slide-to-left">
                    <div v-if="layout.leftSidebar.show && (numberOptions || 0) > 1" @click="() => set(layout, ['organisationsState', layout?.currentParams?.organisation, generateCurrentString(currentNavigation()?.type)], '')" class="text-red-300 hover:text-red-500 cursor-pointer hover:underline text-xxs w-fit px-0.5 py leading-[8px]">
                        {{ trans("Unselect") }}
                    </div>
                </Transition>
            </div>


            <!-- Section: Arrow left-right -->
            <Transition name="slide-to-left">
                <div v-if="layout.leftSidebar.show" class="absolute right-0.5 top-2 flex text-white text-xxs"
                >
                    <component
                        :is="previousNavigation() ? Link : 'div'"
                        :href="routeArrow(previousNavigation())"
                        :class="previousNavigation() ? 'hover:bg-black/10' : 'text-white/40'"
                        class="py-1.5 px-[3px] flex justify-center items-center rounded"
                        @start="() => isLoadingNavigation = 'prevNav'"
                        @finish="() => isLoadingNavigation = false"
                    >
                        <LoadingIcon v-if="isLoadingNavigation == 'prevNav'" />
                        <FontAwesomeIcon v-else icon='fas fa-chevron-left' class='' fixed-width aria-hidden='true' />
                    </component>

                    <component
                        :is="nextNavigation() ? Link : 'div'"
                        :href="routeArrow(nextNavigation())"
                        class="py-1.5 px-[3px] flex justify-center items-center rounded"
                        :class="nextNavigation() ? 'hover:bg-black/10' : 'text-white/40'"
                        @start="() => isLoadingNavigation = 'nextNav'"
                        @finish="() => isLoadingNavigation = false"
                    >
                        <LoadingIcon v-if="isLoadingNavigation == 'nextNav'" />
                        <FontAwesomeIcon v-else icon='fas fa-chevron-right' class='' fixed-width aria-hidden='true' />
                    </component>
                </div>
            </Transition>
        </div>

        <!-- If Shops/Warehouses length is 1 (Show the subnav straighly) -->
        <div v-if="Object.keys(orgNav || []).length === 1" class="flex flex-col gap-y-1 mb-1">
            <!-- group only 1 -->
            <template v-for="nav, navIndex, index in orgNav[Object.keys(orgNav)[0]]" :key="navIndex + index">
                <NavigationSimple :nav="nav" :navKey="navIndex" />
            </template>
        </div>

        <!-- If Shops/Warehouses length is more than 1 and current warehouse is exist -->
        <div v-else-if="layout.organisationsState?.[layout.currentParams.organisation]?.[generateCurrentString(currentNavigation()?.type)]"
            class="flex flex-col gap-y-1 mb-1">
            <!-- Looping: SubNav -->
            <template
                v-for="nav, navKey, navIndex in currentNavigation()?.value.subNavigation"
                :key="navKey + navIndex">
                <!-- {{ navKey }} -->
                <NavigationSimple :nav="nav" :navKey="navKey" />

                <!-- <div v-if="(nav.route?.name ? isRouteSameAsCurrentUrl(route(nav.route.name, nav.route.parameters)) : false)"
                        class="absolute inset-0 bg-black/20 rounded -z-10"
                    /> -->
            </template>
        </div>

        <div v-if="isSomeSubnavActive()" class="absolute inset-0 bg-black/20 rounded -z-10" />
    </div>
</template>
