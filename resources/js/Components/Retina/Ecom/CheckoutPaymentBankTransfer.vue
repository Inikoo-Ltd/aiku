<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { faArrowRight } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import CopyButton from "@/Components/Utils/CopyButton.vue"

library.add(faArrowRight)

const props = defineProps<{
    data: {
        data: {
            bank_name: string
            sort_code?: string
            iban: string
            account_number: string
            swift?: string
            recipient?: string
            note?: string
        }
    }
}>()

const bankRows = computed(() => {
    const bank = props.data?.data
    return [
        { label: trans("Account number"), value: bank?.account_number, copy: true },
        { label: trans("Sort code"), value: bank?.sort_code, copy: true },
        { label: "IBAN", value: bank?.iban, copy: true },
        { label: "SWIFT/BIC", value: bank?.swift, copy: true },
        { label: trans("Recipient"), value: bank?.recipient, copy: false },
    ].filter((row) => row.value)
})

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
            <div class="rounded-xl border border-gray-300 bg-gray-100 p-6">
                <h3 class="font-semibold">{{ data?.data?.bank_name }}</h3>
                <dl class="mt-3 divide-y divide-gray-200 text-sm">
                    <div v-for="row in bankRows" :key="row.label" class="flex items-center justify-between gap-x-4 py-2">
                        <dt class="text-gray-500">{{ row.label }}</dt>
                        <dd class="flex items-center gap-x-1 text-right font-medium break-all">
                            {{ row.value }}
                            <CopyButton v-if="row.copy" :text="row.value" class="inline shrink-0" />
                        </dd>
                    </div>
                </dl>
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
