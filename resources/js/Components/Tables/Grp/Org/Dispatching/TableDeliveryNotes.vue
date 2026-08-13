<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { DeliveryNote } from "@/types/delivery-note"
import { DialogTitle, Tab } from "@headlessui/vue"
import type { Table as TableTS } from "@/types/Table"
import { computed, ref } from "vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"
import { notify } from "@kyvg/vue3-notification"
import NotesDisplay from "@/Components/NotesDisplay.vue"
import WaitingOppositeCountBadge from "@/Components/Warehouse/DeliveryNotes/WaitingOppositeCountBadge.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMapMarkerAlt, faTruck, faYinYang, faCalendarAlt, faHistory } from "@fal"
import { faCertificate } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faTruck, faYinYang)

const props = defineProps<{
	data: TableTS
	tab?: string
	bucket: string
}>()


const routeParams = route().routeParams
const routeCurrent = route().current()

function deliveryNoteHref(deliveryNote: DeliveryNote) {
	return deliveryNoteRoute(deliveryNote) + bucketQuery()
}

function bucketQuery() {
	const bucket = routeCurrent?.match(/^grp\.org\.warehouses\.show\.dispatching\.([^.]+)\.delivery-notes/)?.[1]

	if (!bucket) return ''

	const query = new URLSearchParams({ bucket: bucket.replace('handling-blocked', 'handling_blocked') })
	const sort = new URLSearchParams(location.search).get('sort')

	if (routeParams["shopType"]) {
		query.set('bucket_shop_type', routeParams["shopType"] as string)
	}

	if (sort) {
		query.set('bucket_sort', sort)
	}

	return `?${query.toString()}`
}

function deliveryNoteRoute(deliveryNote: DeliveryNote) {
	switch (routeCurrent) {
		case "shops.show.orders.show":
			return route("shops.show.orders.show.delivery-notes.show", [
				routeParams["shop"],
				routeParams["order"],
				deliveryNote.slug,
			])
		case "orders.show":
			return route("orders.show,delivery-notes.show", [
				routeParams["order"],
				deliveryNote.slug,
			])
		case "shops.show.delivery-notes.index":
			return route("shops.show.delivery-notes.show", [
				deliveryNote.shop_id,
				deliveryNote.slug,
			])
		case "grp.org.warehouses.show.dispatching.delivery-notes":
			return route("grp.org.warehouses.show.dispatching.delivery_notes.show", [
				routeParams["organisation"],
				routeParams["warehouse"],
				deliveryNote.slug,
			])
		case "grp.org.shops.show.ordering.delivery-notes.index":
			return route("grp.org.shops.show.ordering.delivery-notes.show", [
				routeParams["organisation"],
				routeParams["shop"],
				deliveryNote.slug,
			])
		case "grp.org.shops.show.ordering.orders.index":
			return route("grp.org.shops.show.ordering.show.delivery-note.show", [
				routeParams["organisation"],
				routeParams["shop"],
				deliveryNote.slug,
			])
		case "grp.org.shops.show.crm.customers.show.delivery_notes.index":
			return route("grp.org.shops.show.crm.customers.show.delivery_notes.show", [
				routeParams["organisation"],
				routeParams["shop"],
				routeParams["customer"],
				deliveryNote.slug,
			])
		case "grp.org.shops.show.crm.customers.show.replacements.index":
			return route("grp.org.shops.show.crm.customers.show.replacements.show", [
				routeParams["organisation"],
				routeParams["shop"],
				routeParams["customer"],
				deliveryNote.slug,
			])
		case "grp.org.shops.show.crm.customers.show.orders.show":
			return route("grp.org.shops.show.crm.customers.show.delivery_notes.show", [
				routeParams["organisation"],
				routeParams["shop"],
				routeParams["customer"],
				deliveryNote.slug,
			])
		case "grp.overview.ordering.delivery_notes.index":
			return route("grp.org.shops.show.crm.customers.show.delivery_notes.show", [
				deliveryNote.organisation_slug,
				deliveryNote.shop_slug,
				deliveryNote.customer_slug,
				deliveryNote.slug,
			])
		default:
			return route("grp.majordomo.redirect_delivery_notes", [deliveryNote.id])
	}
}

