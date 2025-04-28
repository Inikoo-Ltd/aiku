<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import FlatTreeMap from '@/Components/Navigation/FlatTreeMap.vue'
import { capitalize } from "@/Composables/capitalize"


import { library } from '@fortawesome/fontawesome-svg-core'
import { faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList } from '@fal'
import Dashboard from '@/Components/DataDisplay/Dashboard/DashboardOld.vue'

library.add(faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList)


const props = defineProps<{
    title: string,
    pageHead: {},
    flatTreeMaps: any[]
    dashboard_stats: {
      widgets: {}
    }
}>()
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <!-- <FlatTreeMap class="mx-4" v-for="(treeMap, idx) in flatTreeMaps" :key="idx" :nodes="treeMap" /> -->
    <div class="mx-4">
      <Dashboard 
              :dashboard="dashboard_stats"
      />
    </div>
</template>
