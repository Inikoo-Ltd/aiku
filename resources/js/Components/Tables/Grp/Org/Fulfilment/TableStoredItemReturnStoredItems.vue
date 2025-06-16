<script setup lang="ts">
import Table from '@/Components/Table/Table.vue';
import Icon from "@/Components/Icon.vue";
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue';
import { ref, watch, onBeforeMount,reactive, inject, onMounted} from 'vue';
import { notify } from "@kyvg/vue3-notification";
import { debounce, set, get } from 'lodash-es';
import { Link, router } from "@inertiajs/vue3"
import Popover from '@/Components/Popover.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import Tag from '@/Components/Tag.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faUndoAlt, faArrowDown } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import { Collapse } from 'vue-collapsed'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import axios from 'axios'
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
library.add(faCheck, faUndoAlt, faArrowDown)

const props = defineProps<{
    data?: { data: any[] };
    tab?: string;
    state: any;
    key: any;
    route_checkmark: routeType;
    palletReturn: {
        id: number
        state: string
    }
}>();

const emits = defineEmits<{
    (e: 'isStoredItemAdded', value: boolean): void
}>()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', layoutStructure)
const selectedRow = ref({});
const _table = ref(null);
const isPickingLoading = ref(false)
const isUndoLoading = ref(false)
const listStatusNotPicked = [
    {
        label: trans('Damaged'),
        value: 'damaged'
    },
    {
        label: trans('Lost'),
        value: 'lost'
    },
    {
        label: trans('Other incident'),
        value: 'other_incident'
    }
]
const selectedStatusNotPicked = reactive({
    status: 'other_incident',
    notes: ''
})
const errorNotPicked = reactive({
    status: null,
    notes: null
})
const isSubmitNotPickedLoading = ref<boolean | number>(false)

const onSubmitNotPicked = async (idPallet: number, closePopup: Function, routeNotPicked: routeType) => {
    isSubmitNotPickedLoading.value = idPallet
    router[routeNotPicked.method || 'get'](route(routeNotPicked.name, routeNotPicked.parameters), {
        state: selectedStatusNotPicked.status,
        notes: selectedStatusNotPicked.notes
    }, {
        onSuccess: () => {
            selectedStatusNotPicked.status = 'other'
            selectedStatusNotPicked.notes = ''
            errorNotPicked.status = null
            errorNotPicked.notes = null
            closePopup()
        },
        onError: (error: {}) => {
            console.error('hehehe', error)
        },
        onFinish: () => {
            isSubmitNotPickedLoading.value = false
        }
    })
}

const setUpChecked = () => {
    const set: Record<string, boolean> = {};
    if (props.data?.data) {
        props.data.data.forEach((item) => {
            set[item.id] = item.is_checked || false;
        });
        selectedRow.value = set;
    }
};

const SetSelected = () => {
    const data = props.data?.data || [];
    const finalValue: Record<string, { quantity: number }> = {};

    for(const key in selectedRow.value){
        if (selectedRow.value[key]) {
            const tempData = data.find((item) => item.id == key);
            if (tempData) {
                finalValue[key] = { quantity: tempData.quantity };
            }
        }
    }

    router.post(
        route(props.route_checkmark.name, props.route_checkmark.parameters),
        { stored_items: finalValue },
        {
            preserveScroll: true,
            onSuccess: () => {},
            onError: (e) => {
                console.log('Failed to save', e);
                notify({
                    title: 'Something went wrong.',
                    text: 'Failed to save',
                    type: 'error',
                });
            },
        }
    );
};

const onChangeCheked = (value) => {
    selectedRow.value = value;
    SetSelected();
}

// Debounce the changeValueQty function
const changeValueQty = debounce(() => {
    SetSelected();
}, 1000);

/* watch(selectedRow, () => {
    SetSelected();
}, { deep: true }); */

onBeforeMount(() => {
    setUpChecked();
});

const isMounted = ref(false)
onMounted(() => {
    isMounted.value = true
})

const generateLinkReference = (reference: any) => {
    if (!reference.slug) {
        return null
    }

    switch (route().current()) {
        case 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.stored-items.show',
                {
                    organisation: route().params['organisation'],
                    fulfilment: route().params['fulfilment'],
                    fulfilmentCustomer: route().params['fulfilmentCustomer'],
                    storedItem: reference.slug,
                });
        default:
            null
    }
}

