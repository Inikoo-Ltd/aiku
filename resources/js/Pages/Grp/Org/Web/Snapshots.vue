<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import {
  faPlus,
  faChevronRight,
  faChevronDown,
  faGripLines
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import TableSnapshots from "@/Components/Tables/TableSnapshots.vue";

library.add(faChevronRight, faChevronDown, faPlus, faGripLines);

defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data: {}
}>();


</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <TableSnapshots :data="data" class="mt-5" />
</template>
