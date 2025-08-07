<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
defineProps<{
    data: {
        data: {}
        links: Links
        meta: Meta
    }
    tab?: string
}>()

function shopRoute(zone: {}) {
    console.log(route().current())
    switch (route().current()) {
        case "grp.org.shops.show.billables.shipping.show":
            return route("grp.org.shops.show.billables.shipping.show.shipping-zone.show", [
                route().params["organisation"],
                route().params["shop"],
                route().params["shippingZoneSchema"],
                zone.slug,
            ])
        default:
            return null
    }
}

function mapTerritories(territories: { country_code: string }[]) {
    return territories.map((territory) => territory.country_code)
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: zone }">
            <Link :href="shopRoute(zone)" class="primaryLink">
            {{ zone["code"] }}
            </Link>
        </template>
        <template #cell(name)="{ item: name }">
            <Link :href="shopRoute(name)" class="primaryLink">
            {{ name["name"] }}
            </Link>
        </template>
        <template #cell(position)="{ item: position }">
            {{ position["position"] }}
        </template>
        <template #cell(territories)="{ item: territories }">
            <pre>{{ territories.territories }}</pre>
            <AddressLocation v-for="(territory, index) in mapTerritories(territories.territories)" :key="index"
                :data="[territory, territory, territory]" />
        </template>
        <template #cell(price)="{ item: price }">
            <div class="shipping-price-table">
                <div v-if="price['price'].type === 'TBC'">Shipping price: TBC</div>
                <div v-else-if="price['price'].type === 'Step Order Items Net Amount'">
                    <div v-for="(priceStep, index) in price['price'].steps" :key="index" class="shipping-tier">
                        <div class="shipping-price-row">
                            <span>£{{ priceStep.from.toFixed(2) }}</span>
                            <span>→</span>
                            <span>
                                <span v-if="priceStep.to !== 'INF'">
                                    £{{ Number(priceStep.to).toFixed(2) }}
                                </span>
                                <span v-else>∞</span>
                            </span>
                            <span class="shipping-cost"> £{{ priceStep.price.toFixed(2) }} </span>
                        </div>

                        <div v-if="priceStep.price === 0" class="shipping-free-row">
                            <span>free</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </Table>
</template>
