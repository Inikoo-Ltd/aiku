<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Multiselect from "@vueform/multiselect"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from "laravel-vue-i18n"
import { ctrans } from "@/Composables/useTrans"

library.add(faGoogle)

/**
 * One form, not a wizard. A Search campaign has exactly one of each thing it needs, and splitting six
 * short sections across six steps only hides from the user how much is still left to fill in.
 */
const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    unreachable_reason: string | null
    currency: string
    countries: { value: string; label: string }[]
    default_country: string | null
    default_url: string | null
    prefill?: { name: string | null; keywords: string[] }
    store_route: { name: string; parameters: Record<string, unknown> }
    index_route: { name: string; parameters: Record<string, unknown> }
}>()

const HEADLINE_MAX = 30
const DESCRIPTION_MAX = 90
const MATCH_TYPES = ["BROAD", "PHRASE", "EXACT"]

const form = useForm({
    name: props.prefill?.name ?? "",
    budget_amount: null as number | null,
    max_cpc: null as number | null,
    country_codes: props.default_country ? [props.default_country] : [],
    target_search_partners: false,
    ad_group_name: props.prefill?.name ?? "",
    keywords: (props.prefill?.keywords ?? []).join("\n"),
    match_type: "PHRASE",
    final_url: props.default_url ?? "",
    headlines: ["", "", ""],
    descriptions: ["", ""],
    validate_only: false,
})

const checkedWithGoogle = ref(false)

const keywordList = computed(() =>
    form.keywords
        .split("\n")
        .map((keyword) => keyword.trim())
        .filter(Boolean)
)

const filledHeadlines = computed(() => form.headlines.filter((headline) => headline.trim()).length)
const filledDescriptions = computed(() => form.descriptions.filter((description) => description.trim()).length)

/**
 * Google refuses an ad that repeats a headline or a description, comparing them without regard to
 * case. Caught here so the offending box is outlined while it is still being typed in, rather than
 * coming back as a refusal after the round trip.
 */
const duplicates = (lines: string[]) => {
    const seen = new Map<string, number>()
    const repeated = new Set<number>()

    lines.forEach((line, index) => {
        const key = line.trim().toLowerCase()
        if (!key) return

        if (seen.has(key)) {
            repeated.add(seen.get(key) as number)
            repeated.add(index)
        } else {
            seen.set(key, index)
        }
    })

    return repeated
}

const duplicateHeadlines = computed(() => duplicates(form.headlines))
const duplicateDescriptions = computed(() => duplicates(form.descriptions))

/**
 * What is still outstanding, named. Google will not accept a Search campaign missing any of these, so
 * the buttons stay dead until they are all there; a dead button that does not say why is just a
 * dead end, so the list is shown next to it and shrinks as the form is filled.
 */
const missing = computed(() => {
    const items: string[] = []

    if (!form.name.trim()) items.push(trans("a campaign name"))
    if (!(Number(form.budget_amount) > 0)) items.push(trans("a daily budget"))
    if (!form.country_codes.length) items.push(trans("at least one country"))
    if (!form.ad_group_name.trim()) items.push(trans("an ad group name"))
    if (!form.final_url.trim()) items.push(trans("a landing page"))
    if (!keywordList.value.length) items.push(trans("at least one keyword"))

    const headlinesShort = 3 - filledHeadlines.value
    if (headlinesShort > 0) {
        items.push(
            headlinesShort === 1
                ? trans("1 more headline")
                : ctrans(":count more headlines", { count: String(headlinesShort) })
        )
    }

    const descriptionsShort = 2 - filledDescriptions.value
    if (descriptionsShort > 0) {
        items.push(
            descriptionsShort === 1
                ? trans("1 more description")
                : ctrans(":count more descriptions", { count: String(descriptionsShort) })
        )
    }

    if (duplicateHeadlines.value.size) items.push(trans("headlines that repeat each other"))
    if (duplicateDescriptions.value.size) items.push(trans("descriptions that repeat each other"))

    return items
})

