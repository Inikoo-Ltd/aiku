<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SeoPortfolio from "@/Components/Seo/SeoPortfolio.vue"
import SeoReportToggle from "@/Components/Seo/SeoReportToggle.vue"
import { routeType } from "@/types/route"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: object
    reportSubscription: { is_subscribed: boolean, route: routeType }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <SeoReportToggle :subscription="reportSubscription" />
        </template>
    </PageHeading>
    <SeoPortfolio :data="data" />
</template>
