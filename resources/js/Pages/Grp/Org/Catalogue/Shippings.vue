<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 12:20:38 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {capitalize} from "@/Composables/capitalize"
import {PageHeading as PageHeadingTypes} from '@/types/PageHeading'
import {computed, ref} from "vue";
import {useTabChange} from "@/Composables/tab-change";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import {library} from "@fortawesome/fontawesome-svg-core";
import {faTags,faTasksAlt,faChartPie} from "@fal";
import TableShippingZoneSchemas from '@/Components/Tables/Grp/Org/Catalogue/TableShippingZoneSchemas.vue';


library.add(faTags,faTasksAlt,faChartPie)

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data: {}

}>()

</script>

<template>
  <Head :title="capitalize(title)"/>
  <PageHeading :data="pageHead"></PageHeading>
  <TableShippingZoneSchemas :data="data" />
</template>

