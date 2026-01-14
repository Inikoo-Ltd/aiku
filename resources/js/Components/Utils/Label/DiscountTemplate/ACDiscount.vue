<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBadgePercent } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { formatPercentage } from '@/Composables/Utils'
library.add(faBadgePercent)


const props = defineProps<{
    offers_data: {
        v: number
        o: {
            oc: number  // Offer Campaign id
            o: number  // Offer id
            oa: number  // Offer Allowance id
            t: string  // Type: "percentage"
            p: string  // Percentage: "10.0%"
            l: string  // Label
        }
    }
}>()


</script>

<template>
  <div
    class="bg-red-700 border border-red-900 text-gray-100 w-fit flex items-center rounded-sm px-1 py-0.5 text-[10px] sm:px-1.5 sm:py-1 sm:text-xxs md:px-2 md:py-1 md:text-sm">
    <span class="font-bold mr-1 leading-none">
      {{ formatPercentage(offers_data?.o?.p) }}
    </span>

    <span class="font-bold leading-none truncate max-w-[140px] sm:max-w-none">
      {{ offers_data?.o?.l }}
    </span>
  </div>
</template>