const canSubmit = computed(() => !props.unreachable_reason && missing.value.length === 0)

const submit = (validateOnly: boolean) => {
    form.transform((data) => ({
        ...data,
        keywords: keywordList.value,
        headlines: data.headlines.filter((headline: string) => headline.trim()),
        descriptions: data.descriptions.filter((description: string) => description.trim()),
        validate_only: validateOnly,
    })).post(route(props.store_route.name, props.store_route.parameters), {
        preserveScroll: true,
        onSuccess: () => {
            if (validateOnly) checkedWithGoogle.value = true
        },
    })
}

// Google allows up to 15 headlines and 4 descriptions; more of both usually means better ad strength.
const addHeadline = () => form.headlines.length < 15 && form.headlines.push("")
const addDescription = () => form.descriptions.length < 4 && form.descriptions.push("")
const removeHeadline = (index: number) => form.headlines.length > 3 && form.headlines.splice(index, 1)
const removeDescription = (index: number) => form.descriptions.length > 2 && form.descriptions.splice(index, 1)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div
        v-if="unreachable_reason"
        class="mx-4 mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        {{ unreachable_reason }}
    </div>

    <!-- Submitting on Enter is deliberately dead. Enter in a text field is a keystroke, not a decision
         to create a campaign in a live ad account, and this form has ten fields somebody could be part
         way through. Creating takes the button below. -->
    <form class="grid grid-cols-1 gap-4 px-4 py-4 lg:grid-cols-2" @submit.prevent>
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
            <h2 class="text-sm font-medium text-gray-800">{{ trans("Campaign") }}</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="gads-name" class="block text-xs text-gray-500">{{ trans("Name") }}</label>
                    <input
                        id="gads-name"
                        v-model="form.name"
                        type="text"
                        maxlength="255"
                        :placeholder="trans('Aromatherapy wholesale, UK')"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-[#d03b3b]">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="gads-budget" class="block text-xs text-gray-500">
                            {{ trans("Daily budget") }} ({{ currency }})
                        </label>
                        <input
                            id="gads-budget"
                            v-model.number="form.budget_amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm tabular-nums focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="form.errors.budget_amount" class="mt-1 text-xs text-[#d03b3b]">
                            {{ form.errors.budget_amount }}
                        </p>
                    </div>
                    <div>
                        <label for="gads-maxcpc" class="block text-xs text-gray-500">
                            {{ trans("Max cost per click") }} ({{ trans("optional") }})
                        </label>
                        <input
                            id="gads-maxcpc"
                            v-model.number="form.max_cpc"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm tabular-nums focus:border-indigo-500 focus:ring-indigo-500" />
                        <p class="mt-1 text-xs text-gray-500">
                            {{ trans("Without a ceiling, bidding pays whatever a click costs to spend the budget.") }}
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-gray-500" for="gads-countries">{{ trans("Show ads in") }}</label>
                    <Multiselect
                        id="gads-countries"
                        v-model="form.country_codes"
                        mode="tags"
                        searchable
                        :options="countries"
                        :placeholder="trans('Choose countries')"
                        class="mt-1" />
                    <p v-if="form.errors.country_codes" class="mt-1 text-xs text-[#d03b3b]">
                        {{ form.errors.country_codes }}
                    </p>
                </div>

                <label class="flex items-start gap-2 text-xs text-gray-600">
                    <input
                        v-model="form.target_search_partners"
                        type="checkbox"
                        class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <span>
                        {{ trans("Also show on Google's search partner sites") }}
                        <span class="block text-gray-500">
                            {{ trans("More reach, usually cheaper clicks, and harder to judge. The Display network stays off either way.") }}
                        </span>
                    </span>
                </label>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
            <h2 class="text-sm font-medium text-gray-800">{{ trans("Ad group and keywords") }}</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="gads-group" class="block text-xs text-gray-500">{{ trans("Ad group name") }}</label>
                    <input
                        id="gads-group"
                        v-model="form.ad_group_name"
                        type="text"
                        maxlength="255"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <p v-if="form.errors.ad_group_name" class="mt-1 text-xs text-[#d03b3b]">
                        {{ form.errors.ad_group_name }}
                    </p>
                </div>

                <div>
                    <label for="gads-keywords" class="block text-xs text-gray-500">
                        {{ trans("Keywords, one per line") }}
                        <span v-if="keywordList.length" class="text-gray-400">· {{ keywordList.length }}</span>
                    </label>
                    <textarea
                        id="gads-keywords"
                        v-model="form.keywords"
                        rows="7"
                        :placeholder="'wholesale aromatherapy\nbulk essential oils'"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <p v-if="form.errors.keywords" class="mt-1 text-xs text-[#d03b3b]">{{ form.errors.keywords }}</p>
                </div>

                <div>
                    <label for="gads-match" class="block text-xs text-gray-500">{{ trans("Match type") }}</label>
                    <select
                        id="gads-match"
                        v-model="form.match_type"
                        class="mt-1 rounded-md border-gray-300 text-sm capitalize focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="type in MATCH_TYPES" :key="type" :value="type" class="capitalize">
                            {{ type.toLowerCase() }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ trans("Phrase is the usual starting point: broad reaches furthest and wastes most.") }}
                    </p>
                </div>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-2">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-medium text-gray-800">{{ trans("The ad") }}</h2>
                <span class="text-xs text-gray-500">
                    {{ trans("Google mixes these itself, so each headline has to read on its own.") }}
                </span>
            </div>

            <div class="mt-4">
                <label for="gads-url" class="block text-xs text-gray-500">{{ trans("Landing page") }}</label>
                <input
                    id="gads-url"
                    v-model="form.final_url"
                    type="url"
                    :placeholder="'https://example.com/wholesale'"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-96" />
                <p v-if="form.errors.final_url" class="mt-1 text-xs text-[#d03b3b]">{{ form.errors.final_url }}</p>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <div class="flex items-baseline justify-between">
                        <h3 class="text-xs font-medium text-gray-700">
                            {{ trans("Headlines") }}
                            <span :class="filledHeadlines >= 3 ? 'text-gray-500' : 'text-[#a15c00]'">
                                · {{ filledHeadlines }}/15, {{ trans("at least 3") }}
                            </span>
                        </h3>
                        <button
                            v-if="form.headlines.length < 15"
                            type="button"
                            class="rounded px-1.5 py-0.5 text-xs text-indigo-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            @click="addHeadline">
                            {{ trans("Add one") }}
                        </button>
                    </div>

                    <div class="mt-2 space-y-2">
                        <div v-for="(headline, index) in form.headlines" :key="`headline-${index}`">
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="form.headlines[index]"
                                    type="text"
                                    :maxlength="HEADLINE_MAX"
                                    :aria-label="trans('Headline') + ' ' + (index + 1)"
                                    class="w-full rounded-md text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :class="duplicateHeadlines.has(index) ? 'border-[#d03b3b]' : 'border-gray-300'" />
                                <span class="w-10 shrink-0 text-right text-xs tabular-nums text-gray-500">
                                    {{ headline.length }}/{{ HEADLINE_MAX }}
                                </span>
                                <button
                                    type="button"
                                    :disabled="form.headlines.length <= 3"
                                    :aria-label="trans('Remove headline') + ' ' + (index + 1)"
                                    class="rounded px-1 text-xs text-gray-400 hover:text-[#d03b3b] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:invisible"
                                    @click="removeHeadline(index)">
                                    &times;
                                </button>
                            </div>
                            <p v-if="form.errors[`headlines.${index}`]" class="mt-1 text-xs text-[#d03b3b]">
                                {{ form.errors[`headlines.${index}`] }}
                            </p>
                        </div>
                    </div>
                    <p v-if="duplicateHeadlines.size" class="mt-1 text-xs text-[#d03b3b]">
                        {{ trans("Google will not accept an ad that repeats a headline. Make the outlined ones different.") }}
                    </p>
                    <p v-if="form.errors.headlines" class="mt-1 text-xs text-[#d03b3b]">{{ form.errors.headlines }}</p>
                </div>

                <div>
                    <div class="flex items-baseline justify-between">
                        <h3 class="text-xs font-medium text-gray-700">
                            {{ trans("Descriptions") }}
                            <span :class="filledDescriptions >= 2 ? 'text-gray-500' : 'text-[#a15c00]'">
                                · {{ filledDescriptions }}/4, {{ trans("at least 2") }}
                            </span>
                        </h3>
                        <button
                            v-if="form.descriptions.length < 4"
                            type="button"
                            class="rounded px-1.5 py-0.5 text-xs text-indigo-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            @click="addDescription">
                            {{ trans("Add one") }}
                        </button>
                    </div>

                    <div class="mt-2 space-y-2">
                        <div v-for="(description, index) in form.descriptions" :key="`description-${index}`">
                            <div class="flex items-start gap-2">
                                <textarea
                                    v-model="form.descriptions[index]"
                                    rows="2"
                                    :maxlength="DESCRIPTION_MAX"
                                    :aria-label="trans('Description') + ' ' + (index + 1)"
                                    class="w-full rounded-md text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :class="duplicateDescriptions.has(index) ? 'border-[#d03b3b]' : 'border-gray-300'"></textarea>
                                <span class="w-12 shrink-0 pt-2 text-right text-xs tabular-nums text-gray-500">
                                    {{ description.length }}/{{ DESCRIPTION_MAX }}
                                </span>
                                <button
                                    type="button"
                                    :disabled="form.descriptions.length <= 2"
                                    :aria-label="trans('Remove description') + ' ' + (index + 1)"
                                    class="rounded px-1 pt-2 text-xs text-gray-400 hover:text-[#d03b3b] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:invisible"
                                    @click="removeDescription(index)">
                                    &times;
                                </button>
                            </div>
                            <p v-if="form.errors[`descriptions.${index}`]" class="mt-1 text-xs text-[#d03b3b]">
                                {{ form.errors[`descriptions.${index}`] }}
                            </p>
                        </div>
                    </div>
                    <p v-if="duplicateDescriptions.size" class="mt-1 text-xs text-[#d03b3b]">
                        {{ trans("Google will not accept an ad that repeats a description. Make the outlined ones different.") }}
                    </p>
                    <p v-if="form.errors.descriptions" class="mt-1 text-xs text-[#d03b3b]">
                        {{ form.errors.descriptions }}
                    </p>
                </div>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-2">
            <p class="text-xs text-gray-600">
                {{ trans("The campaign is created paused and spends nothing until you resume it from its own page. Check it with Google first: that runs the whole thing past Google's validation without creating anything.") }}
            </p>

            <p v-if="checkedWithGoogle" class="mt-2 text-xs text-[#006300]">
                {{ trans("Google accepted this as it stands.") }}
            </p>

            <p v-if="missing.length" class="mt-3 text-xs text-[#a15c00]">
                {{ trans("Still needed") }}: {{ missing.join(", ") }}.
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <Button
                    type="tertiary"
                    :label="trans('Check it with Google')"
                    :loading="form.processing"
                    :disabled="!canSubmit || form.processing"
                    @click="submit(true)" />
                <Button
                    type="save"
                    :label="trans('Create it, paused')"
                    :loading="form.processing"
                    :disabled="!canSubmit || form.processing"
                    @click="submit(false)" />
                <Link
                    :href="route(index_route.name, index_route.parameters)"
                    class="rounded px-2 py-1 text-sm text-gray-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    {{ trans("Cancel") }}
                </Link>
            </div>
        </section>
    </form>
</template>

<style src="@vueform/multiselect/themes/default.css"></style>
