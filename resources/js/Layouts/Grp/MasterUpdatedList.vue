<script setup lang="ts">
import { ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHourglassStart, faChevronDown, faChevronRight, faCheck, faCheckDouble } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useLayoutStore } from '@/Stores/layout'
library.add(faHourglassStart, faChevronDown, faChevronRight, faCheck, faCheckDouble)

type ProductEntry = {
    sub_task_id: number
    code: string
    name: string
    slug: string
}

type ShopEntry = {
    slug: string
    name: string
    code: string
    count: number
    products: ProductEntry[]
    route: { name: string; parameters: Record<string, unknown> }
}

type OrgEntry = {
    organisation: { slug: string; name: string; code: string }
    shops: ShopEntry[]
}

const props = defineProps<{
    open: boolean
    close: () => void
}>()

const data = ref<OrgEntry[]>([])
const isLoading = ref(false)
const expandedOrgs = ref<Set<string>>(new Set())
const expandedShops = ref<Set<string>>(new Set())
const completing = ref<Set<number>>(new Set())

watch(() => props.open, async (isOpen) => {
    if (!isOpen) {
        return
    }

    isLoading.value = true
    try {
        const response = await axios.get(route('grp.json.master_updated_badge'))
        data.value = response.data
        data.value.forEach((org: OrgEntry) => {
            expandedOrgs.value.add(org.organisation.slug)
            org.shops.forEach((shop) => expandedShops.value.add(shop.slug))
        })

        useLayoutStore().master_updated_count = data.value.reduce((total, org) =>
            total + org.shops.reduce((sTotal, s) => sTotal + s.count, 0), 0
        )
    } finally {
        isLoading.value = false
    }
}, { immediate: true })

function toggleOrg(orgSlug: string): void {
    if (expandedOrgs.value.has(orgSlug)) {
        expandedOrgs.value.delete(orgSlug)
    } else {
        expandedOrgs.value.add(orgSlug)
    }
}

function toggleShop(shopSlug: string): void {
    if (expandedShops.value.has(shopSlug)) {
        expandedShops.value.delete(shopSlug)
    } else {
        expandedShops.value.add(shopSlug)
    }
}

async function markDone(shop: ShopEntry, subTaskIds: number[]): Promise<void> {
    subTaskIds.forEach((id) => completing.value.add(id))

    try {
        const response = await axios.patch(route('grp.models.sub_task.complete'), {
            sub_task_ids: subTaskIds,
        })

        shop.products = shop.products.filter((product) => !subTaskIds.includes(product.sub_task_id))
        shop.count = shop.products.length

        data.value.forEach((org) => {
            org.shops = org.shops.filter((s) => s.count > 0)
        })
        data.value = data.value.filter((org) => org.shops.length > 0)

        useLayoutStore().master_updated_count = response.data.master_updated_count
    } finally {
        subTaskIds.forEach((id) => completing.value.delete(id))
    }
}
</script>

<template>
    <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
            {{ trans('Products Not Matching Master Prices') }}
        </p>

        <div v-if="isLoading" class="space-y-2">
            <div v-for="i in 2" :key="i" class="animate-pulse">
                <div class="h-8 bg-gray-100 rounded mb-1" />
                <div class="pl-3 space-y-1">
                    <div class="h-6 bg-gray-50 rounded w-3/4" />
                    <div class="h-6 bg-gray-50 rounded w-2/3" />
                </div>
            </div>
        </div>

        <div v-else-if="data.length === 0" class="px-2 py-3 text-xs text-gray-400 italic">
            {{ trans('No products off master prices') }}
        </div>

        <div v-else>
            <div v-for="orgData in data" :key="orgData.organisation.slug" class="mb-2">
                <button
                    @click="toggleOrg(orgData.organisation.slug)"
                    class="w-full flex items-center justify-between px-2 py-1.5 rounded hover:bg-gray-50 text-left"
                >
                    <span class="text-sm font-medium text-gray-700">
                        {{ orgData.organisation.name }}
                        <span class="ml-1 text-xs text-gray-400">({{ orgData.organisation.code }})</span>
                    </span>
                    <FontAwesomeIcon
                        :icon="expandedOrgs.has(orgData.organisation.slug) ? 'fal fa-chevron-down' : 'fal fa-chevron-right'"
                        class="text-gray-400 text-xs"
                    />
                </button>

                <div v-if="expandedOrgs.has(orgData.organisation.slug)" class="pl-2 mt-1 space-y-1">
                    <div v-for="shop in orgData.shops" :key="shop.slug">
                        <div class="flex items-center justify-between px-2 py-1 rounded hover:bg-gray-50">
                            <button
                                @click="toggleShop(shop.slug)"
                                class="flex items-center gap-x-1.5 text-left flex-1 min-w-0"
                            >
                                <FontAwesomeIcon
                                    :icon="expandedShops.has(shop.slug) ? 'fal fa-chevron-down' : 'fal fa-chevron-right'"
                                    class="text-gray-400 text-[10px]"
                                    fixed-width
                                />
                                <span class="text-xs text-gray-500 font-medium truncate">{{ shop.name }}</span>
                                <span class="text-xs font-semibold text-rose-600 bg-rose-100 rounded-full px-1.5 py-0.5">
                                    {{ shop.count }}
                                </span>
                            </button>
                            <button
                                @click="markDone(shop, shop.products.map((product) => product.sub_task_id))"
                                :title="trans('Mark all as done')"
                                class="ml-1 px-1.5 py-0.5 rounded text-gray-400 hover:text-green-600 hover:bg-green-50"
                            >
                                <FontAwesomeIcon icon="fal fa-check-double" class="text-xs" fixed-width />
                            </button>
                        </div>

                        <div v-if="expandedShops.has(shop.slug)" class="pl-5 space-y-0.5">
                            <div
                                v-for="product in shop.products"
                                :key="product.sub_task_id"
                                class="flex items-center justify-between px-2 py-1 rounded hover:bg-rose-50 group"
                                :class="completing.has(product.sub_task_id) ? 'opacity-40' : ''"
                            >
                                <Link
                                    :href="route(shop.route.name, shop.route.parameters)"
                                    @click="close()"
                                    class="flex items-center gap-x-1.5 text-xs text-gray-600 group-hover:text-rose-700 flex-1 min-w-0"
                                >
                                    <FontAwesomeIcon icon="fal fa-hourglass-start" class="text-rose-400" fixed-width />
                                    <span class="truncate" :title="product.name">{{ product.code }}</span>
                                </Link>
                                <button
                                    @click="markDone(shop, [product.sub_task_id])"
                                    :disabled="completing.has(product.sub_task_id)"
                                    :title="trans('Mark as done')"
                                    class="ml-1 px-1.5 py-0.5 rounded text-gray-400 hover:text-green-600 hover:bg-green-50"
                                >
                                    <FontAwesomeIcon icon="fal fa-check" class="text-xs" fixed-width />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
