<script setup lang="ts">
import { ref } from "vue"
import { routeType } from "@/types/route"
import Select from "primevue/select"
import { router } from '@inertiajs/vue3'
import { trans } from "laravel-vue-i18n"
import Button from "../Elements/Buttons/Button.vue"
import { InputNumber } from "primevue"
import { notify } from "@kyvg/vue3-notification"


const model = defineModel()

const props = defineProps<{
	routeSubmit: routeType
    currency: {

    }
    options: {}[]
    types: {}[]
}>()


const amount = ref<number>(0)
const privateNote = ref<string>("")
const reasonToDecrease = ref(null)
const decreaseType = ref(null)

const resetForm = () => {
	amount.value = 0
	privateNote.value = ""
	reasonToDecrease.value = null
	decreaseType.value = null
}

const closeModal = () => {
	model.value = false
	resetForm() 
}

const isLoading = ref(false)
const onSubmitDecrease = () => {
    // console.log(amount.value, privateNote.value, reasonToDecrease.value, )
    if (props.routeSubmit.name) {
        router[props.routeSubmit.method || 'patch'](
            route(props.routeSubmit.name, props.routeSubmit.parameters),
            {
                amount: -amount.value,
                notes: privateNote.value,
                type: decreaseType.value,
                reason: reasonToDecrease.value,
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
                    notify({
                        title: trans("Something went wrong"),
                        text: "Contact administrator.",
                        type: "error",
                    })
                },
            }
        )
    } else {
        console.error("No route defined for balance increase")
    }
}
</script>

<template>
    <div class="p-6">
        <h2 class="text-3xl font-bold text-center">{{ trans("Decrease Balance") }}</h2>
        <p class="text-base text-gray-500 italic mb-6 text-center">{{ trans("Enter the details to decrease balance") }}</p>

        <div class="space-y-6">
            <!-- Reason -->
            <div>
                <label for="amount" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Reason to decrease") }}
                </label>
                <Select
                    v-model="reasonToDecrease"
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
                    v-model="decreaseType"
                    :options="types ?? []"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="trans('Select your type')"
                    class="w-full"
                />
            </div>

            <!-- Amount -->
            <div>
                <label for="amount" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Amount to decrease") }}
                </label>

                <!-- <input
                    type="number"
                    id="amount"
                    name="amount"
                    :placeholder="trans('Enter amount')"
                    v-model.number="amount"
                    class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500" /> -->
                <InputNumber
                    v-model="amount"
                    inputId="currency-us"
                    mode="currency"
                    :currency="currency.code"
                    :maxFractionDigits="2"
                    locale="en-US"
                    :min="0"
                    prefix="-"
                    fluid
                />
            </div>

            <!-- Note -->
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
                @click="() => onSubmitDecrease()"
                full
                :loading="isLoading"
                :disabled="amount <= 0"
                v-tooltip="amount <= 0 ? trans('Add amount to submit') : ''"
            >
            </Button>
        </div>
    </div>
</template>