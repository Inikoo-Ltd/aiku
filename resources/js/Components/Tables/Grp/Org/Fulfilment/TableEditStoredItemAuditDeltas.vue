<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { faCheck, faTimes, faUndoAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import StoredItemsProperty from "@/Components/StoredItemsProperty.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { reactive, ref } from "vue"
import { debounce, get, set } from "lodash-es"
import InputNumber from "primevue/inputnumber"
import { notify } from "@kyvg/vue3-notification"
import CreateStoredItems from "@/Components/CreateStoredItems.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Icon from "@/Components/Icon.vue"
import { routeType } from "@/types/route"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import { faEdit } from "@far"
import { faCheckCircle as fasCheckCircle } from "@fas"
import { RouteParams } from "@/types/route-params"


library.add(faCheck, faTimes, fasCheckCircle, faEdit, faUndoAlt)

const props = defineProps<{
    data: {}
    tab?: string
    route_list: {
        update: routeType
        stored_item_audit_delta: {
            update: routeType  // Update quantity
            store: routeType  // add new stored item
            delete: routeType  // undo select
        }
    }
    storedItemsRoute: {
        index: routeType  // Fetch a list of stored items
        store: routeType  // Add stored items
        delete: routeType  // Delete stored items
    }
}>()


function palletRoute(pallet: {}) {
    switch (route().current()) {
        case "grp.overview.fulfilment.pallets.index":
            return route(
                "grp.org.fulfilments.show.operations.pallets.current.show",
                [
                    pallet.organisation_slug,
                    pallet.fulfilment_slug,
                    pallet.slug
                ])
        case "grp.org.fulfilments.show.operations.pallets.current.index":
            return route(
                "grp.org.fulfilments.show.operations.pallets.current.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    pallet.slug
                ])

        case "grp.org.fulfilments.show.operations.returned_pallets.index":
            return route(
                "grp.org.fulfilments.show.operations.returned_pallets.index",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    pallet.slug
                ])

        case "grp.org.fulfilments.show.crm.customers.show":
        case "grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.pallets.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    (route().params as RouteParams).fulfilmentCustomer,
                    pallet.slug
                ])
        default:
            return []
    }
}


// Helper to give color on change qty stored items
interface StoredItemsQuantity {
    [key: string]: {  // row index of Table
        [key: string]: number  // stored item id
    }
}

const statesBoxEdit = reactive<StoredItemsQuantity>({})

const isLoading = ref(false)
const onCheck = (routex: routeType, store_item_id: number, qty: number) => {
    // router.post(route(routex.name, routex.parameters), {
    //     stored_item_ids	: {
    //         [store_item_id]: {
    //             quantity: qty
    //         }
    //     }
    // }, {
    //     onStart: () => {
    //         isLoading.value = true
    //     },
    //     onFinish: () => {
    //         isLoading.value = false
    //     }
    // })
}

// Unselect Audit Deltas
const isLoadingUnselect = reactive<StoredItemsQuantity>({})
const onUnselectNewStoredItem = (row: number, store_item_audit_deltas_id: number) => {
    props.route_list.stored_item_audit_delta.delete
    router.delete(
        route(props.route_list.stored_item_audit_delta.delete.name, store_item_audit_deltas_id),
        {
            onStart: () => {
                set(isLoadingUnselect, `${row}.${store_item_audit_deltas_id}`, true)
            },
            onFinish: () => {
                set(isLoadingUnselect, `${row}.${store_item_audit_deltas_id}`, false)
            },
            preserveScroll: true
        }
    )
}


