<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableWebsiteNotFoundPaths from "@/Components/Tables/Grp/Org/Web/TableWebsiteNotFoundPaths.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    canEdit: boolean
    website: { id: number, domain: string, url: string }
    retentionDays: number
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <p class="mx-4 mb-4 mt-4 max-w-3xl text-sm text-gray-600">
        {{ ctrans("URLs on :domain that people opened but that have no page. Redirect the ones with many hits to the page they were meant to reach. Paths not seen for :days days are removed.", { domain: website.domain, days: retentionDays }) }}
    </p>

    <TableWebsiteNotFoundPaths :data="data" :website="website" :canEdit="canEdit" />
</template>
