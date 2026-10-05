<script setup lang="ts">
import { computed, ref } from "vue"
import { Popover } from "primevue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown } from "@fal"

library.add(faChevronDown)

interface PillOption {
    value: string
    label: string
    count?: number
}

const props = withDefaults(
    defineProps<{
        label: string
        options: PillOption[]
        selected: string | null
        dropdown?: boolean
    }>(),
    { dropdown: false }
)

const emit = defineEmits<{ select: [value: string | null] }>()

const popover = ref()

const selectedLabel = computed(() => props.options.find((option) => option.value === props.selected)?.label ?? props.selected)

const idleClass = "text-gray-500 hover:bg-white hover:text-gray-800"

function selectedClass(value: string | null): string {
    return `bg-white font-medium shadow-sm ring-1 ring-gray-200 ${value === null ? "text-gray-800" : "text-[--app-accent]"}`
}

function choose(value: string | null): void {
    popover.value?.hide()
    emit("select", value)
}
</script>

<template>
    <div v-if="dropdown" class="inline-flex">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs transition"
            :class="selected === null ? 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' : 'border-gray-300 bg-white text-[--app-accent] shadow-sm'"
            @click="popover.toggle($event)">
            <span :class="selected === null ? 'text-gray-400' : ''">{{ label }}</span>
            <span class="font-medium">{{ selected === null ? ctrans("All") : selectedLabel }}</span>
            <FontAwesomeIcon icon="fal fa-chevron-down" class="text-[10px] opacity-60" fixed-width aria-hidden="true" />
        </button>
        <Popover ref="popover">
            <div class="flex max-h-72 max-w-xl flex-wrap gap-1 overflow-y-auto text-xs">
                <button
                    type="button"
                    class="rounded-md px-2.5 py-1 transition duration-200"
                    :class="selected === null ? selectedClass(null) : idleClass"
                    @click="choose(null)">
                    {{ ctrans("All") }}
                </button>
                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    class="flex items-center gap-1 rounded-md px-2.5 py-1 transition duration-200"
                    :class="selected === option.value ? selectedClass(option.value) : idleClass"
                    @click="choose(option.value)">
                    {{ option.label }}
                    <span v-if="option.count !== undefined" class="opacity-60">{{ option.count }}</span>
                </button>
            </div>
        </Popover>
    </div>

    <div v-else class="inline-flex flex-wrap items-center gap-0.5 rounded-lg border border-gray-200 bg-gray-50 px-1.5 py-1 text-xs">
        <span class="mr-1 font-medium uppercase tracking-wide text-gray-400">{{ label }}</span>
        <button
            type="button"
            class="rounded-md px-2 py-0.5 transition duration-200"
            :class="selected === null ? selectedClass(null) : idleClass"
            @click="emit('select', null)">
            {{ ctrans("All") }}
        </button>
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            class="flex items-center gap-1 rounded-md px-2 py-0.5 transition duration-200"
            :class="selected === option.value ? selectedClass(option.value) : idleClass"
            @click="emit('select', option.value)">
            {{ option.label }}
            <span v-if="option.count !== undefined" class="opacity-60">{{ option.count }}</span>
        </button>
    </div>
</template>
