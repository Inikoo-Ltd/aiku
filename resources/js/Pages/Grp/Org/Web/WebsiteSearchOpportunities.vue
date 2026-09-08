<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed } from "vue"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEyeSlash, faCartPlus, faUsers } from "@fal"

library.add(faEyeSlash, faCartPlus, faUsers)

interface Opportunity {
    query: string
    searches: number
    customers: number
    status: 'unpublished' | 'not_stocked'
    catalogue_matches: number
    samples: string[]
    last_searched_at: string | null
}

const props = defineProps<{
    pageHead: any
    title: string
    opportunities: Opportunity[]
    drilldown: { query: string, params: Record<string, any> }
}>()

const queryUrl = (query: string) => route(props.drilldown.query, { ...props.drilldown.params, q: query })

// Real demand repeats; one-off entries are usually typos, so rank by customers then searches
const byDemand = (a: Opportunity, b: Opportunity) =>
    b.customers - a.customers || b.searches - a.searches

const unpublished = computed(() => props.opportunities.filter(o => o.status === 'unpublished').sort(byDemand))
const notStocked = computed(() => props.opportunities.filter(o => o.status === 'not_stocked').sort(byDemand))

const repeated = computed(() => notStocked.value.filter(o => o.customers > 1 || o.searches > 1))
const oneOffs = computed(() => notStocked.value.filter(o => o.customers <= 1 && o.searches <= 1))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 grid grid-cols-1 xl:grid-cols-2 gap-4">
        <!-- Publishing gap: we have it, customers cannot see it -->
        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <FontAwesomeIcon icon="fal fa-eye-slash" class="text-amber-500" fixed-width aria-hidden="true" />
                {{ ctrans("We have it, it is not on the website") }}
            </h3>
            <p class="text-sm text-gray-500 mb-3">
                {{ ctrans("Customers searched for these and got nothing, but the catalogue has matching products that are not published.") }}
            </p>

            <div v-if="unpublished.length" class="divide-y divide-gray-100 text-sm">
                <div v-for="item in unpublished" :key="item.query" class="py-2">
                    <div class="flex justify-between gap-3">
                        <Link :href="queryUrl(item.query)" class="font-medium text-indigo-600 hover:underline truncate">{{ item.query }}</Link>
                        <span class="shrink-0 text-gray-500 tabular-nums whitespace-nowrap">
                            {{ item.searches }} <FontAwesomeIcon icon="fal fa-users" class="text-gray-300 ml-1" aria-hidden="true" /> {{ item.customers }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ ctrans("Unpublished matches") }}: <span class="font-medium text-amber-600">{{ item.catalogue_matches }}</span>
                        <span v-if="item.samples.length" class="text-gray-400"> — {{ item.samples.join(' · ') }}</span>
                    </p>
                </div>
            </div>
            <p v-else class="text-sm text-gray-400 py-4">{{ ctrans("Nothing here, every failed search is genuinely missing from the catalogue.") }}</p>
        </div>

        <!-- Demand we do not sell: the marketing / buying list -->
        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <FontAwesomeIcon icon="fal fa-cart-plus" class="text-green-600" fixed-width aria-hidden="true" />
                {{ ctrans("Customers want it, we do not sell it") }}
            </h3>
            <p class="text-sm text-gray-500 mb-3">
                {{ ctrans("No product in the catalogue matches these searches at all. Products worth sourcing.") }}
            </p>

            <div v-if="repeated.length" class="divide-y divide-gray-100 text-sm">
                <div v-for="item in repeated" :key="item.query" class="flex justify-between gap-3 py-2">
                    <Link :href="queryUrl(item.query)" class="font-medium text-indigo-600 hover:underline truncate">{{ item.query }}</Link>
                    <span class="shrink-0 text-gray-500 tabular-nums whitespace-nowrap">
                        {{ item.searches }} <FontAwesomeIcon icon="fal fa-users" class="text-gray-300 ml-1" aria-hidden="true" /> {{ item.customers }}
                    </span>
                </div>
            </div>
            <p v-else class="text-sm text-gray-400 py-4">{{ ctrans("No repeated demand for products outside the catalogue.") }}</p>

            <details v-if="oneOffs.length" class="mt-4">
                <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600">
                    {{ ctrans("Show :count one-off searches (mostly typos)", { count: String(oneOffs.length) }) }}
                </summary>
                <div class="divide-y divide-gray-100 text-sm mt-2">
                    <div v-for="item in oneOffs" :key="item.query" class="flex justify-between gap-3 py-1.5">
                        <Link :href="queryUrl(item.query)" class="text-gray-500 hover:underline truncate">{{ item.query }}</Link>
                        <span class="shrink-0 text-gray-400 tabular-nums">{{ item.searches }}</span>
                    </div>
                </div>
            </details>
        </div>
    </div>
</template>
