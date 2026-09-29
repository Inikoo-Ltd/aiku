<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPhone, faPhoneSlash, faPhoneVolume } from "@fas"
import { ctrans } from "@/Composables/useTrans"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useWhatsappCall } from "@/Composables/useWhatsappCall"

defineProps<{ micro?: boolean }>()

const layout = inject("layout", layoutStructure)

const { call, isRinging, isLive, isOutgoing, busy, formattedElapsed, statusLabel, answer, end } = useWhatsappCall()

const organisation = computed(() => call.value?.organisation ?? layout.currentParams?.organisation ?? "")

const isCardOpen = ref(true)
const isCardShown = computed(() => isRinging.value || isCardOpen.value)

watch(isLive, (live) => (isCardOpen.value = !live))
</script>

<template>
    <div v-if="call" class="relative flex w-full flex-col items-center">
        <div
            v-if="isCardShown"
            role="status"
            class="absolute right-full top-0 z-30 mr-2 w-72 max-w-[70vw] cursor-default rounded-l-2xl rounded-r-md bg-green-600 px-3 py-2 leading-snug text-white shadow-[0_0_16px_rgba(22,163,74,0.8)]"
            @click.stop>
            <div class="flex items-center gap-x-2">
                <FontAwesomeIcon :icon="faPhoneVolume" :class="{ 'animate-pulse': isRinging }" fixed-width aria-hidden="true" />
                <div class="min-w-0">
                    <div class="truncate text-xs font-semibold">{{ statusLabel }}</div>
                    <div class="truncate text-xs opacity-90">
                        {{ call.phone_number }}
                        <span v-if="isLive"> · {{ formattedElapsed }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-2 flex gap-x-1.5 text-xs">
                <button
                    v-if="isRinging && !isOutgoing"
                    type="button"
                    :disabled="busy"
                    class="flex flex-1 items-center justify-center gap-x-1 rounded bg-white py-1 font-medium text-green-700 hover:bg-green-50 disabled:opacity-50"
                    @click="answer(organisation)">
                    <FontAwesomeIcon :icon="faPhone" fixed-width aria-hidden="true" />
                    {{ ctrans("Answer") }}
                </button>
                <button
                    type="button"
                    :disabled="busy"
                    class="flex flex-1 items-center justify-center gap-x-1 rounded bg-red-600 py-1 font-medium text-white hover:bg-red-700 disabled:opacity-50"
                    @click="end(organisation)">
                    <FontAwesomeIcon :icon="faPhoneSlash" fixed-width aria-hidden="true" />
                    {{ isRinging ? (isOutgoing ? ctrans("Cancel") : ctrans("Decline")) : ctrans("Hang up") }}
                </button>
            </div>
        </div>

        <button
            type="button"
            class="flex items-center justify-center gap-x-1 rounded-full bg-green-600 text-white shadow-[0_0_12px_rgba(22,163,74,0.6)]"
            :class="micro ? 'p-0.5' : 'px-2 py-1'"
            v-tooltip="`${statusLabel} ${call.phone_number ?? ''}`"
            @click.stop="isCardOpen = !isCardOpen">
            <FontAwesomeIcon
                :icon="faPhoneVolume"
                :class="[micro ? 'text-[8px]' : 'text-xs', { 'animate-pulse': isRinging }]"
                fixed-width
                aria-hidden="true" />
            <span v-if="isLive && !micro" class="text-xxs tabular-nums">{{ formattedElapsed }}</span>
            <span class="sr-only">{{ statusLabel }}</span>
        </button>
    </div>
</template>
