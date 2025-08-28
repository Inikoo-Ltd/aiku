<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 25 Apr 2025 13:37:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBooks } from '@fal'
import StatsBox from '@/Components/Stats/StatsBox.vue'
import Dashboard from '@/Components/DataDisplay/Dashboard/Dashboard.vue'
import { Dashboard as DashboardTS } from '@/types/Components/Dashboard'
library.add(faBooks)

defineProps<{
    title: string
    pageHead: {
        title: string
        description?: string
        icon?: string
    }
    dashboard: DashboardTS
}>()

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <!-- Stats: box -->
    <div class="p-6">
        <Dashboard :dashboard="dashboard" />
        <!-- <pre>{{dashboard}}</pre> -->
    </div>
</template>
