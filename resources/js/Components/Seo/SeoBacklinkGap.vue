<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import Column from "primevue/column"
import DataTable from "primevue/datatable"
import InputText from "primevue/inputtext"
import MultiSelect from "primevue/multiselect"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"

type GapRow = { referring_domain: string, rank: number | null, linked: string[] }

type GapData = {
    competitors: string[]
    domains: string[]
    max: number
    result: { domains: string[], fetched_at: string, rows: GapRow[] } | null
    error: string | null
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: GapData | null
    tab: string
    domain: string
}>()

const locale = useLocaleStore()
const page = usePage()

const picked = ref<string[]>((props.data?.domains ?? []).filter((domain) => props.data?.competitors.includes(domain)))
const typed = ref((props.data?.domains ?? []).filter((domain) => !props.data?.competitors.includes(domain)).join(", "))
const isSearching = ref(false)

const selectedDomains = computed(() => [
    ...picked.value,
    ...typed.value.split(",").map((domain) => domain.trim()).filter(Boolean),
])

const search = () => {
    const url = new URL(page.url, window.location.origin)

    url.searchParams.set("gap_domains", selectedDomains.value.join(","))

    router.get(url.pathname + url.search, {}, {
        preserveState: true,
        preserveScroll: true,
        only: [props.tab],
        onStart: () => isSearching.value = true,
        onFinish: () => isSearching.value = false,
    })
}

const resultDomains = computed(() => props.data?.result?.domains ?? [])

const minimumOptions = computed(() => {
    const count = resultDomains.value.length

    return [
        { value: 1, label: ctrans("Links to 1 or more") },
        ...(count > 2 ? [{ value: 2, label: ctrans("2 or more") }] : []),
        ...(count > 1 ? [{ value: count, label: ctrans("All of them") }] : []),
    ]
})

const minimum = ref(1)

watch(resultDomains, (domains) => minimum.value = domains.length > 1 ? 2 : 1, { immediate: true })

const rows = computed(() => (props.data?.result?.rows ?? []).filter((row) => row.linked.length >= minimum.value))
</script>

<template>
    <div v-if="data" class="space-y-4 px-4 py-4">
        <form
            class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 lg:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_auto] lg:items-end"
            @submit.prevent="search">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Competitors") }}</span>
                <MultiSelect
                    v-model="picked"
                    class="h-10 w-full items-center"
                    :options="data.competitors"
                    :selectionLimit="data.max"
                    :placeholder="data.competitors.length ? ctrans('Pick up to :max', { max: data.max }) : ctrans('No competitors set yet')"
                    display="chip" />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Or other domains") }}</span>
                <InputText v-model="typed" class="h-10 w-full" :placeholder="ctrans('Comma separated, for example: example.com')" />
            </label>
            <Button
                class="h-10 justify-center"
                type="primary"
                :label="ctrans('Find the gap')"
                icon="fal fa-search"
                :loading="isSearching"
                :disabled="!selectedDomains.length || selectedDomains.length > data.max"
                @click="search" />
        </form>

        <p v-if="data.error" role="alert" class="rounded-xl bg-white px-5 py-4 text-sm text-red-700 ring-1 ring-gray-200">{{ data.error }}</p>

        <p v-if="!data.result" class="text-sm text-gray-600">
            {{ ctrans("Pick up to :max competitors to see the domains that link to them but not to :domain. A set of domains costs a few cents of DataForSEO credit once and is kept for 30 days.", { max: data.max, domain }) }}
        </p>

        <section v-else class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Backlink gap')">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-gray-100 px-5 py-3">
                <div>
                    <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Linking to them, not to :domain", { domain }) }}</h2>
                    <p class="text-xs text-gray-500">
                        {{ ctrans(":count domains, the top 1,000 from DataForSEO, fetched :date", { count: locale.number(rows.length), date: useFormatTime(data.result.fetched_at) }) }}
                    </p>
                </div>
                <SegmentedToggle v-if="minimumOptions.length > 1" v-model="minimum" :options="minimumOptions" :ariaLabel="ctrans('Linked domains')" />
            </div>

            <p v-if="!rows.length" class="px-5 py-4 text-sm text-gray-600">{{ ctrans("No domain matches this filter.") }}</p>

            <DataTable v-else :value="rows" dataKey="referring_domain" paginator :rows="25" :rowsPerPageOptions="[25, 50, 100]" size="small" class="text-sm">
                <Column field="referring_domain" :header="ctrans('Referring domain')">
                    <template #body="{ data: row }">
                        <a :href="`https://${row.referring_domain}`" target="_blank" rel="noopener noreferrer" class="text-gray-900 underline-offset-2 hover:underline focus-visible:underline">{{ row.referring_domain }}</a>
                    </template>
                </Column>
                <Column field="rank" :header="ctrans('Rank')" sortable :pt="{ columnHeaderContent: { class: 'justify-end' }, bodyCell: { class: '!text-right tabular-nums' } }">
                    <template #body="{ data: row }">{{ row.rank ?? "-" }}</template>
                </Column>
                <Column v-for="gapDomain in resultDomains" :key="gapDomain" :header="gapDomain" :pt="{ columnHeaderContent: { class: 'justify-center' }, bodyCell: { class: '!text-center' } }">
                    <template #body="{ data: row }">
                        <span v-if="row.linked.includes(gapDomain)" class="text-green-700" :aria-label="ctrans('Links to :domain', { domain: gapDomain })">✓</span>
                        <span v-else class="text-gray-300" aria-hidden="true">-</span>
                    </template>
                </Column>
            </DataTable>
        </section>
    </div>
</template>
