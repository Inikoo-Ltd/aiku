<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:46:51 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link, router } from "@inertiajs/vue3"
import Tag from "@/Components/Tag.vue"
import TagPallet from '@/Components/TagPallet.vue'
import '@/Composables/Icon/PalletReturnStateEnum'  // Import all icon for State
import '@/Composables/Icon/Pallet/PalletType'  // Import all icon for State

import Icon from "@/Components/Icon.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { computed, inject, reactive, ref, onBeforeMount } from 'vue'
import { trans } from "laravel-vue-i18n"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import Modal from "@/Components/Utils/Modal.vue"
import { debounce, isNull } from 'lodash-es'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrashAlt, faPaperPlane } from "@far"
import { faSignOutAlt, faTimes, faShare, faCross, faUndo, faStickyNote, faBackspace, faDebug, faHandHoldingBox } from "@fal"
import { faSkull } from "@fas"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification";
import FieldEditableTable from "@/Components/FieldEditableTable.vue"
import axios from "axios"

const layout = inject('layout', layoutStructure)
const screenType = inject('screenType', ref('desktop'))

library.add(faTrashAlt, faSignOutAlt, faTimes, faShare, faCross, faUndo, faStickyNote, faPaperPlane, faBackspace, faDebug, faSkull, faHandHoldingBox)

const props = defineProps<{
    data: {}
    tab?: string
    state?: string
    route_checkmark : routeType
    palletReturn: {}
}>()

const emit = defineEmits<{
    (e: 'isStoredItemAdded', value: boolean): void
}>()

console.log('s',props)

const isPickingLoading = ref(false)
const isUndoLoading = ref(false)
const isUnlinkLoading = ref(false)
const selectedRow = ref({})
// Not Picked
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
    },
    {
        label: trans('Unlink'),
        value: 'unlink'
    }
]
const selectedStatusNotPicked = reactive({
    status: '',
    notes: ''
})
const errorNotPicked = reactive<{
    status: string | null
    notes: string | null
}>({
    status: null,
    notes: null
})
const isSubmitNotPickedLoading = ref<boolean | number>(false)
const isModalMarkPalletStatus = ref(false)
const selectedPalletForMarkStatus = ref<any | null>(null)

const onSubmitNotPicked = async (idPallet: number, routeNotPicked: routeType, closePopup?: () => void) => {
    isSubmitNotPickedLoading.value = idPallet
    router[routeNotPicked.method || 'get'](route(routeNotPicked.name, routeNotPicked.parameters), {
        state: selectedStatusNotPicked.status,
        notes: selectedStatusNotPicked.notes
    }, {
        onSuccess: () => {
            selectedStatusNotPicked.status = ''
            selectedStatusNotPicked.notes = ''
            errorNotPicked.status = null
            errorNotPicked.notes = null
            closePopup?.()
        },
        onError: (error: {}) => {
            console.error('hehehe', error)
        },
        onFinish: () => {
            isSubmitNotPickedLoading.value = false
        },
        only: ['pallets', 'pageHead', 'data'],
        preserveScroll: true
    })
}

const onOpenModalMarkPalletStatus = (pallet: any) => {
    selectedPalletForMarkStatus.value = pallet
    selectedStatusNotPicked.status = ''
    selectedStatusNotPicked.notes = ''
    errorNotPicked.status = null
    errorNotPicked.notes = null
    isModalMarkPalletStatus.value = true
}

const onCloseModalMarkPalletStatus = () => {
    isModalMarkPalletStatus.value = false
    selectedPalletForMarkStatus.value = null
}

const onSubmitMarkPalletStatus = async () => {
    const pallet = selectedPalletForMarkStatus.value
    if (!pallet) {
        return
    }

    if (!selectedStatusNotPicked.status) {
        errorNotPicked.status = trans('Please select status')
        return
    }

    if (selectedStatusNotPicked.status === 'unlink') {
        if (!pallet.unlinkRoute?.name) {
            return
        }

        isUnlinkLoading.value = pallet.id
        router.patch(route(pallet.unlinkRoute.name, pallet.unlinkRoute.parameters), {}, {
            onSuccess: () => {
                onCloseModalMarkPalletStatus()
            },
            onFinish: () => {
                isUnlinkLoading.value = false
            },
            preserveScroll: true,
            only: ['pallets', 'pageHead', 'data']
        })
        return
    }

    if (!selectedStatusNotPicked.notes) {
        errorNotPicked.notes = trans('Description is required')
        return
    }

    await onSubmitNotPicked(
        pallet.id,
        pallet.notPickedRoute,
        onCloseModalMarkPalletStatus
    )
}

