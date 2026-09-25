<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Deferred, Head, Link, router, useForm } from "@inertiajs/vue3"
import axios from "axios"
import Multiselect from "@vueform/multiselect"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import MailshotJourney from "@/Components/Navigation/MailshotJourney.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import GoogleAdsTextList from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsTextList.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { ctrans } from "@/Composables/useTrans"

library.add(faGoogle)

/**
 * A campaign that exists only in Aiku, and the page where it gets written.
 *
 * Everything is editable here, the type included, because a campaign is not something anybody fills
 * in correctly in one sitting: the ad text gets rewritten, the images get swapped, somebody else
 * reads it before it goes live. The button that made it asked nothing at all.
 *
 * No figures, because nothing is running to have any. Those arrive with the metrics page once this
 * has been published and switched on.
 */
const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    journey: { key: string; label: string; current: boolean; done?: boolean; disabled?: boolean; route: routeType }[]
    campaign: { slug: string; name: string; channel_type: string; data: Record<string, any> }
    currency: string
    campaign_types: { value: string; label: string; description: string }[]
    missing: string[]
    countries: { value: string; label: string }[]
    images?: { id: number; name: string; thumbnail: string }[]
    image_search: string
    image_route: { name: string; parameters: Record<string, unknown> }
    update_route: { name: string; parameters: Record<string, unknown> }
    index_route: { name: string; parameters: Record<string, unknown> }
}>()

const data = props.campaign.data ?? {}

const form = useForm({
    name: props.campaign.name,
    channel_type: props.campaign.channel_type,
    budget_amount: data.budget_amount ?? null,
    max_cpc: data.max_cpc ?? null,
    target_cpa: data.target_cpa ?? null,
    final_url: data.final_url ?? "",
    country_codes: data.country_codes ?? [],
    ad_group_name: data.ad_group_name ?? "",
    asset_group_name: data.asset_group_name ?? "",
    business_name: data.business_name ?? "",
    long_headline: data.long_headline ?? "",
    match_type: data.match_type ?? "PHRASE",
    headlines: [...(data.headlines ?? [])] as string[],
    descriptions: [...(data.descriptions ?? [])] as string[],
    keywords: [...(data.keywords ?? [])] as string[],
    search_themes: [...(data.search_themes ?? [])] as string[],
    marketing_images: (data.marketing_images ?? []) as number[],
    square_marketing_images: (data.square_marketing_images ?? []) as number[],
    logos: (data.logos ?? []) as number[],
})

/* Read from the form rather than from the campaign, so choosing a different type reshapes the page
   under the cursor instead of after a save and a reload. */
const isSearch = computed(() => form.channel_type === "SEARCH")
const isPmax = computed(() => form.channel_type === "PERFORMANCE_MAX")
const isDisplay = computed(() => form.channel_type === "DISPLAY")
const isDemandGen = computed(() => form.channel_type === "DEMAND_GEN")
const needsLongHeadline = computed(() => isPmax.value || isDisplay.value)

const filled = (rows: string[]): string[] => rows.map((row) => row.trim()).filter(Boolean)

const isBlank = (value: unknown) => value === null || value === undefined || String(value).trim() === ""

/* Google's own limits, the same ones the server holds, checked as each field is typed so a wrong value
   is marked where it was entered rather than after a round trip. */
const LIST_LIMITS = {
    headlines: { maxLength: 30, maxItems: 15 },
    descriptions: { maxLength: 90, maxItems: 5 },
    keywords: { maxLength: 80, maxItems: 100 },
    search_themes: { maxLength: 80, maxItems: 25 },
} as const

const moneyProblem = (value: unknown): string | undefined => {
    if (isBlank(value)) {
        return undefined
    }

    const amount = Number(value)

    if (Number.isNaN(amount) || amount < 0.01) {
        return ctrans("At least 0.01")
    }

    if (amount > 1000000) {
        return ctrans("At most 1,000,000")
    }

    return undefined
}

