<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { ref } from "vue"
import { faArrowRight } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import CopyButton from "@/Components/Utils/CopyButton.vue"

library.add(faArrowRight)

defineProps<{
    data: {
        data: {
            bank_name: string
            bank_code: string
            iban: string
            account_number: string
            swift?: string
            recipient?: string
            note?: string
        }
    }
}>()

const isLoading = ref(false)
const onSubmitPlaceOrder = () => {
    router.post(route("retina.models.place_order_pay_by_bank"), {}, {
        onStart: () => {
            isLoading.value = true
        },
        onFinish: () => {
            isLoading.value = false
        }
    })
}
</script>

<template>
    <div class="relative w-full max-w-xl mx-auto my-4 md:my-8 overflow-hidden">
        <div class="mx-auto max-w-md ">
            <div class="flex flex-col gap-x-4 rounded-xl border border-gray-300 bg-gray-100 p-6 ring-1 ring-inset ring-white/10">
                <div class="flex flex-col md:flex-row md:items-center justify-between w-full">
                    <h3 class="font-semibold">{{ data?.data?.bank_name }}</h3>
                    <div class="font-normal text-gray-400 text-sm">
                        {{ data?.data?.bank_code }}
                        <span>
                            {{ data?.data?.iban }}
                            <CopyButton :text="data?.data?.iban" class="ml-0.5 inline" />
                        </span>
                    </div>
                </div>
                <p class="text-gray-400 italic text-sm">{{ data?.data?.account_number }}</p>
                <p v-if="data?.data?.recipient" class="text-sm">{{ trans("Recipient") }}: {{ data.data.recipient }}</p>
                <p v-if="data?.data?.swift" class="text-sm">SWIFT/BIC: {{ data.data.swift }} <CopyButton :text="data.data.swift" class="ml-0.5 inline" /></p>
            </div>

            <div v-if="data?.data?.note" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                {{ data.data.note }}
            </div>

            <Button
                full
                :label="trans('Place order')"
                class="mt-6"
                @click="() => onSubmitPlaceOrder()"
                :loading="isLoading"
                iconRight="fas fa-arrow-right"
            />
        </div>
    </div>
</template>
