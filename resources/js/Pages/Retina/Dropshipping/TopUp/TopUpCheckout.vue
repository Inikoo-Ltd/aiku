<script setup lang="ts">
import { Head, usePage } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as TSPageHeading } from "@/types/PageHeading";
import { CheckoutComFlow } from "@/types/CheckoutComFlow";
import { FlashNotification as FlashNotificationType } from "@/types/FlashNotification";
import { useCheckoutCom } from "@/Composables/useCheckoutComFlow";
import { onMounted } from "vue";
import { PageProps as InertiaPageProps } from "@inertiajs/core";
import FlashNotification from "@/Components/UI/FlashNotification.vue";


const props = defineProps<{
  title: string,
  pageHead: TSPageHeading
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
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <FlashNotification :notification="page.props.flash.notification" />
  <div class="p-8 text-xl font-bold w-full text-center">
    <div class="relative w-full max-w-xl mx-auto my-8 overflow-hidden">
      <div v-show="!isLoading" id="flow-container" class="absolute " />
      <div class="w-full h-[450px]" :class="isLoading ? 'skeleton' : ''">
      </div>
    </div>
  </div>
</template>