const urlProblem = (value: string): string | undefined => {
    if (isBlank(value)) {
        return undefined
    }

    try {
        const url = new URL(value.trim())

        return ["http:", "https:"].includes(url.protocol) && url.hostname.includes(".")
            ? undefined
            : ctrans("A full web address, starting with https://")
    } catch {
        return ctrans("A full web address, starting with https://")
    }
}

const problems = computed<Record<string, string | undefined>>(() => ({
    name: isBlank(form.name) ? ctrans("The campaign needs a name") : form.name.length > 255 ? ctrans("Up to 255 characters") : undefined,
    budget_amount: moneyProblem(form.budget_amount),
    max_cpc: isSearch.value || isDisplay.value ? moneyProblem(form.max_cpc) : undefined,
    target_cpa: isDemandGen.value ? moneyProblem(form.target_cpa) : undefined,
    final_url: urlProblem(form.final_url),
    business_name: !isSearch.value && form.business_name.length > 25 ? ctrans("Up to 25 characters") : undefined,
    long_headline: needsLongHeadline.value && form.long_headline.length > 90 ? ctrans("Up to 90 characters") : undefined,
    ...Object.fromEntries(
        (Object.keys(LIST_LIMITS) as (keyof typeof LIST_LIMITS)[]).map((key) => [
            key,
            form[key].some((row: string) => row.trim().length > LIST_LIMITS[key].maxLength) ? ctrans("Too long") : undefined,
        ])
    ),
}))

const hasProblems = computed(() => Object.values(problems.value).some(Boolean))

const errorFor = (key: string): string | undefined => problems.value[key] ?? form.errors[key]

const fieldClass = (key: string) =>
    errorFor(key)
        ? "border-[#d03b3b] focus:border-[#d03b3b] focus:ring-[#d03b3b]"
        : "border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"

/* A message from the last save stops being true the moment the field is changed. */
const clearServerErrors = (key: string) => {
    const stale = Object.keys(form.errors).filter((field) => field === key || field.startsWith(`${key}.`))

    if (stale.length) {
        form.clearErrors(...(stale as (keyof typeof form.errors)[]))
    }
}

const save = () => {
    if (hasProblems.value) {
        return
    }

    form
        .transform((payload) => ({
            ...payload,
            headlines: filled(payload.headlines),
            descriptions: filled(payload.descriptions),
            keywords: filled(payload.keywords),
            search_themes: filled(payload.search_themes),
            final_url: payload.final_url.trim(),
            budget_amount: isBlank(payload.budget_amount) ? null : payload.budget_amount,
            max_cpc: isBlank(payload.max_cpc) ? null : payload.max_cpc,
            target_cpa: isBlank(payload.target_cpa) ? null : payload.target_cpa,
        }))
        .patch(route(props.update_route.name, props.update_route.parameters), { preserveScroll: true })
}

/* The image slots this type fills. Each is its own list because an image that works as a wide banner
   is the wrong shape for a square one, and Google checks when the campaign is published. */
const imageRoles = computed(() =>
    [
        !isSearch.value ? { key: "marketing_images", label: ctrans("Landscape images, 1.91 to 1"), hint: ctrans("Roughly 1200 by 628") } : null,
        isPmax.value || isDisplay.value
            ? { key: "square_marketing_images", label: ctrans("Square images"), hint: ctrans("Roughly 1200 by 1200") }
            : null,
        !isSearch.value ? { key: "logos", label: ctrans("Logo, square"), hint: ctrans("Roughly 1200 by 1200") } : null,
    ].filter(Boolean) as { key: string; label: string; hint: string }[]
)

const uploaded = ref<{ id: number; name: string; thumbnail: string }[]>([])
const gallery = computed(() => [...uploaded.value, ...(props.images ?? [])])

const imageSearch = ref(props.image_search ?? "")
const searching = ref(false)

const searchImages = () => {
    searching.value = true
    router.reload({
        only: ["images"],
        data: { image_search: imageSearch.value },
        onFinish: () => (searching.value = false),
    })
}

const uploadRole = ref<string | null>(null)
const uploading = ref(false)
const uploadError = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const chooseFile = (role: string) => {
    uploadRole.value = role
    uploadError.value = null
    fileInput.value?.click()
}

