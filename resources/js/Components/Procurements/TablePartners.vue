<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { RouteParams } from "@/types/route-params"

defineProps<{
    data: {}
    tab?: string
}>()



function partnerRoute(partner: {}) {
    if (route().current() === "grp.org.procurement.org_partners.index") {
        return route(
            "grp.org.procurement.org_partners.show",
            [ (route().params as RouteParams).organisation, partner.id])
    } else {
        return route(
            "grp.org.procurement.org_partners.index",
            [ (route().params as RouteParams).organisation, partner.id])
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: partner }">
            <Link :href="partnerRoute(partner)" class="primaryLink">
            {{ partner["code"] }}
            </Link>
        </template>

    </Table>
</template>
