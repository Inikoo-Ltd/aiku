<template>
    <dl class="border border-gray-900/5 rounded-lg overflow-hidden shadow mx-auto grid grid-cols-1 gap-px bg-gray-900/5 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="(stat, index) in data" :key="index"
            class="flex flex-wrap items-baseline justify-between gap-x-4 bg-white px-4 pt-3 pb-2">
            <dt class="flex items-center gap-x-1 text-xs font-medium text-gray-400">
                {{ stat.label }}
                <FontAwesomeIcon v-if="stat.information" v-tooltip="stat.information" icon="fal fa-info-circle"
                    class="text-gray-400 hover:text-gray-600" fixed-width aria-hidden="true" />
            </dt>
            <!-- <dd :class="[stat.changeType === 'negative' ? 'text-rose-600' : 'text-gray-700', 'text-xs font-medium']">
                {{ stat.change }}
            </dd> -->
            <dd class="w-full flex-none text-xl font-bold leading-8 tracking-tight text-indigo-700">{{ stat.value }}
            </dd>
        </div>
    </dl> 
</template>

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle } from "@fal"

library.add(faInfoCircle)

const props = defineProps<{
    data: {
        label: string
        value: number
        information?: string
    }[]
}>()
</script>
