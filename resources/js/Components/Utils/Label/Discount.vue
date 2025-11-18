<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBadgePercent } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faBadgePercent)


const props = defineProps<{
    offers_data: {
        offers: {
            discounted_percentage: number
            label: string
        }
    }
}>()
</script>

<template>
    <div class="bg-pink-500/20 px-1 py-0.5 text-xs border flex items-center border-pink-500/50 rounded w-fit text-pink-700" >
        <FontAwesomeIcon icon="fas fa-badge-percent" class="text-pink-500 text-sm align-middle" fixed-width aria-hidden="true" />
        <span class="ml-0.5 font-bold">{{ offers_data?.offers?.discounted_percentage * 100 }}%</span> {{ offers_data?.offers?.label }}
    </div>
</template>