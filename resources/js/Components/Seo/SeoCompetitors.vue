<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router, useForm } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Button from "@/Components/Elements/Buttons/Button.vue"
import InputText from "primevue/inputtext"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type Competitor = {
    id: number
    domain: string
    label: string | null
    delete_route: routeType & { method: string }
}

type Suggestion = {
    domain: string
    shared_keywords: number
    average_position: number | null
    organic_keywords: number
    estimated_traffic: number
}

type DomainsData = {
    competitors: Competitor[]
    suggestions: Suggestion[]
    suggestions_error: string | null
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: DomainsData | null
    tab: string
    canEdit: boolean
    addRoute: routeType
}>()

const locale = useLocaleStore()

const form = useForm({ domain: "", label: "" })

const addCompetitor = () => {
    form.post(route(props.addRoute.name, props.addRoute.parameters), {
        preserveScroll: true,
        only: [props.tab],
        onSuccess: () => form.reset(),
    })
}

const addingDomain = ref<string | null>(null)

const addSuggestion = (domain: string) => {
    router.post(route(props.addRoute.name, props.addRoute.parameters), { domain }, {
        preserveScroll: true,
        only: [props.tab],
        onStart: () => addingDomain.value = domain,
        onFinish: () => addingDomain.value = null,
    })
}

const remove = (competitor: Competitor) => {
    if (!window.confirm(ctrans("Remove :domain from the competitors?", { domain: competitor.domain }))) {
        return
    }

    router.delete(route(competitor.delete_route.name, competitor.delete_route.parameters), {
        preserveScroll: true,
        only: [props.tab],
    })
}
</script>

<template>
    <div class="space-y-4 px-4 py-4">
        <form
            v-if="canEdit"
            class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_auto] md:items-end"
            @submit.prevent="addCompetitor">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Domain") }}</span>
                <InputText v-model="form.domain" class="h-10 w-full" :placeholder="ctrans('For example: example.com')" />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Name") }} <span class="font-normal text-gray-500">{{ ctrans("optional") }}</span></span>
                <InputText v-model="form.label" class="h-10 w-full" :placeholder="ctrans('How the team calls them')" />
            </label>
            <Button class="h-10 justify-center" type="create" :label="ctrans('Add competitor')" :loading="form.processing" @click="addCompetitor" />
            <p v-if="Object.keys(form.errors).length" role="alert" class="text-sm text-red-600 md:col-span-3">
                {{ Object.values(form.errors)[0] }}
            </p>
        </form>

        <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Competitors')">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Our competitors") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("Their Google positions are read in Rankings, their links in Backlinks, and they are the default domains of the comparison and keyword gap.") }}</p>
            </div>

            <p v-if="!data?.competitors.length" class="px-5 py-4 text-sm text-gray-600">
                {{ ctrans("No competitors yet. Add the domains you see next to yours in Google results.") }}
            </p>

            <ul v-else class="divide-y divide-gray-100">
                <li v-for="competitor in data.competitors" :key="competitor.id" class="flex items-center gap-4 px-5 py-2.5 text-sm">
                    <a :href="`https://${competitor.domain}`" target="_blank" rel="noopener noreferrer" class="text-gray-900 underline-offset-2 hover:underline focus-visible:underline">
                        {{ competitor.domain }}
                    </a>
                    <span v-if="competitor.label" class="text-gray-500">{{ competitor.label }}</span>
                    <Button v-if="canEdit" class="ml-auto" type="tertiary" size="xs" :label="ctrans('Remove')" @click="remove(competitor)" />
                </li>
            </ul>
        </section>

        <section v-if="data" class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Suggested competitors')">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Suggested by Google results") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("Domains ranking for the most of our keywords, from DataForSEO, refreshed monthly. Marketplaces and video sites show up here too; add only the ones we compete with.") }}</p>
            </div>

            <p v-if="data.suggestions_error" role="alert" class="px-5 py-4 text-sm text-red-700">{{ data.suggestions_error }}</p>

            <p v-else-if="!data.suggestions.length" class="px-5 py-4 text-sm text-gray-600">{{ ctrans("No suggestion yet.") }}</p>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs text-gray-500">
                        <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Domain") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="ctrans('Keywords both domains rank for')">{{ ctrans("Shared keywords") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Average position") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Organic keywords") }}</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="ctrans('Monthly visits from Google estimated by DataForSEO')">{{ ctrans("Estimated traffic") }}</th>
                        <th scope="col" class="px-5 py-2" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="suggestion in data.suggestions" :key="suggestion.domain">
                        <td class="px-5 py-2">
                            <a :href="`https://${suggestion.domain}`" target="_blank" rel="noopener noreferrer" class="text-gray-900 underline-offset-2 hover:underline focus-visible:underline">{{ suggestion.domain }}</a>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(suggestion.shared_keywords) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ suggestion.average_position === null ? "-" : locale.number(suggestion.average_position) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(suggestion.organic_keywords) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(suggestion.estimated_traffic) }}</td>
                        <td class="px-5 py-2 text-right">
                            <Button v-if="canEdit" type="tertiary" size="xs" :label="ctrans('Add')" :loading="addingDomain === suggestion.domain" @click="addSuggestion(suggestion.domain)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
