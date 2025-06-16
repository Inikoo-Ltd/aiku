<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from "@/types/route"
import { faStickyNote, faCheck, faCheckCircle as falCheckCircle, faUndo, faArrowToLeft, faTrashAlt, faUndoAlt, faArrowAltToLeft } from '@fal'
import { faCheckCircle } from '@fad'
import { faEdit } from '@far'
import { faPlus, faMinus, faStar, faCheckCircle as fasCheckCircle } from '@fas'
import { reactive, ref } from 'vue'
import { Table as TableTS } from '@/types/Table'
import TableStoredItemAuditDeltas from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemAuditDeltas.vue'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { debounce, get, set } from 'lodash-es'
import { notify } from '@kyvg/vue3-notification'
import InputNumber from 'primevue/inputnumber'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import CreateStoredItems from '@/Components/CreateStoredItems.vue'
import StoredItemsProperty from '@/Components/StoredItemsProperty.vue'
import BoxAuditStoredItems from '@/Components/Box/BoxAuditStoredItems.vue'

library.add(faEdit, faStickyNote, faCheck, faPlus, faMinus, falCheckCircle, faUndo, faArrowToLeft, faTrashAlt, faUndoAlt, faArrowAltToLeft, faCheckCircle, faStar, fasCheckCircle)

const props = defineProps<{
    data: {
        data: {}
    }
    title: string
    pageHead: PageHeadingTypes
    notes_data: any
    stored_item_deltas: TableTS
    fulfilment_customer: any
    route_list: {
        update: routeType
        stored_item_audit_delta: {
            update: routeType  // Update quantity
            store: routeType  // add new stored item
            delete: routeType  // undo select
        }
    }
    storedItemsRoute: {
        index: routeType  // Fetch list of stored items
        store: routeType  // Add stored items
        delete: routeType  // Delete stored items
    }
    editDeltas: {
        stored_items: {
            stored_item_audit_id: number
            stored_item_audit_delta_id: number
            stored_item_id: number
            reference: string
            quantity: number
            audited_quantity: number
            audit_type: string
            is_edit: boolean
        }[]
    }
    pallet: {
        data: {}
    }
}>()

console.log('prooooo', props)

interface StoredItemsQuantity {
    [key: string]: number  // stored item id
}


// Unselect Audit Deltas
const isLoadingUnselect = reactive<StoredItemsQuantity>({})
const onUnselectNewStoredItem = (store_item_audit_deltas_id: number) => {
    props.route_list.stored_item_audit_delta.delete
    router.delete(
        route(props.route_list.stored_item_audit_delta.delete.name, store_item_audit_deltas_id),
        {
            onStart: () => {
                set(isLoadingUnselect, `${store_item_audit_deltas_id}`, true)
            },
            onFinish: () => {
                set(isLoadingUnselect, `${store_item_audit_deltas_id}`, false)
            },
            preserveScroll: true,
        }
    )
}


// Section: store quantity stored item (first update)
const isLoadingStoreQuantity = reactive<StoredItemsQuantity>({})
const onStoreStoredItem = (idStoredItem: number, quantity: number, idStoredItemAudit: number) => {
    console.log('onStoreStoredItem', idStoredItem, props.route_list?.stored_item_audit_delta?.store?.name)

    if (!props.route_list?.stored_item_audit_delta?.store?.name) {
        console.error('No id stored item audit')
        return
    }

    router.post(
        route(props.route_list?.stored_item_audit_delta?.store.name, idStoredItemAudit),
        {
            pallet_id: props.data?.data?.scope_id,
            stored_item_id: idStoredItem,
            audited_quantity: quantity
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                set(isLoadingStoreQuantity, `${idStoredItem}`, true)
            },
            onError: (e) => {
                    console.error(e)
                    notify({
                        title: trans('Something went wrong.'),
                        text: trans('Failed to update the quantity.'),
                        type: 'error',
                    })
            },
            onFinish: () => {
                set(isLoadingStoreQuantity, `${idStoredItem}`, false)
            }
        }
    )
}
const debounceStoreStoredItem = debounce((idStoredItem: number, quantity: number, idStoredItemAudit: number) => onStoreStoredItem(idStoredItem, quantity, idStoredItemAudit), 500)



