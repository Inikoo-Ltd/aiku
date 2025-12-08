<script setup lang="ts">
import {ref} from "vue"
import {routeType} from "@/types/route"
import Select from "primevue/select"
import {router} from '@inertiajs/vue3'
import {trans} from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import {InputNumber} from "primevue"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAsterisk } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faAsterisk)


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
    console.log(amount.value, privateNote.value, increaseReason.value,)
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
        <p class="text-base text-gray-500 italic mb-6 text-center">{{
                trans("Enter the details to increase balance")
            }}</p>

        <div class="space-y-6">
            <!-- Type -->
            <div v-if="types?.length > 0">
                <label for="amount" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Type of payment") }}
                </label>
                <Select
                    v-model="increaseType"
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

            <!-- Amount -->
            <div>
                <label for="amount" class="block text-gray-700 font-medium mb-2">
                    {{ trans("Amount to deposit") }}
                </label>

                <InputNumber
                    v-model="amount"
                    @input="(e) => amount = e?.value || 0"
                    inputId="currency-us"
                    mode="currency"
                    :currency="currency.code"
                    :maxFractionDigits="2"
                    locale="en-US"
                    :min="0"
                    xprefix="-"
                    fluid
                />
            </div>

            <!-- Note -->
            <div>
                <label for="privateNote" class="block text-gray-700 font-medium mb-2">
                    <FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs align-top mt-1" fixed-width aria-hidden="true" />
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
        <div v-if="balance" class="bg-gray-100 py-1 px-3 mt-6 rounded text-gray-700 tabular-nums border border-indigo-300">
            {{ trans("Preview balance") }}:
            <span v-tooltip="trans('Current balance')">{{ locale.currencyFormat(currency.code, Number(balance)) }}</span>
            + <span v-tooltip="trans('Change')" class="text-green-500">{{ locale.currencyFormat(currency.code, amount) }}</span>
            ➞ <span v-tooltip="trans('Will be final balance')" class="font-bold">{{ locale.currencyFormat(currency.code, Number(balance) + (amount || 0)) }}</span>
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
                :disabled="amount <= 0 || !increaseReason || !privateNote"
                v-tooltip="amount <= 0 ? trans('Add amount to submit') : ''"
            >
            </Button>
        </div>
    </div>
</template>
