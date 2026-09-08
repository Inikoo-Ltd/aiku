<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faGoogle } from '@fortawesome/free-brands-svg-icons'
library.add(faGoogle)
import TableGoogleAdsCampaigns from '@/Components/Tables/Grp/Org/CRM/TableGoogleAdsCampaigns.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    is_connected: boolean
    shop_currency: string
    settings_route: { name: string, parameters: object }
}>()
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <div v-if="!is_connected" class="m-5 rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
        {{ $t("Connect Google Ads in the shop settings to see campaigns.") }}
        <Link :href="route(settings_route.name, settings_route.parameters)" class="primaryLink">
            {{ $t("Go to settings") }}
        </Link>
    </div>
    <TableGoogleAdsCampaigns :data="data" :currency="shop_currency" />
</template>
