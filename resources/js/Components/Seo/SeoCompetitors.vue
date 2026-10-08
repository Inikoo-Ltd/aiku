<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Button from "@/Components/Elements/Buttons/Button.vue"
import InputText from "primevue/inputtext"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import type { SeoKeywordRoutes } from "@/Components/Seo/types"

type Competitor = {
    id: number
    domain: string
    label: string | null
    delete_route: routeType & { method: string }
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: Competitor[]
    canEdit: boolean
    routes: SeoKeywordRoutes
}>()

const form = useForm({ domain: "", label: "" })

const addCompetitor = () => {
    form.post(route(props.routes.add_competitor.name, props.routes.add_competitor.parameters), {
        preserveScroll: true,
        only: ["competitors"],
        onSuccess: () => form.reset(),
    })
}

const remove = (competitor: Competitor) => {
    if (!window.confirm(ctrans("Remove :domain from the competitors?", { domain: competitor.domain }))) {
        return
    }

    router.delete(route(competitor.delete_route.name, competitor.delete_route.parameters), {
        preserveScroll: true,
        only: ["competitors"],
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
            <p v-if="!data?.length" class="px-5 py-4 text-sm text-gray-600">
                {{ ctrans("No competitors yet. Add the domains you see next to yours in Google results.") }}
            </p>

            <ul v-else class="divide-y divide-gray-100">
                <li v-for="competitor in data" :key="competitor.id" class="flex items-center gap-4 px-5 py-2.5 text-sm">
                    <a :href="`https://${competitor.domain}`" target="_blank" rel="noopener noreferrer" class="text-gray-900 underline-offset-2 hover:underline focus-visible:underline">
                        {{ competitor.domain }}
                    </a>
                    <span v-if="competitor.label" class="text-gray-500">{{ competitor.label }}</span>
                    <Button v-if="canEdit" class="ml-auto" type="tertiary" size="xs" :label="ctrans('Remove')" @click="remove(competitor)" />
                </li>
            </ul>
        </section>
    </div>
</template>
