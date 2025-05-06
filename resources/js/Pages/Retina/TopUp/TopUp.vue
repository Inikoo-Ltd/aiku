<script setup lang="ts">
import { Head, usePage,Link } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { computed, ref, watchEffect } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { capitalize } from "@/Composables/capitalize";
import { trans } from "laravel-vue-i18n";
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue";
import { routeType } from "@/types/route";
import OrderSummary from "@/Components/Summary/OrderSummary.vue";
import { FieldOrderSummary } from "@/types/Pallet";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faIdCardAlt, faMapMarkedAlt, faPhone, faChartLine, faCreditCard, faCube, faFolder, faPercent, faCalendarAlt, faDollarSign, faMapMarkerAlt, faPencil, faBuilding, faMoneyBillAlt } from "@fal";
import { faClock, faFileExcel, faFileInvoice, faFilePdf } from "@fas";
import { faCheck } from "@far";
import { useFormatTime } from "@/Composables/useFormatTime";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import TableInvoiceTransactions from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceTransactions.vue";
import { InvoiceResource } from "@/types/invoice";
import NeedToPay from "@/Components/Utils/NeedToPay.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import type { Component } from "vue";

library.add(faCheck, faIdCardAlt, faMapMarkedAlt, faPhone, faFolder, faCube, faChartLine, faCreditCard, faClock, faFileInvoice, faPercent, faCalendarAlt, faBuilding, faDollarSign, faFilePdf, faMapMarkerAlt, faPencil, faMoneyBillAlt, faFileExcel);


const props = defineProps<{
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
</template>