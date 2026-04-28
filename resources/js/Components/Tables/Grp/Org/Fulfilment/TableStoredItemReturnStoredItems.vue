<script setup lang="ts">
import Table from '@/Components/Table/Table.vue';
import Icon from "@/Components/Icon.vue";
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue';
import { computed, ref, watch, onBeforeMount,reactive, inject, onMounted} from 'vue';
import { notify } from "@kyvg/vue3-notification";
import { debounce, set, get } from 'lodash-es';
import { Link, router } from "@inertiajs/vue3"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import Tag from '@/Components/Tag.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faUndoAlt, faArrowDown, faTimes, faDebug } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Collapse } from 'vue-collapsed'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import axios from 'axios'
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
library.add(faCheck, faUndoAlt, faArrowDown, faDebug)

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
const selectedRow = ref<Record<string, boolean>>({});
const _table = ref(null);

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

const onChangeCheked = (value: Record<string, boolean>) => {
    selectedRow.value = value;
    SetSelected();
}

// Debounce the changeValueQty function
const changeValueQty = debounce((_value?: number) => {
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

const isWarehouseDispatchingPalletReturnPage = computed(() => route().current('grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show'))

const generateLinkReference = (reference: any) => {
    if (!reference.slug) {
        return undefined
    }

    const params = route().params as Record<string, string | undefined>
    const warehouseSlug = params.warehouse
        ?? reference.warehouse_slug

    if (!params.organisation || !warehouseSlug) {
        return undefined
    }

    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show':
        case 'grp.org.fulfilments.show.backlogs.pallet-returns-backlog.dropship.pallet-returns.show':
        case 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show':
            return route(
                'grp.org.warehouses.show.inventory.stored_items.current.show',
                {
                    organisation: params.organisation,
                    warehouse: warehouseSlug,
                    storedItem: reference.slug,
                }
            );
        default:
            return undefined
    }
}

const generateLinkPalletLocation = (pallet: any) => {
    const palletIdentifier = pallet.pallet_slug ?? pallet.reference
    if (!palletIdentifier) {
        return undefined
    }

    const params = route().params as Record<string, string | undefined>

    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.pallet-returns.show':
        case 'grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show':
            if (!params.organisation || !params.warehouse) {
                return undefined
            }

            return route(
                'grp.org.warehouses.show.inventory.pallets.current.show',
                {
                    organisation: params.organisation,
                    warehouse: params.warehouse,
                    pallet: palletIdentifier,
                }
            );
        case 'grp.org.fulfilments.show.backlogs.pallet-returns-backlog.dropship.pallet-returns.show':
        case 'grp.org.fulfilments.show.operations.pallet-return-with-stored-items.show':
            if (!params.organisation || !params.fulfilment) {
                return undefined
            }

            return route(
                'grp.org.fulfilments.show.operations.pallets.current.show',
                {
                    organisation: params.organisation,
                    fulfilment: params.fulfilment,
                    pallet: palletIdentifier,
                }
            );
        case 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show':
            if (!params.organisation || !params.fulfilment) {
                return undefined
            }

            return route(
                'grp.org.fulfilments.show.operations.pallets.current.show',
                {
                    organisation: params.organisation,
                    fulfilment: params.fulfilment,
                    pallet: palletIdentifier,
                }
            );
        default:
            return undefined
    }
}

const generateLinkPalletLocationHref = (pallet: any): string | undefined => {
    const generatedRoute = generateLinkPalletLocation(pallet)
    if (!generatedRoute) {
        return undefined
    }

    return String(generatedRoute)
}

const submitRouteRequest = async (routeTarget: routeType, payload: Record<string, any> = {}) => {
    const method = (routeTarget.method || 'get').toLowerCase()
    const url = route(routeTarget.name, routeTarget.parameters)

    if (method === 'post') {
        return axios.post(url, payload)
    }

    if (method === 'patch') {
        return axios.patch(url, payload)
    }

    if (method === 'put') {
        return axios.put(url, payload)
    }

    if (method === 'delete') {
        return axios.delete(url, { data: payload })
    }

    return axios.get(url, { params: payload })
}

