<!--
  - Author: Oggie Sutrisna
  - Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
  - Description: Vue page for listing Returns in warehouse incoming
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Table from '@/Components/Table/Table.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faUndoAlt, faClock, faInboxIn, faSearch, faCheckDouble, faTimes } from '@fal'

library.add(faUndoAlt, faClock, faInboxIn, faSearch, faCheckDouble, faTimes)

const props = defineProps<{
    title: string
    pageHead: {}
    data: {
        data: {}[]
    }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 sm:px-6 lg:px-8 py-4">
        <Table :resource="data" />
    </div>
</template>
