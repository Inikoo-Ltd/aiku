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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowRight, faTruck } from "@fal"
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

/* function mapTerritories(territories: { country_code: string }[]) {
    return territories.map((territory) => territory.country_code)
} */
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
            <div v-for="(item, index) in territories.territories" :key="index" class="text-xs text-gray-800">
                <!-- Baris Utama: Country + Flag -->
                <div class="flex items-center gap-1 font-medium">
                    <img class="inline-block h-[14px] w-[20px] object-cover rounded-sm"
                        :src="'/flags/' + item.country_code.toLowerCase() + '.png'"
                        :alt="`Bendera ${item.country_code}`" loading="lazy" />
                    <span>{{ item.country_code }}</span>
                

                <!-- Postal Code Info (Compact) -->
                <div v-if="item.included_postal_codes || item.excluded_postal_codes" class="mt-1 ml-5 space-y-1">
                    <!-- Included -->
                    <div v-if="item.included_postal_codes" class="text-green-600">
                        <span class="font-semibold">✔ Included:</span>
                        <span class="text-gray-700 font-mono">{{ item.included_postal_codes }}</span>
                    </div>

                    <!-- Excluded -->
                    <div v-if="item.excluded_postal_codes" class="text-red-600">
                        <span class="font-semibold">✘ Excluded:</span>
                        <span class="text-gray-700 font-mono">{{ item.excluded_postal_codes }}</span>
                    </div>
                </div>
                </div>
            </div>
        </template>


      <template #cell(price)="{ item: price }">
  <div class="space-y-1 text-xs text-gray-800">
    <!-- TBC Case -->
    <div v-if="price.price.type === 'TBC'" class="text-gray-500 italic">
      <font-awesome-icon icon="fas fa-clock" class="text-yellow-500 mr-1" />
      Shipping price: TBC
    </div>

    <!-- Step Pricing -->
    <div v-else class="space-y-1">
      <div
        v-for="(priceStep, index) in price.price.steps"
        :key="index"
        class="grid grid-cols-[80px_100px_80px] items-center gap-2 text-xs"
      >
        <!-- Type -->
        <div class="flex items-center gap-1 font-medium text-gray-600">
          <font-awesome-icon
            :icon="price.price.type === 'Step Order Estimated Weight' ? 'fas fa-weight' : 'fas fa-box'"
            class="text-blue-500"
          />
          <span>
            {{ price.price.type === 'Step Order Estimated Weight' ? 'Weight' : 'Items' }}
          </span>
        </div>

        <!-- Range -->
        <div class="flex items-center gap-1 text-gray-700">
          <span>£{{ priceStep.from }}</span>
          <font-awesome-icon icon="fas fa-arrow-right" />
          <span v-if="priceStep.to !== 'INF'">£{{ Number(priceStep.to) }}</span>
          <span v-else>∞</span>
        </div>

        <!-- Price -->
        <div
          class="font-bold text-right"
          :class="priceStep.price === 0 ? 'text-green-600' : 'text-black'"
        >
          <span v-if="priceStep.price === 0">
            <font-awesome-icon icon="fas fa-truck" class="mr-1" />
            Free
          </span>
          <span v-else>
            £{{ priceStep.price }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

    </Table>
</template>
