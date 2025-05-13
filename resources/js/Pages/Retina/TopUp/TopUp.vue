<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faIdCardAlt, faMapMarkedAlt, faPhone, faChartLine, faCreditCard, faCube, faFolder, faPercent, faCalendarAlt, faDollarSign, faMapMarkerAlt, faPencil, faBuilding, faMoneyBillAlt } from "@fal";
import { faClock, faFileExcel, faFileInvoice, faFilePdf } from "@fas";
import { faCheck } from "@far";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";

library.add(faCheck, faIdCardAlt, faMapMarkedAlt, faPhone, faFolder, faCube, faChartLine, faCreditCard, faClock, faFileInvoice, faPercent, faCalendarAlt, faBuilding, faDollarSign, faFilePdf, faMapMarkerAlt, faPencil, faMoneyBillAlt, faFileExcel);

defineProps<{
  title: string,
  pageHead: PageHeadingTypes
  tabs: {
    current: string
    navigation: {}
  }
}>();


</script>


<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
  </PageHeading>
</template>