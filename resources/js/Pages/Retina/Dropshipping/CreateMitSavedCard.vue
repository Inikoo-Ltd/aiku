<script setup lang="ts">
import { onMounted } from "vue"
import { useCheckoutCom } from "@/Composables/useCheckoutComFlow"
import { faCheckCircle } from "@fas"
import { faExclamationTriangle, faClock } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { CheckoutComFlow } from "@/types/CheckoutComFlow"
import { usePage } from "@inertiajs/vue3"
import { PageProps as InertiaPageProps } from "@inertiajs/core"
import FlashNotification from "@/Components/UI/FlashNotification.vue"
import { FlashNotification as FlashNotificationType } from "@/types/FlashNotification"

library.add(faCheckCircle, faClock, faExclamationTriangle)

const props = defineProps<{
    checkout_com_data: CheckoutComFlow
    head_title?: string
}>()

interface PagePropsWithFlash extends InertiaPageProps {
    flash: {
        notification?: FlashNotificationType
    }
}

const page = usePage<PagePropsWithFlash>()
// console.log('checkout_com_data', props.checkout_com_data)
const { isLoading, initializeCheckout } = useCheckoutCom(props.checkout_com_data, {
    isChangeLabelToSaved: true
})
onMounted(() => {
    initializeCheckout("flow-container")
})

</script>

<template>
    <FlashNotification :notification="page.props.flash.notification" />
    <div class="p-8 text-xl font-bold w-full text-center">
        <div v-if="head_title" class="max-w-xl mx-auto">
            {{ head_title }}
        </div>

        <div class="mx-auto mt-6 relative w-full max-w-xl min-h-[200px]">
            <div id="flow-container" class="w-full" />
            <div v-show="isLoading" class="pointer-events-none absolute top-0 h-full w-full z-10">
                <div class="w-full min-h-[200px] h-full skeleton">
                </div>
            </div>
        </div>
    </div>
</template>