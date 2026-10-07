<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import type { SeoKeywordOptions, SeoKeywordRoutes, SeoResearchQuery } from "@/Components/Seo/types"

type TrackedKeywordRow = {
    id: number
    keyword: string
    country_code: string
    language_code: string
    device: string
    frequency: string
    is_active: boolean
    avg_monthly_searches: number | null
    update_route: routeType & { method: string }
    delete_route: routeType & { method: string }
}

const props = defineProps<{
    data?: object
    tab: string
    canEdit: boolean
    routes: SeoKeywordRoutes
    options: SeoKeywordOptions
    query: SeoResearchQuery
}>()

const locale = useLocaleStore()

const form = useForm({
    keyword: "",
    country_code: props.query.country_code,
    language_code: props.query.language_code,
    device: "mobile",
    frequency: "weekly",
})

const addKeyword = () => {
    form.post(route(props.routes.track.name, props.routes.track.parameters), {
        preserveScroll: true,
        only: ["tracked_keywords"],
        onSuccess: () => form.reset("keyword"),
    })
}

const update = (trackedKeyword: TrackedKeywordRow, changes: Partial<TrackedKeywordRow>) => {
    router.patch(route(trackedKeyword.update_route.name, trackedKeyword.update_route.parameters), changes, {
        preserveScroll: true,
        only: ["tracked_keywords"],
    })
}

const remove = (trackedKeyword: TrackedKeywordRow) => {
    if (!window.confirm(ctrans("Stop tracking :keyword?", { keyword: trackedKeyword.keyword }))) {
        return
    }

    router.delete(route(trackedKeyword.delete_route.name, trackedKeyword.delete_route.parameters), {
        preserveScroll: true,
        only: ["tracked_keywords"],
    })
}

const labelOf = (options: { value: string, label: string }[], value: string) => options.find((option) => option.value === value)?.label ?? value
</script>

<template>
    <div class="py-4">
        <form
            v-if="canEdit"
            class="mx-4 mb-4 grid grid-cols-1 gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 md:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end"
            @submit.prevent="addKeyword">
            <label class="block text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Keyword") }}</span>
                <PureInput v-model="form.keyword" class="mt-1" :placeholder="ctrans('For example: incense sticks')" />
            </label>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Country") }}</span>
                <PureMultiselect v-model="form.country_code" class="mt-1" :options="options.countries" searchable required />
            </label>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Language") }}</span>
                <PureMultiselect v-model="form.language_code" class="mt-1" :options="options.languages" searchable required />
            </label>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Device") }}</span>
                <PureMultiselect v-model="form.device" class="mt-1" :options="options.devices" required />
            </label>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Check") }}</span>
                <PureMultiselect v-model="form.frequency" class="mt-1" :options="options.frequencies" required />
            </label>
            <Button type="create" :label="ctrans('Track keyword')" :loading="form.processing" @click="addKeyword" />
            <p v-if="Object.keys(form.errors).length" role="alert" class="text-sm text-red-600 lg:col-span-6">
                {{ Object.values(form.errors)[0] }}
            </p>
        </form>

        <Table v-if="data" :resource="data" :name="tab">
            <template #cell(country_code)="{ item: trackedKeyword }: { item: TrackedKeywordRow }">
                {{ labelOf(options.countries, trackedKeyword.country_code) }}
            </template>

            <template #cell(language_code)="{ item: trackedKeyword }: { item: TrackedKeywordRow }">
                {{ labelOf(options.languages, trackedKeyword.language_code) }}
            </template>

            <template #cell(device)="{ item: trackedKeyword }: { item: TrackedKeywordRow }">
                <select
                    v-if="canEdit"
                    :value="trackedKeyword.device"
                    :aria-label="ctrans('Device')"
                    class="rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm"
                    @change="update(trackedKeyword, { device: ($event.target as HTMLSelectElement).value })">
                    <option v-for="option in options.devices" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <span v-else>{{ labelOf(options.devices, trackedKeyword.device) }}</span>
            </template>

            <template #cell(frequency)="{ item: trackedKeyword }: { item: TrackedKeywordRow }">
                <select
                    v-if="canEdit"
                    :value="trackedKeyword.frequency"
                    :aria-label="ctrans('Check')"
                    class="rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm"
                    @change="update(trackedKeyword, { frequency: ($event.target as HTMLSelectElement).value })">
                    <option v-for="option in options.frequencies" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <span v-else>{{ labelOf(options.frequencies, trackedKeyword.frequency) }}</span>
            </template>

            <template #cell(avg_monthly_searches)="{ item: trackedKeyword }: { item: TrackedKeywordRow }">
                <span class="tabular-nums">{{ trackedKeyword.avg_monthly_searches === null ? "-" : locale.number(trackedKeyword.avg_monthly_searches) }}</span>
            </template>

            <template #cell(is_active)="{ item: trackedKeyword }: { item: TrackedKeywordRow }">
                <input
                    type="checkbox"
                    :checked="trackedKeyword.is_active"
                    :disabled="!canEdit"
                    :aria-label="ctrans('Active')"
                    class="rounded border-gray-300 text-[--app-accent] focus:ring-[--app-accent]"
                    @change="update(trackedKeyword, { is_active: ($event.target as HTMLInputElement).checked })" />
            </template>

            <template #cell(actions)="{ item: trackedKeyword }: { item: TrackedKeywordRow }">
                <Button v-if="canEdit" type="tertiary" size="xs" :label="ctrans('Remove')" @click="remove(trackedKeyword)" />
            </template>
        </Table>
    </div>
</template>
