<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 25 Jan 2024 11:46:16 Malaysia Time, Bali Office, Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FulfilmentCustomer } from "@/types/fulfilment-customer"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faTimesCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { faCheckCircle } from "@fas"
import { trans } from "laravel-vue-i18n"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import ModalRejected from "@/Components/Utils/ModalRejected.vue"
import { ref } from "vue"
import ModalApproveConfirmation from "@/Components/Utils/ModalApproveConfirmation.vue"
import { RouteParams } from "@/types/route-params"


library.add(faCheck, faTimes, faCheckCircle, faTimesCircle)

defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore()

function customerRoute(customer: FulfilmentCustomer) {
    return route(
        "grp.org.fulfilments.show.crm.customers.show",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).fulfilment,
            customer.slug
        ])
}

const isModalUploadOpen = ref(false)
const customerID = ref()
const customerName = ref()

function openRejectedModal(customer: any) {
    customerID.value = customer.id
    customerName.value = customer.name
    isModalUploadOpen.value = true
}

const approvedCustomer = ref([])
const isModalApproveOpen = ref(false)

function approveCustomer(customer: any) {
    router.patch(
        route("grp.models.customer.approve", { customer: customer.id }),
        {},
        {
            onStart: () => {
                customer.isLoading = true
            },
            preserveScroll: true,
            onSuccess: () => {
                approvedCustomer.value = customer
                isModalApproveOpen.value = true
            },
            onFinish: () => {
                customer.isLoading = false
            },
            onError: (errors) => {
                console.error("Approval error:", errors)
            }
        }
    )
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item: customer }">
            <div v-tooltip="customer.status_icon.tooltip" class="px-1 py-0.5">
                <FontAwesomeIcon :icon="customer.status_icon.icon" :class="customer.status_icon.class" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(reference)="{ item: customer }">
            <Link :href="customerRoute(customer)" class="primaryLink">
                {{ customer["reference"] }}
            </Link>
        </template>
        <template #cell(created_at)="{ item: customer }">
            <div class="text-gray-500 text-right">{{ useFormatTime(customer["created_at"], { localeCode: locale.language.code, formatTime: "aiku" }) }}</div>
        </template>
        <template #cell(last_invoiced_at)="{ item: customer }">
            <div class="text-gray-500 text-right">{{ useFormatTime(customer["last_invoiced_at"], { localeCode: locale.language.code, formatTime: "aiku" }) }}</div>
        </template>
        <template #cell(invoiced_net_amount)="{ item: customer }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(customer.currency_code, customer.sales_all) }}</div>
        </template>
        <template #cell(sales_all)="{ item: customer }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(customer.currency_code, customer.sales_all) }}</div>
        </template>
        <template #cell(registered_at)="{ item: customer }">
            <div class="text-gray-500 text-right">{{ useFormatTime(customer["registered_at"], { localeCode: locale.language.code, formatTime: "hm" }) }}</div>
        </template>
        <template #cell(action)="{ proxyItem: customer }">
            <div class="flex gap-4">
                <!-- <Link :href="route('grp.models.customer.approve', {customer : customer.id })" method="patch" :data="{ status: 'approved' }">
                     <Button label="Approved" :icon="faCheckCircle" size="xs"></Button>
                </Link> -->
                <ButtonWithLink
                    label="Approve"
                    icon="fal fa-check"
                    :bindToLink="{
                    preserveScroll: true,
                    preserveState: true
                }"
                    type="positive"
                    size="xs"
                    :loading="customer.isLoading"
                    @click="() => approveCustomer(customer)"
                />
                <!-- <Link :href="route('grp.models.customer.approve', {customer : customer.id })" method="patch" :data="{ status: 'rejected' }"> -->
                <ButtonWithLink
                    label="Reject"
                    icon="fal fa-times"
                    :bindToLink="{
                    preserveScroll: true,
                    preserveState: true
                }"
                    type="delete"
                    size="xs"
                    @click="() => openRejectedModal(customer)"
                />
                <!-- </Link> -->
            </div>
        </template>


        <template #cell(location)="{ item: customer }">
            <!-- <pre>{{ JSON.parse(customer['location']) }}</pre> -->
            <AddressLocation :data="customer['location']" />
        </template>

        <!-- Column: Interest -->
        <template #cell(interest)="{ item: customer }">
            <div class="flex gap-2 text-base text-gray-500">
                <FontAwesomeIcon v-if="customer.interest?.pallets_storage" v-tooltip="trans('Pallet storage')" icon="fal fa-pallet" class="" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-if="customer.interest?.items_storage" v-tooltip="trans('Dropshipping')" icon="fal fa-narwhal" class="" fixed-width aria-hidden="true" />
                <!-- <FontAwesomeIcon v-if="customer.interest?.dropshipping" v-tooltip="trans('Dropshipping')" icon='fal fa-' class='' fixed-width aria-hidden='true' /> -->
                <FontAwesomeIcon v-if="customer.interest?.space_rental" v-tooltip="trans('Space (parking)')" icon="fal fa-parking" class="" fixed-width aria-hidden="true" />
            </div>
        </template>
    </Table>

    <ModalRejected
        v-model="isModalUploadOpen"
        :customerID="customerID"
        :customerName="customerName"
    />
    <ModalApproveConfirmation
        v-model="isModalApproveOpen"
        :approvedCustomer
    />
</template>