const onSetAsNotPickedQuick = async (idPallet: number, routeNotPicked: routeType) => {
    if (!routeNotPicked?.name) {
        return
    }

    isSubmitNotPickedLoading.value = idPallet
    router[routeNotPicked.method || 'get'](route(routeNotPicked.name, routeNotPicked.parameters), {}, {
        onFinish: () => {
            isSubmitNotPickedLoading.value = false
        },
        only: ['pallets', 'pageHead', 'data'],
        preserveScroll: true
    })
}

/* const SetSelected = debounce(() => {
    const finalValue: Record<string, { quantity: number }> = [];

    for(const key in selectedRow.value){
        if (selectedRow.value[key] &&  !isNull(key)) {
           finalValue.push(key)
        }
    }


    router.post(
        route(props.route_checkmark.name, props.route_checkmark.parameters),
        { pallets : finalValue },
        {
            preserveScroll: true,
            onSuccess: () => {},
            onError: () => {
                notify({
                    title: 'Something went wrong.',
                    text: 'Failed to save',
                    type: 'error',
                });
            },
        }
    );
}, 500);

const onChangeCheked = (value) => {
    selectedRow.value = value;
    SetSelected();
    console.log('lkm')
} */


const setUpChecked = () => {
    const set: Record<string, boolean> = {};
    if (props.data?.data) {
        props.data.data.forEach((item) => {
            set[item.pallet_id] = item.is_checked || false;
        });
        selectedRow.value = set;
    }
};

const debounceReloadBoxStats = debounce(() => {
    router.reload({
        only: ['pageHead', 'box_stats'],
    })
}, 700)

const isWarehouseDispatchingPalletReturnPage = computed(() => route().current('grp.org.warehouses.show.dispatching.pallet-returns.show'))
const isFulfilmentOperationsPalletReturnPage = computed(() => {
    return route().current('grp.org.fulfilments.show.backlogs.pallet-returns-backlog.wholesale.pallet-returns.show')
        || route().current('grp.org.fulfilments.show.crm.customers.show.pallet_returns.show')
})

const onCheckTable = async (item: {}) => {
    if (item.is_checked) {
        try {
            if(!item.attachRoute?.name) {
                throw new Error('Attach route is not defined')
            }
            const response = await axios.post(
                route(item.attachRoute.name, {
                    ...item.attachRoute.parameters,
                    palletReturn: props.palletReturn.id
                }),
                {},
            )

            emit('isStoredItemAdded', true)
            debounceReloadBoxStats()
        } catch (error) {
            notify({
                title: 'Something went wrong',
                text: 'Failed to select the data',
                type: 'error',
            })

        }

    } else {
        // console.log('pppp', item.deleteFromReturnRoute?.name)
        try {
            if(!item.deleteFromReturnRoute?.name) {
                throw new Error('Delete route is not defined')
            }
            await axios.delete(
                route(item.deleteFromReturnRoute.name, {palletReturn : props.palletReturn.id , pallet : item.pallet_id })
            )

            emit('isStoredItemAdded', false)
            debounceReloadBoxStats()
        } catch (error) {
            console.log('sssss',error)
            notify({
                title: 'Something went wrong',
                text: 'Failed to unselect the data',
                type: 'error',
            })

        }
    }

}

const onSaved = async (pallet: { form: {} }, fieldName: string) => {
	if (pallet[fieldName] != pallet.form.data()[fieldName]) {
		pallet.form.processing = true
		try {
			await axios.patch(route(pallet.updatePalletRoute.name, pallet.updatePalletRoute.parameters), {
				[fieldName]: pallet.form.data()[fieldName],
			})
			onSavedSuccess(pallet, fieldName)

		} catch (error: any) {
			onSavedError(error, pallet, fieldName)
		}

		setTimeout(() => {
			pallet.form.wasSuccessful = false
		}, 3000)
	}
}


