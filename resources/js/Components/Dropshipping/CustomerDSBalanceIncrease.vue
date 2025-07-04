<script setup lang="ts">
import { ref } from "vue"
import { routeType } from "@/types/route"
import Select from "primevue/select"
import { router } from '@inertiajs/vue3'
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"


const model = defineModel()

const props = defineProps<{
	routeSubmit: routeType
    currency: {

    }
    options: {}[]
    types: {}[]
}>()


const amount = ref<number | null>(null)
const privateNote = ref<string>("")
const increaseReason = ref(null)
const increaseType = ref(null)

// const increase = ref([
// 	{ name: "Pay for the shipping of a return", type: "pay_return" },
// 	{ name: "Compensate Customer", type: "compensation" },
// 	{ name: "Transfer from other customer account", type: "transfer_out" },
// 	{ name: "Other Reason", type: "remove_funds_other" },
// ])

const resetForm = () => {
	amount.value = null
	privateNote.value = ""
	increaseReason.value = null
	increaseType.value = null
}

const closeModal = () => {
	model.value = false
	resetForm() 
}

const isLoading = ref(false)
const onSubmitIncrease = () => {
    console.log(amount.value, privateNote.value, increaseReason.value, )
    router[props.routeSubmit.method || 'patch'](
        route(props.routeSubmit.name, props.routeSubmit.parameters),
        {
            amount: amount.value,
            notes: privateNote.value,
            reason: increaseReason.value,
            type: increaseType.value,
        },
        {
            onStart: () => {
                isLoading.value = true
            },
            onFinish: () => {
                isLoading.value = false
            },
            preserveScroll: true,
            onSuccess: () => {
                model.value = false
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
        <h2 class="text-3xl font-bold text-center">{{ trans("Increase Balance") }}</h2>
        <p class="text-base text-gray-500 italic mb-6 text-center">{{ trans("Enter the details to increase balance") }}</p>

        <div class="space-y-6">
            <div>
                <label for="amount" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Reason to deposit") }}
                </label>
                <Select
                    v-model="increaseReason"
                    :options="options ?? []"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="trans('Select your reason')"
                    class="w-full"
                />
            </div>

            <!-- Type -->
            <div>
                <label for="type" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Select type of the decrease:") }}
                </label>
                <Select
                    v-model="increaseType"
                    :options="types ?? []"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="trans('Select your type')"
                    class="w-full"
                />
            </div>

            <div>
                <label for="amount" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Amount to deposit") }}
                </label>

                <input
                    type="number"
                    id="amount"
                    name="amount"
                    :placeholder="trans('Enter amount')"
                    v-model.number="amount"
                    class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
                <label for="privateNote" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Private Note") }}
                </label>
                <textarea
                    v-model="privateNote"
                    id="privateNote"
                    name="privateNote"
                    rows="4"
                    :placeholder="trans('Add any private notes here...')"
                    class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
        </div>

        <div class="mt-8 flex justify-end space-x-4">
            <Button
                :label="trans('Cancel')"
                type="negative"
                @click="() => closeModal()"
            >
            </Button>

            <Button
                :label="trans('Submit')"
                type="primary"
                @click="() => onSubmitIncrease()"
                full
                :loading="isLoading"
            >
            </Button>
        </div>
    </div>
</template>