const generateLinkPalletLocation = (pallet: any) => {
    if (!pallet.reference) {
        return null
    }

    switch (route().current()) {
        case 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                {
                    organisation: route().params['organisation'],
                    fulfilment: route().params['fulfilment'],
                    fulfilmentCustomer: route().params['fulfilmentCustomer'],
                    pallet: pallet.reference,
                });
        default:
            null
    }
}

// Button: undo pick
const isLoadingUndoPick = reactive({})
const onUndoPick = async (routeTarget: routeType, pallet_stored_item: any, loadingKey: string) => {
    try {
        pallet_stored_item.isLoadingUndo = true
        set(isLoadingUndoPick, loadingKey, true)
        await axios[routeTarget.method || 'get'](
            route(routeTarget.name, routeTarget.parameters)
        )
        pallet_stored_item.state = 'picking'
        // console.log('qqqqq', pallet_stored_item)
    } catch (error) {
        console.error('hehehe', error)
        
    } finally {
        set(isLoadingUndoPick, loadingKey, false)
    }
    
}
</script>

<template>
    <!-- {{ selectedRow }} -->
    <!-- <pre>{{ palletReturn.state }}</pre> -->
    <Table :resource="data" :name="'stored_items'" class="mt-5" :xxisCheckBox="state == 'in_process' ? true : false"
        @onSelectRow="onChangeCheked" ref="_table" :selectedRow="selectedRow">
        
        <!-- Column: Type icon -->
        <template #cell(type_icon)="{ item: value }">
            <Icon :data="value['type_icon']" class="px-1" />
        </template>
        
        <!-- Column: Reference -->
        <template #cell(reference)="{ item: value }">
            <Link :href="generateLinkReference(value)" class="primaryLink">
                {{ value.reference }}
            </Link>
        </template>
        
        <!-- Column: Pallet of Stored items -->
        <template #cell(pallet_stored_items)="{ item: value, proxyItem }">
            <div class="grid gap-y-1">
                <template v-for="pallet_stored_item in value.pallet_stored_items" :key="pallet_stored_item.id">
                    <Teleport v-if="isMounted" :to="`#row-${value.id}`" :disabled="palletReturn.state == 'in_process' || pallet_stored_item.selected_quantity > 0">
                        <div class="rounded p-1 flex justify-between gap-x-6 items-center">
                            <!-- <Tag :label="pallet_stored_item.reference" stringToColor>
                                <template #label>
                                    <div class="">
                                        {{ pallet_stored_item.reference }} ({{ pallet_stored_item.quantity }})
                                    </div>
                                </template>
                            </Tag> -->

                            <!-- Pallet name -->
                            <div class="">
                                <span v-if="pallet_stored_item.reference">
                                    <Link :href="generateLinkPalletLocation(pallet_stored_item)" class="secondaryLink">
                                        {{ pallet_stored_item.reference }}
                                    </Link>
                                </span>
                                <span v-else class="text-gray-400 italic">({{ trans('No reference') }})</span>
                                <span v-if="pallet_stored_item.location?.code" v-tooltip="trans('Location code of the pallet')" class="text-gray-400"> [{{ pallet_stored_item.location?.code }}]</span>
                                <div  v-if="pallet_stored_item.selected_quantity && palletReturn.state === 'in_process'" v-tooltip="trans('Will be picked')" class="pl-1 pb-1 inline" >
                                    <FontAwesomeIcon icon='fas fa-circle' class='text-[7px] text-blue-500 animate-pulse' fixed-width aria-hidden='true' />
                                </div>
                                <div v-if="palletReturn.state === 'picking'"
                                    @xxclick="() => pallet_stored_item.picked_quantity = pallet_stored_item.quantity_in_pallet"
                                    v-tooltip="trans('Total Customer\'s SKU in this pallet')"
                                    class="text-gray-400 tabular-nums xcursor-pointer xhover:text-gray-600">
                                    {{ trans("Stocks in pallet") }}: {{ pallet_stored_item.quantity_in_pallet }}
                                </div>
                            </div>
    
                            <div class="flex items-center flex-nowrap gap-x-2">
                                <!-- {{ state === 'picked' || state === 'dispatched' }} -->
                                <ModalConfirmation
                                    v-if="pallet_stored_item.all_items_returned && (state === 'picked' || state === 'dispatched') && !pallet_stored_item.is_pallet_returned"
                                    :routeYes="{
                                        name: 'grp.models.pallet.return',
                                        parameters: {
                                            pallet: pallet_stored_item.pallet_id
                                        },
                                        method: 'patch'
                                    }"
                                    :title="trans(`Return pallet ${pallet_stored_item.reference} to customer?`)"
                                    :description="trans(`The pallet ${pallet_stored_item.reference} will be set as returned to the customer, and no longer exist in warehouse. This action cannot be reverse.`)"
                                >
                                    <template #default="{ changeModel }">
                                        <Button
                                            @click="() => changeModel()"
                                            :label="trans('Return pallet')"
                                            size="xs"
                                        />
                                    </template>

                                    <template #btn-yes="{ isLoadingdelete, clickYes}">
                                        <Button
                                            :loading="isLoadingdelete"
                                            @click="() => clickYes()"
                                            :label="trans('Yes, return the pallet')"
                                        />
                                    </template>
                                </ModalConfirmation>

                                <Tag
                                    v-if="pallet_stored_item.is_pallet_returned"
                                    v-tooltip="trans('Pallet was returned to customer')"
                                    :label="trans('Pallet returned')"
                                    :theme="8"
                                    size="xs"
                                    noHoverColor
                                />

                                <div v-if="palletReturn.state === 'in_process'" v-tooltip="trans('Available quantity')" class="text-base">{{ pallet_stored_item.available_quantity }}</div>
                                <!-- <div v-else-if="palletReturn.state === 'picking'" v-tooltip="trans('Quantity of Customer\'s SKU that should be picked')" class="text-base">{{ pallet_stored_item.selected_quantity }}</div> -->
    
                                <!-- Button: input number (in_process) -->
                                <NumberWithButtonSave
                                    v-if="palletReturn.state === 'in_process'"
                                    key="in_process"
                                    noUndoButton
                                    isUseAxios
                                    @onSuccess="(newVal: number, oldVal: number) => {
                                        proxyItem.total_quantity_ordered += newVal - oldVal
                                        pallet_stored_item.selected_quantity = newVal
                                        emits('isStoredItemAdded', newVal > 0 ? true : false)
                                        router.reload({
                                            only: ['pageHead'],
                                        })
                                    }"
                                    :modelValue="pallet_stored_item.selected_quantity"
                                    saveOnForm
                                    :routeSubmit="{
                                        name: pallet_stored_item.syncRoute.name,
                                        parameters: {
                                            ...pallet_stored_item.syncRoute.parameters,
                                            palletReturn: palletReturn.id
                                        },
                                        method: pallet_stored_item.syncRoute.method
                                    }"
                                    keySubmit="quantity_ordered"
                                    :bindToTarget="{
                                        step: 1,
                                        min: 0,
                                        max: pallet_stored_item.max_quantity
                                    }"
                                >
                                </NumberWithButtonSave>

                                <div v-else-if="palletReturn.state === 'submitted' || palletReturn.state === 'confirmed'" class="flex flex-nowrap gap-x-1 items-center">
                                    {{ locale.number(pallet_stored_item.selected_quantity) }}
                                </div>
    
                                <!-- Button: input number (picking) -->
                                <template v-else-if="palletReturn.state === 'picking' && pallet_stored_item.state !== 'picked'">
                                    <div>
                                        <!-- Not isUseAxios due timeline state is not auto updated -->
                                        <NumberWithButtonSave
                                            key="pickingpicked"
                                            noUndoButton
                                            xisUseAxios
                                            @xonSuccess="(newVal: number, oldVal: number) => {
                                                pallet_stored_item.state = 'picked',
                                                pallet_stored_item.picked_quantity = newVal
                                            }"
                                            @xxonError="(error: any) => {
                                                pallet_stored_item.error = error.message
                                            }"
                                            :modelValue="pallet_stored_item.selected_quantity"
                                            saveOnForm
                                            :routeSubmit="
                                                pallet_stored_item.pallet_return_item_id
                                                    ? pallet_stored_item.updateRoute
                                                    : pallet_stored_item.newPickRoute
                                            "
                                            :keySubmit="
                                                pallet_stored_item.pallet_return_item_id
                                                    ? 'quantity_picked'
                                                    : 'quantity_ordered'
                                            "
                                            :bindToTarget="{
                                                step: 1,
                                                min: 0,
                                                max: pallet_stored_item.max_quantity
                                            }"
                                            :xxcolorTheme="
                                                pallet_stored_item.selected_quantity == pallet_stored_item.picked_quantity
                                                    ? '#374151'
                                                    : pallet_stored_item.selected_quantity < pallet_stored_item.picked_quantity
                                                        ? '#22c55e'
                                                        : '#ff0000'
                                            "
                                            :xxparentClass="''
                                                // pallet_stored_item.error ? 'errorShake' : ''
                                            "
                                        >
                                            <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                                                <Button
                                                    v-if="pallet_stored_item.selected_quantity > 0"
                                                    @click="() => (
                                                        // pallet_stored_item.error = null,  // make slow a little bit
                                                        onSaveViaForm()
                                                    )"
                                                    icon="fal fa-save"
                                                    :label="trans('pick')"
                                                    size="xs"
                                                    :xdisabled="!isDirty"
                                                    type="secondary"
                                                    :loading="isProcessing"
                                                    class="py-0"
                                                />
                                            </template>
                                        </NumberWithButtonSave>
                                        <!-- <p v-if="pallet_stored_item.error" class="text-xs text-red-500 italic">*{{ pallet_stored_item.error }}</p> -->
                                    </div>
                                </template>
    
                                <div v-else class="flex flex-nowrap gap-x-1 items-center tabular-nums">
                                    <!-- <ButtonWithLink
                                        
                                        icon="fal fa-undo-alt"
                                        :label="trans('Undo pick')"
                                        size="xs"
                                        type="tertiary"
                                        :key="2"
                                        class="py-0 mr-1"
                                        :routeTarget="pallet_stored_item.undoRoute"
                                    /> -->
                                    <Button
                                        v-if="palletReturn.state == 'picking' && pallet_stored_item.state == 'picked'"
                                        @click="() => onUndoPick(pallet_stored_item.undoRoute, pallet_stored_item, `row${value.rowIndex}.id${pallet_stored_item.id}`)"
                                        icon="fal fa-undo-alt"
                                        :label="trans('Undo pick')"
                                        size="xs"
                                        :loading="get(isLoadingUndoPick, [`row${value.rowIndex}`, `id${pallet_stored_item.id}`], false)"
                                        type="tertiary"
                                    />
                                    {{ locale.number(pallet_stored_item.picked_quantity) }}/{{ locale.number(pallet_stored_item.selected_quantity) }}
                                    <FontAwesomeIcon v-if="pallet_stored_item.state == 'picked'" v-tooltip="trans('Picked')" icon='fal fa-check' class='text-green-500' fixed-width aria-hidden='true' />
                                </div>
    
                                
                            </div>
                            <!-- {{ get(isLoadingUndoPick, [`row${value.rowIndex}.id${pallet_stored_item.id}`], '000') }} --  -->
                            <!-- {{ pallet_stored_item.isLoadingUndo }} -->
                            
                        </div>
                    </Teleport>
                </template>

                <div v-if="!value.pallet_stored_items?.length" class="italic text-gray-400">
                    {{ trans('No pallet') }}
                </div>

                <!-- Section: area for pallet that have 0 selected quantity -->
                <div v-if="palletReturn.state != 'in_process'">
                    <Collapse as="section" :when="get(proxyItem, ['is_open_collapsed'], false)" class="">
                        <div :id="`row-${value.id}`">
                            <!-- Something will teleport here -->
                        </div>
                    </Collapse>
                    <div class="w-full mt-2">
                        <Button
                            v-if="!value.pallet_stored_items.every(val => {return val.selected_quantity > 0})" @click="() => set(proxyItem, ['is_open_collapsed'], !get(proxyItem, ['is_open_collapsed'], false))"
                            type="dashed"
                            full
                            size="sm"
                        >
                            <div class="py-1 text-gray-500">
                                <FontAwesomeIcon icon='fal fa-arrow-down' class="transition-all" :class="get(proxyItem, ['is_open_collapsed'], false) ? 'rotate-180' : ''" fixed-width aria-hidden='true' />
                                {{ get(proxyItem, ['is_open_collapsed'], false) ? 'Close' : 'Open hidden pallets' }}
                            </div>
                        </Button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Column: State -->
        <template #cell(state)="{ item: palletReturn }">
            <Icon :key="palletReturn['state_icon']?.icon" :data="palletReturn['state_icon']" class="px-1" />
        </template>

        <!-- Column: Quantity -->
        <template #cell(quantity)="{ item, proxyItem }">
            <div class="w-full flex justify-end">
                <div class="flex flex-col min-w-8 max-w-32">
                    <template v-if="state == 'in_process'">
                        <PureInputNumber
                            v-if="item.is_checked"
                            :modelValue="item.data.quantity"
                            :maxValue="item.total_quantity"
                            :minValue="1"
                            @update:modelValue="(e) => e ? (set(proxyItem, 'error_quantity', false), changeValueQty(e)) : set(proxyItem, 'error_quantity', true)"  
                        />
                        <PureInputNumber
                            v-else
                            :modelValue="0"
                            disabled
                            v-tooltip="trans('Check the row to edit')"
                        />

                        <p v-if="proxyItem.error_quantity" class="mt-1 text-left text-xs text-red-500 italic">*{{ trans('Quantity can\'t empty') }}</p>
                    </template>
                    
                    <div v-else class="py-3">{{ item.data.quantity }}</div>
                </div>
            </div>
        </template>

        <template #cell(total_quantity_ordered)="{ item }">
            <div class="">
                <Transition name="spin-to-right">
                    <span :key="item.total_quantity_ordered">{{ locale.number(item.total_quantity_ordered) }}</span>
                </Transition>
            </div>
        </template>

        <!-- Column: Actions -->
        <template #cell(actions)="{ item: pallet }" v-if="props.state == 'in_process' || props.state == 'picking'">
            <div v-if="props.state == 'picking' && layout.app.name == 'Aiku'" class="flex gap-x-2 relative">
                <Link v-if="pallet.state === 'picking'" as="div"
                    :href="route(pallet.updateRoute.name, pallet.updateRoute.parameters)"
                    :data="{ state: 'picked' }"
                    @start="() => isPickingLoading = pallet.id"
                    @finish="() => isPickingLoading = false"
                    method="patch"
                    v-tooltip="`Set as picked`"
                >
                    <Button icon="fal fa-check" type="positive" :loading="isPickingLoading === pallet.id" class="py-0" />
                </Link>

                <Link v-if="pallet.state === 'picked'" as="div"
                    :href="route(pallet.undoPickingRoute.name, pallet.undoPickingRoute.parameters)"
                    :data="{ state: 'picked' }"
                    @start="() => isUndoLoading = pallet.id"
                    @finish="() => isUndoLoading = false"
                    method="patch"
                    v-tooltip="`Undo`"
                >
                    <Button icon="fal fa-undo" label="Undo picking" type="tertiary" size="xs" :loading="isUndoLoading === pallet.id" class="py-0" />
                </Link>

                <div class="relative">
                <Popover v-if="pallet.state === 'picking'" >
                    <template #button="{ open }">
                        <Button icon="fal fa-times"
                            v-tooltip="trans('Set as not picked')"
                            :type="'negative'"
                            :key="pallet.id + open"
                            :loading="isSubmitNotPickedLoading == pallet.id"
                        />
                    </template>

                    <template #content="{ close }">
                        <div class="w-[250px]">
                    
                            <div class="mb-3">
                                <div class="text-xs px-1 mb-1"><span class="text-red-500 text-sm mr-0.5">*</span>Select status: </div>
                                <PureMultiselect v-model="selectedStatusNotPicked.status" @update:modelValue="() => errorNotPicked.status = null" :options="listStatusNotPicked" required caret :class="errorNotPicked.status ? 'errorShake' : ''" />
                                <div v-if="errorNotPicked.status" class="mt-1 text-red-500 italic text-xxs">{{ errorNotPicked.status }}</div>
                            </div>

                           
                            <div class="mb-4 ">
                                <div class="text-xs px-1 mb-1"><span class="text-red-500 text-sm mr-0.5">*</span>Description:</div>
                                <PureTextarea v-model="selectedStatusNotPicked.notes" @update:modelValue="() => errorNotPicked.notes = null" placeholder="Enter reason why the pallet is not picked" :class="errorNotPicked.notes ? 'errorShake' : ''" />
                                <div v-if="errorNotPicked.notes" class="mt-1 text-red-500 italic text-xxs">{{ errorNotPicked.notes }}</div>
                            </div>

                            
                            <div class="flex justify-end mt-2">
                                <Button @click="async () => onSubmitNotPicked(pallet.id, close, pallet.notPickedRoute)"
                                    full
                                    label="Submit"
                                    :disabled="!selectedStatusNotPicked.status || !selectedStatusNotPicked.notes"
                                    :loading="isSubmitNotPickedLoading == pallet.id"
                                />
                            </div>
                        </div>
                    </template>
                </Popover>
                </div>
            </div>
        </template>

        
    </Table>
</template>
