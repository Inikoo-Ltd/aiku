<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCartPlus, faUsers, faGlobe } from "@fal"

library.add(faCartPlus, faUsers, faGlobe)

defineProps<{
    demand?: {
        days: number
        opportunities: {
            query: string
            searches: number
            customers: number
            websites: number
            last_searched_at: string | null
        }[]
    } | null
}>()
</script>

<template>
    <div v-if="demand" class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
        <h3 class="text-lg font-semibold flex items-center gap-2">
            <FontAwesomeIcon icon="fal fa-cart-plus" class="text-green-600" fixed-width aria-hidden="true" />
            {{ ctrans("Customers asked for, we do not sell") }}
            <span class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(demand.days) }) }}</span>
        </h3>
        <p class="text-sm text-gray-500 mb-3">
            {{ ctrans("Searched on our websites, no matching product in any of our shops.") }}
        </p>

        <div v-if="demand.opportunities.length" class="divide-y divide-gray-100 text-sm">
            <div v-for="item in demand.opportunities" :key="item.query" class="flex justify-between gap-3 py-1.5">
                <span class="text-gray-700 font-medium truncate min-w-0">{{ item.query }}</span>
                <span class="shrink-0 text-gray-500 tabular-nums whitespace-nowrap flex items-center gap-3">
                    <span v-tooltip="ctrans('Searches')">{{ item.searches }}</span>
                    <span v-tooltip="ctrans('Customers')">
                        <FontAwesomeIcon icon="fal fa-users" class="text-gray-300" aria-hidden="true" /> {{ item.customers }}
                    </span>
                    <span v-if="item.websites > 1" v-tooltip="ctrans('Websites')">
                        <FontAwesomeIcon icon="fal fa-globe" class="text-gray-300" aria-hidden="true" /> {{ item.websites }}
                    </span>
                </span>
            </div>
        </div>
        <p v-else class="text-sm text-gray-400 py-3">
            {{ ctrans("No repeated demand for products outside the catalogue.") }}
        </p>
    </div>
</template>
