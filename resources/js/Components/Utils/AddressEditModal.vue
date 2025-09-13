<script setup lang="ts">
import PureAddress from "@/Components/Pure/PureAddress.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { ref } from "vue"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import { Address, AddressManagement } from "@/types/PureComponent/Address"
import { faCheckCircle as faCheckCircleSolid } from "@fas"
import { faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch, faCheckCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { cloneDeep } from "lodash-es"
import { Checkbox } from "primevue"
import InformationIcon from "./InformationIcon.vue"

library.add(faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch, faCheckCircle, faThumbtack, faCheckCircleSolid)

const props = defineProps<{
    updateRoute: routeType
    addresses: AddressManagement
    address: Address
    keyPayloadEdit?: string
    title?: string
}>()


const emits = defineEmits<{
    (e: "onDone"): void
    (e: "submitted"): void
}>()

// Method: Edit address history
const isChangeTheParent = ref(false)
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
            [props.keyPayloadEdit || "address"]: filterDataAddress,
            update_parent: isChangeTheParent.value,
        },
        {
            preserveScroll: true,
            onStart: () => isSubmitAddressLoading.value = true,
            onFinish: () => {
                isSubmitAddressLoading.value = false

            },
            onSuccess: () => {
                selectedAddress.value = cloneDeep(props.address)
                emits("submitted")
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


</script>

<template>
    <div class="px-2 py-1 ">
        <div class="font-semibold text-xl mb-3 text-center gap-2">
            {{ title ?? trans("Edit delivery address") }}
        </div>

        <div class="px-2 overflow-y-auto relative transition-all">
            <PureAddress v-model="selectedAddress" :options="addresses.options" xfieldLabel />

            <div class="mt-3 flex items-center">
                <Checkbox v-model="isChangeTheParent" inputId="is_change_the_parent" name="is_change_the_parent" binary />
                <label for="is_change_the_parent" class="ml-1.5 text-sm cursor-pointer select-none">{{ trans('Update the parent address') }}</label>
                <InformationIcon :information="trans('If not checked, the changes will only apply to this order.')" class="ml-1 text-sm" />
            </div>

            <div class="mt-6 flex justify-center">
                <Button @click="() => onSubmitEditAddress(selectedAddress)" :label="trans('Save')" :loading="isSubmitAddressLoading" full />
            </div>
        </div>

    </div>

    <!-- <div class="border-t border-gray-300 pt-3">
        <Button @click="emits('onDone')" type="tertiary" label="done" full>

        </Button>
    </div> -->
</template>