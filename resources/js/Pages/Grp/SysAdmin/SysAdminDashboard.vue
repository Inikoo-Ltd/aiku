<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 11 Sept 2022 12:17:26 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import SearchAnalyticsDisplay from '@/Components/DataDisplay/Dashboard/Widget/SearchAnalyticsDisplay.vue';
import McpAnalyticsDisplay from '@/Components/DataDisplay/Dashboard/Widget/McpAnalyticsDisplay.vue';
import UsersInsightsDisplay from '@/Components/DataDisplay/Dashboard/Widget/UsersInsightsDisplay.vue';
import { capitalize } from "@/Composables/capitalize"

defineProps(['title', 'pageHead', 'users_insights', 'search_insights', 'ai_insights']);
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <div class="m-4 grid gap-4 xl:grid-cols-2 items-start">
        <UsersInsightsDisplay :widget="users_insights"/>
        <SearchAnalyticsDisplay :widget="search_insights"/>
        <McpAnalyticsDisplay :widget="ai_insights"/>
    </div>
</template>
