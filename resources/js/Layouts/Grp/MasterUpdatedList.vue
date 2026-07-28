<script setup lang="ts">
import { ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHourglassStart, faHourglassHalf, faChevronDown, faChevronRight } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useLayoutStore } from '@/Stores/layout'
library.add(faHourglassStart, faHourglassHalf, faChevronDown, faChevronRight)

type ShopEntry = {
    slug: string
    name: string
    code: string
    master_updated_items: { count: number; route: { name: string; parameters: Record<string, string> } }
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

watch(() => props.open, async (isOpen) => {
    if (!isOpen) {
        return
    }

    isLoading.value = true
    try {
        const response = await axios.get(route('grp.json.master_updated_badge'))
        data.value = response.data
        data.value.forEach((org: OrgEntry) => expandedOrgs.value.add(org.organisation.slug))

        const layout = useLayoutStore()
        layout.master_updated_count = data.value.reduce((total, org) =>
            total + org.shops.reduce((sTotal, s) =>
                sTotal + s.master_updated_items.count, 0
            ), 0
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

                <div v-if="expandedOrgs.has(orgData.organisation.slug)" class="pl-3 mt-1 space-y-1">
                    <div v-for="shop in orgData.shops" :key="shop.slug" class="space-y-1" :class="shop.master_updated_items.count > 0 ? '' : 'hidden'">
                        <p class="text-xs text-gray-400 font-medium">{{ shop.name }}</p>

                        <Link
                            v-if="shop.master_updated_items.count > 0"
                            :href="route(shop.master_updated_items.route.name, shop.master_updated_items.route.parameters)"
                            @click="close()"
                            class="flex items-center justify-between px-2 py-1 rounded hover:bg-rose-50 group"
                        >
                            <div class="flex items-center gap-x-1.5 text-xs text-gray-600 group-hover:text-rose-700">
                                <FontAwesomeIcon icon="fal fa-hourglass-start" class="text-rose-400" fixed-width />
                                <span>{{ trans('Products not following master prices') }}</span>
                            </div>
                            <span class="text-xs font-semibold text-rose-600 bg-rose-100 rounded-full px-1.5 py-0.5">
                                {{ shop.master_updated_items.count }}
                            </span>
                        </Link>
                        <div
                            v-else
                            class="px-2 py-1 text-xs text-gray-400 italic"
                        >
                            {{ trans('No products off master prices') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