const notifyRequestError = (error: unknown) => {
    const messages: string[] = []

    if (axios.isAxiosError(error)) {
        const responseData = error.response?.data as { message?: string; errors?: Record<string, string[] | string> } | undefined

        if (responseData?.message) {
            messages.push(responseData.message)
        }

        if (responseData?.errors) {
            Object.values(responseData.errors).forEach((errorValue) => {
                if (Array.isArray(errorValue)) {
                    errorValue.forEach((message) => {
                        if (message) {
                            messages.push(message)
                        }
                    })
                } else if (errorValue) {
                    messages.push(errorValue)
                }
            })
        }
    }

    const uniqueMessages = [...new Set(messages)].filter(Boolean)

    notify({
        title: trans('Something went wrong.'),
        text: uniqueMessages[0] ?? trans('Request failed'),
        type: 'error',
    })
}

// Button: undo pick
const isLoadingUndoPick = reactive({})
const isLoadingSetNotPicked = reactive({})
const isLoadingSetPicked = reactive({})
const onUndoPick = async (routeTarget: routeType, pallet_stored_item: any, loadingKey: string) => {
    try {
        pallet_stored_item.isLoadingUndo = true
        set(isLoadingUndoPick, loadingKey, true)
        await submitRouteRequest(routeTarget)
        pallet_stored_item.state = 'picking'
        router.reload({
            only: ['stored_items', 'box_stats', 'pageHead', 'data']
        })
        // console.log('qqqqq', pallet_stored_item)
    } catch (error) {
        notifyRequestError(error)
    } finally {
        set(isLoadingUndoPick, loadingKey, false)
    }

}

const onSetAsNotPicked = async (pallet_stored_item: any, quantity: number, loadingKey: string) => {
    if (!pallet_stored_item?.pallet_return_item_id) {
        return
    }

    try {
        set(isLoadingSetNotPicked, loadingKey, true)
        await submitRouteRequest(
            pallet_stored_item.notPickedRoute,
            {
                quantity_not_picked: Number(quantity || 0)
            }
        )
        router.reload({
            only: ['stored_items', 'box_stats', 'pageHead', 'data']
        })
    } catch (error) {
        console.error(error)
    } finally {
        set(isLoadingSetNotPicked, loadingKey, false)
    }
}

const onSetAsPicked = async (pallet_stored_item: any, quantity: number, loadingKey: string) => {
    const remainingQuantity = getRemainingActionQuantity(pallet_stored_item)
    if (remainingQuantity <= 0) {
        return
    }

    const quantityToPick = Math.min(Math.max(Number(quantity || 0), 1), remainingQuantity)

    try {
        set(isLoadingSetPicked, loadingKey, true)

        if (pallet_stored_item?.pallet_return_item_id) {
            const nextPickedQuantity = Number(pallet_stored_item?.picked_quantity || 0) + quantityToPick
            await submitRouteRequest(
                pallet_stored_item.updateRoute,
                {
                    quantity_picked: nextPickedQuantity
                }
            )
        } else {
            await submitRouteRequest(
                pallet_stored_item.newPickRoute,
                {
                    quantity_ordered: quantityToPick
                }
            )
        }

        router.reload({
            only: ['stored_items', 'box_stats', 'pageHead', 'data']
        })
    } catch (error) {
        console.error(error)
    } finally {
        set(isLoadingSetPicked, loadingKey, false)
    }
}

const getProcessedQuantity = (palletStoredItem: any): number => {
    return Number(palletStoredItem?.picked_quantity || 0) + Number(palletStoredItem?.not_picked_quantity || 0)
}

const getRemainingActionQuantity = (palletStoredItem: any): number => {
    const orderedQuantity = Number(palletStoredItem?.selected_quantity || 0)
    return Math.max(0, orderedQuantity - getProcessedQuantity(palletStoredItem))
}

