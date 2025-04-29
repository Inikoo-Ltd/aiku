<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableCustomerPlatformOrders from "@/Components/Tables/Grp/Org/Ordering/TableCustomerPlatformOrders.vue"
import { Head, router } from "@inertiajs/vue3"
import { PageHeading as PageHeadingTS } from "@/types/PageHeading"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import Popover from "@/Components/Utils/Popover.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { RouteParams } from "@/types/route-params"

const props = defineProps<{
	data: {}
	title: string
	pageHead: PageHeadingTS
	tabs: {
		current: string
		navigation: {}
	}
	platform: {
		data: {
			id: number
		}
	}
    attachRoute:{}
}>()

// Section: Popup Add Order
const isLoadingSubmit = ref(false)
const selectedClient = ref(null)
const listClient = ref<{}[]>([])
const onSubmitAddOrder = (close: Function) => {
	router.post(
		route('grp.models.customer-client.platform-order.store', { customerClient: selectedClient.value, platform: props.platform?.data.id }),
		{ },
		{
			onStart: () => {
				isLoadingSubmit.value = true
			},
			onError: (error) => {
				isLoadingSubmit.value = false
				notify({
					title: trans("Something went wrong"),
					text: error.message,
					type: "error",
				})
			},
			onSuccess: (response) => {
				
			}
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-add-order="{ action}">
			<Popover>
				<template #button="{ open }">
					<Button icon="fas fa-plus" :disabled="open" :loading="isLoadingSubmit">
						<template #label>{{ trans('Add order') }}</template>
					</Button>
				</template>

				<template #content="{ close: closed }">
                        <div class="w-[350px]">
							<div class="font-semibold text-center text-xl mb-4">
								Add order
							</div>
                            <span class="text-sm px-1 my-2 flex items-start">
								<FontAwesomeIcon icon="fas fa-asterisk" class="-ml-2 -mr-0.5 mt-1 text-red-500 h-[7px]" fixed-width aria-hidden="true" />
								{{ trans('Client') }}:
							</span>
                            <div class="">
                                <PureMultiselectInfiniteScroll
                                    v-model="selectedClient"
                                    :fetchRoute="{
										name: 'grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.manual.index',
										parameters: {
											organisation: (route().params as RouteParams).organisation,
											shop: (route().params as RouteParams).shop,
											customer: (route().params as RouteParams).customer,
											platform: (route().params as RouteParams).platform
										}
									}"
                                    :placeholder="trans('Select client to add order')"
                                    valueProp="id"
									required
                                    @optionsList="(options: {}[]) => listClient = options"
                                >
                                    <template #singlelabel="{ value }">
                                        <div class="w-full text-left pl-4">{{ value.name }} <span class="text-sm text-gray-400">(bbbb)</span></div>
                                    </template>

                                    <template #option="{ option, isSelected, isPointed }">
                                        <div class="">{{ option.name }} <span class="text-sm text-gray-400">(xxxx)</span></div>
                                    </template>
                                </PureMultiselectInfiniteScroll>
                            </div>

                            <div class="flex justify-end mt-3">
                                <Button
									v-tooltip="!selectedClient ? trans('Select client to add order') : ''"
                                    @click="() => onSubmitAddOrder(closed)"
                                    :type="'save'"
                                    :loading="isLoadingSubmit"
                                    :disabled="!selectedClient"
                                    label="Submit"
                                    full
                                />
                            </div>
                        </div>
                    </template>
			</Popover>
		</template>
	</PageHeading>
	<TableCustomerPlatformOrders :data="data" />

</template>
