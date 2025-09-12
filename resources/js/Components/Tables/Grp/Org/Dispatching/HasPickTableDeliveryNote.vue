<!--
  Updated DeliveryNotesTable.vue - Now using the reusable NotesDisplay component
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { DeliveryNote } from "@/types/delivery-note"
import { DialogTitle } from "@headlessui/vue"
import type { Table as TableTS } from "@/types/Table"
import { inject, ref } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useFormatTime } from '@/Composables/useFormatTime'
import Icon from "@/Components/Icon.vue"
import { useLocaleStore } from "@/Stores/locale";
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
// Import the new NotesDisplay component
import NotesDisplay from "@/Components/NotesDisplay.vue"

const props = defineProps<{
    data: TableTS,
    tab?: string
    HasPickTableDeliveryNote?: Array<Number>
}>()

console.log(props.data);

const locale = useLocaleStore();
const layout = inject('layout', layoutStructure)

// Pick modal related code (unchanged)
const isModalPick = ref(null)
const isLoadingPick = ref(false)
const isErrorPicker = ref<string | null>(null)

const onClickPick = () => {
    if (!isModalPick.value?.employee_pick_route?.name) {
        console.error("No route name found for employee pick")
        return
    }

    router.patch(
        route(isModalPick.value.employee_pick_route.name, isModalPick.value.employee_pick_route.parameters),
        {},
        {
            onStart: () => {
                isLoadingPick.value = true
            },
            onError: (errors) => {
                isErrorPicker.value = errors.messages
                notify({
                    title: trans("Something went wrong"),
                    text: isErrorPicker.value,
                    type: "error",
                })
            },
            onSuccess: () => {
                isModalPick.value = null
            },
            onFinish: () => {
                isLoadingPick.value = false
            }
        }
    )
}

const selectedDeliveryNotes = defineModel<number[]>('selectedDeliveryNotes')

const onChangeCheked = (checked: boolean, item: DeliveryNote) => {
    if (!selectedDeliveryNotes.value) return

    if (checked) {
        if (!selectedDeliveryNotes.value.includes(item.id)) {
            selectedDeliveryNotes.value.push(item.id)
        }
    } else {
        selectedDeliveryNotes.value = selectedDeliveryNotes.value.filter(id => id != item.id)
    }
}

const onCheckedAll = ({ data, allChecked }) => {
    if (!selectedDeliveryNotes.value) return

    if (allChecked) {
        const newIds = data.map(row => row.id)
        selectedDeliveryNotes.value = Array.from(new Set([...selectedDeliveryNotes.value, ...newIds]))
    } else {
        const uncheckIds = data.map(row => row.id)
        selectedDeliveryNotes.value = selectedDeliveryNotes.value.filter(id => !uncheckIds.includes(id))
    }
}

// Route functions (unchanged)
function deliveryNoteRoute(deliveryNote: DeliveryNote) {
    switch (route().current()) {
        case "shops.show.orders.show":
            return route(
                "shops.show.orders.show.delivery-notes.show",
                [route().params["shop"], route().params["order"], deliveryNote.slug])
        case "orders.show":
            return route(
                "orders.show,delivery-notes.show",
                [route().params["order"], deliveryNote.slug])
        case "shops.show.delivery-notes.index":
            return route(
                "shops.show.delivery-notes.show",
                [deliveryNote.shop_id, deliveryNote.slug])
        case "grp.org.warehouses.show.dispatching.delivery-notes":
            return route(
                "grp.org.warehouses.show.dispatching.delivery_notes.show",
                [route().params["organisation"], route().params["warehouse"], deliveryNote.slug])
        case "grp.org.shops.show.ordering.delivery-notes.index":
            return route(
                "grp.org.shops.show.ordering.delivery-notes.show",
                [route().params["organisation"], route().params["shop"], deliveryNote.slug])
        case "grp.org.shops.show.ordering.orders.index":
            return route(
                "grp.org.shops.show.ordering.show.delivery-note.show",
                [route().params["organisation"], route().params["shop"], deliveryNote.slug])
        case "grp.org.shops.show.ordering.orders.show":
            return route(
                "grp.org.shops.show.ordering.orders.show.delivery-note",
                [route().params["organisation"], route().params["shop"], route().params["order"], deliveryNote.slug])
        case "grp.org.shops.show.crm.customers.show.delivery_notes.index":
            return route(
                "grp.org.shops.show.crm.customers.show.delivery_notes.show",
                [route().params["organisation"], route().params["shop"], route().params["customer"], deliveryNote.slug])
        case "grp.org.shops.show.crm.customers.show.orders.show":
            return route(
                "grp.org.shops.show.crm.customers.show.delivery_notes.show",
                [route().params["organisation"], route().params["shop"], route().params["customer"], deliveryNote.slug])
        case "grp.overview.ordering.delivery_notes.index":
            return route(
                "grp.org.shops.show.crm.customers.show.delivery_notes.show",
                [deliveryNote.organisation_slug, deliveryNote.shop_slug, deliveryNote.customer_slug, deliveryNote.slug])
        default:
            return route(
                "grp.org.warehouses.show.dispatching.delivery_notes.show",
                [route().params["organisation"], route().params["warehouse"], deliveryNote.slug]);
    }
}