const getPickedPalletStoredItems = (item: any) => {
    return (item.pallet_stored_items || []).filter((palletStoredItem: any) => {
        return Number(palletStoredItem.picked_quantity || 0) > 0
    })
}

const getNotPickedPalletStoredItems = (item: any) => {
    return (item.pallet_stored_items || []).filter((palletStoredItem: any) => {
        return Number(palletStoredItem.not_picked_quantity || 0) > 0
    })
}

const getRequiredPalletStoredItems = (item: any) => {
    return (item.pallet_stored_items || []).filter((palletStoredItem: any) => {
        return Number(palletStoredItem.selected_quantity || 0) > 0
    })
}

const getRequestedPalletStoredItems = (item: any) => {
    return (item.pallet_stored_items || []).filter((palletStoredItem: any) => {
        return Number(palletStoredItem.selected_quantity || 0) > 0
            || Number(palletStoredItem.picked_quantity || 0) > 0
            || Number(palletStoredItem.not_picked_quantity || 0) > 0
    })
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
            <Link v-if="generateLinkReference(value)" :href="generateLinkReference(value)" class="primaryLink">
                {{ value.reference }}
            </Link>
            <div v-else>
                {{ value.reference }}
            </div>
        </template>

        <!-- Column: Pallet of Stored items -->
        <template #cell(pallet_stored_items)="{ item: value, proxyItem }">
            <div class="grid gap-y-1">
                <template v-for="pallet_stored_item in getRequestedPalletStoredItems(value)" :key="pallet_stored_item.id">
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
                                    <Link v-if="generateLinkPalletLocation(pallet_stored_item)" :href="generateLinkPalletLocation(pallet_stored_item)" class="secondaryLink">
                                        {{ pallet_stored_item.reference }}
                                    </Link>
                                    <div v-else>{{ pallet_stored_item.reference }}</div>
                                </span>
                                <span v-else class="text-gray-400 italic">({{ trans('No reference') }})</span>
                                <span v-if="pallet_stored_item.location?.code" v-tooltip="trans('Location code of the pallet')" class="text-gray-400"> [{{ pallet_stored_item.location?.code }}]</span>
                                <div  v-if="pallet_stored_item.selected_quantity && palletReturn.state === 'in_process'" v-tooltip="trans('Will be picked')" class="pl-1 pb-1 inline" >
                                    <FontAwesomeIcon icon='fas fa-circle' class='text-[7px] text-blue-500 animate-pulse' fixed-width aria-hidden='true' />
                                </div>
                                <!-- <div v-if="palletReturn.state === 'picking'"
                                    @xxclick="() => pallet_stored_item.picked_quantity = pallet_stored_item.quantity_in_pallet"
                                    v-tooltip="trans(`Total Customer's SKU in this pallet`)"
                                    class="text-gray-400 tabular-nums xcursor-pointer xhover:text-gray-600">
                                    {{ trans("Stocks in pallet") }}: {{ pallet_stored_item.quantity_in_pallet }}
                                </div> -->
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
                                    :title="trans(`Return pallet :palletReference to customer?`, { palletReference: pallet_stored_item.reference ?? '' })"
                                    :description="trans(`The pallet :palletReference will be set as returned to the customer, and no longer exist in warehouse. This action cannot be reverse.`, { palletReference: pallet_stored_item.reference ?? '' })"
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
                                <!-- <div v-else-if="palletReturn.state === 'picking'" v-tooltip="trans(`Quantity of Customer's SKU that should be picked`)" class="text-base">{{ pallet_stored_item.selected_quantity }}</div> -->

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

                                <div v-else-if="palletReturn.state === 'dispatched'" class="hidden" />

                                <div v-else-if="!['picking', 'picked', 'dispatched'].includes(palletReturn.state)" class="flex flex-nowrap gap-x-1 items-center tabular-nums">
                                    <span v-if="pallet_stored_item.state == 'cancel'" class="pr-2 mr-1 text-red-500 border-r-2 border-gray-300" v-tooltip="trans('Item quantity on storage left untouched')">
                                        <FontAwesomeIcon :icon="faTimes" />
                                        {{ trans('Cancelled') }}
                                    </span>
                                    {{ locale.number(pallet_stored_item.picked_quantity) }}/{{ locale.number(pallet_stored_item.selected_quantity) }}
                                    <FontAwesomeIcon v-if="pallet_stored_item.state == 'picked'" v-tooltip="trans('Picked')" icon='fal fa-check' class='text-green-500' fixed-width aria-hidden='true' />
                                    <FontAwesomeIcon v-if="pallet_stored_item.state == 'not_picked'" v-tooltip="trans('Not picked')" icon='fas fa-skull' class='text-red-500' fixed-width aria-hidden='true' />
                                </div>

                            </div>
                            <!-- {{ get(isLoadingUndoPick, [`row${value.rowIndex}.id${pallet_stored_item.id}`], '000') }} --  -->
                            <!-- {{ pallet_stored_item.isLoadingUndo }} -->

                    </div>
                </template>

                <div v-if="!getRequestedPalletStoredItems(value).length" class="italic text-gray-400">
                    {{ trans('No pallet') }}
                </div>

                <!-- Section: area for pallet that have 0 selected quantity -->
                <!-- <div v-if="palletReturn.state != 'in_process'">
                    <Collapse as="section" :when="get(proxyItem, ['is_open_collapsed'], false)" class="">
                        <div :id="`row-${value.id}`">
                        </div>
                    </Collapse>
                    <div class="w-full mt-2">
                        <Button
                            v-if="!value.pallet_stored_items.every((val: any) => {return val.selected_quantity > 0})" @click="() => set(proxyItem, ['is_open_collapsed'], !get(proxyItem, ['is_open_collapsed'], false))"
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
                </div> -->
            </div>
        </template>

        <template #cell(required)="{ item }">
            <div v-if="getRequiredPalletStoredItems(item).length" class="grid gap-y-1 tabular-nums">
                <div
                    v-for="pallet_stored_item in getRequiredPalletStoredItems(item)"
                    :key="`required-${pallet_stored_item.id}`"
                    class="flex items-center justify-end gap-x-1"
                >
                    <span v-if="['picking', 'picked', 'dispatched'].includes(props.state)">
                        {{ locale.number(pallet_stored_item.selected_quantity || 0) }}
                    </span>
                    <template v-else>
                        <span v-if="pallet_stored_item.state == 'cancel'" class="text-red-500">
                            <FontAwesomeIcon :icon="faTimes" />
                        </span>
                        <span>{{ locale.number(pallet_stored_item.picked_quantity || 0) }}/{{ locale.number(pallet_stored_item.selected_quantity || 0) }}</span>
                        <FontAwesomeIcon v-if="pallet_stored_item.state == 'picked'" v-tooltip="trans('Picked')" icon='fal fa-check' class='text-green-500' fixed-width aria-hidden='true' />
                        <FontAwesomeIcon v-if="pallet_stored_item.state == 'not_picked'" v-tooltip="trans('Not picked')" icon='fas fa-skull' class='text-red-500' fixed-width aria-hidden='true' />
                    </template>
                </div>
            </div>
            <div v-else class="text-xs text-gray-400 italic text-right">
                -
            </div>
        </template>

        <template #cell(picked)="{ item }">
            <div v-if="getRequiredPalletStoredItems(item).length" class="grid gap-y-1 tabular-nums">
                <div
                    v-for="pallet_stored_item in getRequiredPalletStoredItems(item)"
                    :key="`picked-action-${pallet_stored_item.id}`"
                    class="flex items-center justify-end gap-x-2"
                >
                    <span>{{ locale.number(pallet_stored_item.picked_quantity || 0) }}</span>
                    <span
                        v-if="Number(pallet_stored_item.not_picked_quantity || 0) > 0"
                        v-tooltip="trans('Not picked')"
                        class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-sm border border-red-400 bg-red-50 text-red-600 text-xs"
                    >
                        {{ locale.number(pallet_stored_item.not_picked_quantity || 0) }}
                    </span>
                </div>
            </div>
            <div v-else class="text-xs text-gray-400 italic text-right">
                -
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
                            @update:modelValue="(e) => e ? (set(proxyItem, 'error_quantity', false), changeValueQty()) : set(proxyItem, 'error_quantity', true)"
                        />
                        <PureInputNumber
                            v-else
                            :modelValue="0"
                            disabled
                            v-tooltip="trans('Check the row to edit')"
                        />

                        <p v-if="proxyItem.error_quantity" class="mt-1 text-left text-xs text-red-500 italic">*{{ trans(`Quantity can't empty`) }}</p>
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

        <template #cell(total_quantity)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.total_quantity || 0) }}</span>
        </template>

        <template #cell(pickings)="{ item }">
            <div v-if="['picked', 'dispatched'].includes(props.state)">
                <div v-if="getPickedPalletStoredItems(item).length" class="grid gap-y-1">
                    <div
                        v-for="pallet_stored_item in getPickedPalletStoredItems(item)"
                        :key="`picked-${pallet_stored_item.id}`"
                        class="flex items-center gap-x-2"
                    >
                        <Link
                            v-if="generateLinkPalletLocationHref(pallet_stored_item)"
                            :href="generateLinkPalletLocationHref(pallet_stored_item)"
                            class="secondaryLink"
                        >
                            {{ pallet_stored_item.location?.code || pallet_stored_item.reference }}
                        </Link>
                        <span v-else>{{ pallet_stored_item.location?.code || pallet_stored_item.reference || '-' }}</span>
                        <span class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-gray-100 text-gray-700 text-xs tabular-nums">
                            {{ locale.number(pallet_stored_item.picked_quantity || 0) }}
                        </span>
                    </div>
                </div>
                <div v-else class="text-xs text-gray-400 italic">
                    {{ trans('No items picked yet') }}
                </div>
            </div>
            <div v-else-if="getPickedPalletStoredItems(item).length || getNotPickedPalletStoredItems(item).length" class="grid gap-y-1">
                <div v-for="pallet_stored_item in getPickedPalletStoredItems(item)" :key="`picked-${pallet_stored_item.id}`" class="flex items-center gap-x-2">
                    <Link v-if="generateLinkPalletLocationHref(pallet_stored_item)"
                        :href="generateLinkPalletLocationHref(pallet_stored_item)"
                        class="secondaryLink">
                        {{ pallet_stored_item.location?.code || pallet_stored_item.reference }}
                    </Link>
                    <span v-else>{{ pallet_stored_item.location?.code || pallet_stored_item.reference || '-' }}</span>

                    <div class="text-gray-500 tabular-nums whitespace-nowrap" v-tooltip="trans('Total picked quantity in this location')">
                        <FontAwesomeIcon icon="fal fa-hand-holding-box" fixed-width aria-hidden="true" />
                        {{ locale.number(pallet_stored_item.picked_quantity || 0) }}
                    </div>
                    <Button
                        v-if="palletReturn.state === 'picking' && isWarehouseDispatchingPalletReturnPage"
                        @click="() => onUndoPick(pallet_stored_item.undoRoute, pallet_stored_item, `undo_${item.id}_${pallet_stored_item.id}`)"
                        icon="fal fa-undo-alt"
                        size="xxs"
                        :loading="get(isLoadingUndoPick, `undo_${item.id}_${pallet_stored_item.id}`, false)"
                        type="negative"
                    />
                </div>

                <div v-for="pallet_stored_item in getNotPickedPalletStoredItems(item)"
                    :key="`not-picked-${pallet_stored_item.id}`"
                    class="text-red-500 w-fit mr-auto tabular-nums flex items-center gap-x-2"
                    v-tooltip="trans('Quantity not gonna be picked')">
                    <div>
                        <FontAwesomeIcon icon="fas fa-skull" fixed-width aria-hidden="true" />
                        {{ locale.number(pallet_stored_item.not_picked_quantity || 0) }}
                    </div>
                    <Button
                        v-if="palletReturn.state === 'picking' && isWarehouseDispatchingPalletReturnPage"
                        @click="() => onUndoPick(pallet_stored_item.undoRoute, pallet_stored_item, `undo_${item.id}_${pallet_stored_item.id}`)"
                        icon="fal fa-undo-alt"
                        size="xxs"
                        :loading="get(isLoadingUndoPick, `undo_${item.id}_${pallet_stored_item.id}`, false)"
                        type="negative"
                    />
                </div>
            </div>
            <div v-else class="text-xs text-gray-400 italic">
                {{ trans('No item picked yet') }}
            </div>
        </template>

        <!-- Column: Actions -->
        <template #cell(actions)="{ item: pallet }" v-if="props.state == 'picking'">
            <div class="grid gap-y-1">
                <template v-for="pallet_stored_item in pallet.pallet_stored_items" :key="pallet_stored_item.id">
                    <div
                        v-if="Number(pallet_stored_item.selected_quantity || 0) > 0 && getRemainingActionQuantity(pallet_stored_item) > 0"
                        class="flex items-center justify-between gap-x-3"
                    >
                        <div class="text-sm tabular-nums">
                            <Link
                                v-if="generateLinkPalletLocationHref(pallet_stored_item)"
                                :href="generateLinkPalletLocationHref(pallet_stored_item)"
                                class="secondaryLink"
                            >
                                {{ pallet_stored_item.location?.code || pallet_stored_item.reference }}
                            </Link>
                            <span v-else>{{ pallet_stored_item.location?.code || pallet_stored_item.reference || '-' }}</span>
                            <span class="text-gray-700"> (<span class="font-bold">{{ locale.number(pallet_stored_item.quantity_in_pallet || 0) }}</span> {{ trans("Stocks") }})</span>
                        </div>

                        <NumberWithButtonSave
                            :key="`pickingpicked-action_${pallet.id}_${pallet_stored_item.id}_${getRemainingActionQuantity(pallet_stored_item)}`"
                            noUndoButton
                            :modelValue="getRemainingActionQuantity(pallet_stored_item)"
                            :bindToTarget="{
                                step: 1,
                                min: 1,
                                max: getRemainingActionQuantity(pallet_stored_item)
                            }"
                            :xxparentClass="''"
                        >
                            <template #save="{ quantity }">
                                <div class="flex items-center gap-x-1">
                                    <Button
                                        v-if="pallet_stored_item.selected_quantity > 0"
                                        @click="() => onSetAsPicked(pallet_stored_item, Number(quantity || 0), `picked_${pallet.id}_${pallet_stored_item.id}`)"
                                        icon="fal fa-clipboard-list-check"
                                        :label="locale.number(quantity || 0)"
                                        :key="1"
                                        size="xs"
                                        type="secondary"
                                        :loading="get(isLoadingSetPicked, `picked_${pallet.id}_${pallet_stored_item.id}`, false)"
                                        class="py-0"
                                    />
                                    <Button
                                        v-if="pallet_stored_item.selected_quantity > 0"
                                        @click="() => onSetAsNotPicked(pallet_stored_item, Number(quantity || 0), `not-picked_${pallet.id}_${pallet_stored_item.id}`)"
                                        iconRight="fal fa-debug"
                                        :label="locale.number(quantity || 0)"
                                        :key="2"
                                        size="xs"
                                        type="negative"
                                        :loading="get(isLoadingSetNotPicked, `not-picked_${pallet.id}_${pallet_stored_item.id}`, false)"
                                        class="py-0"
                                    />
                                </div>
                            </template>
                        </NumberWithButtonSave>
                    </div>
                </template>
            </div>
        </template>


    </Table>
</template>