// Section: Update quantity stored item
const isStoredItemEdited = reactive<StoredItemsQuantity>({})
const isLoadingQuantity = reactive<StoredItemsQuantity>({})
const onChangeQuantity = (idStoredItemAuditDelta: number | null, quantity: number) => {
    console.log('onChangeQuantity')
    //todo if   "store_item_audit_delta_id" ==null,
    // use props.route_list?.stored_item_audit_delta?.store.name
    // get back the store_item_audit_delta_id and set it uo so next time you call update
    
    // Update
    console.log('lolo', props.route_list?.stored_item_audit_delta?.update?.name)
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
                set(isLoadingQuantity, `${idStoredItemAuditDelta}`, true)
            },
            onError: (e) => {
                    console.error(e)
                    notify({
                        title: trans('Something went wrong.'),
                        text: trans('Failed to update the quantity.'),
                        type: 'error',
                    })
            },
            onFinish: () => {
                set(isLoadingQuantity, `${idStoredItemAuditDelta}`, false)
                set(isStoredItemEdited, [`id${idStoredItemAuditDelta?.toString()}`], true)
                // isStoredItemEdited["1"]["256"] = true
            }
        }
    )
}
const debounceChangeQuantity = debounce((idStoredItemAuditDelta: number, quantity: number) => onChangeQuantity(idStoredItemAuditDelta, quantity), 500)


const statesBoxEdit = reactive<StoredItemsQuantity>({})

const edit_block = (audit_type: string, is_edit: boolean, keep_is_edit: boolean) => {
    return keep_is_edit ? 'edit' : audit_type === 'no_change' ? 'checked' : audit_type || is_edit ? 'edit' : false
}

