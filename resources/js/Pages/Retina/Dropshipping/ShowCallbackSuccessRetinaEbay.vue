<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 09 May 2025 14:26:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { ctrans } from "@/Composables/useTrans";
import { faCheckCircle } from "@fas";
import { faUnlink, faArrowRight } from "@fal";
import { faExclamationTriangle, faClock } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import FlashNotification from "@/Components/UI/FlashNotification.vue";
import {FlashNotification  as FlashNotificationType} from "@/types/FlashNotification";

import { PageProps as InertiaPageProps } from "@inertiajs/core";
import { usePage } from "@inertiajs/vue3";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { onMounted, ref } from "vue"
import axios from "axios"

library.add(faCheckCircle, faUnlink, faArrowRight, faExclamationTriangle, faClock)

const props = defineProps<{
    success?: boolean
    connectWooCommerce?: boolean
}>()

const wooState = ref<"connecting" | "connected" | "failed">("connecting")
const wooError = ref<string | null>(null)

const closeSoon = () => setTimeout(() => window.close(), 1000)

const connectWooCommerce = async () => {
    const channel = new BroadcastChannel("woo-connect")
    try {
        await axios.get(route("retina.models.dropshipping.woocommerce.tmp_user_keys_check"))
        wooState.value = "connected"
        channel.postMessage({ connected: true })
        closeSoon()
    } catch (error: any) {
        wooState.value = "failed"
        wooError.value = error.response?.data?.errors?.url?.[0] ?? error.response?.data?.message ?? null
        channel.postMessage({ connected: false, errors: error.response?.data?.errors })
    } finally {
        channel.close()
    }
}

onMounted(() => {
    if (props.success === false) {
        return
    }
    if (props.connectWooCommerce) {
        connectWooCommerce()
    } else {
        closeSoon()
    }
})


</script>

<template>
  <div class="px-4">
      <template v-if="props.success !== false && props.connectWooCommerce">
          <h1 v-if="wooState === 'connecting'">{{ ctrans("Your store approved the connection, connecting it now...") }}</h1>
          <div v-else-if="wooState === 'connected'" class="flex flex-col gap-2">
              <h1>{{ ctrans("Your store is connected. You can close this window now.") }}</h1>
              <ButtonWithLink :routeTarget="{ name: 'retina.dropshipping.customer_sales_channels.index' }" type="tertiary" icon="fal fa-arrow-right" :label="ctrans('Go to my channels')" class="w-fit" />
          </div>
          <div v-else class="flex flex-col gap-2 max-w-2xl">
              <h1 class="text-lg font-semibold">{{ ctrans("Your store approved the connection but we could not finish it") }}</h1>
              <p v-if="wooError">{{ wooError }}</p>
          </div>
      </template>
      <h1 v-else-if="props.success !== false">{{ ctrans("Your store was successfully authenticated. You can close this window now.") }}</h1>
      <div v-else class="flex flex-col gap-2 max-w-2xl">
          <h1 class="text-lg font-semibold">{{ ctrans("Your store approved the connection but could not send us the keys") }}</h1>
          <p>{{ ctrans("WooCommerce tried to post the new API keys to our servers from your hosting and that request failed. This usually means your hosting blocks outgoing connections or has a firewall rule against our servers.") }}</p>
          <p>{{ ctrans("You can still connect: in WooCommerce go to Settings, Advanced, REST API, create a key with Read/Write permissions, and paste the consumer key and secret into the connection form in AW.") }}</p>
      </div>
  </div>
</template>
