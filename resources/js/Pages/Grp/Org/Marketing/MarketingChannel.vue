<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Tue, 11 Aug 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TrafficSourceShowcase from '@/Components/Showcases/Grp/TrafficSourceShowcase.vue'
import { PageHeadingTypes } from '@/types/PageHeading'
import { capitalize } from '@/Composables/capitalize'

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    overview: any
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <TrafficSourceShowcase :data="overview" />
</template>