const upload = async (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    const role = uploadRole.value

    if (!file || !role) return

    const body = new FormData()
    body.append("image", file)

    uploading.value = true
    uploadError.value = null

    try {
        const response = await axios.post(route(props.image_route.name, props.image_route.parameters), body, {
            headers: { "Content-Type": "multipart/form-data" },
        })

        uploaded.value.unshift(response.data)
        ;(form[role] as number[]).push(response.data.id)
    } catch (failure: any) {
        uploadError.value =
            failure?.response?.data?.errors?.image?.[0] ??
            failure?.response?.data?.message ??
            ctrans("That image could not be uploaded.")
    } finally {
        uploading.value = false
        input.value = ""
    }
}

const toggleImage = (role: string, id: number) => {
    const chosen = form[role] as number[]
    const at = chosen.indexOf(id)

    if (at === -1) {
        chosen.push(id)
    } else {
        chosen.splice(at, 1)
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <MailshotJourney :steps="journey" class="ml-4" />
        </template>
    </PageHeading>

    <div class="grid grid-cols-1 gap-4 px-4 py-4 lg:grid-cols-3">
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-2">
            <h2 class="text-sm font-medium text-gray-800">{{ ctrans("The campaign") }}</h2>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <fieldset class="sm:col-span-2">
                    <legend class="text-xs text-gray-500">
                        {{ ctrans("What kind of campaign") }}
                        <HelpTip :text="ctrans('It decides everything asked below, so the page changes with it. Changeable until the campaign is published; after that Google will not turn one type into another.')" />
                    </legend>

                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <button
                            v-for="type in campaign_types"
                            :key="type.value"
                            type="button"
                            :aria-pressed="form.channel_type === type.value"
                            class="rounded-lg p-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            :class="form.channel_type === type.value ? 'bg-indigo-50 ring-1 ring-indigo-400' : 'ring-1 ring-gray-200 hover:bg-gray-50'"
                            @click="form.channel_type = type.value">
                            <span class="block text-sm font-medium text-gray-800">{{ type.label }}</span>
                            <span class="mt-1 block text-xs text-gray-600">{{ type.description }}</span>
                        </button>
                    </div>

                    <p class="mt-2 text-xs text-gray-500">
                        {{ ctrans("Video and Shopping are missing on purpose. Google's API refuses to create a Video campaign, and a Shopping campaign needs a Merchant Center feed, so both are still built in Google Ads itself.") }}
                    </p>
                </fieldset>

                <div class="sm:col-span-2">
                    <label for="c-name" class="block text-xs text-gray-500">{{ ctrans("Campaign name") }}</label>
                    <input
                        id="c-name"
                        v-model="form.name"
                        type="text"
                        :aria-invalid="!!errorFor('name')"
                        aria-describedby="c-name-error"
                        class="mt-1 w-full rounded-md text-sm"
                        :class="fieldClass('name')"
                        @input="clearServerErrors('name')" />
                    <p v-if="errorFor('name')" id="c-name-error" class="mt-1 text-xs text-[#d03b3b]">{{ errorFor("name") }}</p>
                </div>

                <div>
                    <label for="c-budget" class="block text-xs text-gray-500">{{ ctrans("Daily budget") }} ({{ currency }})</label>
                    <input
                        id="c-budget"
                        v-model="form.budget_amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :aria-invalid="!!errorFor('budget_amount')"
                        aria-describedby="c-budget-error"
                        class="mt-1 w-full rounded-md text-sm tabular-nums"
                        :class="fieldClass('budget_amount')"
                        @input="clearServerErrors('budget_amount')" />
                    <p v-if="errorFor('budget_amount')" id="c-budget-error" class="mt-1 text-xs text-[#d03b3b]">{{ errorFor("budget_amount") }}</p>
                </div>

                <div v-if="isSearch || isDisplay">
                    <label for="c-cpc" class="block text-xs text-gray-500">
                        {{ isSearch ? ctrans("Highest cost per click, optional") : ctrans("Cost per click bid") }}
                    </label>
                    <input
                        id="c-cpc"
                        v-model="form.max_cpc"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :aria-invalid="!!errorFor('max_cpc')"
                        aria-describedby="c-cpc-error"
                        class="mt-1 w-full rounded-md text-sm tabular-nums"
                        :class="fieldClass('max_cpc')"
                        @input="clearServerErrors('max_cpc')" />
                    <p v-if="errorFor('max_cpc')" id="c-cpc-error" class="mt-1 text-xs text-[#d03b3b]">{{ errorFor("max_cpc") }}</p>
                </div>

                <div v-if="isDemandGen">
                    <label for="c-cpa" class="block text-xs text-gray-500">{{ ctrans("Target cost per conversion") }} ({{ currency }})</label>
                    <input
                        id="c-cpa"
                        v-model="form.target_cpa"
                        type="number"
                        step="0.01"
                        min="0.01"
                        :aria-invalid="!!errorFor('target_cpa')"
                        aria-describedby="c-cpa-error"
                        class="mt-1 w-full rounded-md text-sm tabular-nums"
                        :class="fieldClass('target_cpa')"
                        @input="clearServerErrors('target_cpa')" />
                    <p v-if="errorFor('target_cpa')" id="c-cpa-error" class="mt-1 text-xs text-[#d03b3b]">{{ errorFor("target_cpa") }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="c-url" class="block text-xs text-gray-500">{{ ctrans("Landing page") }}</label>
                    <input
                        id="c-url"
                        v-model="form.final_url"
                        type="url"
                        placeholder="https://"
                        :aria-invalid="!!errorFor('final_url')"
                        aria-describedby="c-url-error"
                        class="mt-1 w-full rounded-md text-sm"
                        :class="fieldClass('final_url')"
                        @input="clearServerErrors('final_url')" />
                    <p v-if="errorFor('final_url')" id="c-url-error" class="mt-1 text-xs text-[#d03b3b]">{{ errorFor("final_url") }}</p>
                </div>

                <div>
                    <label for="c-countries" class="block text-xs text-gray-500">
                        {{ ctrans("Show ads in") }}
                        <HelpTip :text="ctrans('Left empty the campaign shows everywhere Google can reach, which is rarely what anyone wants.')" />
                    </label>
                    <Multiselect
                        id="c-countries"
                        v-model="form.country_codes"
                        mode="tags"
                        :options="countries"
                        :searchable="true"
                        class="mt-1" />
                </div>

                <div>
                    <label :for="isPmax ? 'c-asset-group' : 'c-ad-group'" class="block text-xs text-gray-500">
                        {{ isPmax ? ctrans("Asset group name") : ctrans("Ad group name") }}
                    </label>
                    <input
                        :id="isPmax ? 'c-asset-group' : 'c-ad-group'"
                        v-model="form[isPmax ? 'asset_group_name' : 'ad_group_name']"
                        type="text"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                <div v-if="!isSearch" class="sm:col-span-2">
                    <label for="c-business" class="flex justify-between text-xs text-gray-500">
                        {{ ctrans("Business name") }}
                        <span class="tabular-nums" :class="form.business_name.length > 25 ? 'text-[#d03b3b]' : 'text-gray-400'">{{ form.business_name.length }}/25</span>
                    </label>
                    <input
                        id="c-business"
                        v-model="form.business_name"
                        type="text"
                        :aria-invalid="!!errorFor('business_name')"
                        aria-describedby="c-business-error"
                        class="mt-1 w-full rounded-md text-sm"
                        :class="fieldClass('business_name')"
                        @input="clearServerErrors('business_name')" />
                    <p v-if="errorFor('business_name')" id="c-business-error" class="mt-1 text-xs text-[#d03b3b]">{{ errorFor("business_name") }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
            <h2 class="text-sm font-medium text-gray-800">{{ ctrans("Saving") }}</h2>
            <p class="mt-2 text-xs text-gray-600">
                {{ ctrans("Nothing on this page reaches Google. Save as often as you like and come back to it.") }}
            </p>

            <Button class="mt-4" :label="ctrans('Save the campaign')" :loading="form.processing" :disabled="hasProblems" size="s" @click="save" />

            <p v-if="hasProblems" class="mt-3 text-xs text-[#d03b3b]">{{ ctrans("Fix what is marked in red to save.") }}</p>
            <p v-else-if="form.recentlySuccessful" class="mt-3 text-xs text-[#006300]">{{ ctrans("Saved.") }}</p>

            <div v-if="missing.length" class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-600">
                <p class="text-gray-500">{{ ctrans("Still needed before Google will accept it") }}</p>
                <ul class="mt-1 list-inside list-disc">
                    <li v-for="item in missing" :key="item">{{ item }}</li>
                </ul>
            </div>
            <p v-else class="mt-4 border-t border-gray-100 pt-3 text-xs text-[#006300]">
                {{ ctrans("Everything Google needs is here. Review it next, from the button at the top.") }}
            </p>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">
                {{ ctrans("What the ad says") }}
                <HelpTip :text="ctrans('Google mixes headlines and descriptions itself rather than showing them in the order written, so each one has to read on its own. Enter starts the next one, and a pasted list fills one box per line.')" />
            </h2>

            <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-2">
                <GoogleAdsTextList
                    id="c-headlines"
                    v-model="form.headlines"
                    name="headlines"
                    :label="ctrans('Headlines')"
                    :add-label="ctrans('Add a headline')"
                    :max-length="LIST_LIMITS.headlines.maxLength"
                    :max-items="LIST_LIMITS.headlines.maxItems"
                    :errors="form.errors"
                    @edited="clearServerErrors('headlines')" />

                <GoogleAdsTextList
                    id="c-descriptions"
                    v-model="form.descriptions"
                    name="descriptions"
                    :label="ctrans('Descriptions')"
                    :add-label="ctrans('Add a description')"
                    :max-length="LIST_LIMITS.descriptions.maxLength"
                    :max-items="LIST_LIMITS.descriptions.maxItems"
                    :errors="form.errors"
                    @edited="clearServerErrors('descriptions')" />

                <div v-if="needsLongHeadline">
                    <label for="c-long" class="flex justify-between text-xs text-gray-500">
                        {{ ctrans("Long headline") }}
                        <span class="tabular-nums" :class="form.long_headline.length > 90 ? 'text-[#d03b3b]' : 'text-gray-400'">{{ form.long_headline.length }}/90</span>
                    </label>
                    <input
                        id="c-long"
                        v-model="form.long_headline"
                        type="text"
                        :aria-invalid="!!errorFor('long_headline')"
                        aria-describedby="c-long-error"
                        class="mt-1 w-full rounded-md text-sm"
                        :class="fieldClass('long_headline')"
                        @input="clearServerErrors('long_headline')" />
                    <p v-if="errorFor('long_headline')" id="c-long-error" class="mt-1 text-xs text-[#d03b3b]">{{ errorFor("long_headline") }}</p>
                </div>

                <template v-if="isSearch">
                    <GoogleAdsTextList
                        id="c-keywords"
                        v-model="form.keywords"
                        name="keywords"
                        :label="ctrans('Keywords')"
                        :add-label="ctrans('Add a keyword')"
                        :max-length="LIST_LIMITS.keywords.maxLength"
                        :max-items="LIST_LIMITS.keywords.maxItems"
                        :errors="form.errors"
                        @edited="clearServerErrors('keywords')" />

                    <div>
                        <label for="c-match" class="block text-xs text-gray-500">{{ ctrans("Match type") }}</label>
                        <select id="c-match" v-model="form.match_type" class="mt-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="BROAD">{{ ctrans("Broad") }}</option>
                            <option value="PHRASE">{{ ctrans("Phrase") }}</option>
                            <option value="EXACT">{{ ctrans("Exact") }}</option>
                        </select>
                    </div>
                </template>

                <div v-if="isPmax">
                    <GoogleAdsTextList
                        id="c-themes"
                        v-model="form.search_themes"
                        name="search_themes"
                        :label="ctrans('Search themes')"
                        :add-label="ctrans('Add a search theme')"
                        :max-length="LIST_LIMITS.search_themes.maxLength"
                        :max-items="LIST_LIMITS.search_themes.maxItems"
                        :errors="form.errors"
                        @edited="clearServerErrors('search_themes')" />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ ctrans("Phrases telling Google what someone looking for this would type. They steer its targeting rather than restricting it, so they are hints and not keywords.") }}
                    </p>
                </div>
            </div>
        </section>

        <section v-if="imageRoles.length" class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">{{ ctrans("Images") }}</h2>
            <p class="mt-1 max-w-3xl text-xs text-gray-600">
                {{ ctrans("Pick images already in Aiku, or upload new ones. Each is sent to Google once and reused by later campaigns. Google checks the shape of an image against the slot it fills.") }}
            </p>

            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label for="c-image-search" class="sr-only">{{ ctrans("Search images in Aiku") }}</label>
                    <input
                        id="c-image-search"
                        v-model="imageSearch"
                        type="search"
                        :placeholder="ctrans('Search images in Aiku')"
                        class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-72"
                        @keyup.enter="searchImages" />
                </div>
                <Button :label="ctrans('Search')" size="s" :style="'tertiary'" :loading="searching" @click="searchImages" />
            </div>

            <input ref="fileInput" type="file" accept="image/jpeg,image/png" class="hidden" @change="upload" />
            <p v-if="uploadError" class="mt-2 text-xs text-[#d03b3b]">{{ uploadError }}</p>

            <Deferred data="images">
                <template #fallback>
                    <div class="mt-4 grid grid-cols-4 gap-2 sm:grid-cols-8">
                        <div v-for="tile in 16" :key="tile" class="aspect-square animate-pulse rounded bg-gray-100" />
                    </div>
                </template>

                <div v-if="gallery.length" class="mt-4 space-y-5">
                    <div v-for="role in imageRoles" :key="role.key">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-xs font-medium text-gray-700">
                                {{ role.label }}
                                <span class="font-normal" :class="(form[role.key] as number[]).length ? 'text-gray-500' : 'text-[#a15c00]'">
                                    · {{ (form[role.key] as number[]).length }} {{ ctrans("chosen") }}
                                </span>
                            </span>
                            <span class="flex items-center gap-3 text-xs text-gray-500">
                                {{ role.hint }}
                                <button
                                    type="button"
                                    :disabled="uploading"
                                    class="rounded px-2 py-1 text-indigo-600 underline-offset-2 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-40"
                                    @click="chooseFile(role.key)">
                                    {{ uploading ? ctrans("Uploading") : ctrans("Upload an image") }}
                                </button>
                            </span>
                        </div>

                        <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-8 lg:grid-cols-10">
                            <button
                                v-for="image in gallery"
                                :key="role.key + image.id"
                                type="button"
                                :aria-pressed="(form[role.key] as number[]).includes(image.id)"
                                :title="image.name"
                                class="aspect-square overflow-hidden rounded transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                :class="(form[role.key] as number[]).includes(image.id) ? 'ring-2 ring-indigo-500' : 'ring-1 ring-gray-200 hover:ring-gray-400'"
                                @click="toggleImage(role.key, image.id)">
                                <img :src="image.thumbnail" :alt="image.name" loading="lazy" class="h-full w-full object-cover" />
                            </button>
                        </div>
                    </div>
                </div>

                <p v-else class="mt-4 text-xs text-gray-500">
                    {{ imageSearch
                        ? ctrans("No image in Aiku matches that. Clear the search, or upload one.")
                        : ctrans("No images in Aiku for this shop's group yet. Upload one to advertise with.") }}
                </p>
            </Deferred>
        </section>
    </div>

    <div class="px-4 pb-6">
        <Link :href="route(index_route.name, index_route.parameters)" class="primaryLink text-sm">
            {{ ctrans("Back to campaigns") }}
        </Link>
    </div>
</template>
