<script setup lang="ts">
import {inject, ref} from "vue"
import {routeType} from "@/types/route"
import Select from "primevue/select"
import {router} from "@inertiajs/vue3"
import {trans} from "laravel-vue-i18n"
import Button from "../Elements/Buttons/Button.vue"
import {InputNumber} from "primevue"
import {notify} from "@kyvg/vue3-notification"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"


const model = defineModel()

const props = defineProps<{
    routeSubmit: routeType
    currency: {}
    options: {}[]
    types?: {}[]
    balance: number
}>()

const locale = inject('locale', aikuLocaleStructure)

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
    if (props.routeSubmit.name) {
        router[props.routeSubmit.method || "patch"](
            route(props.routeSubmit.name, props.routeSubmit.parameters),
            {
                amount: -amount.value,
                notes: privateNote.value,
                type: decreaseType.value,
                reason: reasonToDecrease.value
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
                        type: "error"
                    })
                }
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
        <p class="text-base text-gray-500 italic mb-6 text-center">{{
                trans("Enter the details to decrease balance")
            }}</p>

        <div class="space-y-6">
            <!-- Type -->
            <div v-if="types?.length > 0">
                <label for="amount" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Type of payment") }}
                </label>
                <Select
                    v-model="decreaseType"
                    :options="types ?? []"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="trans('Select your type of payment')"
                    class="w-full"
                />
            </div>

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

            <!-- Amount -->
            <div>
                <label for="amount" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Amount to decrease") }}
                </label>

                <InputNumber
                    v-model="amount"
                    @input="(e) => amount = e?.value ?? 0"
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

        <!-- Section: Preview balance -->
        <div v-if="balance" class="bg-indigo-50 py-1 px-3 mt-6 rounded text-gray-700 tabular-nums border border-indigo-300">
            {{ trans("Preview balance") }}:
            <span v-tooltip="trans('Current balance')">{{ locale.currencyFormat(currency.code, Number(balance)) }}</span>
            - <span v-tooltip="trans('Change')" class="text-green-500">{{ locale.currencyFormat(currency.code, amount) }}</span>
            ➞ <span v-tooltip="trans('Will be final balance')" class="font-bold">{{ locale.currencyFormat(currency.code, Number(balance) - (amount || 0)) }}</span>
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
                :disabled="amount <= 0 || !reasonToDecrease"
                v-tooltip="amount <= 0 ? trans('Submit amount to decrease') : ''"
            >
            </Button>
        </div>
    </div>
</template>
