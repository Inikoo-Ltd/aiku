<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { defineAsyncComponent } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Table as TableTS } from "@/types/Table"
import { faYinYang, faShoppingBasket, faSitemap, faStore, faRepeat, faPercentage, faFlag, faUsers, faTags, faBox, faSortAmountUp, faHandHoldingUsd } from "@fal"
import { faStop } from "@fas"

import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faYinYang, faShoppingBasket, faSitemap, faStore, faRepeat, faPercentage, faFlag, faUsers, faTags, faBox, faSortAmountUp, faHandHoldingUsd, faStop)

const TableOfferCampaigns = defineAsyncComponent(() => import("@/Components/Shop/Offers/TableOfferCampaigns.vue"))

defineProps<{
    data: TableTS
    title: string
    pageHead: PageHeadingTypes
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <Suspense>
        <template #default>
            <TableOfferCampaigns :data="data" />
        </template>

        <template #fallback>
            <div class="px-4 py-3 space-y-2" aria-hidden="true">
                <div class="h-10 w-full rounded-sm bg-gray-200 animate-pulse"></div>
                <div v-for="row in 5" :key="row" class="h-8 w-full rounded-sm bg-gray-100 animate-pulse"></div>
            </div>
        </template>
    </Suspense>
</template>