const onSavedSuccess = (pallet: { form: {} }, fieldName: string) => {
	pallet.form.processing = false
	pallet.form.wasSuccessful = true
	pallet.form.hasErrors = false
	pallet.form.clearErrors()
	pallet[fieldName] = pallet.form.data()[fieldName]
}

const onSavedError = (error: {}, pallet: { form: {} }) => {
	pallet.form.processing = false
	pallet.form.wasSuccessful = false
	pallet.form.hasErrors = true
	if (error.response && error.response.data && error.response.data.errors) {
		const errors = error.response.data.errors
		const setErrors = {}
		for (const er in errors) {
			setErrors[er] = errors[er][0]
		}
		pallet.form.setError(setErrors)
	} else {
		if (error.response.data.message)
			notify({
				title: "Failed to update",
				text: error.response.data.message,
				type: "error",
			})
	}
}

onBeforeMount(() => {
    setUpChecked();
});

// Generate link to pallet
const generateLinkPallet = (pallet: any) => {
    if (!pallet.slug) {
        return null
    }

    const params = route().params as Record<string, string | undefined>

    switch (route().current()) {
        case "grp.org.warehouses.show.dispatching.pallet-returns.show":
            return route("grp.org.warehouses.show.inventory.pallets.current.show", [
                params.organisation,
                params.warehouse,
                pallet.slug,
            ])
        case "grp.org.fulfilments.show.backlogs.pallet-returns-backlog.wholesale.pallet-returns.show":
            if (!params.organisation || !params.fulfilment) {
                return null
            }

            return route("grp.org.fulfilments.show.operations.pallets.current.show", [
                params.organisation,
                params.fulfilment,
                pallet.slug,
            ])
        case "grp.org.fulfilments.show.crm.customers.show.pallet_returns.show":
            if (!params.organisation || !params.fulfilment) {
                return null
            }

            return route("grp.org.fulfilments.show.crm.customers.show.pallets.show", [
                params.organisation,
                params.fulfilment,
                params.fulfilmentCustomer ?? pallet.fulfilment_customer_slug,
                pallet.slug,
            ])
        default:
            return null
    }
}

const generateLocationRoute = (item: any, picking?: any) => {
    const locationSlug = picking?.location_slug
        ?? picking?.location
        ?? item?.location_slug
        ?? item?.location
    const organisation = route().params["organisation"]
    const warehouse = route().params["warehouse"]

    if (!locationSlug || !organisation || !warehouse) {
        return null
    }

    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        organisation,
        warehouse,
        locationSlug
    ])
}
</script>

