<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 28 Nov 2024 16:53:16 Central Indonesia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import FlatTreeMap from "@/Components/Navigation/FlatTreeMap.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faSeedling, faThumbsDown } from "@fal";
import { faCheckCircle, faTimesCircle, faPauseCircle } from "@fas";

import { capitalize } from "@/Composables/capitalize";
import { useLocaleStore } from "@/Stores/locale";

import { PageHeadingTypes } from "@/types/PageHeading";

library.add(faSeedling, faThumbsDown, faTimesCircle, faPauseCircle, faCheckCircle);

const locale = useLocaleStore();

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    flatTreeMaps: {}

}>();


</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <FlatTreeMap class="mx-4" v-for="(treeMap, idx) in flatTreeMaps" :key="idx" :nodes="treeMap" />

</template>
