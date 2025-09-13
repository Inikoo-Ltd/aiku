<script setup lang='ts'>
import { inject, computed } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useTruncate } from '@/Composables/useTruncate'
import { trans } from 'laravel-vue-i18n'
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { MenuItem } from '@headlessui/vue'
import Image from '@/Components/Image.vue'
import { Image as ImageTS } from '@/types/Image'
import axios from 'axios'
import { set } from 'lodash-es'

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

const onClickOrg = async (slug?: string) => {
    if (!slug) return

    // console.log('11 onClickOrg', layout.agents.data)
    // console.log('22 onClickOrg', layout.agents.data.find(organisation => organisation.slug == slug)?.authorised_shops.length)
    // return 

    // If current route is in Shop or Fulfilment, redirect to index
    if (route().current()?.includes('grp.org.shops') || route().current()?.includes('grp.org.fulfilments') || route().current()?.includes('grp.org.warehouses')) {
        // If org have shop or fulfilment, redirect to Shops index
        if (layout.organisations.data.find(organisation => organisation.slug == slug)?.authorised_shops.length || layout.organisations.data.find(organisation => organisation.slug == slug)?.authorised_fulfilments.length) {
            // console.log('333')
            router.visit(route('grp.org.dashboard.show', { organisation: slug }))
            // set(layout, ['organisationsState', slug, 'currentShop'], '')
            // set(layout, ['organisationsState', slug, 'currentFulfilment'], '')
            return
        } else if (layout.agents.data.find(agent => agent.slug == slug)?.authorised_shops.length || layout.agents.data.find(agent => agent.slug == slug)?.authorised_fulfilments.length) {
            // console.log('444')
            router.visit(route('grp.org.dashboard.show', { organisation: slug }))
            // set(layout, ['organisationsState', slug, 'currentShop'], '')
            // set(layout, ['organisationsState', slug, 'currentFulfilment'], '')
            return
        } else {
            // console.log('5555')
            router.visit(route('grp.org.dashboard.show', { organisation: slug }))
            set(layout, ['organisationsState', slug, 'currentShop'], '')
            set(layout, ['organisationsState', slug, 'currentFulfilment'], '')
            return
        }
    }

    try {
        if (!route().current()?.includes('grp.org.')) {
            throw new Error('Redirect to dashboard')
        }

        // const response = await axios.patch(
        //     route('grp.models.profile.can_visit'), {
        //         route_name: route().current(),
        //         route_parameters: route().params
        // })

        const response = await axios.get(route('grp.profile.can_visit'))

        if (!!response.data) {
            router.visit(route(route().current(), { ...route().params, organisation: slug }))
        } else {
            throw new Error('Redirect to dashboard')
        }
    } catch (error) {
        console.error(error)
        router.visit(route('grp.org.dashboard.show', { organisation: slug }))
    }

}
</script>

<template>
    <div>
        <div class="flex items-center gap-x-1.5 px-1 mb-1">
            <FontAwesomeIcon :icon="icon" class="text-gray-400 text-xxs" aria-hidden="true" />
            <span class="text-[9px] leading-none text-gray-400 capitalize whitespace-nowrap">{{ label }}</span>
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
                <div @click="() => onClickOrg(item.slug)" :class="[
                    item.slug == layout.currentParams?.organisation ? 'bg-slate-300 text-slate-600' : 'text-slate-600 hover:bg-slate-200/75 hover:text-indigo-600',
                    'group flex gap-x-2 w-full justify-start items-center rounded pl-2 pr-4 py-2 text-sm cursor-pointer',
                ]">
                    <div class="h-5 aspect-square rounded-full overflow-hidden ring-1 ring-slate-200 bg-slate-50">
                        <Image v-show="!imageSkeleton[item.slug]" :src="item.logo"
                            @onLoadImage="() => imageSkeleton[item.slug] = false" />
                        <div v-show="imageSkeleton[item.slug]" class="skeleton w-5 h-5" />
                    </div>
                    <div class="font-semibold whitespace-nowrap">{{ useTruncate(item.label, 20) }}</div>
                </div>
                </MenuItem>
            </template>
        </div>
    </div>
</template>