<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n';
import { faExpandAlt, faMonument, faYinYang } from '@fal';
import Modal from '@/Components/Utils/Modal.vue';
import { ref } from 'vue';
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from '@/Composables/useFormatTime';
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from '@kyvg/vue3-notification';


const props = defineProps<{
	data: {}
	tab?: string
}>()

const locale = useLocaleStore()

const isOpenPickedBayModal = ref(false);
const selectedDeliveryNotes = ref([])
const isLoadingDelete = ref(null);

function bayRoute(bay: bay) {
	switch (route().current()) {
		case 'grp.org.warehouses.show.dispatching.picked_bays.index':
			return route(
				'grp.org.warehouses.show.dispatching.picked_bays.show',
				[route().params['organisation'], route().params['warehouse'], bay.slug])
		default:
			return route(
				'grp.org.warehouses.show.dispatching.picked_bays.index',
				[bay.organisation_slug, bay.slug])
	}
}

const deliveryNoteRoute = (bay: {}) => {
    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.picked_bays.show':
        case 'grp.org.warehouses.show.dispatching.picked_bays.index':
            return route(
                'grp.org.warehouses.show.dispatching.delivery_notes.show',
                {
                    organisation: route().params['organisation'],
                    warehouse: route().params['warehouse'],
                    deliveryNote: bay.current_delivery_note.slug
                })
        default:
            return '#'
    }
}

const openModalPickBay = (deliveryNotes: []) => {
	isOpenPickedBayModal.value = true;
	selectedDeliveryNotes.value = deliveryNotes;
}

const resetModalPickBay = () => {
	isOpenPickedBayModal.value = false;
	selectedDeliveryNotes.value = [];
}

const routeDeliveryNote = (deliveryNote: any) => {
	return route("grp.helpers.redirect_delivery_notes", [deliveryNote.id])
}

const deletePickBay = (pickedBay: any) => {
	router.delete(route('grp.models.picked_bays.delete', {
					pickedBay: pickedBay.id
	}), {
		onStart: () => {
			isLoadingDelete.value = pickedBay.id;
		},
		onSuccess: () => {
			notify({
				title: "Success",
				text: trans("Picked Bay has been successfully deleted"),
				type: "success",
			})
		}, 
		onError: (err) => {
			const errMsg = err.pickedBay;
			notify({
				title: "Failed",
				text: errMsg ??  trans("Failed to delete Picked Bay"),
				type: "error",
			})
		}, 
		onFinish: () => {
			isLoadingDelete.value = null;
		}
	})
}

</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<!-- Column: Code -->
		<template #cell(code)="{ item: bay }">
			<Link :href="bayRoute(bay)" class="primaryLink">
				{{ bay['code'] }}
			</Link>
		</template>

		<template #cell(delivery_notes)="{ item: bay }">
			<span v-if="bay.delivery_notes.length" class="border rounded-md border-yellow-400 px-2 py-1 text-yellow-600 bg-yellow-200 cursor-pointer pulse-animate hover:opacity-[80%] ease-in-out transition" @click="openModalPickBay(bay.delivery_notes)">
				{{ trans("Used in :_countDeliveryNotes Delivery Notes", {_countDeliveryNotes: bay.delivery_notes.length}) }}
				<FontAwesomeIcon :icon="faExpandAlt" class="ml-2"/>
			</span>
            <span v-else class="italic text-xs opacity-60">
                {{ trans('No current delivery note') }}
            </span>
		</template>

		<template #cell(actions)="{item: bay}">
			<Button
				v-if="!bay.delivery_notes.length"
				v-tooltip="trans('Delete picking bay')"
				@click="deletePickBay(bay)"
				:type="'negative'"
				icon="fal fa-skull"
				:size="'xs'"
				:loading="isLoadingDelete == bay.id"
			/>
		</template>
	</Table>

	<Modal :isOpen="isOpenPickedBayModal" @onClose="resetModalPickBay" :width="'xl:w-[700px] w-4/5 px-0 py-2'">
		<div class="px-4 pb-2 pt-2">
			<FontAwesomeIcon :icon="faMonument" class="mr-2" /> 
			<span class="font-medium">
				{{ trans("Picked Bays - Delivery Notes") }}
			</span>
		</div>
		<hr class="border-gray-500">
		<div class="pt-2 px-4 pb-2">
			<div class="grid grid-cols-12 border-b border-gray-300 pt-1 pb-2">
				<span class="pl-2">
					<FontAwesomeIcon :icon="faYinYang" />
				</span>
				<span class="col-span-3">
					{{ trans("Reference") }}
				</span>
				<span class="col-span-4 text-right">
                	{{ trans("Date") }}
				</span>
				<span class="col-span-2 text-right">
					{{ trans("Weight")}}
				</span>
				<span class="col-span-2 text-right pr-2">
                	{{ trans("Items") }}
				</span>
			</div>
			<div v-for="deliveryNote in selectedDeliveryNotes" class="grid grid-cols-12 py-2">
				<span class="pl-2">
					<Icon :data="deliveryNote.state_icon"/>
				</span>
				<span class="col-span-3">
					<Link :href="routeDeliveryNote(deliveryNote)" class="secondaryLink">
						{{ deliveryNote.reference }}
					</Link>
				</span>
				<span class="col-span-4 text-right">
                	{{ useFormatTime(deliveryNote.date, { 
						localeCode: locale.language.code, 
						formatTime: "EEE, do MMM yy, HH:mm"
					}) }}
				</span>
				<span class="col-span-2 text-right">
					{{ deliveryNote.weight }}	
				</span>
				<span class="col-span-2 text-right pr-2">
                	{{ deliveryNote.number_items }}
				</span>
			</div>
		</div>
	</Modal>
</template>