function returnNoteRoute(returnDeliveryNote) {
	switch(routeCurrent) {
		case "grp.org.warehouses.show.incoming.return_delivery_notes.index":
			return route('grp.org.warehouses.show.incoming.return_delivery_notes.show', [
				routeParams["organisation"],
				routeParams["warehouse"],
				returnDeliveryNote.slug,
			])
		case "grp.org.shops.show.ordering.backlog":
		case "grp.org.shops.show.ordering.return_delivery_notes.index":
			return route("grp.org.shops.show.ordering.return_delivery_notes.show", [
				routeParams["organisation"],
				routeParams["shop"],
				returnDeliveryNote.slug,

			])
		case "grp.org.shops.show.crm.customers.show.return_delivery_notes.index":
			return route("grp.org.shops.show.crm.customers.show.return_delivery_notes.show", [
				routeParams["organisation"],
				routeParams["shop"],
				routeParams["customer"],
				returnDeliveryNote.slug,

			])
		default:
			return route("grp.majordomo.redirect_return_notes", returnDeliveryNote.id)
	}
}

function customerRoute(deliveryNote: DeliveryNote) {
	// console.log('deliveryNote', deliveryNote)
	if (!deliveryNote.customer_slug) {
		return "#"
	}

	switch (routeCurrent) {
		case "grp.overview.ordering.delivery_notes.index":
			return route("grp.org.shops.show.crm.customers.show", [
				deliveryNote.organisation_slug,
				deliveryNote.shop_slug,
				deliveryNote.customer_slug,
			])
		case "grp.org.warehouses.show.dispatching.delivery-notes":
			return route("grp.org.shops.show.crm.customers.show", [
				routeParams["organisation"],
				deliveryNote.shop_slug,
				deliveryNote.customer_slug,
			])
		default:
			return route("grp.org.shops.show.crm.customers.show", [
				routeParams["organisation"],
				deliveryNote.shop_slug,
				deliveryNote.customer_slug,
			])
	}
}

const isModalPick = ref(null)
const isLoadingPick = ref(false)
const isErrorPicker = ref<string | null>(null)
const onClickPick = () => {
	if (!isModalPick.value?.employee_pick_route?.name) {
		console.error("No route name found for employee pick")
		return
	}

	router.patch(
		route(
			isModalPick.value.employee_pick_route.name,
			isModalPick.value.employee_pick_route.parameters
		),
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
			},
		}
	)
}

const generateRouteDeliveryNote = (id: string) => {
	if (!id) return ""

	return route("grp.majordomo.redirect_delivery_notes", {
		deliveryNote: id,
	})
}

const showSubmittedDate = ref(props.bucket == 'all')

const hasDateColumnToggle = computed(() => !!props.bucket && props.bucket != 'all')

const bucketEvent = computed(() => {
	const events: Record<string, { label: string; header: string }> = {
		handling: { label: trans('Handling'), header: trans('Handling date') },
		handling_blocked: { label: trans('Blocked'), header: trans('Blocked date') },
		waiting: { label: trans('Blocked'), header: trans('Blocked date') },
		picked: { label: trans('Picked'), header: trans('Picked date') },
		packing: { label: trans('Packing'), header: trans('Packing date') },
		packed: { label: trans('Packed'), header: trans('Packed date') },
		finalised: { label: trans('Finalised'), header: trans('Finalised date') },
		dispatched: { label: trans('Dispatched'), header: trans('Dispatched date') },
	}

	return events[props.bucket] ?? { label: trans('Last event'), header: trans('Last event date') }
})