// Section: store quantity stored item (first update)
const isLoadingStoreQuantity = reactive<StoredItemsQuantity>({})
const onStoreStoredItem = (row: number, idPallet: number, idStoredItem: number, quantity: number, idStoredItemAudit: number) => {
    console.log("onStoreStoredItem", idStoredItem)
    // Store
    console.log("lolo", props.route_list?.stored_item_audit_delta?.store?.name)
    if (!props.route_list?.stored_item_audit_delta?.store?.name) {
        console.error("No id stored item audit")
        return
    }

    router.post(
        route(props.route_list?.stored_item_audit_delta?.store.name, idStoredItemAudit),
        {
            pallet_id: idPallet,
            stored_item_id: idStoredItem,
            audited_quantity: quantity
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                set(isLoadingStoreQuantity, `${row}.${idStoredItem}`, true)
            },
            onError: (e) => {
                console.error(e)
                notify({
                    title: trans("Something went wrong."),
                    text: trans("Failed to update the quantity."),
                    type: "error"
                })
            },
            onFinish: () => {
                set(isLoadingStoreQuantity, `${row}.${idStoredItem}`, false)
            }
        }
    )
}
const debounceStoreStoredItem = debounce((row: number, idPallet: number, idStoredItem: number, quantity: number, idStoredItemAudit: number) => onStoreStoredItem(row, idPallet, idStoredItem, quantity, idStoredItemAudit), 500)


// Section: Update quantity stored item
const isStoredItemEdited = reactive<StoredItemsQuantity>({})
const isLoadingQuantity = reactive<StoredItemsQuantity>({})
const onChangeQuantity = (row: number, idStoredItemAuditDelta: number | null, quantity: number) => {
    console.log("onChangeQuantity")
    //todo if   "store_item_audit_delta_id" ==null,
    // use props.route_list?.stored_item_audit_delta?.store.name
    // get back the store_item_audit_delta_id and set it uo so next time you call update

    // Update
    console.log("lolo", props.route_list?.stored_item_audit_delta?.update?.name)
    if (!props.route_list?.stored_item_audit_delta?.update?.name) {
        return
    }
    router.patch(
        route(props.route_list?.stored_item_audit_delta?.update.name, idStoredItemAuditDelta),
        {
            audited_quantity: quantity
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                set(isLoadingQuantity, `${row}.${idStoredItemAuditDelta}`, true)
            },
            onError: (e) => {
                console.error(e)
                notify({
                    title: trans("Something went wrong."),
                    text: trans("Failed to update the quantity."),
                    type: "error"
                })
            },
            onFinish: () => {
                set(isLoadingQuantity, `${row}.${idStoredItemAuditDelta}`, false)
                set(isStoredItemEdited, [(row + 1)?.toString(), `id${idStoredItemAuditDelta?.toString()}`], true)
                // isStoredItemEdited["1"]["256"] = true
            }
        }
    )
}
const debounceChangeQuantity = debounce((row: number, idStoredItemAuditDelta: number, quantity: number) => onChangeQuantity(row, idStoredItemAuditDelta, quantity), 500)


const stateStoredItemEdited = reactive<StoredItemsQuantity>({})


