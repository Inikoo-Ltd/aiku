<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { trans } from 'laravel-vue-i18n'
import InputNumber from 'primevue/inputnumber'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
library.add(faInfoCircle)


const props = defineProps<{
    current_balance: string
    currency: {}
    submit_route: routeType
}>()

const amount = ref<number | null>(null)
const privateNote = ref<string>("")


const onSubmitTopup = () => {
    router.post(route(props.submit_route.name, props.submit_route.parameters), {
        amount: amount.value,
        notes: privateNote.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            notify({
                title: trans("Success!"),
                text: trans("Topup request has been submitted successfully."),
                type: "error",
            })
            amount.value = null
            privateNote.value = ""
        },
        onError: () => {
            notify({
                title: trans("Something went wrong"),
                text: trans("Please try again or contact support."),
                type: "error",
            })
        }
    })
}

</script>

<template>
    <div class="p-6 max-w-lg mx-auto w-full">
        <div class="mb-8">
            <h2 class="text-3xl font-bold">{{ trans("Topup Balance") }}</h2>
            <span class="text-sm italic text-gray-400">
                <FontAwesomeIcon icon="fal fa-info-circle" class="" fixed-width aria-hidden="true" />
                {{ trans("Deposit funds for various purposes such as orders payment.") }}
            </span>
        </div>
        <div class="space-y-8">
            <div>
                <label for="amount" class="block font-medium mb-2">
                    {{ trans("Amount to deposit") }}
                </label>
                <InputNumber v-model="amount" inputId="currency-germany" mode="currency" placeholder="100" currency="EUR" locale="de-DE" fluid />
            </div>

            <div>
                <label for="privateNote" class="block font-medium mb-2">
                    {{ trans("Note") }}
                </label>
                <PureTextarea
                    id="privateNote"
                    name="privateNote"
                    :rows="4"
                    placeholder="Add any private notes here..."
                    v-model="privateNote"
                />
            </div>
        </div>

        <div class="mt-8 flex justify-end space-x-4">
            <Button
                full
                @click="() => onSubmitTopup()"
                :label="trans('Submit')"
            />
        </div>
    </div>
</template>