const dateColumnOptions = computed(() => [
	{
		value: false,
		icon: faHistory,
		label: bucketEvent.value.label,
		tooltip: trans('Show when the delivery note entered this stage (:event)', {
			event: bucketEvent.value.header.toLowerCase(),
		}),
	},
	{
		value: true,
		icon: faCalendarAlt,
		label: trans('Submitted'),
		tooltip: trans('Show when the delivery note was submitted by the customer'),
	},
])
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #tableExtraAction v-if="hasDateColumnToggle">
			<div class="flex items-center gap-x-1.5">
				<label class="text-xs text-gray-500 whitespace-nowrap">
					{{ ctrans('Date') }}
				</label>
				<div
					role="group"
					:aria-label="ctrans('Date shown in the date column')"
					class="inline-flex items-center gap-0.5 rounded-md border border-gray-300 bg-gray-100 p-0.5">
					<button
						v-for="option in dateColumnOptions"
						:key="String(option.value)"
						type="button"
						v-tooltip="option.tooltip"
						:aria-pressed="showSubmittedDate === option.value"
						@click="showSubmittedDate = option.value"
						:class="showSubmittedDate === option.value
							? 'bg-white text-gray-900 shadow-sm'
							: 'text-gray-500 hover:text-gray-700'"
						class="flex items-center gap-x-1 rounded px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
						<FontAwesomeIcon
							:icon="option.icon"
							class="text-[0.65rem]"
							fixed-width
							aria-hidden="true" />
						{{ option.label }}
					</button>
				</div>
			</div>
		</template>

		<template #cell(status)="{ item: deliveryNote }">
			<!-- {{deliveryNote.state_icon}} -->
			<Icon :data="deliveryNote.state_icon" />
			<!-- <Link :href="deliveryNoteHref(deliveryNote)" class="primaryLink">
                {{ deliveryNote["reference"] }}
            </Link> -->
		</template>

		<template #cell(state)="{ item: deliveryNote }">
			<Icon :data="deliveryNote.state_icon" />
		</template>

		<template #cell(reference)="{ item: deliveryNote }">
            <div class="flex flex-rows gap-2">
				<div class="flex gap-2 flex-wrap items-center">
					<Link :href="deliveryNoteHref(deliveryNote)" class="primaryLink">
						{{ deliveryNote["reference"] }}
					</Link>
					<FontAwesomeIcon
						v-if="deliveryNote.is_premium_dispatch"
						v-tooltip="trans('Priority dispatch')"
						icon="fas fa-star"
						class="text-yellow-500"
						fixed-width
						aria-hidden="true" />
					<FontAwesomeIcon
						v-if="deliveryNote.is_customer_vip"
						v-tooltip="trans('VIP Customer')"
						:icon="faCertificate"
						color="#191970"
						fixed-width
					/>
					<FontAwesomeIcon
						v-if="deliveryNote.has_extra_packing"
						v-tooltip="trans('Extra packing')"
						icon="fas fa-box-heart"
						class="text-yellow-500"
						fixed-width
						aria-hidden="true" />
					<FontAwesomeIcon
						v-if="Number(deliveryNote.number_items_composition_dirty) > 0"
						v-tooltip="trans('The packing of :count item(s) changed after this note was worked — check the quantities before dispatching', { count: deliveryNote.number_items_composition_dirty })"
						icon="fas fa-triangle-exclamation"
						class="text-red-600"
						fixed-width
						aria-hidden="true" />
					<NotesDisplay :item="deliveryNote" reference-field="reference" />
					<WaitingOppositeCountBadge
						v-if="Number(deliveryNote.waiting_warehouse_count) > 0"
						:count="Number(deliveryNote.waiting_warehouse_count)"
						type="warehouse"
						:href="deliveryNoteHref(deliveryNote)"
					/>
					<WaitingOppositeCountBadge
						v-if="Number(deliveryNote.waiting_crm_count) > 0"
						:count="Number(deliveryNote.waiting_crm_count)"
						type="crm"
						:href="deliveryNoteHref(deliveryNote)"
					/>
				</div>
				<span
                    v-if="deliveryNote.is_collection"
					class="border border-pink-500 text-pink-500 py-[0.15rem] px-[0.25rem] rounded-md ml-auto text-xs my-auto whitespace-nowrap"
				>
					{{ ctrans('Collection') }}
					<FontAwesomeIcon 
						:icon="faMapMarkerAlt"
						class="text-pink-500"
					/>
				</span>
			</div>
		</template>

		<template #cell(reference_return)="{ item: deliveryNote }">
			<div class="flex gap-2 flex-wrap items-center">
				<Link :href="returnNoteRoute(deliveryNote)" class="primaryLink">
					{{ deliveryNote["reference"] }}
				</Link>
			</div>
		</template>

		<template #table-header-date v-if="hasDateColumnToggle || showSubmittedDate">
			{{ showSubmittedDate ? ctrans("Submitted date") : bucketEvent.header }}
		</template>

		<template #cell(date)="{ item }">
			<span v-if="showSubmittedDate">
				{{
					useFormatTime(item.date, {
						formatTime: "EEE, do MMM yy, HH:mm",
					})
				}}
			</span>
			<span v-else>
				{{
					useFormatTime(item.last_modified_date, {
						formatTime: "EEE, do MMM yy, HH:mm",
					})
				}}
			</span>
		</template>

		<template #cell(effective_weight)="{ item: deliveryNote }">
			{{ deliveryNote.effective_weight }}
		</template>

		<template #cell(customer_name)="{ item: deliveryNote }">
			<Link :href="customerRoute(deliveryNote)" class="secondaryLink">
				{{ deliveryNote["customer_name"] }}
			</Link>
		</template>

		<template #cell(action)="{ item: deliveryNote }">
			<Button
				@click="() => (isModalPick = deliveryNote)"
				type="secondary"
				:label="trans('Pick')"
				size="xs" />
		</template>

		<template #cell(delivery)="{ item: deliveryNote }">
			<div v-if="deliveryNote.state === 'cancelled'"></div>

			<div v-else-if="deliveryNote.shipping_data?.[0]?.trackings?.[0]"
				class="flex gap-1 group pr-2 py-1.5">
				<img
					v-if="deliveryNote.shipping_data?.[0]?.shipper_slug"
					:src="`/assets/shipper_logo/${deliveryNote.shipping_data[0].shipper_slug}.png`"
					class="h-5 w-5 object-contain"
					:title="deliveryNote.shipping_data?.[0]?.shipper_label"
					v-tooltip="deliveryNote.shipping_data?.[0]?.shipper_label"
				/>
				<div class="group w-fit whitespace-nowrap max-w-96 truncate group-hover:max-w-max">
					<template v-if="deliveryNote.shipping_data?.[0].trackings?.[0]">
						{{ deliveryNote.shipping_data?.[0].shipper_slug }}:
						<a
							v-if="deliveryNote.shipping_data?.[0].tracking_urls.length"
							:href="deliveryNote.shipping_data?.[0].tracking_urls[0]"
							class="underline"
							target="_blank"
							rel="noopener">
							{{ deliveryNote.shipping_data?.[0].trackings?.[0] }}
							<FontAwesomeIcon
								icon="fal fa-external-link-alt"
								class="opacity-50 group-hover:opacity-100"
								fixed-width
								aria-hidden="true" />
						</a>
						<span v-else>
							{{ deliveryNote.shipping_data?.[0].trackings?.[0] }}
						</span>
					</template>
				</div>
			</div>
			<div v-else></div>
		</template>

		<!-- <template #cell(delivery)="{ item: deliveryNote }">
            <pre class="text-xs">
                {{ deliveryNote.shipping_data }}
            </pre>
        </template> -->


		  <template  #cell(sort_packer)="{ item: deliveryNote }">
            <div class="flex gap-x-4 items-center">
                    <dl v-tooltip="trans('Packer name')"
					  	class=" bg-indigo-100  flex items-center w-fit px-2 flex-none gap-x-1.5"
                        xclass=" border-l-4 border-indigo-300 bg-indigo-100 pl-1 flex items-center w-fit pr-3 flex-none gap-x-1.5">
                        <dd class="text-gray-500">
                            {{ deliveryNote?.packer?.name }}
                        </dd>
                    </dl>
            </div>
		</template>


		 <template  #cell(sort_picker)="{ item: deliveryNote }">
            <div class="flex gap-x-4 items-center">
                    <dl
                        class=" bg-indigo-100  flex items-center w-fit px-2 flex-none gap-x-1.5">
                        <dd class="text-gray-500">
                          {{ deliveryNote?.picker?.name }}
                        </dd>
                    </dl>
            </div>
		</template>



		  <template  #cell(sort_trolleys)="{ item: deliveryNote }">
            <!-- Section: Trolleys -->
            <dl>
                <dd class="flex flex-wrap gap-y-0.5 gap-x-2 text-gray-500 align-middle px-2">
                    <div v-for="trolley in deliveryNote.trolleys"
                        class="bg-pink-400/30 rounded-sm px-1 text-pink-800 flex items-center">
                          {{ trolley.name }}
                    </div>
                </dd>
            </dl>
        </template>

		 <template #cell(sort_picked_bays)="{ item: deliveryNote }">
			<dl class="border-l-4 border-pink-300 bg-pink-100 pl-1 flex items-center w-fit pr-3 flex-none gap-x-1.5">
				<dd v-if="deliveryNote?.picked_bays?.length" class="text-gray-500">
					<span v-for="bay in deliveryNote?.picked_bays" :key="bay.id"
						xclass="underline opacity-70 hover:opacity-100">
						{{ bay?.name ?? '-' }}
					</span>
				</dd>
			</dl>
		</template>

		<template #cell(country)="{ item: deliveryNote }">
			<div v-if="deliveryNote.country" class="flex items-center gap-1.5">
				<span class="font-mono text-xs font-medium text-gray-600">{{ deliveryNote.country.code }}</span>
				<span class="text-sm text-gray-900">{{ deliveryNote.country.name }}</span>
			</div>
		</template>

		<template #cell(weight_bracket)="{ item: deliveryNote }">
			<span v-if="deliveryNote.weight_bracket" class="text-sm text-gray-700">
				{{ deliveryNote.weight_bracket }}
			</span>
		</template>

		<template #cell(batch_code_sku)="{ item: deliveryNote }">
			<Link
				v-if="deliveryNote.org_stock_route"
				:href="route(deliveryNote.org_stock_route.name, deliveryNote.org_stock_route.parameters)"
				class="primaryLink">
				{{ deliveryNote.batch_code_sku }}
			</Link>
			<span v-else class="text-sm text-gray-700">{{ deliveryNote.batch_code_sku || '—' }}</span>
		</template>

		<template #cell(batch_code_expiry_date)="{ item: deliveryNote }">
			<span class="text-sm text-gray-700">{{ useFormatTime(deliveryNote.batch_code_expiry_date) || '—' }}</span>
		</template>
	</Table>

	<Modal
		:isOpen="!!isModalPick"
		@close=";(isModalPick = null), (isErrorPicker = null)"
		width="w-full max-w-lg">
		<div class="sm:flex sm:items-start w-full">
			<div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
				<DialogTitle as="h3" class="text-base font-semibold">
					{{ trans("Are you sure to pick the delivery?") }}
				</DialogTitle>
				<div class="mt-2">
					<p class="text-sm text-gray-500">
						{{ trans("This action will pick the delivery note") }}
						<strong>{{ isModalPick?.reference }}</strong> {{ trans("with") }}
						{{ isModalPick?.number_items }} {{ trans("items") }}
					</p>
				</div>

				<div class="mt-5 sm:flex sm:flex-row-reverse gap-x-2">
					<Button
						:loading="isLoadingPick"
						@click="() => onClickPick()"
						:label="trans('Yes')"
						full />

					<Button
						type="tertiary"
						icccon="far fa-arrow-left"
						:label="trans('cancel')"
						@click="() => (isModalPick = null)" />
				</div>

				<p v-if="isErrorPicker" class="mt-2 text-xs text-red-500 italic">
					*{{ isErrorPicker }}
				</p>
			</div>
		</div>
	</Modal>
</template>
