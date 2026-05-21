<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject, ref } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Icon from '../Icon.vue'
import CountUp from 'vue-countup-v3'
import { faFireAlt } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { StatsBoxTS } from '@/types/Components/StatsBox'

library.add(faFireAlt)

defineProps<{
    stats: StatsBoxTS[]
}>()

const locale = inject('locale', aikuLocaleStructure)
const isLoadingIndex = ref<null | number>(null)
</script>

<template>
    <div class="relative overflow-hidden rounded-lg border border-red-200 shadow-sm bg-red-50">
        <FontAwesomeIcon
            icon="fad fa-fire-alt"
            class="text-red-400 opacity-20 absolute -bottom-2 -right-4 text-8xl -z-0 pointer-events-none"
            fixed-width
            aria-hidden="true"
        />
        <div class="divide-y divide-red-200">
            <component
                v-for="(stat, index) in stats"
                :key="index"
                :is="stat.route?.name ? Link : 'div'"
                :href="stat.route?.name ? route(stat.route.name, stat.route.parameters) : ''"
                @start="() => isLoadingIndex = index"
                @finish="() => isLoadingIndex = null"
                class="relative z-10 px-4 py-3 flex items-center justify-between gap-4 hover:bg-red-100 cursor-pointer"
            >
                <div class="flex items-center gap-3">
                    <div class="text-red-400 text-lg">
                        <FontAwesomeIcon v-if="typeof stat.icon === 'string'" :icon="stat.icon" fixed-width aria-hidden="true" />
                        <Icon v-else-if="stat.icon" :data="stat.icon" />
                    </div>
                    <span class="text-sm font-medium text-red-500">{{ stat.label }}</span>
                </div>
                <dd class="text-2xl font-semibold tracking-tight text-red-600 tabular-nums">
                    <CountUp
                        :endVal="stat?.value ?? 0"
                        :duration="1.5"
                        :scrollSpyOnce="true"
                        :options="{ formattingFn: (value: number) => locale.number(value) }"
                    />
                </dd>
            </component>
        </div>
    </div>
</template>
