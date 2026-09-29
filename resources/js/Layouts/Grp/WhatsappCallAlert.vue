<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPhone, faPhoneSlash, faPhoneVolume } from "@fas"
import { ctrans } from "@/Composables/useTrans"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useWhatsappCall } from "@/Composables/useWhatsappCall"

defineProps<{ micro?: boolean }>()

const layout = inject("layout", layoutStructure)

const { call, activeCall, ringingCalls, isLive, isOutgoing, busy, formattedElapsed, statusLabel, answer, end } = useWhatsappCall()

const organisationFor = (callOrganisation?: string | null) => callOrganisation ?? layout.currentParams?.organisation ?? ""

const isCardOpen = ref(true)
const isCardShown = computed(() => ringingCalls.value.length > 0 || isCardOpen.value)
const badgeCount = computed(() => ringingCalls.value.length + (activeCall.value ? 1 : 0))

watch(isLive, (live) => (isCardOpen.value = !live))
</script>

<template>
    <div v-if="call" class="relative flex w-full flex-col items-center">
        <div
            v-if="isCardShown"
            role="status"
            class="absolute right-full top-0 z-30 mr-2 w-72 max-w-[70vw] cursor-default space-y-2 rounded-l-2xl rounded-r-md bg-green-600 px-3 py-2 leading-snug text-white shadow-[0_0_16px_rgba(22,163,74,0.8)]"
            @click.stop>
            <div v-if="activeCall">
                <div class="flex items-center gap-x-2">
                    <FontAwesomeIcon :icon="faPhoneVolume" :class="{ 'animate-pulse': !isLive }" fixed-width aria-hidden="true" />
                    <div class="min-w-0">
                        <div class="truncate text-xs font-semibold">{{ statusLabel }}</div>
                        <div class="truncate text-xs opacity-90">
                            {{ activeCall.phone_number }}
                            <span v-if="isLive"> · {{ formattedElapsed }}</span>
                        </div>
                    </div>
                </div>
                <button
                    type="button"
                    :disabled="busy"
                    class="mt-2 flex w-full items-center justify-center gap-x-1 rounded bg-red-600 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                    @click="end(organisationFor(activeCall.organisation), activeCall.id)">
                    <FontAwesomeIcon :icon="faPhoneSlash" fixed-width aria-hidden="true" />
                    {{ isLive ? ctrans("Hang up") : isOutgoing ? ctrans("Cancel") : ctrans("Decline") }}
                </button>
            </div>

            <div v-if="activeCall && ringingCalls.length" class="border-t border-white/30 pt-1 text-xxs font-semibold uppercase opacity-90">
                {{ ctrans(":count more waiting", { count: ringingCalls.length }) }}
            </div>

            <div v-for="ringing in ringingCalls" :key="ringing.id">
                <div class="flex items-center gap-x-2">
                    <FontAwesomeIcon :icon="faPhoneVolume" class="animate-pulse" fixed-width aria-hidden="true" />
                    <div class="min-w-0">
                        <div class="truncate text-xs font-semibold">{{ ctrans("Incoming WhatsApp call") }}</div>
                        <div class="truncate text-xs opacity-90">{{ ringing.phone_number }}</div>
                    </div>
                </div>
                <div class="mt-2 flex gap-x-1.5 text-xs">
                    <button
                        type="button"
                        :disabled="busy || !!activeCall"
                        v-tooltip="activeCall ? ctrans('Finish your current call first') : ''"
                        class="flex flex-1 items-center justify-center gap-x-1 rounded bg-white py-1 font-medium text-green-700 hover:bg-green-50 disabled:opacity-50"
                        @click="answer(organisationFor(ringing.organisation), ringing.id)">
                        <FontAwesomeIcon :icon="faPhone" fixed-width aria-hidden="true" />
                        {{ ctrans("Answer") }}
                    </button>
                    <button
                        type="button"
                        :disabled="busy"
                        class="flex flex-1 items-center justify-center gap-x-1 rounded bg-red-600 py-1 font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="end(organisationFor(ringing.organisation), ringing.id)">
                        <FontAwesomeIcon :icon="faPhoneSlash" fixed-width aria-hidden="true" />
                        {{ ctrans("Decline") }}
                    </button>
                </div>
            </div>
        </div>

        <button
            type="button"
            class="relative flex items-center justify-center gap-x-1 rounded-full bg-green-600 text-white shadow-[0_0_12px_rgba(22,163,74,0.6)]"
            :class="micro ? 'p-0.5' : 'px-2 py-1'"
            v-tooltip="`${statusLabel} ${call.phone_number ?? ''}`"
            @click.stop="isCardOpen = !isCardOpen">
            <FontAwesomeIcon
                :icon="faPhoneVolume"
                :class="[micro ? 'text-[8px]' : 'text-xs', { 'animate-pulse': ringingCalls.length }]"
                fixed-width
                aria-hidden="true" />
            <span v-if="isLive && !micro" class="text-xxs tabular-nums">{{ formattedElapsed }}</span>
            <span
                v-if="badgeCount > 1"
                class="absolute -right-1.5 -top-1.5 flex h-3.5 min-w-[0.875rem] items-center justify-center rounded-full bg-red-500 px-0.5 text-[8px] leading-none tabular-nums">{{ badgeCount }}</span>
            <span class="sr-only">{{ statusLabel }}</span>
        </button>
    </div>
</template>
