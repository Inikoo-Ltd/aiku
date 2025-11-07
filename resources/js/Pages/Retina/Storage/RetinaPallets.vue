<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import Popover from '@/Components/Popover.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Multiselect from "@vueform/multiselect"

import '@/Composables/Icon/PalletStateEnum'
import '@/Composables/Icon/PalletStatusEnum'
import { Link } from "@inertiajs/vue3"
import RetinaTablePallets from "@/Components/Tables/Retina/RetinaTablePallets.vue"
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot, } from "@headlessui/vue"
import { faStickyNote, faPallet, faUser, faNarwhal, faTruckCouch, faFileInvoiceDollar, faSignOutAlt, faPaperclip, faPaperPlane, faShare } from '@fal'
library.add( faStickyNote, faUser, faNarwhal, faTruckCouch, faPallet, faFileInvoiceDollar, faSignOutAlt, faPaperclip, faPaperPlane, faShare )

const props = defineProps<{
    title: string,
    pageHead: {},
    data: {}
}>()

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

}


const warehouseChange = (value) => {
    errorMessage.value = null
    warehouseValue.value = value
}


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-create-delivery="{ action }">
            <div v-if="action.options.warehouses.data.length > 1" class="relative">
                <Popover :width="'w-full'" ref="_popover">
                    <template #button>
                        <Button :style="action.style" :label="action.label" :icon="action.icon"
                            :iconRight="action.iconRight"
                            :key="`ActionButton${action.label}${action.style}`"
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
                <Link :href="route(action.route?.name, action.route?.parameters)" :method="'post'"
                    :as="'button'">
                    <Button :style="action.style" :label="action.label" :icon="action.icon"
                        :iconRight="action.iconRight" :key="`ActionButton${action.label}${action.style}`"
                        :tooltip="action.tooltip" />
                </Link>
            </div>

        </template>
    </PageHeading>

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
    
    <RetinaTablePallets :data="data" tab="pallets" />
</template>
