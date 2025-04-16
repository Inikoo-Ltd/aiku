<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 05 Jan 2025 14:59:28 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, ref } from 'vue'
import type { Component } from 'vue'

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'

const props = defineProps<{
  title: string,
  pageHead: PageHeadingTypes
  dashboard_stats: {
    label: string
    count: number
    icon: string
  }[]


}>()


</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>


</template>

