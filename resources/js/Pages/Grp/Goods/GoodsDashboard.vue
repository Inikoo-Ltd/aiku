<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 23 Mar 2024 04:25:24 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import FlatTreeMap from '@/Components/Navigation/FlatTreeMap.vue'
import StatsBox from '@/Components/Stats/StatsBox.vue'
import { capitalize } from '@/Composables/capitalize'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faWeight, faScarecrow } from '@fal'
import { PageHeadingTypes } from '@/types/PageHeading'
import { StatsBoxTS } from '@/types/Components/StatsBox'

library.add(faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faWeight, faScarecrow)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    flatTreeMaps?: {}
    statsBox?: StatsBoxTS[]
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <FlatTreeMap class="mx-4" v-for="(treeMap, idx) in flatTreeMaps" :key="idx" :nodes="treeMap" />
    <div v-if="statsBox?.length" class="py-6 px-4">
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
            <StatsBox v-for="(stat, index) in statsBox" :key="index" :stat="stat" />
        </dl>
    </div>
</template>