<template>
    <!-- <pre>{{ data.data[0] }}</pre> -->
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="state == 'in_process'"
        checkboxKey='pallet_id'
        @onChecked="(item) => onCheckTable(item)"
        @onUnchecked="(item) => onCheckTable(item)"
    >

        <!-- Column: Type Icon -->
		<template #cell(type_icon)="{ item: palletDelivery }">
            <div class="space-x-1 space-y-1">
                <!-- Icon: Type -->
                <div v-if="layout.app.name == 'retina'" class="px-3">
                    <TagPallet :stateIcon="palletDelivery.type_icon" />
                </div>
                <FontAwesomeIcon v-else v-tooltip="palletDelivery.type_icon.tooltip" :icon='palletDelivery.type_icon.icon' :class='palletDelivery.type_icon.class' fixed-width aria-hidden='true' />
                <!-- Icon: State -->
                <div v-if="layout.app.name == 'retina'" class="px-3">
                    <TagPallet :stateIcon="palletDelivery.state_icon" />
                </div>
                            <Icon v-else :data="palletDelivery['state_icon']" class="px-1" />
            </div>

		</template>


        <!-- Column: Rental -->
        <template #cell(reference)="{ item }">
            <Link v-if="generateLinkPallet(item)" :href="generateLinkPallet(item)" class="primaryLink">
                {{ item.reference }}
            </Link>
            <div v-else>
                {{ item.reference || '-' }}
            </div>
        </template>

        <!-- Column: Rental -->
        <template #cell(rental)="{ item }">
                {{ item.rental_name }}
        </template>

        <!-- Column: Customer Reference, Notes -->
        <template #cell(customer_reference)="{ item }">
            <div>
                {{ item.customer_reference }}
                <div v-if="item.notes" class="text-gray-400">
                    <FontAwesomeIcon v-tooltip="trans(`Pallet's note`)" icon="fal fa-sticky-note" fixed-width aria-hidden="true" />
                    <span>
                        {{ item.notes }}
                    </span>
                </div>
            </div>
        </template>

        <!-- Column: State -->
		<!-- <template #cell(state)="{ item: palletDelivery }">
            <div v-if="layout.app.name == 'retina'" class="px-3">
                <TagPallet :stateIcon="palletDelivery.state_icon" />
            </div>
			<Icon v-else :data="palletDelivery['state_icon']" class="px-1" />
		</template> -->


        <!-- Column: Stored Items -->
        <template #cell(stored_items)="{ item: pallet }">
            <div v-if="pallet.stored_items.length" class="flex flex-wrap gap-x-1 gap-y-1.5">
                <Tag v-for="item of pallet.stored_items" :theme="item.id" :label="`${item.reference} (${item.quantity})`" :closeButton="false"
                    :stringToColor="true">
                    <template #label>
                        <div class="whitespace-nowrap text-xs">
                            {{ item.reference }} (<span class="font-light">{{ item.quantity }}</span>)
                        </div>
                    </template>
                </Tag>
            </div>

            <div v-else class="text-gray-400 text-xs italic">
                {{ trans('No items')}}
            </div>
        </template>


        <!-- Column: Location -->
		<template #cell(location)="{ item: palletDelivery }">
      <!--   <pre>{{ palletDelivery }}</pre> -->
            <Tag v-if="isFulfilmentOperationsPalletReturnPage && palletDelivery.location_code" :label="palletDelivery.location_code" />
            <Link
                v-else-if="generateLocationRoute(palletDelivery, palletDelivery)"
                :href="generateLocationRoute(palletDelivery, palletDelivery)!"
                class="secondaryLink"
            >
                {{ palletDelivery.location_code }}
            </Link>
            <div v-else class="text-gray-400">{{ palletDelivery.location_code || '-' }}</div>
		</template>

        <!-- Column: Pickings -->
        <template #cell(pickings)="{ item }">
            <div v-if="item.pickings?.length" class="space-y-1">
                <div v-for="picking in item.pickings" :key="picking.id" class="flex gap-x-2 w-fit items-center">
                    <template v-if="isWarehouseDispatchingPalletReturnPage">
                        <Link v-if="generateLocationRoute(item, picking)" :href="generateLocationRoute(item, picking)" class="secondaryLink">
                            {{ picking.location_code }}
                        </Link>
                        <div v-else class="text-gray-400">
                            {{ picking.location_code || '-' }}
                        </div>
                    </template>
                    <Tag v-else-if="picking.location_code" :label="picking.location_code" />
                    <div v-else class="text-gray-400">-</div>
                    <div
                        v-if="(item.picked?.picked ?? 0) > 0"
                        v-tooltip="trans('Picked pallet')"
                        class="inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-gray-100 text-gray-700 text-xs font-medium"
                    >
                        {{ item.picked?.picked }}
                    </div>
                </div>
            </div>
            <div v-else-if="item.pivot_state === 'not_picked'" class="text-red-500 italic flex items-center gap-x-1">
                <FontAwesomeIcon icon="fas fa-skull" fixed-width aria-hidden="true" />
                <span v-if="item.state === 'lost'">{{ trans("Pallet lost") }}</span>
                <span v-else-if="item.state === 'damaged'">{{ trans("Pallet damaged") }}</span>
                <span v-else-if="item.state === 'other_incident'">{{ trans("Other incident") }}</span>
                <span v-else>{{ trans("Not picked") }}</span>
            </div>
            <div v-else class="text-xs text-gray-400 italic">
                {{ trans("No items picked yet") }}
            </div>
        </template>

        <!-- Column: Picked -->
        <template #cell(picked)="{ item }">
            <div class="flex items-center justify-center gap-x-2">
                <span>{{ item.picked?.picked ?? 0 }}</span>
                <span
                    v-if="(item.picked?.not_picked ?? 0) > 0"
                    v-tooltip="trans('Not Picked')"
                    class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 border border-red-400 bg-red-50 text-red-500 text-xs font-medium leading-none"
                >
                    {{ item.picked?.not_picked }}
                </span>
            </div>
        </template>


        <!-- Column: Actions -->
        <template #cell(actions)="{ item: pallet }" v-if="props.state == 'in_process' || props.state == 'picking'">
            <!-- State: Pick or not-picked -->
            <div v-if="props.state == 'picking' && layout.app.name == 'Aiku'" class="flex gap-x-2 ">
                <!-- {{ pallet.state }} -->

                <!-- 1. Picking -> Able to pick / set it as not picked due to reasons (Will set pallet to lost)
                2. Picked -> Undo picking
                3. Lost -> Display text that pallet is lost
                4. NEW -> if pallet state is picking have a button to unlink -->

                <!-- Button: Picking -->
                <Link v-if="pallet.state === 'picking' && !isFulfilmentOperationsPalletReturnPage" as="div"
                    :href="route(pallet.updateRoute.name, pallet.updateRoute.parameters)"
                    :data="{ state: 'picked' }"
                    @start="() => isPickingLoading = pallet.id"
                    @finish="() => isPickingLoading = false"
                    preserveScroll
                    :only="['pallets', 'pageHead', 'data']"
                    method="patch"
                    v-tooltip="trans(`Set as picked`)"
                >
                    <!-- <div class="border border-green-500 rounded py-2 px-6 hover:bg-green-500/10 cursor-pointer">
                        <FontAwesomeIcon icon='fal fa-check' class='flex items-center justify-center text-green-500' fixed-width aria-hidden='true' />
                    </div> -->
                    <Button icon="fal fa-clipboard-list-check" type="secondary"
                        size="sm" :loading="isPickingLoading === pallet.id" class="py-0" />
                </Link>

                <Button
                    v-if="pallet.state === 'picking' && isWarehouseDispatchingPalletReturnPage && pallet.notPickedPalletRoute?.name"
                    icon="fal fa-debug"
                    v-tooltip="trans('Set as not picked')"
                    :type="'negative'"
                    size="sm"
                    :loading="isSubmitNotPickedLoading == pallet.id"
                    @click="() => onSetAsNotPickedQuick(pallet.id, pallet.notPickedPalletRoute)"
                />
                <!-- Button: Set as not picked -->
                <Button
                    v-if="pallet.state === 'not_picked' && isFulfilmentOperationsPalletReturnPage"
                    icon="fal fa-debug"
                    v-tooltip="trans('Mark pallet status')"
                    :type="'negative'"
                    size="sm"
                    :loading="isSubmitNotPickedLoading == pallet.id || isUnlinkLoading === pallet.id"
                    @click="() => onOpenModalMarkPalletStatus(pallet)"
                />

                <!-- Button: Unlink -->
                <Link v-if="pallet.state === 'picking' && pallet.unlinkRoute && !isWarehouseDispatchingPalletReturnPage && !isFulfilmentOperationsPalletReturnPage" as="div"
                    :href="route(pallet.unlinkRoute.name, pallet.unlinkRoute.parameters)"
                    @start="() => isUnlinkLoading = pallet.id"
                    @finish="() => isUnlinkLoading = false"
                    preserveScroll
                    :only="['pallets', 'pageHead', 'data']"
                    method="patch"
                    v-tooltip="trans(`Unlink pallet from this return order (Will set it as in-warehouse)`)"
                >
                    <!-- <div class="border border-green-500 rounded py-2 px-6 hover:bg-green-500/10 cursor-pointer">
                        <FontAwesomeIcon icon='fal fa-check' class='flex items-center justify-center text-green-500' fixed-width aria-hidden='true' />
                    </div> -->
                    <Button icon="fal fa-backspace" type="warning" :size="screenType == 'desktop' ? 'sm' : 'lg'"
                        :loading="isUnlinkLoading === pallet.id" class="py-0" />
                </Link>

                <!-- Button: Undo picking -->
                <div v-if="(pallet.state === 'picked' || pallet.state === 'not_picked') && isWarehouseDispatchingPalletReturnPage" class="flex items-center justify-center gap-x-1">
                    <FontAwesomeIcon v-if="pallet.state === 'not_picked'"
                        v-tooltip="trans('Pallet not picked')"
                        icon="fas fa-skull"
                        class="text-red-500"
                        fixed-width
                        aria-hidden="true"
                    />
                    <FontAwesomeIcon v-if="pallet.state === 'picked'" v-tooltip="trans('Pallet picked')"
                        :icon="faHandHoldingBox" class="text-gray-500" fixed-width aria-hidden="true" />
                    <Link
                        as="div"
                        :href="route(pallet.undoPickingRoute.name, pallet.undoPickingRoute.parameters)"
                        @start="() => isUndoLoading = pallet.id"
                        @finish="() => isUndoLoading = false"
                        method="patch"
                        preserveScroll
                        :only="['pallets', 'pageHead', 'data']"
                        v-tooltip="trans('Undo pick')"
                        class="flex items-center justify-center"
                    >
                        <Button icon="fal fa-undo" type="negative" size="xs" :loading="isUndoLoading === pallet.id" class="py-0" />
                    </Link>
                </div>

                <div v-else-if="['lost', 'damaged', 'other_incident'].includes(pallet.state)" class="text-red-300 italic">
                    <FontAwesomeIcon v-tooltip="trans('Pallet not picked')" icon="fas fa-skull" class="text-red-500" fixed-width aria-hidden="true" />
                    {{ trans(pallet.state === 'lost' ? 'Pallet lost' : pallet.state === 'damaged' ? 'Pallet damaged' : 'Other incident') }}
                </div>
            </div>
        </template>

        <!-- <template #cell(actions)="{ item: pallet }" v-else>
            <div v-if="pallet.pivot_state == 'cancel'" class="text-red-300 italic" >
                {{ trans("Pallet set back to storing") }}
            </div>
        </template> -->


    </Table>

    <Modal :isOpen="isModalMarkPalletStatus" @onClose="onCloseModalMarkPalletStatus" width="w-full max-w-md" closeButton>
        <div class="text-base font-semibold mb-4">{{ trans('Mark pallet status') }}</div>

        <div class="mb-3">
            <div class="text-xs px-1 mb-1">
                <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans('Select status') }}:
            </div>
            <PureMultiselect
                v-model="selectedStatusNotPicked.status"
                @update:modelValue="() => errorNotPicked.status = null"
                :options="listStatusNotPicked"
                required
                caret
                :class="errorNotPicked.status ? 'errorShake' : ''"
            />
            <div v-if="errorNotPicked.status" class="mt-1 text-red-500 italic text-xxs">{{ errorNotPicked.status }}</div>
        </div>

        <div v-if="selectedStatusNotPicked.status !== 'unlink'" class="mb-4">
            <div class="text-xs px-1 mb-1">
                <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans('Description') }}:
            </div>
            <PureTextarea
                v-model="selectedStatusNotPicked.notes"
                @update:modelValue="() => errorNotPicked.notes = null"
                :placeholder="trans('Enter reason why the pallet is not picked')"
                :class="errorNotPicked.notes ? 'errorShake' : ''"
            />
            <div v-if="errorNotPicked.notes" class="mt-1 text-red-500 italic text-xxs">{{ errorNotPicked.notes }}</div>
        </div>

        <div class="flex justify-end mt-2">
            <Button
                @click="onSubmitMarkPalletStatus"
                full
                :label="selectedStatusNotPicked.status === 'unlink' ? trans('Unlink') : trans('Submit')"
                :loading="isSubmitNotPickedLoading == selectedPalletForMarkStatus?.id || isUnlinkLoading === selectedPalletForMarkStatus?.id"
                :disabled="!selectedStatusNotPicked.status || (selectedStatusNotPicked.status !== 'unlink' && !selectedStatusNotPicked.notes)"
            />
        </div>
    </Modal>
</template>
