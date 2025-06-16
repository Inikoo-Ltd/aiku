<script setup lang="ts">
import PureAddress from "@/Components/Pure/PureAddress.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { ref } from "vue"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import { Address, AddressManagement } from "@/types/PureComponent/Address"
// import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle as faCheckCircleSolid } from "@fas"
import { faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch, faCheckCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { cloneDeep } from "lodash-es"

library.add(faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch, faCheckCircle, faThumbtack, faCheckCircleSolid)

const props = defineProps<{
    updateRoute: routeType
    addresses: AddressManagement
    address: Address
    keyPayloadEdit?: string
    address_modal_title?: string
}>()

console.log('eweqw', props.address)

const emits = defineEmits<{
    (e: "onDone"): void
    (e: "onHasChange"): void
}>()

// Method: Edit address history
const isEditAddress = ref(false)
const selectedAddress = ref<Address>(cloneDeep(props.address))
const isSubmitAddressLoading = ref<boolean>(false)
const onSubmitEditAddress = (address: Address) => {
    if (!props.updateRoute) {
        notify({
            title: trans("Failed to update the address"),
            text: trans("Please contact the administrator to fix."),
            type: "error",
        })

        return
    }

    const filterDataAddress = { ...address }
    delete filterDataAddress.formatted_address
    delete filterDataAddress.country
    delete filterDataAddress.country_code

    router.patch(
        route(props.updateRoute.name, props.updateRoute.parameters),
        {
            [props.keyPayloadEdit || "address"]: filterDataAddress
        },
        {
            preserveScroll: true,
            onStart: () => isSubmitAddressLoading.value = true,
            onFinish: () => {
                isSubmitAddressLoading.value = false
                // isModalAddress.value = false
                isEditAddress.value = false

            },
            onSuccess: () => {
                selectedAddress.value = cloneDeep(props.address)
                emits("onHasChange")
                notify({
                    title: trans("Success"),
                    text: trans("Successfully update the address."),
                    type: "success"
                })
            },
            onError: () => notify({
                title: trans("Failed"),
                text: trans("Failed to update the address, try again."),
                type: "error"
            })
        }
    )
}

// Method: Select address history
// const isSelectAddressLoading = ref<number | boolean | null | undefined>(false)
// const onSelectAddress = (selectedAddress: Address) => {
//     router.patch(
//         route(props.addresses.routes_list.switch_route.name, props.addresses.routes_list.switch_route.parameters),
//         {
//             delivery_address_id: selectedAddress.id
//         },
//         {
//             onStart: () => isSelectAddressLoading.value = selectedAddress.id,
//             onSuccess: () => {
//                 emits("onHasChange")
//             },
//             onFinish: () => isSelectAddressLoading.value = false
//         }
//     )
// }

</script>

<template>
    <div class="px-2 py-1 ">
        <div class="flex justify-between items-center border-b border-gray-300 py-2">
            <div class="text-2xl font-bold text-center flex gap-x-2">
                {{ address_modal_title ?? trans('Manage address') }}

                <div class="relative">
                    <Transition name="slide-to-right">
                        <div v-if="isEditAddress" class="inline text-gray-400 italic text-base font-normal">({{
                            trans("Edit") }})</div>
                        <div v-else></div>
                    </Transition>
                </div>
            </div>
        </div>

        <div class="h-[360px] lg:h-[600px] px-2 overflow-y-auto relative transition-all">

            <!-- Section: Edit address -->
            <div v-if="true || isEditAddress"
                :key="'edit' + selectedAddress?.id"
                class="col-span-2 relative py-4 h-fit grid lg:grid-cols-2 gap-y-5 gap-x-4">
                <div class="overflow-hidden relative text-xs rounded-lg h-fit transition-all"
                    :class="[
                        selectedAddress?.id ? 'border border-gray-300 ring-2 ring-offset-4 ring-indigo-500' : 'ring-1 ring-gray-300'
                    ]
                ">
                    <div class="flex justify-between border-b border-gray-300 px-3 py-2 bg-gray-100">
                        <div class="flex gap-x-1 items-center relative">
                            <div v-if="[...addresses.address_list?.data].find(xxx => xxx.id === selectedAddress?.id)?.label"
                                class="font-semibold text-sm whitespace-nowrap">
                                {{[...addresses.address_list.data].find(xxx => xxx.id === selectedAddress?.id)?.label }}
                            </div>
                            <div v-else class="text-xs italic whitespace-nowrap text-gray-400">
                                ({{ trans("No label") }})
                            </div>
                        </div>
                    </div>

                    <div v-html="selectedAddress?.formatted_address" class="px-3 py-2"></div>
                </div>

                <!-- Form: Edit address -->
                <div class="relative bg-gray-100 p-4 rounded-md">
                    <PureAddress v-model="selectedAddress" :options="addresses.options" fieldLabel />
                    <div class="mt-6 flex justify-center">
                        <Button @click="() => onSubmitEditAddress(selectedAddress)" :label="trans('Save')" :loading="isSubmitAddressLoading" full />
                    </div>
                </div>
            </div>

        </div>

    </div>

    <div class="border-t border-gray-300 pt-3">
        <Button @click="emits('onDone')" type="tertiary" label="done" full>

        </Button>
    </div>
</template>