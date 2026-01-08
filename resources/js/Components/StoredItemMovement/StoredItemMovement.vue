<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
-->

<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import Modal from "@/Components/Utils/Modal.vue"
import StoredItemMovementForm from './StoredItemMovementForm.vue'
import { routeType } from '@/types/route'
import { notify } from "@kyvg/vue3-notification"
import { router } from "@inertiajs/vue3"


const props = defineProps<{
    locationRoute: {
        index: routeType
    }
    palletRoute: {
        index: routeType
    }
    pallet: {}
    updateRoute: routeType
}>()

const sendToServer = async (data) => {
    router.patch(route(props.updateRoute.name, props.updateRoute.parameters), { ...data }, {
        onError: (e) => {
            notify({
                title: "Failed to move stored items",
                text: e.quantity,
                type: "error"
            })
        },
        onSuccess: (e) => {
            isModalOpen.value = false
            form.errors = {}
        },
        onBefore: () => {
            form.processing = true
        },
        onFinish: () => {
            form.processing = false
        }
    })
}

const isModalOpen = ref(false)
const form = useForm({ quantity: props.pallet.stored_items_quantity, pallet_id: props.pallet.id, location_id: props.pallet.location_id, type: 'pallet' })
</script>

<template>
    <div class="" v-tooltip="'Move stored item'">
        <Button :icon="'far fa-person-dolly'" @click="() => isModalOpen = true" type="secondary" />
    </div>

    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-1/2">
        <Button class="sr-only" />
        <div class="space-y-4">
            <StoredItemMovementForm :form="form" :palletRoute="palletRoute" :locationRoute="locationRoute"
                :pallet="pallet" @onSave="sendToServer" />
        </div>
    </Modal>
</template>
