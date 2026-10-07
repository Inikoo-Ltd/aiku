<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 04 Oct 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { faFileAlt, faComments, faAbacus, faBooks, faUsers, faStore, faUserHardHat, faIndustry, faShippingFast, faBullhorn, faShoppingCart, faWarehouse, faUserCircle, faClipboardList } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, ref } from "vue"

library.add(faFileAlt, faComments, faAbacus, faBooks, faUsers, faStore, faUserHardHat, faIndustry, faShippingFast, faBullhorn, faShoppingCart, faWarehouse, faUserCircle, faClipboardList)

const moduleLabels: Record<string, { label: string, icon: string }> = {
    "help-desk": { label: ctrans("Chat"), icon: "fal fa-comments" },
    accounting: { label: ctrans("Accounting"), icon: "fal fa-abacus" },
    catalogue: { label: ctrans("Catalogue"), icon: "fal fa-books" },
    crm: { label: ctrans("CRM"), icon: "fal fa-users" },
    shop: { label: ctrans("Shop"), icon: "fal fa-store" },
    hr: { label: ctrans("HR"), icon: "fal fa-user-hard-hat" },
    production: { label: ctrans("Production"), icon: "fal fa-industry" },
    dispatch: { label: ctrans("Dispatch"), icon: "fal fa-shipping-fast" },
    marketing: { label: ctrans("Marketing"), icon: "fal fa-bullhorn" },
    orders: { label: ctrans("Orders"), icon: "fal fa-shopping-cart" },
    warehouse: { label: ctrans("Warehouse"), icon: "fal fa-warehouse" },
    profile: { label: ctrans("Profile"), icon: "fal fa-user-circle" },
    procurement: { label: ctrans("Procurement"), icon: "fal fa-clipboard-list" },
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    modules: { category: string, docs: { title: string, summary: string | null, url: string }[] }[]
    publicSiteVisits: {
        daily: { day: string, views: number, visitors: number }[]
        visitors: number
        views: number
        top_referrer: string | null
    }
}>()

const moduleOf = (category: string) => moduleLabels[category] ?? { label: category.replace("-", " "), icon: "fal fa-file-alt" }

const sortedModules = computed(() => [...props.modules].sort((a, b) => moduleOf(a.category).label.localeCompare(moduleOf(b.category).label)))

const selectedCategory = ref<string | null>(null)

const selectedModule = computed(() => sortedModules.value.find(module => module.category === selectedCategory.value) ?? sortedModules.value[0])

const sparklinePoints = computed(() => {
    const daily = props.publicSiteVisits.daily
    if (!daily.length) return ""
    const max = Math.max(...daily.map(d => Number(d.views)), 1)
    return daily.map((d, i) =>
        `${(i / Math.max(daily.length - 1, 1)) * 100},${28 - (Number(d.views) / max) * 26}`
    ).join(" ")
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4">
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-7">
            <button v-for="module in sortedModules" :key="module.category" type="button" @click="selectedCategory = module.category"
                class="flex items-center gap-3 rounded-lg border px-3 py-2 text-left transition"
                :class="module.category === selectedModule?.category ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                <FontAwesomeIcon :icon="moduleOf(module.category).icon" fixed-width class="text-lg text-gray-500" aria-hidden="true" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium">{{ moduleOf(module.category).label }}</span>
                    <span class="block text-xs text-gray-500">{{ module.docs.length }} {{ ctrans("guides") }}</span>
                </span>
            </button>
        </div>

        <div v-if="selectedModule" class="mb-8 max-w-3xl divide-y divide-gray-100 rounded-lg border border-gray-200">
            <a v-for="doc in selectedModule.docs" :key="doc.url" :href="doc.url" target="_blank" rel="noopener" class="block px-4 py-3 hover:bg-gray-50">
                <span class="block text-sm font-medium text-indigo-600">{{ doc.title }}</span>
                <span v-if="doc.summary" class="mt-0.5 block text-xs text-gray-500 line-clamp-2">{{ doc.summary }}</span>
            </a>
        </div>

        <div class="w-64 rounded-lg border border-gray-200 p-4">
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-medium">aiku.io</span>
                <span class="text-xs text-gray-500">{{ ctrans("Last 7 days") }}</span>
            </div>
            <div class="mt-2 flex items-baseline gap-3">
                <span class="text-sm">{{ publicSiteVisits.visitors }} {{ ctrans("visitors") }}</span>
                <span class="text-xs text-gray-500">{{ publicSiteVisits.views }} {{ ctrans("views") }}</span>
            </div>
            <svg v-if="sparklinePoints" viewBox="0 0 100 28" class="mt-2 h-7 w-full" preserveAspectRatio="none">
                <polyline :points="sparklinePoints" fill="none" stroke="currentColor" stroke-width="1.5" class="text-indigo-500" />
            </svg>
            <div v-if="publicSiteVisits.top_referrer" class="mt-2 truncate text-xs text-gray-500">
                {{ ctrans("Top referrer") }}: {{ publicSiteVisits.top_referrer }}
            </div>
            <Link :href="route('grp.docs.aiku-public-analytics')" class="mt-3 block text-xs text-indigo-600 hover:underline">
                {{ ctrans("See more") }} →
            </Link>
        </div>
    </div>
</template>