const edit_block = (audit_type: string, is_edit: boolean, keep_is_edit: boolean) => {
    return keep_is_edit ? "edit" : audit_type === "no_change" ? "checked" : audit_type || is_edit ? "edit" : false
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" striped rowAlignTop>

        <!-- Column: Reference -->
        <template #cell(reference)="{ item: pallet }">
            <component :is="pallet.slug ? Link : 'div'" :href="pallet.slug ? palletRoute(pallet) : undefined"
                       :class="pallet.slug ? 'primaryLink' : ''">
                {{ pallet.reference }}
            </component>

            <div v-if="pallet.customer_reference" v-tooltip="trans('Customer\'s reference')"
                 class="mt-1 space-x-1.5 whitespace-nowrap">
                <span class="text-gray-400 text-sm">({{ pallet.customer_reference }})</span>
            </div>
        </template>

        <!-- Column: Location -->
        <template #cell(location_code)="{ item: pallet }">
            <component :is="pallet.location_code ? Link : 'div'"
                       :href="pallet.location_code ? route('grp.org.warehouses.show.infrastructure.locations.show', ['aw', pallet.warehouse_slug, pallet.location_slug]) : undefined"
                       :class="pallet.slug ? 'secondaryLink' : ''">
                {{ pallet.location_code }}
            </component>
        </template>

        <!-- Column: Customer Reference -->
        <template #cell(customer_reference)="{ item: item }">
            <div class="">
                {{ item.customer_reference }}
                <span v-if="item.notes" class="text-gray-400 text-xs ml-1">
                    <FontAwesomeIcon icon="fal fa-sticky-note" class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ item.notes }}
                </span>
            </div>
        </template>


        <!-- Column: Icon (status and state) -->
        <template #cell(state)="{ item: pallet }">
            <div class="flex gap-x-1 gap-y-1.5">
                <Icon :data="pallet.type_icon" class="px-1" />
                <Icon :data="pallet['status_icon']" />
                <Icon :data="pallet['state_icon']" />
            </div>
        </template>


        <!-- Column: Notes -->
        <template #cell(notes)="{ item: pallet }">
            <div class="text-gray-500 italic">{{ pallet.notes }}</div>
        </template>


        <!-- Column: Customer SKUS -->
        <template #cell(stored_items)="{ proxyItem, item }">
            <!-- pallet id: {{ item.id }} <br />
            item.stored_item_audit_id: {{ item.stored_item_audit_id }} <br />
            route store: {{ props.route_list?.stored_item_audit_delta?.store.name }} <br /> -->

            <DataTable v-if="proxyItem.stored_items?.length || proxyItem.new_stored_items?.length"
                       :value="[...proxyItem.stored_items, ...proxyItem.new_stored_items]">
                <Column field="reference" :header="trans('SKU')" class="">
                    <template #body="{ data }">
                        <div class="whitespace-nowrap">{{ data.reference }}
                            <FontAwesomeIcon v-if="data.type === 'new_item'" v-tooltip="trans('New added Customer\'s SKU')" icon="fas fa-star" size="xs" class="text-indigo-500" fixed-width aria-hidden="true" />
                        </div>
                    </template>
                </Column>

                <Column field="quantity" header="Current qty" class="">
                    <template #body="{ data }">
                        <div class="text-right">{{ data.quantity || "" }}</div>
                    </template>
                </Column>

                <Column field="quantity" header="Actions" class="">


                    <template #body="{ data }">
                        <!-- <pre>{{ data }}</pre>
                        stored_item_audit_id: {{ data.stored_item_audit_id || '-' }} <br />
                        stored_item_id: {{ data.stored_item_id || '-' }} <br />
                        audit_type: {{ data.audit_type || '-' }} <br />
                        stored_item_audit_delta_id: {{ data.stored_item_audit_delta_id || '-' }} <br /> -->
                        <!-- edit_block: {{ edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [(item.rowIndex+1)?.toString(), `id${data.stored_item_audit_delta_id?.toString()}`], false)) }} -->

                        <!-- <pre>{{ props.route_list?.stored_item_audit_delta?.store }}</pre>
                        <pre>{{ data }}</pre> -->
                        <div class="relative">
                            <!-- <div 
                                class="z-10 opacity-60 absolute w-full h-full top-0 left-0">
                                <div class="skeleton h-full w-full"></div>
                            </div> -->


                            <div class="flex gap-x-2.5 items-center w-64">
                                <!-- Check green -->
                                <div
                                    v-if="data.audit_type === 'no_change' && edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [(item.rowIndex+1)?.toString(), `id${data.stored_item_audit_delta_id?.toString()}`], false)) != 'edit'">
                                    <Button
                                        type="tertiary"
                                        icon="fas fa-check-circle"
                                        class="border-none rounded-none text-green-500 cursor-auto hover:bg-transparent -mx-2"
                                    />
                                </div>

                                <div class="flex justify-center border border-gray-300 rounded gap-y-1">
                                    <!-- Button: Check -->
                                    <Button
                                        v-if="data.type !== 'new_item' && data.audit_type !== 'no_change' && data.audit_type !== 'addition' && !get(data, ['is_edit'], false) && edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [(item.rowIndex+1)?.toString(), `id${data.stored_item_audit_delta_id?.toString()}`], false)) != 'edit'"
                                        @click="() => data.audit_type === 'no_change' ? null : onStoreStoredItem(item.rowIndex, item.id, data.stored_item_id, data.quantity, data.stored_item_audit_id)"
                                        type="tertiary"
                                        :icon="data.audit_type === 'no_change' ? 'fas fa-check-circle' : 'fal fa-check-circle'"
                                        class="border-none rounded-none"
                                        :class="data.audit_type === 'no_change' ? 'text-green-500 hover:cursor-auto' : ''"
                                        :disabled="data.audit_type === 'no_change'"
                                    />

                                    <!-- Edit button -->
                                    <div
                                        v-if="!data.stored_item_audit_delta_id && edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [(item.rowIndex+1)?.toString(), `id${data.stored_item_audit_delta_id?.toString()}`], false)) != 'edit'"
                                        @click="() => set(data, ['is_edit'], !(get(data, ['is_edit'], false)))" class="px-2 flex items-center hover:bg-gray-200 cursor-pointer">
                                        <FontAwesomeIcon icon="far fa-edit" class="" fixed-width aria-hidden="true" />
                                    </div>

                                    <!-- Section: - and + -->
                                    <template
                                        v-if=" (!data.stored_item_audit_delta_id && get(data, ['is_edit'], false)) || (data.audit_type && data.audit_type != 'no_change') || edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [(item.rowIndex+1)?.toString(), `id${data.stored_item_audit_delta_id?.toString()}`], false)) == 'edit'">
                                        <div class="transition-all relative inline-flex items-center justify-center "
                                             :class="!get('statesBoxEdit', `${item.rowIndex}.${data.id}`, false) ? 'w-28' : 'w-14'">
                                            <transition>
                                                <div v-if="!get('statesBoxEdit', `${item.rowIndex}.${data.id}`, false)"
                                                     class="relative flex flex-nowrap items-center justify-center gap-y-1 gap-x-1">
                                                    <!-- Button: Minus -->
                                                    <div @click="async () => (
                                                            set(data, `${data.stored_item_audit_delta_id ? 'audited_quantity' : 'quantity'}`, ((data.stored_item_audit_delta_id ? data.audited_quantity : data.quantity) - 1) >= 0 ? ((data.stored_item_audit_delta_id ? data.audited_quantity : data.quantity) - 1) : 0),
                                                            data.stored_item_audit_delta_id
                                                                ? debounceChangeQuantity(item.rowIndex, data.stored_item_audit_delta_id, get(data, `audited_quantity`, data.quantity))
                                                                : debounceStoreStoredItem(item.rowIndex, item.id, data.stored_item_id, get(data, `quantity`, data.quantity), data.stored_item_audit_id)

                                                        )"
                                                         class="leading-4 cursor-pointer inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed min-w-max bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70 rounded px-1 py-1.5 text-xs justify-self-center">
                                                        <FontAwesomeIcon icon="fas fa-minus" class="" fixed-width aria-hidden="true" />
                                                    </div>
                                                    <div class="text-center tabular-nums border border-transparent hover:border-dashed hover:border-gray-300 group-focus:border-dashed group-focus:border-gray-300">

                                                        <InputNumber
                                                            :modelValue="data.stored_item_audit_delta_id ? data.audited_quantity : data.quantity ||   edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [(item.rowIndex+1)?.toString(), `id${data.stored_item_audit_delta_id?.toString()}`], false)) =='edit'  "
                                                            @update:modelValue="(e) => (
                                                                set(data, `${data.stored_item_audit_delta_id ? 'audited_quantity' : 'quantity'}`, e),
                                                                data.stored_item_audit_delta_id
                                                                    ? debounceChangeQuantity(item.rowIndex, data.stored_item_audit_delta_id, e)
                                                                    : debounceStoreStoredItem(item.rowIndex, item.id, data.stored_item_id, e, data.stored_item_audit_id)
                                                            )"
                                                            buttonLayout="horizontal" :min="0" style="width: 100%"
                                                            :inputStyle="{
                                                                padding: '0px',
                                                                width: '50px',
                                                                coloxxr: data.audited_quantity > data.quantity ? '#00d200' : data.audited_quantity === data.quantity ? 'gray' : 'red'
                                                            }" />
                                                    </div>
                                                    <!-- Button: Plus -->
                                                    <div @click="async () => (
                                                            set(data, `${data.stored_item_audit_delta_id ? 'audited_quantity' : 'quantity'}`, (data.stored_item_audit_delta_id ? data.audited_quantity : data.quantity) + 1),
                                                            data.stored_item_audit_delta_id
                                                                ? debounceChangeQuantity(item.rowIndex, data.stored_item_audit_delta_id, get(data, `audited_quantity`, data.quantity))
                                                                : debounceStoreStoredItem(item.rowIndex, item.id, data.stored_item_id, get(data, `quantity`, data.quantity), data.stored_item_audit_id)
                                                        )"
                                                         type="tertiary" size="xs"
                                                         class="leading-4 cursor-pointer inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed min-w-max bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70 rounded px-1 py-1.5 text-xs justify-self-center">
                                                        <FontAwesomeIcon icon="fas fa-plus" class="" fixed-width
                                                                         aria-hidden="true" />
                                                    </div>
                                                </div>
                                                <div v-else @click="set(statesBoxEdit, `zzz${item.rowIndex}.${data.id}`, true)"
                                                     class="hover:bg-gray-200 text-gray-400 hover:text-gray-600 w-full flex justify-center items-center h-full cursor-pointer px-2 gap-x-1">
                                                    <span class="text-gray-600">
                                                        {{ !data.stored_item_audit_delta_id_id ? data.quantity : data.audited_quantity }}
                                                    </span>
                                                    <FontAwesomeIcon v-tooltip="trans('Edit')" icon="fal fa-pencil" size="sm" class=""
                                                                     fixed-width aria-hidden="true" />
                                                </div>
                                            </transition>
                                        </div>
                                    </template>

                                    <!-- Button: Reset -->
                                    <Button
                                        v-if="get(data, ['is_edit'], false)"
                                        @click="set(data, ['is_edit'], false)"
                                        @clicccck="() => onUnselectNewStoredItem(item.rowIndex, data.stored_item_audit_delta_id)"
                                        v-tooltip="trans('Close input')"
                                        type="tertiary"
                                        icon="fal fa-undo-alt"
                                        class="border-none rounded-none text-gray-500"
                                        :loading="!!get(isLoadingUnselect, [item.rowIndex, data.stored_item_audit_delta_id], false)"
                                    />

                                    <Button
                                        v-else-if="   data.stored_item_audit_delta_id"
                                        @click="() => onUnselectNewStoredItem(item.rowIndex, data.stored_item_audit_delta_id)"
                                        v-tooltip="trans('Reset to original')"
                                        type="tertiary"
                                        icon="fal fa-undo"
                                        class="border-none rounded-none"
                                        :loading="!!get(isLoadingUnselect, [item.rowIndex, data.storedItemAuditDelta], false)"
                                    />
                                </div>

                                <LoadingIcon v-if="get(isLoadingQuantity, [item.rowIndex, data.stored_item_audit_delta_id], false) || get(isLoadingUnselect, [item.rowIndex, data.stored_item_audit_delta_id], false)"
                                             class="-ml-1 text-xs" />

                                <!-- {{ isStoredItemEdited }} -->

                                <!-- <FontAwesomeIcon v-tooltip="trans('Close')"
                                    @click="() => set(statesBoxEdit, `${item.rowIndex}.${data.id}`, false)"
                                    icon='fal fa-arrow-to-left'
                                    class='py-1 px-1 transition-all cursor-pointer text-gray-400 hover:text-gray-700'
                                    :class="get(statesBoxEdit, `${item.rowIndex}.${data.id}`, false) ? '' : 'hidden'"
                                    fixed-width aria-hidden='true' /> -->
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- <Column field="quantity" header="Checked" class="text-right"> -->
                <template #body="{ data }">
                    <template v-if="data.type === 'new_item'">
                        <div v-if="get(isLoadingUnselect, [item.rowIndex, data.stored_item_audit_delta_id], false)"
                             class="text-center text-red-500">
                            <LoadingIcon class="" />
                        </div>
                        <div v-else @click="() => onUnselectNewStoredItem(item.rowIndex, data.stored_item_audit_delta_id)"
                             class="text-red-500 hover:underline cursor-pointer">
                            {{ trans("Unselect") }}
                        </div>
                    </template>
                    <div v-else @click="onCheck(proxyItem.auditRoute, data.id, data.quantity)"
                         class="mx-auto cursor-pointer w-fit py-0.5 px-3 text-gray-500 hover:text-green-500">
                        <FontAwesomeIcon icon="fad fa-check-circle" class="" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <!-- </Column> -->

                <!-- <Column field="quantity" header="">
            <template #body="{ data }">
            </template>
        </Column> -->
            </DataTable>

            <div v-else class="text-gray-400">

            </div>

        </template>

        <template #cell(actions)="{ item }">
            <StoredItemsProperty
                :pallet="item"
                :storedItemsRoute
                :editable="true"
                :saveRoute="route_list.stored_item_audit_delta.store"
                class="mt-2"
                :sendToServerOptions="{
                    preserveScroll: true,
                    preserveState: true,
                }"
            >
                <template #default="{ openModal }">
                    <Button @click="openModal" type="dashed" icon="fas fa-plus" fuxll
                            :label="trans('Customer\'s SKU')" />
                </template>

                <template #modal="{ form, sendToServer, closeModal }">
                    <CreateStoredItems :storedItemsRoute="storedItemsRoute" :form="form" @onSave="() => sendToServer(
                        {
                            pallet_id: item.id,
                            stored_item_id: form.id,
                            audited_quantity: form.quantity,
                        },
                        true
                    )" :stored_items="item.stored_items" @closeModal="closeModal" title="Add Customer's SKU" />
                </template>
            </StoredItemsProperty>
        </template>

        <!-- Column: edited -->
        <template #cell(audits)="{ item }">

            <!-- <StoredItemsProperty :pallet="item" :storedItemsRoute="storedItemsRoute" :editable="true"
        :saveRoute="item.auditRoute" /> -->

            <!-- <div v-if="item.audited_at" class="flex items-center justify-center">
        <font-awesome-icon :icon="['fas', 'check-circle']" class="text-lg text-green-500 mr-2"
            v-tooltip="`Audited at: ${useFormatTime(item.audited_at)}`" />

        <Popover>
            <template #button="{ isOpen }">
                <Button label="Undo" type="tertiary" size="xs" icon="fal fa-history" />
            </template>

<template #content="{ open, close }">
                <div class="font-bold text-xs mb-3">are you sure to undo the Audit ?</div>
                <div class="flex justify-end gap-1">
                    <Button label="No" type="tertiary" size="xs" @click="close()" />
                    <Link :href="route(item.resetAuditRoute.name, item.resetAuditRoute.parameters)"
                        method="delete" type="button" preserve-scroll>
                    <Button label="Yes" size="xs" />
                    </Link>
                </div>
            </template>
</Popover>


</div> -->
        </template>
    </Table>
</template>
