<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

</script>

<template>
    <VTooltip class="w-fit">
        <div class="w-fit inline">
            <FontAwesomeIcon icon="fas fa-medal" class="text-yellow-500" fixed-width aria-hidden="true" />
        </div>

        <template #popper>
            <div class="text-xs tabular-nums">
                <div>
                    Spent $1234 more to reach Gold Reward Member!
                </div>

                <div class="w-full rounded-full h-2 bg-gray-200 relative overflow-hidden">
                    <div class="absolute  left-0   top-0 h-full w-3/4 transition-all duration-1000 ease-in-out"
                        :class="true ? 'shimmer bg-green-400' : 'bg-green-500'"
                        :style="{
                            width: true ? 4/7 * 100 + '%' : '100%'
                        }"
                    />
                </div>
            </div>
        </template>
    </VTooltip>
</template>