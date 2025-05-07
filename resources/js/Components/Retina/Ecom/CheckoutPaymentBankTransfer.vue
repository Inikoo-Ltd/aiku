<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { faArrowRight } from '@fas'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faArrowRight)

const props = defineProps<{
    data: {
        data: {
            bank_name: string
            bank_code: string
            iban: string
            account_number: string
        }
    }
}>()

const isLoading = ref(false)
const onSubmitPlaceOrder = () => {
    router.post(route('retina.models.place-order-pay-by-bank'), {

    }, {
        onStart: () => {
            isLoading.value = true
        },
        onFinish: () => {
            isLoading.value = false
        },
    })
}
</script>

<template>
    <div class="relative w-full max-w-xl mx-auto my-8 overflow-hidden">
        <div class="mx-auto max-w-md ">
            <div class="flex flex-col gap-x-4 rounded-xl border border-gray-300 bg-gray-100 p-6 ring-1 ring-inset ring-white/10">
                <div class="flex items-center justify-between w-full">
                    <h3 class="font-semibold">{{ data?.data?.bank_name }}</h3>
                    <div class="font-normal text-gray-400 text-sm">
                        {{ data?.data?.bank_code }}
                        {{ data?.data?.iban }}
                    </div>
                </div>
                <p class="text-gray-400 italic text-sm">{{ data?.data?.account_number }}</p>
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