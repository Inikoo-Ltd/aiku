<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"

/**
 * The controls under a locally paged list: what is on screen, how much fits on a page, and the way
 * back and forward. `unit` names what is being counted, so the line reads "of 617 keywords" rather
 * than leaving the reader to guess what a row is.
 */
defineProps<{
    firstRow: number
    lastRow: number
    total: number
    page: number
    pageCount: number
    perPage: number
    perPageOptions: number[]
    unit: string
}>()

const emit = defineEmits<{
    (e: "update:page", value: number): void
    (e: "update:perPage", value: number): void
}>()

const locale = useLocaleStore()
</script>

<template>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500">
        <span aria-live="polite">
            {{ trans("Showing") }} {{ locale.number(firstRow) }} {{ trans("to") }} {{ locale.number(lastRow) }}
            {{ trans("of") }} {{ locale.number(total) }} {{ unit }}
        </span>

        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-1.5">
                {{ trans("Per page") }}
                <select
                    :value="perPage"
                    class="rounded-md border-gray-300 py-0.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                    @change="emit('update:perPage', Number(($event.target as HTMLSelectElement).value))">
                    <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
                </select>
            </label>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    :disabled="page === 1"
                    class="rounded px-2 py-1 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-40 disabled:hover:bg-transparent"
                    @click="emit('update:page', page - 1)">
                    {{ trans("Previous") }}
                </button>
                <span class="tabular-nums">{{ page }} / {{ pageCount }}</span>
                <button
                    type="button"
                    :disabled="page === pageCount"
                    class="rounded px-2 py-1 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-40 disabled:hover:bg-transparent"
                    @click="emit('update:page', page + 1)">
                    {{ trans("Next") }}
                </button>
            </div>
        </div>
    </div>
</template>
