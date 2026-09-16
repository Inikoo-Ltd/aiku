<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from "laravel-vue-i18n"

library.add(faGoogle)

/**
 * Two questions, then the campaign exists in Aiku and is filled in on its own page.
 *
 * The type comes first because it decides everything asked afterwards: a Search campaign wants
 * keywords, the other three want images and a logo, and asking for all of it here would ask most
 * people for most of it in vain.
 */
const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    unreachable_reason: string | null
    campaign_types: { value: string; label: string; description: string }[]
    prefill?: { name: string | null; channel_type: string | null }
    store_route: { name: string; parameters: Record<string, unknown> }
    index_route: { name: string; parameters: Record<string, unknown> }
}>()

const form = useForm({
    name: props.prefill?.name ?? "",
    channel_type: props.campaign_types.some((type) => type.value === props.prefill?.channel_type)
        ? (props.prefill?.channel_type as string)
        : props.campaign_types[0].value,
})

const submit = () => form.post(route(props.store_route.name, props.store_route.parameters))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div
        v-if="unreachable_reason"
        class="mx-4 mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        {{ unreachable_reason }}
    </div>

    <!-- Submitting on Enter is deliberately dead. Enter in a text field is a keystroke, not a decision. -->
    <form class="px-4 py-4" @submit.prevent>
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
            <h2 class="text-sm font-medium text-gray-800">{{ trans("What kind of campaign") }}</h2>

            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
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

            <p class="mt-3 text-xs text-gray-500">
                {{ trans("Video and Shopping are missing on purpose. Google's API refuses to create a Video campaign, and a Shopping campaign needs a Merchant Center feed, so both are still built in Google Ads itself.") }}
            </p>

            <div class="mt-5 max-w-lg">
                <label for="gads-name" class="block text-xs text-gray-500">{{ trans("Campaign name") }}</label>
                <input
                    id="gads-name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-[#d03b3b]">{{ form.errors.name }}</p>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <Button
                    :label="trans('Start the campaign')"
                    :loading="form.processing"
                    :disabled="!form.name.trim() || !!unreachable_reason"
                    size="s"
                    @click="submit" />
                <Link :href="route(index_route.name, index_route.parameters)" class="text-xs text-gray-500 underline-offset-2 hover:underline">
                    {{ trans("Cancel") }}
                </Link>
            </div>

            <p class="mt-3 text-xs text-gray-500">
                {{ trans("It is created in Aiku only. The budget, the ad text and the images are filled in on its own page, and nothing reaches Google until you publish it there.") }}
            </p>
        </section>
    </form>
</template>
