<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrashAlt } from "@fal"
import { faExclamationTriangle } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faTrashAlt, faExclamationTriangle)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        reference: string
        orders: number
        invoices: number
        route: routeType
    }
}>()

const deleteForm = useForm({
    reason: "",
    reference: ""
})

const submitDelete = () => {
    deleteForm.post(route(props.fieldData.route.name, props.fieldData.route.parameters))
}
</script>

<template>
    <div class="max-w-2xl space-y-4">
        <div v-if="fieldData.orders || fieldData.invoices" class="flex items-center gap-4 rounded-lg bg-red-600 p-6 text-white shadow-lg ring-4 ring-red-300 animate-pulse">
            <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-5xl" fixed-width aria-hidden="true" />
            <div>
                <p class="text-2xl font-bold uppercase">{{ ctrans("This customer has :orders orders and :invoices invoices", { orders: fieldData.orders, invoices: fieldData.invoices }) }}</p>
                <p class="mt-1 text-base">{{ ctrans("Only do this for a written erasure request from the customer. Orders and invoices are kept exactly as they are, including the name and address on them.") }}</p>
            </div>
        </div>
        <p class="text-sm text-gray-600">
            {{ ctrans("Names, email, phone, addresses, notes and login will be erased and the customer deleted. Invoices, orders and payments are kept. This can not be undone.") }}
        </p>
        <PureTextarea v-model="deleteForm.reason" :placeholder="ctrans('Reason (e.g. erasure request received on ...)')" :rows="3" />
        <p v-if="deleteForm.errors.reason" class="text-xs text-red-500">{{ deleteForm.errors.reason }}</p>
        <div>
            <p class="text-sm mb-1">{{ ctrans("Type the customer reference") }} <strong>{{ fieldData.reference }}</strong> {{ ctrans("to confirm") }}</p>
            <PureInput v-model="deleteForm.reference" :placeholder="fieldData.reference" />
            <p v-if="deleteForm.errors.reference" class="text-xs text-red-500">{{ deleteForm.errors.reference }}</p>
        </div>
        <Button type="delete" icon="fal fa-trash-alt" :label="ctrans('Delete customer')" :loading="deleteForm.processing"
            :disabled="!deleteForm.reason || deleteForm.reference !== fieldData.reference"
            @click="submitDelete" />
    </div>
</template>
