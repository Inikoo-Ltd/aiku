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
import Image from "@/Components/Image.vue"
import { faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar as falStar, faTrashAlt, faExclamationCircle } from "@fal"
import { faStar, faFilter } from "@fas"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faCheck } from "@far"
import Icon from "@/Components/Icon.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import Tag from "@/Components/Tag.vue"
import { Link } from "@inertiajs/vue3"
library.add(fadExclamationTriangle, faConciergeBell, faGarage, faExclamationTriangle, faPencil, faSearch, faThLarge, faListUl, faStar, faFilter, falStar, faTrashAlt, faCheck, faExclamationCircle)


const props = defineProps<{
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
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">

        <template #cell(image)="{ item: product }">
            <div class="overflow-hidden w-10 h-10">
                <Image :src="product.image" :alt="product.name" />
            </div>
        </template>

        <template #cell(state)="{ item: product }">
            <Tag :label="product.state.tooltip" v-tooltip="product.state.tooltip">
                <template #label>
                    <Icon :data="product.state" /> <span :class="product.state.class">{{ product.state.tooltip }}</span>
                </template>
            </Tag>
        </template>

        <template #cell(code)="{ item: product }">
            <Link :href="productRoute(product)" class="primaryLink whitespace-nowrap">
            {{ product["code"] }}
            </Link>
        </template>

        <!-- Column: Stock -->
        <template #cell(quantity_left)="{ item }">

            <div>
                {{ locale.number(item.quantity_left) }}
            </div>
        </template>

        <!-- Column: Weight -->
        <template #cell(weight)="{ item }">
            <div>
                {{ locale.number(item.weight / 1000) }} kg
            </div>
        </template>

        <!-- Column: Price -->
        <template #cell(price)="{ item }">
            <div>
                {{ locale.currencyFormat(item.currency_code, item.price) }}
            </div>
        </template>

        <!-- Column: RPP -->
        <template #cell(customer_price)="{ item }">
            <div>
                {{ locale.currencyFormat(item.currency_code, item.customer_price) }}
            </div>
        </template>

    </Table>
</template>
