<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { computed, inject, ref } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Icon from '../Icon.vue'
import CountUp from 'vue-countup-v3'
import { faFireAlt } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { StatsBoxTS } from '@/types/Components/StatsBox'

library.add(faFireAlt)

const props = defineProps<{
    stats: StatsBoxTS[]
}>()

const ROWS_PER_COLUMN = 4
const MAX_COLUMNS = 3

const isMultiColumn = computed(() => props.stats.length > ROWS_PER_COLUMN)
const columnCount = computed(() => Math.min(MAX_COLUMNS, Math.ceil(props.stats.length / ROWS_PER_COLUMN)))
const rowCount = computed(() => Math.max(ROWS_PER_COLUMN, Math.ceil(props.stats.length / columnCount.value)))
const blankCount = computed(() => (isMultiColumn.value ? columnCount.value * rowCount.value - props.stats.length : 0))

const gridStyle = computed(() => isMultiColumn.value
    ? { gridTemplateRows: `repeat(${rowCount.value}, auto)`, gridTemplateColumns: `repeat(${columnCount.value}, minmax(0, 1fr))` }
    : {})

const cellBorderClass = (index: number) => [
    index > 0 && index < props.stats.length ? 'border-t' : '',
    isMultiColumn.value && index % rowCount.value === 0 ? 'lg:border-t-0' : '',
    isMultiColumn.value && index % rowCount.value !== 0 ? 'lg:border-t' : '',
    isMultiColumn.value && index >= rowCount.value ? 'lg:border-l' : '',
]

const locale = inject('locale', aikuLocaleStructure)
const isLoadingIndex = ref<null | number>(null)
</script>

<template>
    <div class="relative overflow-hidden rounded-lg border border-red-200 shadow-sm bg-red-50" :class="isMultiColumn ? 'lg:col-span-full' : ''">
        <FontAwesomeIcon
            icon="fad fa-fire-alt"
            class="text-red-400 opacity-20 absolute -bottom-2 -right-4 text-8xl -z-0 pointer-events-none"
            fixed-width
            aria-hidden="true"
        />
        <div :class="isMultiColumn ? 'lg:grid lg:grid-flow-col' : ''" :style="gridStyle">
            <component
                v-for="(stat, index) in stats"
                :key="index"
                :is="stat.route?.name ? Link : 'div'"
                :href="stat.route?.name ? route(stat.route.name, stat.route.parameters) : ''"
                @start="() => isLoadingIndex = index"
                @finish="() => isLoadingIndex = null"
                class="relative z-10 px-4 py-3 flex items-center justify-between gap-4 border-red-200 hover:bg-red-100 cursor-pointer"
                :class="cellBorderClass(index)"
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
            <div
                v-for="blank in blankCount"
                :key="`blank-${blank}`"
                class="hidden lg:block border-red-200"
                :class="cellBorderClass(stats.length + blank - 1)"
            />
        </div>
    </div>
</template>
