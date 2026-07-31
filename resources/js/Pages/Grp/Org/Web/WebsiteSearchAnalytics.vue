<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { capitalize } from '@/Composables/capitalize'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import SearchAnalyticsDisplay from '@/Components/DataDisplay/Dashboard/Widget/SearchAnalyticsDisplay.vue'

defineProps(['title', 'pageHead', 'search_insights'])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 max-w-4xl">
        <SearchAnalyticsDisplay :widget="search_insights" :logs-url="null" />
    </div>
</template>
