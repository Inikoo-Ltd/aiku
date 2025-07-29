<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params"
import { CustomerSalesChannel } from "@/types/customer-sales-channel"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref } from "vue"
import ConfirmPopup from "primevue/confirmpopup"
import { useConfirm } from "primevue/useconfirm"
import { faExclamationTriangle } from "@far"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"

import customerSalesChannel from "@/Pages/Grp/Org/Dropshipping/CustomerSalesChannel.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUndoAlt, faTrashAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faUndoAlt)

defineProps<{
    data: TableTS,
    tab?: string
}>()

const confirm = useConfirm()
const deletingId = ref<number | null>(null)

function customerSalesChannelRoute(customerSalesChannel: CustomerSalesChannel) {

    switch (route().current()) {
        case "grp.org.shops.show.crm.platforms.show":
            return route("grp.org.shops.show.crm.platforms.show.customer_sales_channels.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).platform,
                    customerSalesChannel.slug]
            )
            break
        default:
            return route("grp.org.shops.show.crm.customers.show.customer_sales_channels.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).customer,
                    customerSalesChannel.slug]
            )
    }
}

function portfoliosRoute(customerSalesChannel: CustomerSalesChannel) {
    switch (route().current()) {
        case "grp.org.shops.show.crm.platforms.show":
            return ""

        default:
            return route(
                "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios.index",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).customer,
                    customerSalesChannel.slug])
    }


}

function clientsRoute(customerSalesChannel: CustomerSalesChannel) {
    switch (route().current()) {
        case "grp.org.shops.show.crm.platforms.show":
            return ""

        default:
            return route(
                "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).customer,
                    customerSalesChannel.slug])
    }

}

function ordersRoute(customerSalesChannel: CustomerSalesChannel) {
    switch (route().current()) {
        case "grp.org.shops.show.crm.platforms.show":
            return ""

        default:
            return route(
                "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).customer,
                    customerSalesChannel.slug])
    }

}


function confirmDelete(event: MouseEvent, customerSalesChannel: CustomerSalesChannel) {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: trans("Are you sure to delete this channel?"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("Delete"),
        rejectLabel: trans("Cancel"),
        acceptClass: "p-button-danger",
        rejectClass: "p-button-text",
        accept: () => {
            deletingId.value = customerSalesChannel.id

            router.delete(route("grp.models.customer_sales_channel.delete", {
                customerSalesChannel: customerSalesChannel.id
            }), {
                preserveScroll: true,
                onFinish: () => {
                    deletingId.value = null
                },
                onError: (errors) => {
                    console.error("Delete failed:", errors)
                    notify({
                        title: "Failed to Delete",
                        text: "error",
                        type: "error"
                    })
                }
            })
        }
    })
}

</script>


<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: customerSalesChannel }">
            <div class="flex items-center gap-2">
                <img v-tooltip="customerSalesChannel.platform_name" :src="customerSalesChannel.platform_image"
                     :alt="customerSalesChannel.platform_name" class="w-6 h-6" />
                <Link :href="(customerSalesChannelRoute(customerSalesChannel) as string)" class="primaryLink">
                    {{ customerSalesChannel.name || customerSalesChannel.reference }}
                </Link>
            </div>
        </template>

        <template #cell(platform_status)="{ item }">

            <template v-if="item.status==='open'">
                <template v-if="item.platform_code=='manual'">
                    <FontAwesomeIcon v-tooltip="trans('Web/Api channel active')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />

                </template>
                <template v-else>
                    <FontAwesomeIcon v-if="item.can_connect_to_platform" v-tooltip="trans('App installed ok')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-else v-tooltip="trans('Broken channel delete it and create new one')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-if="item.exist_in_platform" v-tooltip="trans('Exist in platform')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-else v-tooltip="trans('Exist in platform')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-if="item.platform_status" v-tooltip="trans('Platform status')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-else v-tooltip="trans('Platform status')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                </template>
            </template>
            <template v-else>
                {{ item.status }}
            </template>
        </template>

        <template #cell(number_portfolios)="{ item: customerSalesChannel }">
            <Link :href="(portfoliosRoute(customerSalesChannel) as string)" class="secondaryLink">
                <span class="text-red-500">{{
                        customerSalesChannel.number_portfolio_broken
                    }}</span>/{{ customerSalesChannel.number_portfolios }}
            </Link>
        </template>

        <template #cell(number_clients)="{ item: customerSalesChannel }">
            <Link :href="(clientsRoute(customerSalesChannel) as string)" class="secondaryLink">
                {{ customerSalesChannel.number_clients }}
            </Link>
        </template>

        <template #cell(number_orders)="{ item: customerSalesChannel }">
            <Link :href="(ordersRoute(customerSalesChannel) as string)" class="secondaryLink">
                {{ customerSalesChannel.number_orders }}
            </Link>
        </template>

        <template #cell(action)="{ item }">

            <div v-if="item.status=='open'" class="space-y-1">
                <ModalConfirmationDelete
                    v-if=" item.can_connect_to_platform &&  !item.platform_status"
                    :routeDelete="{
                        name: 'grp.models.customer_sales_channel.delete',
                        parameters: {
                            customerSalesChannel: item.id,
                        },
                        method: 'patch'
                    }"
                >
                    <template #default="{ isOpenModal, changeModel }">
                        <Button @click.stop="changeModel" label="Reset channel" type="negative" icon="fal fa-undo-alt">
                        </Button>
                    </template>
                </ModalConfirmationDelete>

                <Button v-if="!item.platform_status" type="negative" :label="trans('Delete')" :icon="faTrashAlt"
                        @click="(event) => confirmDelete(event, item)" />
            </div>
        </template>
    </Table>


    <ConfirmPopup>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmPopup>
</template>
