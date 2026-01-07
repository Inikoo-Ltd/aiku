<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:46:53 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import Popover from '@/Components/Popover.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Multiselect from "@vueform/multiselect"
import { Link } from "@inertiajs/vue3"
import { get } from 'lodash-es'
import axios from 'axios'
import { PalletDelivery } from "@/types/pallet-delivery"
import { faNarwhal, faSeedling, faShare, faSpellCheck, faTruck, faCheck, faCheckDouble, faCross, faTruckRamp } from '@fal'
library.add(faNarwhal, faSeedling, faShare, faSpellCheck, faTruck, faCheck, faCheckDouble, faCross, faTruckRamp)


const props = defineProps<{
    title: string,
    pageHead: {},
    // tabs: {
    //     current: string
    //     navigation: {}
    // }
    data: {}
}>()


import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionChild,
    TransitionRoot,
} from "@headlessui/vue"
import TablePalletReturns from "@/Components/Tables/Grp/Org/Fulfilment/TablePalletReturns.vue"


const isOpen = ref(false)
const warehouseValue = ref(null)
const errorMessage = ref(null)

function setIsOpen(value) {
    isOpen.value = value
}

const webUserForm = useForm({
    // username: props["customer"].email,
    username: null,
    password: null,
})


const sendWarehouse = async (data: object) => {
    try {
        const response = await axios.post(
            route(data.route?.name, data.route?.parameters),
            { warehouse_id: get(warehouseValue.value, 'id') }
        )
        router.visit(route(response.data.route.name, response.data.route.parameters))
    } catch (error) {
        console.log('error', error)
        errorMessage.value = error.response.data.message
    }
}


const warehouseChange = (value) => {
    errorMessage.value = null
    warehouseValue.value = value
}


</script>

<template>
    <Head :title="capitalize(title)" />
    <!-- <pre>{{ usePage().props.data }}</pre> -->
    <PageHeading :data="pageHead">
        <template #button-create-delivery="{ action }">
            <div v-if="action.options.warehouses.data.length > 1" class="relative">
                <Popover :width="'w-full'" ref="_popover">
                    <template #button>
                        <Button :style="action.style" :label="action.label" :icon="action.icon"
                            :iconRight="action.iconRight" :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip" />
                    </template>
                    <template #content="{ close: closed }">
                        <div class="w-[250px]">
                            <Multiselect v-model="warehouseValue" :searchable="true" :object="true" valueProp="id"
                                :options="action.options.warehouses.data" track-by="name" label="name"
                                @change="(value) => warehouseChange(value)" :mode="'single'" ref="multiselect"
                                placeholder="select a warehouse" class="w-full" />
                            <p v-if="errorMessage" class="mt-2 text-sm text-red-600" id="email-error">
                                {{ errorMessage }}
                            </p>
                            <div class="flex justify-end mt-3">
                                <Button :style="'save'" :label="'save'" @click="() => sendWarehouse(action)" />
                            </div>

                        </div>
                    </template>
                </Popover>
            </div>
            <div v-else>
                <Link :href="route(action.route?.name, action.route?.parameters)" :method="'post'" :as="'button'">
                    <Button :style="action.style" :label="action.label" :icon="action.icon" :iconRight="action.iconRight"
                        :key="`ActionButton${action.label}${action.style}`" :tooltip="action.tooltip" />
                </Link>
            </div>

        </template>
    </PageHeading>
    <!--
      Todo: modal forms for quick creation of models
      -->

    <TransitionRoot as="template" :show="isOpen">
        <Dialog :open="isOpen" @close="setIsOpen" as="div" class="relative z-10">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500/75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
                            <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900">Create web user
                            </DialogTitle>

                            <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                <div class="sm:col-span-4">
                                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                                    <div class="mt-1">
                                        <input v-model="webUserForm.username" id="username" name="username" type="text"
                                            autocomplete="email"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    </div>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>

    <TablePalletReturns :data="data" tab="pallet_returns" />
</template>


<style src="@vueform/multiselect/themes/default.css"></style>

<style scoped>
/* Style for multiselect globally */
:deep(.multiselect-option.is-selected),
:deep(.multiselect-option.is-selected.is-pointed) {
    background: var(--ms-option-bg-selected, #6366f1) !important;
    color: var(--ms-option-color-selected, #fff) !important;
}

:deep(.multiselect-option.is-selected.is-disabled) {
    background: var(--ms-option-bg-selected-disabled, #c7d2fe);
    color: var(--ms-option-color-selected-disabled, #818cf8);
}

:deep(.multiselect.is-active) {
    border: var(--ms-border-width-active, var(--ms-border-width, 1px)) solid var(--ms-border-color-active, var(--ms-border-color, #d1d5db));
    box-shadow: 0 0 0 var(--ms-ring-width, 3px) var(--ms-ring-color, rgba(99, 102, 241, 0.188));
}
</style>
