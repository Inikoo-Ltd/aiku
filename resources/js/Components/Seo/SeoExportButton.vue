<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { route } from "ziggy-js"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileExport } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faFileExport)

const props = defineProps<{
    table?: string
    routeName?: string
    extra?: Record<string, string | number | null | undefined>
}>()

const href = computed(() => {
    const params = route().params as Record<string, string>
    const base = props.table
        ? route("grp.org.shops.show.seo.export", [params.organisation, params.shop, props.table])
        : route(props.routeName!)

    const query = new URLSearchParams(window.location.search)

    Object.entries(props.extra ?? {}).forEach(([key, value]) => {
        if (value === null || value === undefined || value === "") {
            query.delete(key)
        } else {
            query.set(key, String(value))
        }
    })

    const search = query.toString()

    return search ? `${base}?${search}` : base
})
</script>

<template>
    <a
        :href="href"
        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]"
        v-tooltip="ctrans('Download this table as an Excel file, with the filters and the sort it has now (up to 20,000 rows)')">
        <FontAwesomeIcon :icon="faFileExport" fixed-width aria-hidden="true" />
        {{ ctrans("Excel") }}
    </a>
</template>
