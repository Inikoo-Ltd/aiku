<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link } from '@inertiajs/vue3'
import { inject, ref } from 'vue'
import Icon from '../Icon.vue'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { Icon as IconTS } from '@/types/Utils/Icon'

const props = defineProps<{
    data: {
        [key: string]: {
            label: string
            sublabel: string
            count: number
            cases: {
                key: string
                label: string
                value?: number
                icon: string | string[]
                icon_state: IconTS
                class?: string
                route?: {
                    name: string
                    parameters?: object
                }
            }[]
        }
    }
}>()

const locale = inject('locale', aikuLocaleStructure)
const isLoadingSub = ref<null | number>(null)
</script>

<template>
    <div class="flex flex-wrap p-4 gap-x-4 gap-y-4">
        <div v-for="dash in data" class="w-full max-w-sm px-6 py-6 rounded-lg bg-white shadow border border-gray-200">
            <div class="text-lg font-semibold text-gray-400 capitalize mb-3">
                {{ dash.label }}
            </div>
            <div class="flex flex-col gap-4">
                <!-- Total Count -->
                <div class="flex items-center">
                    <span class="text-3xl font-bold text-org-500">
                        {{ locale.number(dash.count) }}
                    </span>
                    <span class="ml-2 text-sm text-gray-500">{{ dash.sublabel }}</span>
                </div>
                
                <!-- Breakdown of each case -->
                <div class="flex flex-wrap gap-4">
                    <component
                        v-for="(item, idx) in dash.cases"
                        :is="item.route?.name ? Link : 'div'"
                        :href="item.route?.name ? route(item.route.name, item.route.parameters) : '#'"
                        :key="item.key"
                        class="flex items-center gap-2 px-1 py-0.5 rounded"
                        :class="item.route?.name ? 'hover:bg-gray-200' : ''"
                        xv-tooltip="item.label"
                        @start="() => isLoadingSub = idx"
                    >
                        <LoadingIcon
                            v-if="isLoadingSub === idx"
                        />
                        <Icon
                            v-else-if="item.icon_state"
                            :data="item.icon_state"
                        />
                        <FontAwesomeIcon v-else :icon="item.icon" class="" fixed-width aria-hidden="true" />
                        <span class="text-base font-medium">
                            {{ locale.number(item.value || 0) }}
                        </span>
                    </component>
                </div>
            </div>
        </div>
    </div>
</template>