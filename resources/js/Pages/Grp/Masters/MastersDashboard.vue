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
library.add(faBooks)

defineProps<{
    title: string
    pageHead: {
        title: string
        description?: string
        icon?: string
    }
    flatTreeMaps: Array<any>
    stats: {

    }
}>()

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <!-- Stats: box -->
    <div class="p-6">
        <dl class="grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <StatsBox
                v-for="(stat, idxStat) in stats"
                :stat="stat"
            >
            </StatsBox>
        </dl>
    </div>
</template>
