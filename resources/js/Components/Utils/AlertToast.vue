<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faTimes)

defineProps<{
    icon: string
    iconClass: string
    eyebrow: string
    heading: string
    url?: string | null
}>()

const emit = defineEmits<{
    (e: "close"): void
}>()
</script>

<template>
    <div class="px-2 pt-1 pb-2">
        <div role="status" class="group relative rounded-xl border border-gray-200 bg-white shadow-[0_2px_6px_-1px_rgba(15,23,42,0.12)]">
            <component
                :is="url ? Link : 'div'"
                :href="url ?? undefined"
                class="flex items-start gap-x-3 rounded-xl px-3 py-2.5 pr-9"
                :class="url ? 'cursor-pointer hover:bg-gray-50' : ''"
                @click="url && emit('close')">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm" :class="iconClass">
                    <FontAwesomeIcon :icon="icon" fixed-width aria-hidden="true" />
                </div>
                <div class="min-w-0 flex-1 leading-snug">
                    <div class="flex items-baseline gap-x-2 text-xs text-gray-500">
                        <span class="truncate">{{ eyebrow }}</span>
                        <span class="ml-auto shrink-0 text-[11px] text-gray-400">{{ ctrans("now") }}</span>
                    </div>
                    <div class="truncate text-sm font-semibold text-gray-900">{{ heading }}</div>
                    <slot />
                </div>
            </component>
            <div class="absolute top-1.5 right-1.5 flex flex-col items-center">
                <button
                    type="button"
                    class="flex h-6 w-6 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
                    :aria-label="ctrans('Close')"
                    @click="emit('close')">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
