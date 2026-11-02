<!--
  - Author Louis Perez
  - Created on 16-10-2026-12h-40m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import Select from "primevue/select"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"

interface UserOption {
    value: number | string
    label: string
    avatar?: Record<string, string> | null
}

const props = defineProps<{
    options: UserOption[]
    placeholder?: string
    size?: "small" | "large"
    disabled?: boolean
    appendTo?: "body" | "self"
}>()

const model = defineModel<number | string | null>()

const optionFor = (value: unknown) => props.options.find((option) => option.value === value)
</script>

<template>
    <Select v-model="model" :options="options" optionLabel="label" optionValue="value" filter :size="size" :disabled="disabled" :placeholder="placeholder" :appendTo="appendTo ?? 'body'" class="w-full min-w-0" overlayClass="text-sm" :pt="{ label: { class: 'min-w-0' } }">
        <template #value="{ value, placeholder: emptyText }">
            <span v-if="optionFor(value)" class="flex min-w-0 items-center gap-2">
                <TicketUserAvatar :name="optionFor(value)!.label" :avatar="optionFor(value)!.avatar" size="sm" />
                <span class="truncate">{{ optionFor(value)!.label }}</span>
            </span>
            <span v-else class="text-gray-400">{{ emptyText }}</span>
        </template>
        <template #option="{ option }">
            <span class="flex items-center gap-2">
                <TicketUserAvatar :name="option.label" :avatar="option.avatar" size="sm" />
                <span class="truncate">{{ option.label }}</span>
            </span>
        </template>
    </Select>
</template>
