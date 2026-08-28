<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPercent, faHamburger, faFlowerTulip } from "@fal"
import { taxPresetIcon } from "@/Composables/taxPresets"

library.add(faPercent, faHamburger, faFlowerTulip)

// The one way tax presets are picked, everywhere: the product edit page inlines it, the
// bulk edit modal wraps it. Selected-but-unsaved is amber and unmistakable.
const props = defineProps<{
    options: { value: string; title: string; description?: string }[]
    savedValue?: string | null
    disabled?: boolean
}>()

const model = defineModel<string>()

export type PresetOption = { value: string; title: string; description?: string }

const cardClass = (value: string) => {
    const selected = model.value === value
    const saved    = props.savedValue === value

    if (selected && saved) return 'border-indigo-500 ring-2 ring-indigo-500 bg-indigo-50'
    if (selected)          return 'border-amber-400 ring-2 ring-amber-400 border-dashed bg-amber-50'
    if (saved)             return 'border-indigo-300 bg-white'
    return 'border-gray-300 bg-white hover:bg-gray-50'
}
</script>

<template>
    <div class="space-y-2">
        <div
            v-for="option in options"
            :key="option.value"
            @click="!disabled && (model = option.value)"
            class="relative flex items-start gap-3 rounded-lg border p-3 shadow-sm transition-all"
            :class="[cardClass(option.value), disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer']">
            <span
                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="model === option.value
                    ? (savedValue === option.value ? 'bg-indigo-100 text-indigo-600' : 'bg-amber-100 text-amber-600')
                    : 'bg-gray-100 text-gray-500'">
                <FontAwesomeIcon :icon="taxPresetIcon(option.value)" class="h-4 w-4" />
            </span>

            <span class="flex flex-1 flex-col">
                <span class="flex items-center gap-2 text-sm font-medium text-gray-800">
                    {{ option.title }}
                    <span
                        v-if="savedValue === option.value"
                        class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">
                        {{ trans("Current") }}
                    </span>
                    <span
                        v-else-if="model === option.value"
                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                        {{ trans("Not saved yet") }}
                    </span>
                </span>
                <span v-if="option.description" class="mt-1 text-xs text-gray-500">
                    {{ option.description }}
                </span>
            </span>
        </div>
    </div>
</template>
