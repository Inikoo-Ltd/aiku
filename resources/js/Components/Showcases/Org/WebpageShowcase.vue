
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 14 Sep 2023 00:06:48 Malaysia Time, Pantai Lembeng, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { getIrisComponent } from '@/Composables/getIrisComponents'
import BrowserView from '@/Components/Pure/BrowserView.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref } from 'vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

library.add(faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull)

const props = defineProps<{
    data: {
        slug: string
        state: string
        status: string
        created_at: string
        updated_at: string
        domain: string
    }
}>()


const iframeSrc = 
	route("grp.websites.preview", [
		route().params["website"],
		route().params["webpage"],
		{
			organisation: route().params["organisation"],
			shop: route().params["shop"],
			fulfilment : route().params["fulfilment"]
		},
	]
)

const isIframeLoading = ref(true)
</script>

<template>
    <div class="px-6 py-0 sm:py-5 lg:px-8">
        <div class="grid grid-cols-2">
            <BrowserView
                :tab="{
                    icon: data.typeIcon,
                    label: data.code
                }"
                :url="{
                    domain: data.domain,
                    page: data.url
                }"

            >
                <template #page v-if="data.layout.web_blocks?.length">
                    <div class="relative w-full h-full">
                        <div v-if="isIframeLoading"
                            class="absolute inset-0 flex items-center justify-center bg-white">
                            <LoadingIcon class="w-24 h-24 text-6xl" />
                        </div>
                        <iframe
                            ref="_iframe"
                            :src="iframeSrc"
                            :title="props.title"
                            class="w-full h-full"
                            @load="isIframeLoading = false"
                        />
                    </div>
                   <!--  <template v-if="data.layout.web_blocks?.length">
                        <div class="px-10">
                            <div v-for="(activityItem, activityItemIdx) in data.layout.web_blocks"
                                :key="'block' + activityItem.id"
                                class="w-full"
                            >
                                <component
                                    v-if="activityItem.web_block?.layout?.data?.fieldValue"
                                    :is="getIrisComponent(activityItem.type)"
                                    :key="activityItemIdx"
                                    :properties="activityItem?.web_block?.layout?.properties"
                                    :isEditable="false"
                                    :fieldValue="activityItem.web_block.layout.data.fieldValue"
                                />
                            </div>
                        </div>
                    </template>
                    <div v-else class="text-center text-2xl sm:text-4xl font-bold text-gray-400 mt-16">
                        This page have no data
                    </div> -->
                </template>
            </BrowserView>
            <div>
                <!-- <pre>{{ data }}</pre> -->
            </div>

           
        </div>

    </div>
</template>
