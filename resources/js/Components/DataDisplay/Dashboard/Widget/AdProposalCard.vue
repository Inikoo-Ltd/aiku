<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { trans } from "laravel-vue-i18n"

/**
 * One suggestion, with what it is based on shown rather than summarised.
 *
 * The figures come from the stored evidence, never from the sentence beside them. A model wrote that
 * sentence and models get numbers wrong, so nothing it says is allowed to become a number on screen.
 */
const props = defineProps<{
    proposal: {
        id: number
        type: string
        term: string
        campaign: string | null
        rationale: string | null
        evidence: Record<string, any>
        amount: number
        match_type: string | null
        ad_group_id: string | null
        ad_groups: { id: string; name: string | null }[]
        headlines: string[]
        existing_headlines: string[]
        is_draft: boolean
        draft_route: { name: string; parameters: Record<string, unknown>; payload: Record<string, unknown> } | null
        apply_route: { name: string; parameters: Record<string, unknown> }
        dismiss_route: { name: string; parameters: Record<string, unknown> }
    }
    currency: string
}>()

const locale = useLocaleStore()
const confirm = useConfirm()

const busy = ref(false)
const error = ref<string | null>(null)
const adGroup = ref<string>(props.proposal.ad_group_id ?? props.proposal.ad_groups[0]?.id ?? "")

const needsAdGroup = computed(
    () => !props.proposal.is_draft && props.proposal.type !== "strengthen_ad" && props.proposal.ad_groups.length > 1
)

const isStrengthen = computed(() => props.proposal.type === "strengthen_ad")

/* Every drafted line starts accepted, and a marketer strikes out the ones they do not want. Starting
   unticked would mean approving does nothing until twelve boxes are clicked, which is not an offer. */
const chosen = ref<Record<number, boolean>>(
    Object.fromEntries(props.proposal.headlines.map((_, index) => [index, true]))
)

const chosenHeadlines = computed(() => props.proposal.headlines.filter((_, index) => chosen.value[index]))

const evidenceCurrency = computed(() => props.proposal.evidence.currency ?? props.currency)

/* Only the facts that bear on the decision. A site search has no cost and a Google search has no
   session count, so each kind shows what it actually has rather than a row of dashes. */
const facts = computed(() => {
    const e = props.proposal.evidence
    const rows: { label: string; value: string }[] = []

    if (isStrengthen.value) {
        rows.push({
            label: trans("Headlines in use"),
            value: `${e.headline_count} ${trans("of")} ${e.headline_allowance}`,
        })
        rows.push({ label: trans("Ad group"), value: e.ad_group ?? "—" })
    } else if (e.products !== undefined) {
        rows.push({ label: trans("Products you sell"), value: locale.number(e.products) })
        rows.push({ label: trans("Visitors who searched for it"), value: locale.number(e.sessions) })
    } else if (e.sessions !== undefined) {
        rows.push({ label: trans("Visitors who searched your site"), value: locale.number(e.sessions) })
        rows.push({ label: trans("Times searched"), value: locale.number(e.searches) })
        rows.push({ label: trans("Clicked a result"), value: locale.number(e.clicked) })
    } else {
        rows.push({ label: trans("Clicks"), value: locale.number(e.clicks) })
        rows.push({ label: trans("Sales recorded"), value: locale.number(e.conversions) })
        rows.push({
            label: trans("Cost so far"),
            value: String(locale.currencyFormat(evidenceCurrency.value, e.cost ?? 0)),
        })
    }

    return rows
})

const submit = (routeName: string, parameters: Record<string, unknown>, payload: Record<string, unknown> = {}) =>
    router.post(route(routeName, parameters), payload, {
        preserveScroll: true,
        onStart: () => {
            busy.value = true
            error.value = null
        },
        onError: (errors) =>
            (error.value = Object.values(errors as Record<string, string>)[0] ?? ctrans("That was refused.")),
        onFinish: () => (busy.value = false),
    })

const approve = () => {
    if (isStrengthen.value) {
        confirm.require({
            header: ctrans("Add these headlines"),
            message:
                ctrans("They go live on an ad that is already running, and Google reviews them before showing them. The headlines already on the ad are kept."),
            rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
            acceptProps: { label: ctrans("Add them") },
            accept: () =>
                submit(props.proposal.apply_route.name, props.proposal.apply_route.parameters, {
                    headlines: chosenHeadlines.value,
                }),
        })

        return
    }

    const group = props.proposal.ad_groups.find((g) => g.id === adGroup.value)

    confirm.require({
        header: ctrans("Add this keyword"),
        message:
            ctrans("It starts costing money as soon as Google accepts it") +
            (group ? ctrans(", in ad group ") + (group.name ?? group.id) : "") +
            ". " + ctrans("Keyword: ") + props.proposal.term,
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: ctrans("Add it") },
        accept: () =>
            submit(props.proposal.apply_route.name, props.proposal.apply_route.parameters, {
                ad_group_id: adGroup.value || null,
            }),
    })
}

