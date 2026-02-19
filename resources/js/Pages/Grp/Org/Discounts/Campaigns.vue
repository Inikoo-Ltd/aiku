<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableCampaigns from '@/Components/Shop/Offers/TableCampaigns.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import { Table as TableTS } from '@/types/Table'
import { faYinYang, faShoppingBasket, faSitemap, faStore, faRepeat, faPercentage, faFlag, faUsers, faTags, faBox, faHandHoldingUsd } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(faYinYang, faShoppingBasket, faSitemap, faStore, faRepeat, faPercentage, faFlag, faUsers, faTags, faBox, faHandHoldingUsd);

defineProps<{
    data: TableTS
    title: string
    pageHead: PageHeadingTypes
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableCampaigns :data="data" />
</template>
