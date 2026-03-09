<script setup lang="ts">
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { inject } from "vue"

const props = defineProps<{
    label?: string
    meter?: number[]
}>()


const layout = inject('layout', retinaLayoutStructure)
</script>

<template>
    <VTooltip class="w-fit">
        <slot>
            <div class="w-fit inline">
                <FontAwesomeIcon icon="fas fa-medal" class="text-yellow-500" fixed-width aria-hidden="true" />
            </div>
        </slot>

        <template #popper>
            <div class="text-xs tabular-nums">
                <div class="text-yellow-500 text-lg font-bold text-center mb-1">
                    <FontAwesomeIcon icon="fas fa-medal" class="text-sm align-middle text-yellow-500" fixed-width aria-hidden="true" />
                    <span class="align-middle mx-1">{{ props.label ?? layout.offer_data?.label }}</span>
                    <FontAwesomeIcon icon="fas fa-medal" class="text-sm align-middle text-yellow-500" fixed-width aria-hidden="true" />
                </div>

                <div>
                    You have {{ Number(props.meter?.[0] ?? layout.offer_data?.meter?.[0]).toFixed(0) }} days left before lose your Gold Reward status.
                </div>

                <div class="mb-2">
                    Make an order to refresh it.
                </div>

                <!-- <pre>{{ layout.offer_data }}</pre> -->

                <div class="w-full rounded-sm h-4 bg-gray-200 relative overflow-hidden mb-2">
                    <div class="absolute  left-0   top-0 h-full w-3/4 transition-all duration-1000 ease-in-out"
                        :class="true ? 'xshimmer bg-green-500' : 'bg-green-500'"
                        :style="{
                            width: true ? (props.meter?.[0] ?? layout.offer_data?.meter?.[0]) / (props.meter?.[1] ?? layout.offer_data?.meter?.[1]) * 100 + '%' : '100%'
                        }"
                    />
                    
                    <div class="absolute inset-0 flex items-center justify-center text-xs font-medium text-black">
                        {{ Number(props.meter?.[0] ?? layout.offer_data?.meter?.[0]).toFixed(0) }} / {{ Number(props.meter?.[1] ?? layout.offer_data?.meter?.[1]).toFixed(0) }} days
                    </div>
                    
                </div>
            </div>
        </template>
    </VTooltip>
</template>