function pickingSessionRoute(id) {
    return route(
        "grp.helpers.redirect_picking_session",
        [id])
}

function customerRoute(deliveryNote: DeliveryNote) {
    if (!deliveryNote.customer_slug) {
        return '#'
    }

    switch (route().current()) {
        case "grp.overview.ordering.delivery_notes.index":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [deliveryNote.organisation_slug, deliveryNote.shop_slug, deliveryNote.customer_slug])
        case "grp.org.warehouses.show.dispatching.delivery-notes":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [route().params["organisation"], deliveryNote.shop_slug, deliveryNote.customer_slug])
        default:
            return route(
                "grp.org.shops.show.crm.customers.show",
                [route().params["organisation"], deliveryNote.shop_slug, deliveryNote.customer_slug])
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="true"
        @onChecked="(item) => onChangeCheked(true, item)" @onUnchecked="(item) => onChangeCheked(false, item)"
        @onCheckedAll="(data) => onCheckedAll(data)" checkboxKey='id'
        :isChecked="(item) => selectedDeliveryNotes.includes(item.id)"
        :disabledCheckbox="(item) => item.picking_sessions_count > 0">

        <template #disable-checkbox>
            <div></div>
        </template>

        <template #cell(status)="{ item: deliveryNote }">
            <Icon :data="deliveryNote.state_icon" />
        </template>

        <template #cell(effective_weight)="{ item: deliveryNote }">
            {{ deliveryNote.effective_weight }} g
        </template>

        <template #cell(reference)="{ item: deliveryNote }">
            <div class="flex gap-4 flex-wrap items-center">
                <span>
                    <Link :href="deliveryNoteRoute(deliveryNote)" class="primaryLink">
                    {{ deliveryNote["reference"] }}
                    </Link>
                    <FontAwesomeIcon v-if="deliveryNote.is_premium_dispatch" v-tooltip="trans('Priority dispatch')"
                        icon="fas fa-star" class="text-yellow-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-if="deliveryNote.has_extra_packing" v-tooltip="trans('Extra packing')"
                        icon="fas fa-box-heart" class="text-yellow-500" fixed-width aria-hidden="true" />
                </span>
                <NotesDisplay :item="deliveryNote" reference-field="reference" />
            </div>

            <template v-if="deliveryNote.picking_sessions_count > 0 && deliveryNote.picking_session_ids">
                <Link v-for="id in deliveryNote.picking_session_ids.split(',')" :key="id"
                    :href="pickingSessionRoute(id)" class="secondaryLink">
                <FontAwesomeIcon icon="fab fa-stack-overflow" class="text-yellow-500" fixed-width aria-hidden="true" />
                </Link>
            </template>
        </template>

        <template #cell(date)="{ item }">
            {{ useFormatTime(item.date) }}
        </template>

        <template #cell(customer_name)="{ item: deliveryNote }">
            <Link :href="customerRoute(deliveryNote)" class="secondaryLink">
            {{ deliveryNote["customer_name"] }}
            </Link>
        </template>

        <template #cell(action)="{ item: deliveryNote }">
            <Button @click="() => isModalPick = deliveryNote" type="secondary" :label="trans('Pick')" size="xs" />
        </template>
    </Table>

    <!-- Pick Modal (unchanged) -->
    <Modal :isOpen="!!isModalPick" @close="isModalPick = null, isErrorPicker = null" width="w-full max-w-lg">
        <div class="sm:flex sm:items-start w-full">
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <DialogTitle as="h3" class="text-base font-semibold">
                    {{ trans("Are you sure to pick the delivery?") }}
                </DialogTitle>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">
                        {{ trans("This action will pick the delivery note") }} <strong>{{ isModalPick?.reference
                        }}</strong>
                        {{ trans('with') }} {{ isModalPick?.number_items }} {{ trans('items') }}
                    </p>
                </div>

                <div class="mt-5 sm:flex sm:flex-row-reverse gap-x-2">
                    <Button :loading="isLoadingPick" @click="() => onClickPick()" :label="trans('Yes')" full />
                    <Button type="tertiary" icccon="far fa-arrow-left" :label="trans('cancel')"
                        @click="() => (isModalPick = null)" />
                </div>

                <p v-if="isErrorPicker" class="mt-2 text-xs text-red-500 italic">
                    *{{ isErrorPicker }}
                </p>
            </div>
        </div>
    </Modal>
</template>