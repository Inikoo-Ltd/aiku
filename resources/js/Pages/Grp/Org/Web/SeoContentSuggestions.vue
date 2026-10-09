<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

type SuggestionRow = {
    id: number
    field: "title" | "description"
    current_value: string | null
    suggestion: string
    reason: string
    state: "pending" | "accepted" | "dismissed"
    created_at: string | null
    decided_at: string | null
    decided_by: string | null
    webpage_slug: string
    webpage_code: string
    accept_route: routeType
    dismiss_route: routeType
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    websiteSlug: string
    canEdit: boolean
    counts: Record<string, number>
    limits: { title: number, description: number }
    model: string
    data: { data: SuggestionRow[] }
}>()

const locale = useLocaleStore()
const page = usePage()

const fieldLabels: Record<string, string> = {
    title: ctrans("Page title"),
    description: ctrans("Meta description"),
}

const reasonLabels: Record<string, string> = {
    requested: ctrans("Asked for"),
    low_ctr: ctrans("Many Google impressions, few clicks"),
    missing_title: ctrans("No title"),
    missing_meta_description: ctrans("No meta description"),
    duplicate_title: ctrans("Title used by other pages"),
    duplicate_meta_description: ctrans("Meta description used by other pages"),
    title_too_long: ctrans("Title too long"),
    title_too_short: ctrans("Title too short"),
    meta_description_too_long: ctrans("Meta description too long"),
    meta_description_too_short: ctrans("Meta description too short"),
}

const stateOptions = computed(() => [
    { value: "pending", label: ctrans("Waiting (:count)", { count: locale.number(props.counts.pending ?? 0) }) },
    { value: "accepted", label: ctrans("Used (:count)", { count: locale.number(props.counts.accepted ?? 0) }) },
    { value: "dismissed", label: ctrans("Dismissed (:count)", { count: locale.number(props.counts.dismissed ?? 0) }) },
])

const state = computed({
    get: () => new URL(page.url, window.location.origin).searchParams.get("filter[state]") ?? "pending",
    set: (value: string) => {
        const url = new URL(page.url, window.location.origin)

        url.searchParams.delete("page")
        url.searchParams.set("filter[state]", value)

        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true })
    },
})

const drafts = reactive<Record<number, string>>({})

watch(() => props.data.data, (rows) => rows.forEach((row) => drafts[row.id] ??= row.suggestion), { immediate: true })

const busy = ref<string | null>(null)
const errors = reactive<Record<number, string | null>>({})

const decide = (row: SuggestionRow, action: "accept" | "dismiss") => {
    const target = action === "accept" ? row.accept_route : row.dismiss_route

    router.patch(route(target.name, target.parameters), action === "accept" ? { value: drafts[row.id] } : {}, {
        preserveScroll: true,
        onStart: () => {
            busy.value = `${action}-${row.id}`
            errors[row.id] = null
        },
        onError: (messages) => errors[row.id] = Object.values(messages)[0] ?? null,
        onFinish: () => busy.value = null,
    })
}

const webpageHref = (row: SuggestionRow) => {
    const params = route().params as Record<string, string>

    return route("grp.org.shops.show.web.webpages.show", [params.organisation, params.shop, props.websiteSlug, row.webpage_slug])
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 pt-4">
        <SegmentedToggle v-model="state" :options="stateOptions" :ariaLabel="ctrans('State')" />
        <span class="text-xs text-gray-500">
            {{ ctrans("Written by :model through the AI gateway. Nothing is published until someone uses it; the text can be edited first.", { model }) }}
        </span>
    </div>

    <Table :resource="data">
        <template #cell(webpage_code)="{ item: row }: { item: SuggestionRow }">
            <Link :href="webpageHref(row)" class="primaryLink">{{ row.webpage_code }}</Link>
        </template>

        <template #cell(suggestion)="{ item: row }: { item: SuggestionRow }">
            <div class="max-w-2xl py-1">
                <div class="text-xs font-medium text-gray-700">{{ fieldLabels[row.field] }}</div>
                <p class="mt-0.5 text-xs text-gray-500">{{ ctrans("Now") }}: <span class="text-gray-700">{{ row.current_value || ctrans("Not set") }}</span></p>

                <template v-if="row.state === 'pending' && canEdit">
                    <Textarea v-model="drafts[row.id]" class="mt-1.5 w-full text-sm" autoResize :rows="row.field === 'title' ? 1 : 2" :aria-label="ctrans('Suggested :field', { field: fieldLabels[row.field] })" />
                    <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs tabular-nums" :class="(drafts[row.id] ?? '').length > limits[row.field] ? 'text-red-700' : 'text-gray-500'">
                            {{ (drafts[row.id] ?? "").length }}/{{ limits[row.field] }}
                        </span>
                        <div class="flex gap-2">
                            <Button type="tertiary" size="xs" :label="ctrans('Dismiss')" :loading="busy === `dismiss-${row.id}`" @click="decide(row, 'dismiss')" />
                            <Button type="primary" size="xs" :label="ctrans('Use this')" :loading="busy === `accept-${row.id}`" :disabled="!(drafts[row.id] ?? '').trim()" @click="decide(row, 'accept')" />
                        </div>
                    </div>
                    <p v-if="errors[row.id]" role="alert" class="mt-1 text-xs text-red-700">{{ errors[row.id] }}</p>
                </template>

                <template v-else>
                    <p class="mt-1 text-sm text-gray-900">{{ row.suggestion }}</p>
                    <p v-if="row.decided_at" class="mt-0.5 text-xs text-gray-500">
                        {{ row.state === "accepted" ? ctrans("Used :date", { date: useFormatTime(row.decided_at) }) : ctrans("Dismissed :date", { date: useFormatTime(row.decided_at) }) }}
                        <span v-if="row.decided_by">{{ ctrans("by :name", { name: row.decided_by }) }}</span>
                    </p>
                </template>
            </div>
        </template>

        <template #cell(reason)="{ item: row }: { item: SuggestionRow }">
            <span class="text-sm text-gray-700">{{ reasonLabels[row.reason] ?? row.reason }}</span>
        </template>

        <template #cell(created_at)="{ item: row }: { item: SuggestionRow }">
            <span class="tabular-nums">{{ row.created_at ? useFormatTime(row.created_at) : "-" }}</span>
        </template>
    </Table>
</template>
