<script setup lang="ts">
import { Head, usePage } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as TSPageHeading } from "@/types/PageHeading"
import { CheckoutComFlow } from "@/types/CheckoutComFlow"
import { FlashNotification as FlashNotificationType } from "@/types/FlashNotification"
import { useCheckoutCom } from "@/Composables/useCheckoutComFlow"
import { onMounted } from "vue"
import { PageProps as InertiaPageProps } from "@inertiajs/core"
import FlashNotification from "@/Components/UI/FlashNotification.vue"
import { watch } from "vue"
import { ref } from "vue"


const props = defineProps<{
    title: string,
    pageHead: TSPageHeading
    checkout_com_data: CheckoutComFlow
}>()

interface PagePropsWithFlash extends InertiaPageProps {
    flash: {
        notification?: FlashNotificationType
    }
}

const page = usePage<PagePropsWithFlash>()

const { isLoading, initializeCheckout } = useCheckoutCom(props.checkout_com_data)
onMounted(() => {
    initializeCheckout("flow-container")
})

const isLoadingCheckout = ref(true)
watch(() => isLoading.value, (loading) => {
    console.log('ewew', loading)
    if (!loading) {
        console.log('rr')
        setTimeout(() => {
            isLoadingCheckout.value = false
        }, 2000)
    }
})

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <FlashNotification :notification="page.props.flash.notification" />

    <div class="flex justify-center">
        <div class="relative w-full max-w-xl isolate xmx-auto my-8 overflow-hidden">
            <!-- <div class="mb-2">
                Need to pay: <span class="font-bold">{{ locale.currencyFormat(currency_code, props.needToPay) }}</span>
                <Transition name="spin-to-right">
                    <FontAwesomeIcon v-if="isRecentlyCopied" icon="fal fa-check" class="ml-1 text-green-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-else @click="() => onClickCopy(props.needToPay.toFixed(2))" icon="fal fa-copy" class="ml-1 text-gray-400 hover:text-gray-600 cursor-pointer" fixed-width aria-hidden="true" />
                </Transition>
            </div> -->
            <div xv-show="!isLoading" id="flow-container" class="absolute " />
            <div class="w-full h-[511px] -z-10" :class="isLoadingCheckout ? 'skeleton' : ''">
            </div>
        </div>
    </div>


</template>