<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import ToggleSwitch from "primevue/toggleswitch"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    tab: string
    statuses: { value: string, label: string }[]
}>()

const page = usePage()

const param = (name: string) => `${props.tab}_filter[${name}]`

const currentUrl = () => new URL(page.url, window.location.origin)

const visit = (name: string, value: string | null) => {
    const url = currentUrl()

    url.searchParams.delete(`${props.tab}Page`)

    if (value === null) {
        url.searchParams.delete(param(name))
    } else {
        url.searchParams.set(param(name), value)
    }

    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, only: [props.tab] })
}

const status = computed({
    get: () => currentUrl().searchParams.get(param("status")) ?? props.statuses[0].value,
    set: (value: string) => visit("status", value === props.statuses[0].value ? null : value),
})

const includeOwnWebsites = computed({
    get: () => currentUrl().searchParams.get(param("websites")) === "include",
    set: (value: boolean) => visit("websites", value ? "include" : null),
})
</script>

<template>
    <div class="mx-4 mt-4 flex flex-wrap items-center justify-between gap-3">
        <SegmentedToggle v-model="status" :options="statuses" :ariaLabel="ctrans('Status')" />
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <ToggleSwitch v-model="includeOwnWebsites" />
            {{ ctrans("Include links from our own websites") }}
        </label>
    </div>
</template>
