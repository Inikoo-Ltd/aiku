<script setup lang="ts">
import { onMounted } from "vue";
import { useCheckoutCom } from "@/Composables/useCheckoutComFlow";
import { faCheckCircle } from "@fas";
import { faExclamationTriangle, faClock } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";
import { CheckoutComFlow } from "@/types/CheckoutComFlow";
import { usePage } from "@inertiajs/vue3";
import { PageProps as InertiaPageProps } from "@inertiajs/core";
import FlashNotification from "@/Components/UI/FlashNotification.vue";
import {FlashNotification  as FlashNotificationType} from "@/types/FlashNotification";

library.add(faCheckCircle, faClock, faExclamationTriangle);

const props = defineProps<{
  checkout_com_data: CheckoutComFlow
}>();

interface PagePropsWithFlash extends InertiaPageProps {
  flash: {
    notification?: FlashNotificationType
  };
}

const page = usePage<PagePropsWithFlash>();

const { isLoading, initializeCheckout } = useCheckoutCom(props.checkout_com_data);
onMounted(() => {
  initializeCheckout("flow-container");
});



</script>

<template>
  <FlashNotification :notification="page.props.flash.notification" />
  <div class="p-8 text-xl font-bold w-full text-center">
    <div class="relative w-full max-w-xl mx-auto my-8 overflow-hidden">
      <div v-show="!isLoading" id="flow-container" class="absolute " />
      <div class="w-full h-[450px]" :class="isLoading ? 'skeleton' : ''">
      </div>
    </div>
  </div>
</template>