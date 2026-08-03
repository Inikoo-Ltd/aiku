<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import FlatTreeMap from '@/Components/Navigation/FlatTreeMap.vue';
import { capitalize } from "@/Composables/capitalize"

defineProps(['title', 'pageHead', 'flatTreeMaps', 'search_demand']);

import SearchDemandOpportunities from '@/Components/DataDisplay/Dashboard/Widget/SearchDemandOpportunities.vue';

import {library} from '@fortawesome/fontawesome-svg-core';
import {faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList} from '@fal';

library.add(faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList);

</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <FlatTreeMap class="mx-4" v-for="(treeMap,idx) in flatTreeMaps" :key="idx" :nodes="treeMap"/>
    <div class="mx-4 mt-4 max-w-3xl">
        <SearchDemandOpportunities :demand="search_demand"/>
    </div>
</template>

