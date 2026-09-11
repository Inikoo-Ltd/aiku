<!--
  -  Author: Andi Ferdiawan
  -  Created: Mon, 14 Jul 2026 15:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@common/Components/Image.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown, faBoxOpen, faInfoCircle } from "@fal"

library.add(faChevronDown, faBoxOpen, faInfoCircle)

interface PackagingOption {
    id: number
    name: string
    dimensions: string | null
    price: number
    is_free: boolean
    is_downgrade?: boolean
    family_code: string | null
    image?: { thumbnail?: any, source?: any } | null
}

const props = defineProps<{
    options: PackagingOption[]
    selectedId?: number | null
    loading?: boolean
}>()

const emit = defineEmits<{
    (e: "change", packagingId: number): void
}>()

const MENU_WIDTH = 288
const MENU_MAX_HEIGHT = 320
const VIEWPORT_MARGIN = 8

const open = ref(false)
const trigger = ref<HTMLButtonElement | null>(null)
const menuStyle = ref<Record<string, string>>({})

// The picker lives inside a scrolling table, so it is teleported to the body and positioned
// against the viewport. Otherwise the rows near the bottom render their options off-screen.
const positionMenu = () => {
    const button = trigger.value
    if (!button) {
        return
    }

    const rect = button.getBoundingClientRect()
    const spaceBelow = window.innerHeight - rect.bottom - VIEWPORT_MARGIN
    const spaceAbove = rect.top - VIEWPORT_MARGIN
    const dropUp = spaceBelow < Math.min(MENU_MAX_HEIGHT, spaceAbove)
    const maxHeight = Math.min(MENU_MAX_HEIGHT, dropUp ? spaceAbove : spaceBelow)

    menuStyle.value = {
        position: "fixed",
        width: `${MENU_WIDTH}px`,
        maxHeight: `${Math.max(maxHeight, 160)}px`,
        left: `${Math.min(rect.left, window.innerWidth - MENU_WIDTH - VIEWPORT_MARGIN)}px`,
        ...(dropUp
            ? { bottom: `${window.innerHeight - rect.top + 4}px` }
            : { top: `${rect.bottom + 4}px` }),
    }
}

const close = () => {
    open.value = false
    window.removeEventListener("resize", positionMenu)
    window.removeEventListener("scroll", positionMenu, true)
}

const toggle = async () => {
    if (open.value) {
        close()
        return
    }

    open.value = true
    await nextTick()
    positionMenu()
    window.addEventListener("resize", positionMenu)
    window.addEventListener("scroll", positionMenu, true)
}

const onSelect = (option: PackagingOption) => {
    close()
    if (option.id !== props.selectedId) {
        emit("change", option.id)
    }
}

onBeforeUnmount(close)
</script>

<template>
    <div>
        <button
            ref="trigger"
            type="button"
            class="inline-flex w-fit items-center gap-1.5 rounded border border-orange-400 px-2 py-0.5 text-xs font-semibold text-orange-600 transition hover:bg-orange-50 disabled:opacity-60"
            :disabled="loading"
            @click="toggle"
        >
            <span class="flex items-center gap-1.5">
                <FontAwesomeIcon v-if="loading" :icon="['fad', 'spinner-third']" class="animate-spin" fixed-width aria-hidden="true" />
                {{ trans("Change packaging") }}
            </span>
            <FontAwesomeIcon :icon="['fal', 'chevron-down']" class="text-[10px] transition" :class="open ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
        </button>

        <Teleport to="body">
            <!-- Backdrop to close on outside click -->
            <div v-if="open" class="fixed inset-0 z-[1000]" @click="close" />

            <div
                v-if="open"
                class="z-[1001] flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
                :style="menuStyle"
            >
                <div class="shrink-0 px-3 py-2 text-xs font-semibold text-gray-700">
                    {{ trans("Select most suitable packaging") }}
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <button
                        v-for="option in options"
                        :key="option.id"
                        type="button"
                        class="flex w-full items-center gap-3 px-3 py-2 text-left transition hover:bg-gray-50"
                        @click="onSelect(option)"
                    >
                        <span
                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border"
                            :class="option.id === selectedId ? 'border-orange-500' : 'border-gray-300'"
                        >
                            <span v-if="option.id === selectedId" class="h-2 w-2 rounded-full bg-orange-500" />
                        </span>

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center">
                            <Image
                                v-if="option.image"
                                :src="option.image.source"
                                :alt="option.name"
                                class="h-full w-full rounded object-contain"
                            />
                            <FontAwesomeIcon v-else :icon="['fal', 'box-open']" class="text-xl text-amber-600" fixed-width aria-hidden="true" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-800">{{ option.name }}</div>
                            <div class="text-xs text-gray-400">
                                <span v-if="option.is_free">{{ trans("No extra charge") }}</span>
                                <span v-else>{{ option.dimensions }}</span>
                            </div>
                            <div v-if="option.is_downgrade" class="text-xs text-amber-600">
                                {{ trans("Cheaper than the customer paid") }}
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex shrink-0 items-start gap-2 bg-gray-50 px-3 py-2 text-xs text-gray-500">
                    <FontAwesomeIcon :icon="['fal', 'info-circle']" class="mt-0.5" fixed-width aria-hidden="true" />
                    {{ trans("Choose the packaging that best fits the items in this order.") }}
                </div>
            </div>
        </Teleport>
    </div>
</template>
