<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faStar, faBoxHeart, faShieldAlt } from "@fas"
import Icon from "@/Components/Icon.vue"
import { inject } from "vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { GridProducts } from "@/Components/Product"

library.add(faPlus, faStar)

defineProps<{
    data: object,
    currency: {
        code: string
        symbol: string
        name: string
    }
}>()

const locale = inject("locale", null)

function orderRoute(order) {
    console.log(route().current())
    switch (route().current()) {
        case "retina.ecom.orders.index":
            return route(
                "retina.ecom.orders.show",
                {
                    order: order.slug
                })
        default:
            return ""

    }
}

// function clientRoute(order) {
//     return route(
//         "retina.dropshipping.customer_sales_channels.client.show",
//         {
//             customerSalesChannel: (route().params as RouteParams).customerSalesChannel,
//             customerClient: order.client_ulid
//         })
// }
</script>

<template>
    <div>
        <Table :resource="data" class="mt-5 hidden md:block">

            <!-- Column: Reference -->
            <template #cell(reference)="{ item }">
                <Link :href="(orderRoute(item) as string)" class="primaryLink">
                    {{ item["reference"] }}
                </Link>

                <span class="whitespace-nowrap text-yellow-500">
                    <FontAwesomeIcon v-if="item.is_premium_dispatch" v-tooltip="trans('Premium dispatch')"
                        :icon="faStar" class="" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-if="item.has_extra_packing" v-tooltip="trans('Extra packing')" :icon="faBoxHeart"
                        class="" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-if="item.has_insurance" v-tooltip="trans('Insurance')" :icon="faShieldAlt"
                        class="" fixed-width aria-hidden="true" />
                </span>
            </template>

            <!-- <template #cell(client_name)="{ item }">
                <Link :href="(clientRoute(item) as string)" class="secondaryLink">
                {{ item["client_name"] }}
                </Link>
            </template> -->

            <!-- Column: State -->
            <template #cell(state)="{ item: order }">
                <Icon :data="order['state_icon']" class="px-1" />
            </template>


            <template #cell(total_amount)="{ item }">
                <!-- {{ currency?.code }} -->
                {{ locale?.currencyFormat(currency?.code, item.total_amount || 0) }}
            </template>

            <template #cell(date)="{ item: order }">
                {{ useFormatTime(order.date) }}
            </template>

            <template #cell(actions)="{ item: order }">
                <ModalConfirmationDelete v-if="order.delete_route" :routeDelete="order.delete_route"
                    :title="trans('Are you sure you want to delete this order?')" isFullLoading>
                    <template #default="{ isOpenModal, changeModel }">
                        <div class="w-fit mx-auto">
                            <Button v-tooltip="trans('Delete basket')" @click="() => changeModel()" type="negative"
                                icon="fal fa-trash-alt" :label="trans('Delete')" size="s" :key="1" />
                        </div>
                    </template>
                </ModalConfirmationDelete>
            </template>

        </Table>
    </div>

    <GridProducts :resource="data" :preserve-scroll="true" class="mt-5 md:hidden" :name="tab"
        :gridClass="'grid grid-cols-1'">
        <template #card="{ item }">
            <div
                class="rounded-lg border border-gray-200 bg-white px-3 py-2.5 transition hover:border-primary-300 hover:shadow-sm">
                <!-- Header -->
                <div class="flex items-start justify-between gap-2">
                    <Link :href="orderRoute(item) as string"
                        class="primaryLink flex min-w-0 items-center gap-1.5 font-medium">
                        <Icon :data="item.state_icon" class="text-sm shrink-0" />

                        <span class="truncate">
                            {{ item.reference }}
                        </span>
                    </Link>

                    <div class="flex shrink-0 items-center gap-1 text-xs text-yellow-500">
                        <FontAwesomeIcon v-if="item.is_premium_dispatch" :icon="faStar"
                            v-tooltip="trans('Premium dispatch')" fixed-width />

                        <FontAwesomeIcon v-if="item.has_extra_packing" :icon="faBoxHeart"
                            v-tooltip="trans('Extra packing')" fixed-width />

                        <FontAwesomeIcon v-if="item.has_insurance" :icon="faShieldAlt" v-tooltip="trans('Insurance')"
                            fixed-width />
                    </div>
                </div>

                <!-- Body -->
                <div class="mt-2 flex items-end justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-base font-semibold text-gray-900">
                            {{ locale?.currencyFormat(currency?.code, item.total_amount || 0) }}
                        </div>

                        <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                            <span>{{ item.number_item_transactions }} items</span>

                            <span>•</span>

                            <span class="truncate">
                                {{ useFormatTime(item.date) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </GridProducts>

</template>
