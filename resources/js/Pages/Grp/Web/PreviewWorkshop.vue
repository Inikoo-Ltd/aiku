<!--
  - Author: Artha <artha@aw-advantage.com>
  - Created: Thu, 26 Sep 2024 13:18:33 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { getComponent } from '@/Composables/getWorkshopComponents'
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { ref, onMounted, provide, onBeforeUnmount } from 'vue'
import WebPreview from "@/Layouts/WebPreview.vue";
import { sendMessageToParent} from '@/Composables/Workshop'
import RenderHeaderMenu from './RenderHeaderMenu.vue'
import { router } from '@inertiajs/vue3'
import "@/../css/Iris/editor.css"

import { Root as RootWebpage } from '@/types/webpageTypes'
import ButtonPreviewLogin from '@/Components/Workshop/Tools/ButtonPreviewLogin.vue';


defineOptions({ layout: WebPreview })
const props = defineProps<{
    webpage?: RootWebpage
    header: {
        data: {}
    }
    footer: {
        footer: {}
    }
    navigation: {
        menu: {}
    }
    layout: {

    }
}>()

const isPreviewLoggedIn = ref(false)
const { mode } = route().params;
const isPreviewMode = ref(mode != 'iris' ? false : true)
const isInWorkshop = route().params.isInWorkshop || false
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')

const showWebpage = (activityItem) => {
    if (activityItem?.web_block?.layout && activityItem.show) {
        if (isPreviewLoggedIn.value && activityItem.visibility.in) return true
        else if (!isPreviewLoggedIn.value && activityItem.visibility.out) return true
        else return false
    } else return false
}

const updateData = (newVal) => {
    sendMessageToParent('autosave', newVal)
}

onMounted(() => {
    window.addEventListener('message', (event) => {
        if (event.data.key === 'isPreviewLoggedIn') isPreviewLoggedIn.value = event.data.value
        if (event.data.key === 'isPreviewMode') isPreviewMode.value = event.data.value
        if (event.data.key === 'reload') {
            router.reload({
                only: ['footer', 'header', 'webpage'],
                onSuccess: () => {
                    /*   if(props.footer?.footer) Object.assign(layout.footer, toRaw(props.footer.footer));
                      if(props.header?.data) Object.assign(layout.header, toRaw(props.header.data)); */
                    if (props.webpage) data.value = props.webpage
                }
            });
        }
    });
});

const checkScreenType = () => {
  const width = window.innerWidth
  if (width < 640) screenType.value = 'mobile'
  else if (width >= 640 && width < 1024) screenType.value = 'tablet'
  else screenType.value = 'desktop'
}

onBeforeUnmount(() => {
  window.removeEventListener('resize', checkScreenType)
})


provide('isPreviewLoggedIn', isPreviewLoggedIn)
provide('isPreviewMode', isPreviewMode)

console.log(route().current())
</script>


<template>
    <div class="editor-class">
        <div v-if="isInWorkshop" class="bg-gray-200 shadow-xl px-8 py-4 flex justify-center items-center gap-x-2">
            <ButtonPreviewLogin v-model="isPreviewLoggedIn" />
        </div>

        <div class="shadow-xl" :class="layout?.layout == 'fullscreen' ? 'w-full' : 'container max-w-7xl mx-auto'">
            <div>
                <RenderHeaderMenu v-if="header?.data" :data="header.data" :menu="navigation"
                    :loginMode="isPreviewLoggedIn" @update:model-value="updateData(header.data)" />
            </div>

            <div  class="bg-white">
                <template v-if="webpage?.layout?.web_blocks?.length">
                    <div v-for="(activityItem, activityItemIdx) in webpage?.layout?.web_blocks"
                        :key="'block' + activityItem.id" class="w-full">
                        <component v-if="showWebpage(activityItem)" :is="getIrisComponent(activityItem.type)"
                            :key="activityItemIdx" :theme="layout"
                            :fieldValue="activityItem.web_block?.layout?.data?.fieldValue" />
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <component 
                v-if="footer?.data?.data"
                :is="isPreviewMode || route().current() == 'grp.websites.preview' ? getIrisComponent(footer.data.code) : getComponent(footer.data.code)"
                v-model="footer.data.data.fieldValue" 
                @update:model-value="updateData(footer.data)" 
            />
        </div>
    </div>

</template>



<style lang="scss">
.hover-dashed {
    @apply relative;

    &::after {
        content: "";
        @apply absolute inset-0 hover:bg-gray-200/30 border border-transparent hover:border-white/80 border-dashed cursor-pointer;
    }
}
</style>
