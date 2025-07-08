<script setup lang="ts">
import { ref } from "vue"
import Modal from "@/Components/Utils/Modal.vue"
import { routeType } from "@/types/route"
import Select from "primevue/select"
import { router } from '@inertiajs/vue3'
import { trans } from "laravel-vue-i18n"
import Button from "../Elements/Buttons/Button.vue"


const model = defineModel()

const props = defineProps<{
	type?: string
	updateBalanceRoute?: routeType
}>()

const amount = ref<number | null>(null)
const privateNote = ref<string>("")
const loading = ref(false)

const increaseBalance = ref(null)
const decreaseBalance = ref(null)

const increase = ref([
	{ name: "Pay for the shipping of a return", type: "pay_return" },
	{ name: "Compensate Customer", type: "compensation" },
	{ name: "Transfer from other customer account", type: "transfer_out" },
	{ name: "Other Reason", type: "remove_funds_other" },
])

const decrease = ref([
	{ name: "Customer want money back", type: "money_back" },
	{ name: "Transfer to other customer account", type: "transfer_in" },
	{ name: "Other Reason", type: "remove_funds_other" },
])

const resetForm = () => {
	amount.value = null
	privateNote.value = ""
	increaseBalance.value = null
	decreaseBalance.value = null
}

const closeModal = () => {
	model.value = false
	resetForm() 
}

const onSubmitIncrease = () => {
	const chosenType =
		props.type === "increase"
			? increaseBalance.value?.type
			: decreaseBalance.value?.type

	const payload = {
		amount: amount.value,
		notes: privateNote.value,
		type: chosenType,
	}

	router.patch(
		route(props.updateBalanceRoute.name, props.updateBalanceRoute.parameters),
		payload,
		{
			onStart: () => {
				loading.value = true
			},
			onFinish: () => {
				loading.value = false
			},
			preserveScroll: true,
			onSuccess: () => {
				closeModal()
			},
			onError: (errors) => {
				console.error("Error updating balance:", errors)
			},
		}
	)
}
</script>

<template>
    <div class="p-6">

        <h2 class="text-3xl font-bold text-gray-800 mb-4">Decrease Balance</h2>
        <p class="text-lg text-gray-600 mb-6">Enter the details to decrease balance:</p>
        <div class="space-y-6">
            <Select
                v-model="decreaseBalance"
                :options="decrease"
                optionLabel="name"
                placeholder="Select your reason"
                class="w-full" />

            <div>
                <label for="amount" class="block text-gray-700 font-medium mb-2">
                    Amount to withdraw
                </label>
                <input
                    type="number"
                    id="amount"
                    name="amount"
                    placeholder="Enter amount"
                    v-model.number="amount"
                    class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
                <label for="privateNote" class="block text-gray-700 font-medium mb-2">
                    Private Note
                </label>
                <textarea
                    id="privateNote"
                    name="privateNote"
                    rows="4"
                    placeholder="Add any private notes here..."
                    v-model="privateNote"
                    class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
        </div>

        <div class="mt-8 flex justify-end space-x-4">
            <Button
                :label="trans('Cancel')"
                type="negative"
                @click="closeModal"
            >
            </Button>

            <Button
                :label="trans('Submit')"
                type="primary"
                @click="() => onSubmitIncrease()"
                full
            >
            </Button>
        </div>
    </div>
</template>