const dismiss = () =>
    confirm.require({
        header: ctrans("Turn this down"),
        message: ctrans("It will not be suggested again."),
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: ctrans("Turn it down") },
        accept: () => submit(props.proposal.dismiss_route.name, props.proposal.dismiss_route.parameters),
    })
</script>

<template>
    <article class="flex flex-col rounded-xl bg-white p-5 ring-1 ring-gray-200">
        <h3 class="text-sm font-medium text-gray-900">{{ proposal.term }}</h3>

        <p v-if="proposal.campaign" class="mt-0.5 text-xs text-gray-500">
            {{ proposal.campaign }}
            <span v-if="proposal.match_type" class="capitalize">· {{ proposal.match_type.toLowerCase() }}</span>
        </p>

        <p v-if="proposal.rationale" class="mt-3 text-xs text-gray-700">{{ proposal.rationale }}</p>

        <dl class="mt-4 space-y-1.5 text-xs">
            <div v-for="fact in facts" :key="fact.label" class="flex justify-between gap-3">
                <dt class="text-gray-500">{{ fact.label }}</dt>
                <dd class="tabular-nums text-gray-800">{{ fact.value }}</dd>
            </div>
        </dl>

        <!-- The drafted copy, beside what is already running, because the question is whether these
             belong with those. -->
        <div v-if="isStrengthen" class="mt-4 space-y-3">
            <div>
                <p class="text-xs font-medium text-gray-700">{{ trans("Already on this ad") }}</p>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(line, i) in proposal.existing_headlines" :key="`e-${i}`" class="text-xs text-gray-500">
                        {{ line }}
                    </li>
                </ul>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-700">
                    {{ trans("Suggested") }}
                    <span class="font-normal text-gray-500">· {{ chosenHeadlines.length }} {{ trans("selected") }}</span>
                </p>
                <ul class="mt-1 space-y-1">
                    <li v-for="(line, i) in proposal.headlines" :key="`n-${i}`">
                        <label class="flex items-start gap-2 text-xs">
                            <input
                                v-model="chosen[i]"
                                type="checkbox"
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span :class="chosen[i] ? 'text-gray-800' : 'text-gray-400 line-through'">{{ line }}</span>
                        </label>
                    </li>
                </ul>
            </div>
        </div>

        <p class="mt-3 border-t border-gray-100 pt-2 text-xs text-gray-500">{{ proposal.evidence.rule }}</p>

        <p v-if="proposal.is_draft && proposal.evidence.example_terms?.length" class="mt-2 text-xs text-gray-600">
            {{ trans("They searched for") }}:
            <span class="text-gray-500">{{ proposal.evidence.example_terms.slice(0, 4).join(", ") }}</span>
        </p>

        <div v-if="needsAdGroup" class="mt-3">
            <label :for="`proposal-group-${proposal.id}`" class="block text-xs text-gray-500">
                {{ trans("Put it in") }}
            </label>
            <select
                :id="`proposal-group-${proposal.id}`"
                v-model="adGroup"
                :disabled="busy"
                class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                <option v-for="group in proposal.ad_groups" :key="group.id" :value="group.id">
                    {{ group.name ?? group.id }}
                </option>
            </select>
        </div>

        <p v-if="error" class="mt-2 text-xs text-[#d03b3b]">{{ error }}</p>

        <div class="mt-4 flex flex-wrap items-center gap-2 pt-1">
            <Link
                v-if="proposal.is_draft && proposal.draft_route"
                :href="route(proposal.draft_route.name, proposal.draft_route.parameters)"
                method="post"
                :data="proposal.draft_route.payload"
                as="button"
                class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs text-white transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1">
                {{ trans("Review and build it") }}
            </Link>
            <Button
                v-else
                type="save"
                size="xs"
                :label="isStrengthen ? trans('Add them') : trans('Add it')"
                :loading="busy"
                :disabled="busy || (needsAdGroup && !adGroup) || (isStrengthen && !chosenHeadlines.length)"
                @click="approve" />
            <button
                type="button"
                :disabled="busy"
                class="rounded px-2 py-1 text-xs text-gray-500 underline-offset-2 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50"
                @click="dismiss">
                {{ trans("Not this one") }}
            </button>
        </div>
    </article>
</template>
