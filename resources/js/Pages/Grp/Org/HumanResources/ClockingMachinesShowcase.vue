<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 14:00:48 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import QrcodeVue from 'qrcode.vue'
import { faAndroid } from "@fortawesome/free-brands-svg-icons"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faAndroid)


const props = defineProps<{
    data: {
        slug: String,
        qr_code: String,
        status: String,
        name: String,
        type: String,
        device_name: String
        device_uuid: String
    },
}>()

console.log(props.data)
</script>


<template>
    <div class="flex">
    <div v-if="data.status === 'disconnected'" class="relative w-fit p-4 bg-white shadow-sm rounded-lg flex flex-col items-center gap-y-2">
        <QrcodeVue :value="data.qr_code" :size="200" level="L" render-as="svg" foreground="#334155"
            class="p-2 bg-gray-100 rounded-md" />
        <div class="text-gray-800 font-medium">
            <p>{{ data.qr_code }}</p>
        </div>
    </div>
    <div class="m-4" v-else>
        You connected using device: {{ data.device_name }}
    </div>

    <div class="ring-1 ring-gray-300 shadow-sm rounded-2xl p-6 m-2 w-1/3 h-fit">
            <div class="font-semibold">Download  App</div>
            <a href="https://github.com/inikoo/han/releases"  target="_blank"  class="flex items-end gap-x-2 mt-2">
                <font-awesome-icon :icon="['fab', 'android']" />
                    <div class="text-gray-400 text-sm leading-4">
                        Android
                    </div>
                </a>
        </div>
    </div>
</template>