const isModalOpened = ref(false)
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-add-sku="{ }">
            <!-- <Button
                :label="trans('Add SKU')"
                icon="fal fa-plus"
                type="secondary"
            /> -->

            <StoredItemsProperty
                :pallet="pallet?.data"
                :storedItemsRoute
                :editable="true"
                :saveRoute="route_list.stored_item_audit_delta.store"
                :sendToServerOptions="{
                    preserveScroll: true,
                    preserveState: true,
                }"
                :isModalOpened
                @onCloseModal="() => isModalOpened = false"
                @onSuccessSubmit="() => isModalOpened = false"
            >
                <template #default="{ openModal }">
                    <Button @click="openModal" type="dashed" icon="fas fa-plus" fuxll
                        :label="trans('Customer\'s SKU')" />
                </template>

                <template #modal="{ form, sendToServer, closeModal }">
                    <!-- <pre>{{ item.id }}</pre> -->
                    <CreateStoredItems :storedItemsRoute="storedItemsRoute" :form="form" @onSave="() => sendToServer(
                        {
                            pallet_id: pallet?.data?.id,
                            stored_item_id: form.id,
                            audited_quantity: form.quantity,
                        },
                        true
                    )" :stored_items="pallet?.data?.stored_items" @closeModal="closeModal" :title="trans('Add Customer\'s SKU')" />
                </template>
            </StoredItemsProperty>
        </template>
    </PageHeading>

    <!-- <div class="grid grid-cols-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
        <BoxNote
            v-for="(note, index) in notes_data"
            :key="index + note.label"
            :noteData="note"
            :updateRoute="route_list.update"
        />
    </div> -->

    <BoxAuditStoredItems isHidePalletCount :auditData="data.data" :boxStats="fulfilment_customer" />
    
    <TableStoredItemAuditDeltas
        v-if="stored_item_deltas"
        :data="stored_item_deltas"
        tab="stored_item_deltas"
    />

    <DataTable v-if="editDeltas"
        :value="[...editDeltas.stored_items, ...editDeltas.new_stored_items]">
        <Column field="reference" :header="trans('SKU')" class="">
            <template #body="{ data }">
                <div class="whitespace-nowrap">{{ data.reference }}
                    <FontAwesomeIcon v-if="data.type === 'new_item'" v-tooltip="trans('New added Customer\'s SKU')" icon='fas fa-star' size="xs" class='text-indigo-500' fixed-width aria-hidden='true' />
                </div>
            </template>
        </Column>

        <Column field="quantity" header="Current qty" sortable class="">
            <template #body="{ data }">
                <div class="text-right">{{ data.quantity || '' }}</div>
            </template>
        </Column>

        <Column field="action" header="Actions" class="">
            <template #body="{ data }">
                <!-- <pre>{{ data }}</pre>
                stored_item_audit_id: {{ data.stored_item_audit_id || '-' }} <br />
                stored_item_id: {{ data.stored_item_id || '-' }} <br />
                audit_type: {{ data.audit_type || '-' }} <br />
                stored_item_audit_delta_id: {{ data.stored_item_audit_delta_id || '-' }} <br /> -->
                <!-- edit_block: {{ edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [`id${data.stored_item_audit_delta_id?.toString()}`], false)) }} -->

                <!-- <pre>{{ props.route_list?.stored_item_audit_delta?.store }}</pre>
                <pre>{{ data }}</pre> -->
                <div class="relative">
                    <!-- <div 
                        class="z-10 opacity-60 absolute w-full h-full top-0 left-0">
                        <div class="skeleton h-full w-full"></div>
                    </div> -->


                    <div class="flex gap-x-2.5 items-center w-64">
                        <!-- Check green -->
                        <div v-if="data.audit_type === 'no_change' && edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [`id${data.stored_item_audit_delta_id?.toString()}`], false)) != 'edit'">
                            <Button
                                type="tertiary"
                                icon="fas fa-check-circle"
                                class="border-none rounded-none text-green-500 cursor-auto hover:bg-transparent -mx-2"
                            />
                        </div>

                        <div class="flex justify-center border border-gray-300 rounded gap-y-1">
                            <!-- Button: Check -->
                            <Button v-if="data.type !== 'new_item' && data.audit_type !== 'no_change' && data.audit_type !== 'addition' && !get(data, ['is_edit'], false) && edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [`id${data.stored_item_audit_delta_id?.toString()}`], false)) != 'edit'"
                                @click="() => data.audit_type === 'no_change' ? null : onStoreStoredItem(data.stored_item_id, data.quantity, data.stored_item_audit_id)"
                                type="tertiary"
                                :icon="data.audit_type === 'no_change' ? 'fas fa-check-circle' : 'fal fa-check-circle'"
                                class="border-none rounded-none"
                                :class="data.audit_type === 'no_change' ? 'text-green-500 hover:cursor-auto' : ''"
                                :disabled="data.audit_type === 'no_change'"
                            />

                            <!-- Edit button -->
                            <div v-if="!data.stored_item_audit_delta_id && edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [`id${data.stored_item_audit_delta_id?.toString()}`], false)) != 'edit'" @click="() => set(data, ['is_edit'], !(get(data, ['is_edit'], false)))" class="px-2 flex items-center hover:bg-gray-200 cursor-pointer">
                                <FontAwesomeIcon icon='far fa-edit' class='' fixed-width aria-hidden='true' />
                            </div>
                            
                            <!-- Section: - and + -->
                            <template v-if=" (!data.stored_item_audit_delta_id && get(data, ['is_edit'], false)) || (data.audit_type && data.audit_type != 'no_change') || edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [`id${data.stored_item_audit_delta_id?.toString()}`], false)) == 'edit'">
                                <div class="transition-all relative inline-flex items-center justify-center "
                                    :class="!get('statesBoxEdit', `${data.id}`, false) ? 'w-28' : 'w-14'">
                                    <transition>
                                        <div v-if="!get('statesBoxEdit', `${data.id}`, false)"
                                            class="relative flex flex-nowrap items-center justify-center gap-y-1 gap-x-1">
                                            <!-- Button: Minus -->
                                            <div  @click="async () => (
                                                    set(data, `${data.stored_item_audit_delta_id ? 'audited_quantity' : 'new_quantity'}`, ((data.stored_item_audit_delta_id ? data.audited_quantity : (data.new_quantity ?? data.quantity)) - 1) >= 0 ? ((data.stored_item_audit_delta_id ? data.audited_quantity : (data.new_quantity ?? data.quantity)) - 1) : 0),
                                                    data.stored_item_audit_delta_id
                                                        ? debounceChangeQuantity(data.stored_item_audit_delta_id, get(data, `audited_quantity`, data.quantity))
                                                        : debounceStoreStoredItem(data.stored_item_id, get(data, `new_quantity`, data.quantity), data.stored_item_audit_id)
                                                    
                                                )"
                                                class="leading-4 cursor-pointer inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed min-w-max bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70 rounded px-1 py-1.5 text-xs justify-self-center">
                                                <FontAwesomeIcon icon='fas fa-minus' class='' fixed-width aria-hidden='true' />
                                            </div>
                                            
                                            <div class="text-center tabular-nums border border-transparent hover:border-dashed hover:border-gray-300 group-focus:border-dashed group-focus:border-gray-300">
                                                <!-- {{ data.audited_quantity }}
                                                {{ data.new_quantity }}
                                                {{ data.quantity }} -->
                                                <InputNumber
                                                    :modelValue="data.stored_item_audit_delta_id ? data.audited_quantity : (data.new_quantity ?? data.quantity) ||   edit_block(data.audit_type, data.is_edit, !!get(isStoredItemEdited, [`id${data.stored_item_audit_delta_id?.toString()}`], false)) =='edit'  "
                                                    @update:modelValue="(e) => (
                                                        set(data, `${data.stored_item_audit_delta_id ? 'audited_quantity' : 'new_quantity'}`, e),
                                                        data.stored_item_audit_delta_id
                                                            ? debounceChangeQuantity(data.stored_item_audit_delta_id, e)
                                                            : debounceStoreStoredItem(data.stored_item_id, e, data.stored_item_audit_id)
                                                    )"
                                                    buttonLayout="horizontal" :min="0" style="width: 100%"
                                                    :inputStyle="{
                                                        padding: '0px',
                                                        width: '50px',
                                                        coloxxr: data.audited_quantity > data.quantity ? '#00d200' : data.audited_quantity === data.quantity ? 'gray' : 'red'
                                                    }" />
                                            </div>
                                            
                                            <!-- Button: Plus -->
                                            <div  @click="async () => (
                                                set(data, `${data.stored_item_audit_delta_id ? 'audited_quantity' : 'new_quantity'}`, (data.stored_item_audit_delta_id ? data.audited_quantity : (data.new_quantity ?? data.quantity)) + 1),
                                                data.stored_item_audit_delta_id
                                                    ? debounceChangeQuantity(data.stored_item_audit_delta_id, get(data, `audited_quantity`, (data.new_quantity ?? data.quantity)))
                                                    : debounceStoreStoredItem(data.stored_item_id, get(data, `new_quantity`, data.quantity), data.stored_item_audit_id)
                                            )"
                                                type="tertiary" size="xs"
                                                class="leading-4 cursor-pointer inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed min-w-max bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70 rounded px-1 py-1.5 text-xs justify-self-center">
                                                <FontAwesomeIcon icon='fas fa-plus' class='' fixed-width
                                                    aria-hidden='true' />
                                            </div>
                                        </div>
                                        <div v-else @click="set(statesBoxEdit, `zzz${data.id}`, true)"
                                            class="hover:bg-gray-200 text-gray-400 hover:text-gray-600 w-full flex justify-center items-center h-full cursor-pointer px-2 gap-x-1">
                                            <span class="text-gray-600">
                                                {{!data.stored_item_audit_delta_id_id ?  data.quantity :  data.audited_quantity }}
                                            </span>
                                            <FontAwesomeIcon v-tooltip="trans('Edit')" icon='fal fa-pencil' size="sm" class=''
                                                fixed-width aria-hidden='true' />
                                        </div>
                                    </transition>
                                </div>
                            </template>

                            <!-- Button: Reset -->
                            <Button
                                v-if="get(data, ['is_edit'], false)"
                                @click="set(data, ['is_edit'], false)"
                                @clicccck="() => onUnselectNewStoredItem(data.stored_item_audit_delta_id)"
                                v-tooltip="trans('Close input')"
                                type="tertiary"
                                
                                class="border-none rounded-none"
                                :loading="!!get(isLoadingUnselect, [data.stored_item_audit_delta_id], false)"
                            >
                                <template #icon>
                                    <FontAwesomeIcon icon="fal fa-arrow-alt-to-left" fixed-width class="text-gray-500" aria-hidden="true"/>
                                </template>
                            </Button>

                            <Button
                                v-else-if="   data.stored_item_audit_delta_id"
                                @click="() => onUnselectNewStoredItem(data.stored_item_audit_delta_id)"
                                v-tooltip="trans('Reset to original')"
                                type="tertiary"
                                icon="fal fa-undo"
                                class="border-none rounded-none text-red-500"
                                :loading="!!get(isLoadingUnselect, [data.storedItemAuditDelta], false)"
                            />
                        </div>

                        <LoadingIcon v-if="get(isLoadingQuantity, [data.stored_item_audit_delta_id], false) || get(isLoadingUnselect, [data.stored_item_audit_delta_id], false)" class="-ml-1 text-xs" />

                        <!-- {{ isStoredItemEdited }} -->

                        <!-- <FontAwesomeIcon v-tooltip="trans('Close')"
                            @click="() => set(statesBoxEdit, `${data.id}`, false)"
                            icon='fal fa-arrow-to-left'
                            class='py-1 px-1 transition-all cursor-pointer text-gray-400 hover:text-gray-700'
                            :class="get(statesBoxEdit, `${data.id}`, false) ? '' : 'hidden'"
                            fixed-width aria-hidden='true' /> -->
                    </div>
                </div>
            </template>
        </Column>

        <!-- <Column field="quantity" header="Checked" class="text-right"> -->
        <!-- <template #xxbody="{ data }">
            <template v-if="data.type === 'new_item'">
                <div v-if="get(isLoadingUnselect, [data.stored_item_audit_delta_id], false)"
                    class="text-center text-red-500">
                    <LoadingIcon class="" />
                </div>
                <div v-else @click="() => onUnselectNewStoredItem(data.stored_item_audit_delta_id)"
                    class="text-red-500 hover:underline cursor-pointer">
                    {{ trans("Unselect") }}
                </div>
            </template>
            <div v-else @click="onCheck(proxyItem.auditRoute, data.id, data.quantity)"
                class="mx-auto cursor-pointer w-fit py-0.5 px-3 text-gray-500 hover:text-green-500">
                <FontAwesomeIcon icon='fad fa-check-circle' class='' fixed-width aria-hidden='true' />
            </div>
        </template> -->
        <!-- </Column> -->

        <!-- <Column field="quantity" header="">
    <template #body="{ data }">
    </template>
</Column> -->
    </DataTable>

    <div v-if="editDeltas" class="mx-auto px-4 w-10/12 mt-4">
        <Button @click="isModalOpened = true" type="dashed" icon="fas fa-plus" full :label="trans('Customer\'s SKU')" />
    </div>


    <!-- <pre>{{ pallet }}</pre> -->

    <!-- <div v-else class="text-gray-400">

    </div> -->
</template>

<style scoped>
:deep(.p-inputtext) {
    padding: 0.5rem;
    font-size: 0.875rem;
    border: 1px solid transparent;
    background-color: transparent;
    border-radius: 0;
    box-shadow: 0;
    text-align: center;
}

:deep(.p-inputtext:enabled:hover) {
    border: 1px solid transparent;
}

:deep(.p-inputtext:enabled:focus) {
    border: 1px solid transparent;
    border-bottom: 1px solid rgb(192, 192, 192);
}
</style>