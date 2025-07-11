<script setup lang="ts">
import PureAddress from "@/Components/Pure/PureAddress.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { computed, ref } from "vue"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import { Address, AddressManagement } from "@/types/PureComponent/Address"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle as faCheckCircleSolid } from "@fas"
import { faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch, faCheckCircle, fal } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { useTruncate } from "@/Composables/useTruncate"
import { faThumbtack as faThumbtackSolid } from "@fas"

library.add(faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch, faCheckCircle, faThumbtack, faCheckCircleSolid)

const props = defineProps<{
    updateRoute: routeType
    keyPayloadEdit?: string
    address_modal_title?: string
    address: {}
    options: {}[]
}>()

const emits = defineEmits<{
    (e: "onDone"): void
    (e: "onHasChange"): void
}>()


const xxxx = ref({...props.address})

// Method: Edit address history
const isEditLoading = ref<boolean>(false)
const onSubmitEditAddress = () => {
    if (!props.updateRoute) {
        notify({
            title: trans("Failed to update the address"),
            text: trans("Please contact the administrator to fix."),
            type: "error",
        })

        return
    }

    const filterDataAddress = { ...xxxx.value }
    delete filterDataAddress.formatted_address
    delete filterDataAddress.country
    delete filterDataAddress.country_code
    delete filterDataAddress.id
    delete filterDataAddress.can_edit
    delete filterDataAddress.can_delete

    router.patch(
        route(props.updateRoute.name, props.updateRoute.parameters),
        {
            [props.keyPayloadEdit || "address"]: filterDataAddress
        },
        {
            preserveScroll: true,
            onStart: () => isEditLoading.value = true,
            onFinish: () => {
                isEditLoading.value = false

            },
            onSuccess: () => {
                emits("onHasChange")
                notify({
                    title: trans("Success"),
                    text: trans("Successfully update the address."),
                    type: "success"
                })
            },
            onError: () => notify({
                title: trans("Something went wrong"),
                text: trans("Failed to update the address, try again."),
                type: "error"
            })
        }
    )
}



</script>

<template>
    <div class="min-h-[400px] px-2 py-1 overflow-auto">
        <div class="xflex justify-between items-center xborder-b border-gray-300 py-2">
            <div class="text-2xl font-bold text-center xflex gap-x-2 mb-6">
                {{ address_modal_title ?? trans('Manage address') }}
            </div>

            <PureAddress
                v-model="xxxx"
                :options="options"
                xfieldLabel
            />
        </div>
    </div>

    <Button
        @click="onSubmitEditAddress"
        full
        label="Save changes"
        :loading="isEditLoading"
    />

    <div class="border-t border-gray-300 mt-3 pt-3">
        <Button @click="emits('onDone')" type="tertiary" label="done" full>

        </Button>
    </div>
</template>

<style scoped></style>