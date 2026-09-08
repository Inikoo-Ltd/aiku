<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Image from "@common/Components/Image.vue"
import { faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar as falStar, faTrashAlt, faExclamationCircle } from "@fal"
import { faStar, faFilter } from "@fas"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faCheck } from "@far"
import Icon from "@/Components/Icon.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { GridProducts } from "@/Components/Product"
import { faExternalLink } from "@far";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";


library.add(fadExclamationTriangle, faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar, faFilter, falStar, faTrashAlt, faCheck, faExclamationCircle)


defineProps<{
    data: {}
    tab?: string

}>()

function productRoute(family): string {
    const current = route().current()
    if (current === "retina.catalogue.products.index") {
        return route("retina.catalogue.products.show", [family.slug])
    }
    return route("retina.catalogue.products.show", [family.slug])
}

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

console.log("RetinaTableProducts.vue", layout)


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5 hidden md:block">
        <template #cell(image)="{ item: product }">
            <div class="flex justify-center">
                <Image :src="product['image_thumbnail']" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>

        <template #cell(state)="{ item: product }">
             <Icon :data="product.state" />
        </template>

        <template #cell(code)="{ item: product }">
            <a :href="product.iris_url" class="primaryLink whitespace-nowrap">
            {{ product["code"] }}
            </a>
        </template>

        <!-- Column: Stock -->
        <template #cell(quantity_left)="{ item }">

            <div>
                {{ locale.number(item.quantity_left) }}
            </div>
        </template>

        <!-- Column: Weight -->
        <template #cell(weight)="{ item }">
            <div class="flex justify-end">
                {{ item.gross_weight }} g
            </div>
        </template>

        <!-- Column: Price -->
        <template #cell(price)="{ item }">
            <div class="flex justify-end">

                {{ locale.currencyFormat(layout?.retina?.currency.code, item.price) }}
            </div>
        </template>

        <!-- Column: RPP -->
        <template #cell(rrp)="{ item }">
         <div class="flex justify-end">
                {{ locale.currencyFormat(layout?.retina?.currency.code, item.rrp) }}
            </div>
        </template>

    </Table>


    <GridProducts :resource="data" :preserve-scroll="true" class="mt-5 md:hidden" :name="tab"
        :gridClass="'grid grid-cols-1'">
        <template #card="{ item }">
            <div
                class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition-all hover:border-primary-300 hover:shadow-sm">
                <Image
                    :src="item.image_thumbnail ?? item.web_images?.main?.thumbnail ?? item.web_images?.main?.original"
                    class="h-12 w-12 rounded-full object-cover shadow-sm flex-shrink-0" />

                <div class="min-w-0 flex-1">
                      <a :href="item.iris_url" class="primaryLink whitespace-nowrap">
                        {{ item.code }}
                      </a>

                    <p class="mt-2 p-1 truncate text-sm text-gray-500">
                        {{ item.name }}
                    </p>
                </div>
            </div>
        </template>
    </GridProducts>
</template>
