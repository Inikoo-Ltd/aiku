<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { reactive, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import type { ContentSuggestion, ContentSuggestions } from "@/Components/Seo/types"

const props = defineProps<{
    data: ContentSuggestions
}>()

const fieldLabels: Record<string, string> = {
    title: ctrans("Page title"),
    description: ctrans("Meta description"),
}

const reasonLabels: Record<string, string> = {
    requested: ctrans("Asked for"),
    low_ctr: ctrans("Many Google impressions, few clicks"),
    missing_title: ctrans("Audit: no title"),
    missing_meta_description: ctrans("Audit: no meta description"),
    duplicate_title: ctrans("Audit: title used by other pages"),
    duplicate_meta_description: ctrans("Audit: meta description used by other pages"),
    title_too_long: ctrans("Audit: title too long"),
    title_too_short: ctrans("Audit: title too short"),
    meta_description_too_long: ctrans("Audit: meta description too long"),
    meta_description_too_short: ctrans("Audit: meta description too short"),
}

const drafts = reactive<Record<number, string>>({})

watch(() => props.data.items, (items) => items.forEach((item) => drafts[item.id] ??= item.suggestion), { immediate: true })

const busy = ref<string | null>(null)
const error = ref<string | null>(null)

const visit = (key: string, method: "post" | "patch", url: string, data: Record<string, unknown>) => {
    router[method](url, data, {
        preserveScroll: true,
        onStart: () => {
            busy.value = key
            error.value = null
        },
        onError: (errors) => error.value = Object.values(errors)[0] ?? null,
        onFinish: () => busy.value = null,
    })
}

const requestSuggestions = () => visit("request", "post", route(props.data.request_route.name, props.data.request_route.parameters), { fields: ["title", "description"] })

const accept = (item: ContentSuggestion) => visit(`accept-${item.id}`, "patch", route(item.accept_route.name, item.accept_route.parameters), { value: drafts[item.id] })

const dismiss = (item: ContentSuggestion) => visit(`dismiss-${item.id}`, "patch", route(item.dismiss_route.name, item.dismiss_route.parameters), {})
</script>

<template>
    <div class="border-t pt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <dt class="text-xs text-gray-500">{{ ctrans("Suggested by AI") }}</dt>
            <Button
                type="tertiary"
                size="xs"
                icon="fal fa-magic"
                :label="data.items.length ? ctrans('Suggest again') : ctrans('Suggest title and description')"
                :loading="busy === 'request'"
                @click="requestSuggestions" />
        </div>

        <p v-if="error" role="alert" class="mt-2 text-sm text-red-700">{{ error }}</p>

        <dd v-if="!data.items.length" class="mt-1 text-sm italic text-gray-400">
            {{ ctrans("No suggestion waiting. Suggestions are written weekly for pages the audit flags or that get many Google impressions and few clicks, and never published until someone accepts them.") }}
        </dd>

        <dd v-for="item in data.items" :key="item.id" class="mt-3 rounded-md bg-gray-50 p-3 text-sm ring-1 ring-gray-200">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                <span class="font-medium text-gray-900">{{ fieldLabels[item.field] }}</span>
                <span class="text-xs text-gray-500">{{ reasonLabels[item.reason] ?? item.reason }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">
                {{ ctrans("Now") }}: <span class="text-gray-700">{{ item.current_value || ctrans("Not set") }}</span>
            </p>
            <Textarea v-model="drafts[item.id]" class="mt-2 w-full text-sm" autoResize :rows="item.field === 'title' ? 1 : 3" :aria-label="ctrans('Suggested :field', { field: fieldLabels[item.field] })" />
            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs tabular-nums" :class="(drafts[item.id] ?? '').length > data.limits[item.field] ? 'text-red-700' : 'text-gray-500'">
                    {{ (drafts[item.id] ?? "").length }}/{{ data.limits[item.field] }}
                </span>
                <div class="flex gap-2">
                    <Button type="tertiary" size="xs" :label="ctrans('Dismiss')" :loading="busy === `dismiss-${item.id}`" @click="dismiss(item)" />
                    <Button type="primary" size="xs" :label="ctrans('Use this')" :loading="busy === `accept-${item.id}`" :disabled="!(drafts[item.id] ?? '').trim()" @click="accept(item)" />
                </div>
            </div>
        </dd>
    </div>
</template>
