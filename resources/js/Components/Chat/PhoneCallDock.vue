<script setup lang="ts">
import { computed, onMounted, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPhoneVolume } from "@fas"
import PhoneCallModal from "@/Components/Chat/PhoneCallModal.vue"
import { ctrans } from "@/Composables/useTrans"
import { useChatPhoneCall } from "@/Composables/useChatPhoneCall"

const { state, formattedElapsed, isRunningOut, fetchActive, openModal, warnIfRunningOut } =
    useChatPhoneCall()

// Only somebody who works chat can be on one of these calls, so nobody else pays for the request.
const isAgent = computed(() => usePage().props?.auth?.user?.is_agent === true)

onMounted(() => {
    if (isAgent.value && !state.loaded) {
        fetchActive()
    }
})

watch(isRunningOut, () => warnIfRunningOut())
</script>

<template>
    <div v-if="state.call"
        class="fixed left-1/2 top-[42px] z-[95] -translate-x-1/2 px-3 w-full max-w-md">
        <button type="button" @click="openModal"
            class="w-full flex items-center gap-3 rounded-full px-4 py-2 shadow-lg ring-1 transition-colors"
            :class="isRunningOut
                ? 'bg-amber-500 ring-amber-600 text-white hover:bg-amber-600'
                : 'bg-emerald-600 ring-emerald-700 text-white hover:bg-emerald-700'">
            <span class="relative flex h-2.5 w-2.5 shrink-0">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75" />
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-white" />
            </span>
            <FontAwesomeIcon :icon="faPhoneVolume" class="text-sm shrink-0" />
            <span class="flex-1 truncate text-left text-sm font-medium">
                {{ ctrans("You are on a phone call") }}
            </span>
            <span class="text-sm font-semibold tabular-nums">{{ formattedElapsed }}</span>
            <span class="text-xs underline decoration-white/50 underline-offset-2">
                {{ ctrans("End it") }}
            </span>
        </button>
    </div>

    <PhoneCallModal v-if="isAgent" />
</template>
