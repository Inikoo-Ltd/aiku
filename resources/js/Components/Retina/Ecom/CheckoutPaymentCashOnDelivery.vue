<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { ref } from "vue"
import { faArrowRight } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faArrowRight)

defineProps<{
    data: {
        data: {
            content: {
                info: {
                    description: string,
                }
            }


        }
    }
}>()

const isLoading = ref(false)
const onSubmitPlaceOrder = () => {
    router.post(route("retina.models.place_order_pay_by_cash_on_delivery"), {}, {
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
    <div class="relative w-full max-w-xl mx-auto my-8 overflow-hidden">
        <div class="mx-auto max-w-md ">
            <div class="flex flex-col gap-x-4 rounded-xl border border-gray-300 bg-gray-100 p-6 ring-1 ring-inset ring-white/10">

               {{data.content.info.body}}
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