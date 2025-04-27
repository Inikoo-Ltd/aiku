<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 23 Mar 2024 04:25:24 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup>
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import FlatTreeMap from '@/Components/Navigation/FlatTreeMap.vue';
import { capitalize } from "@/Composables/capitalize"
import {library} from '@fortawesome/fontawesome-svg-core';
import {faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList} from '@fal';

defineProps(['title', 'pageHead', 'flatTreeMaps']);

library.add(faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList);

</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <FlatTreeMap class="mx-4" v-for="(treeMap,idx) in flatTreeMaps" :key="idx" :nodes="treeMap"/>
</template>

