<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import ConfirmDialog from "primevue/confirmdialog"
import AdProposalCard from "@/Components/DataDisplay/Dashboard/Widget/AdProposalCard.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from "laravel-vue-i18n"

type Proposal = {
    id: number
    type: string
    type_label: string
    term: string
    campaign: string | null
    rationale: string | null
    evidence: Record<string, any>
    amount: number
    match_type: string | null
    ad_group_id: string | null
    ad_groups: { id: string; name: string | null }[]
    apply_route: { name: string; parameters: Record<string, unknown> }
    dismiss_route: { name: string; parameters: Record<string, unknown> }
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    proposals: Proposal[]
    currency: string
    stats: { applied: number; dismissed: number; last_run_at: string | null }
}>()

/* Grouped by kind so a marketer reads one kind of decision at a time, rather than switching between
   "should we bid on this" and "is this the right ad group" every card. */
const groups = computed(() => {
    const byType = new Map<string, { label: string; items: Proposal[] }>()

    props.proposals.forEach((proposal) => {
        if (!byType.has(proposal.type)) {
            byType.set(proposal.type, { label: proposal.type_label, items: [] })
        }
        byType.get(proposal.type)?.items.push(proposal)
    })

    return Array.from(byType.values())
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <ConfirmDialog />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4">
        <p class="max-w-3xl text-xs text-gray-600">
            {{ trans("Found by reading your own figures: what visitors search for on your site, and what people searched on Google before they bought. Every number shown is measured, not predicted. Approving does exactly what the matching button on the campaign page does, and can be undone there.") }}
        </p>

        <p v-if="stats.last_run_at" class="mt-2 text-xs text-gray-500">
            {{ trans("Last looked") }}: {{ useFormatTime(stats.last_run_at, { formatTime: "short-datetime" }) }}
            <span v-if="stats.applied || stats.dismissed">
                · {{ trans("you have applied") }} {{ stats.applied }},
                {{ trans("turned down") }} {{ stats.dismissed }}
            </span>
        </p>
    </div>

    <div v-if="proposals.length" class="space-y-6 px-4 pb-8">
        <section v-for="group in groups" :key="group.label">
            <h2 class="text-sm font-medium text-gray-800">
                {{ group.label }}
                <span class="font-normal text-gray-500">· {{ group.items.length }}</span>
            </h2>

            <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-3">
                <AdProposalCard
                    v-for="proposal in group.items"
                    :key="proposal.id"
                    :proposal="proposal"
                    :currency="currency" />
            </div>
        </section>
    </div>

    <!-- Empty is the normal state most mornings, so it says so rather than implying something broke. -->
    <div v-else class="mx-4 mb-8 rounded-xl bg-white p-8 text-center ring-1 ring-gray-200">
        <p class="text-sm text-gray-700">{{ trans("Nothing to suggest at the moment.") }}</p>
        <p class="mt-1 text-xs text-gray-500">
            {{ trans("Your figures are checked every morning. Suggestions appear here when something in them is worth acting on, which is not every day.") }}
        </p>
    </div>
</template>
