<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { faCube, faStar, faImage } from "@fas"
  import { library } from "@fortawesome/fontawesome-svg-core"
  import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
  import Modal from "@/Components/Utils/Modal.vue";
  import { layoutStructure } from '@/Composables/useLayoutStructure'
  import { inject, ref } from 'vue'
  import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
  import Upload from './Upload.vue'
  import StockImages from './StockImages.vue'
  import UploadedImages from "@/Components/Fulfilment/Website/Gallery/UploadedImages.vue";

  library.add(faCube, faStar, faImage)

  const props = withDefaults(defineProps<{
      open: Boolean
      width?: String,
      uploadRoutes: String
      stockImageRoutes?: routeType,
      imagesUploadedRoutes?: routeType,
      tabs?: Array,
      useCrop? : Bollean,
      cropProps? : Object
    }>(), {
        tabs: ['upload','images_uploaded','stock_images'],
        useCrop : false,
        stockImageRoutes : {
            name : 'grp.gallery.stock-images.index',
            parameters : ""
        },
        imagesUploadedRoutes : {
            name : 'grp.gallery.uploaded-images.index',
            parameters : ""
        },
        cropProps : {
            ratio :  { w: 1, h: 1 } 
        }
})

const layout = inject('layout', layoutStructure)
const selectedTab = ref(0)

const emits = defineEmits<{
    (e: 'onClose'): void
    (e: 'onPick', value: Object): void 
    (e: 'onUpload', value: Object): void
}>()

function changeTab(index) {
    selectedTab.value = index
}

const tabsData = [
    {
        label: "Upload",
        key: 'upload',
    },
    {
        label: "Images Uploaded",
        key: 'images_uploaded',
    },
    {
        label: "Stock Images",
        key: 'stock_images',
    },
].filter((item) => props.tabs.includes(item.key))


const getComponent = (componentName: string) => {
  const components: any = {
    'upload': Upload,
    'images_uploaded': UploadedImages,
    'stock_images' : StockImages
  };
  return components[componentName] ?? null;
};

const OnPickImage = (e) => {
    emits('onPick', e)
}

const onUpload = (e) => {
    emits('onUpload', e)
    selectedTab.value = 1
}


  </script>


<template>
    <Modal :isOpen="open" @onClose="() => emits('onClose')" width="w-1/2">
        <TabGroup :selectedIndex="selectedTab" @change="changeTab">
            <TabList class="flex space-x-8 border-b-2">
                <Tab v-for="tab in tabsData" as="template" :key="tab.key" v-slot="{ selected }">
                    <button
                        :style="selected ? { color: layout.app.theme[0], borderBottomColor: layout.app.theme[0] } : {}"
                        :class="[
                            'whitespace-nowrap border-b-2 py-1.5 px-1 text-sm font-medium focus:ring-0 focus:outline-hidden mb-2',
                            selected
                                ? `border-org-5s00 text-[${layout.app.theme[0]}]`
                                : `border-transparent text-[${layout.app.theme[0]}] hover:border-[${layout.app.theme[0]}]`,
                        ]">
                        {{ tab.label }}
                    </button>
                </Tab>
            </TabList>

            <TabPanels class="mt-2">
                <TabPanel v-for="(tab, idx) in tabsData" :key="idx" :class="[
                    'rounded-xl bg-white p-3 h-96 overflow-auto',
                    'ring-white/60 ring-offset-2 ring-offset-blue-400 focus:outline-hidden focus:ring-2',
                ]">
                    <component 
                        :is="getComponent(tab['key'])" 
                        :uploadRoutes="uploadRoutes" 
                        @pick="OnPickImage" 
                        :imagesUploadedRoutes="imagesUploadedRoutes"
                        @onUpload="onUpload"  
                        :useCrop="useCrop" 
                        :cropProps="cropProps" 
                        :stockImageRoutes="stockImageRoutes" />

                </TabPanel>
            </TabPanels>
        </TabGroup>
    </Modal>
</template>