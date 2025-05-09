<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, provide } from "vue"
import Modal from '@/Components/Utils/Modal.vue'
import BlockList from '@/Components/CMS/Webpage/BlockList.vue'
import { getIrisComponent } from "@/Composables/getIrisComponents"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import { getBlueprint } from "@/Composables/getBlueprintWorkshop"

library.add(faCube, faLink, faStar, faCircle)

const props = defineProps<{
    modelValue: any
    data: {
        web_block_types: any
        category: any
    }
}>()

const isModalOpen = ref(false)
const usedTemplates = ref(
    props.modelValue?.family?.code ?
    props.data.web_block_types.data.find((template) => template.code === props.modelValue?.family?.code) :
    props.data.web_block_types.data[0]
)

const onPickTemplate = (family: any) => {
    isModalOpen.value = false
    usedTemplates.value = family
    props.modelValue.family = {
        code: family.code,
        settings: null
    }
}

const onSaveWorkshopFromId = (blockId: number, from?: string) => {
    console.log("onSaveWorkshopFromId", blockId, from)
}

const onSaveWorkshop = (block) => {
	console.log(block)
}

provide('onSaveWorkshopFromId', onSaveWorkshopFromId)
provide('onSaveWorkshop', onSaveWorkshop)
</script>

<template>
    <div class="h-[79vh] grid overflow-hidden grid-cols-4">
        <div class="col-span-1 flex flex-col border-r border-gray-300 shadow-lg relative overflow-auto">
            <div class="px-4 py-3 rounded-t-lg shadow">
                <div class="flex items-center">
                    <font-awesome-icon :icon="['fas', 'chevron-left']"
                        class="px-4 cursor-pointer text-gray-600 hover:text-gray-800 transition duration-200"
                        @click="selectPreviousTemplate" />
                    <div class="border w-full rounded-md p-2 align-center flex justify-center" @click="isModalOpen = true">
                         {{ usedTemplates.code }}
                    </div>
                    <font-awesome-icon :icon="['fas', 'chevron-right']"
                        class="px-4 cursor-pointer text-gray-600 hover:text-gray-800 transition duration-200"
                        @click="selectNextTemplate" />
                </div>
            </div>
            <div class="px-4 py-5 flex-grow">
                <SideEditor 
                     v-if="usedTemplates.code"
                    v-model="modelValue.family" 
			        :blueprint="getBlueprint(usedTemplates.code)" 
                    @update:model-value="(a)=>console.log('sdsdsd',a)"
			        :uploadImageRoute="null" 
                />
         
            </div>
        </div>

        <div class="bg-gray-100 h-full col-span-3 rounded-lg shadow-lg">
            <div class="bg-gray-100 px-6 py-6 h-[79vh] rounded-lg overflow-auto flex justify-center items-center">
               
                <div v-if="usedTemplates?.code" class="bg-white shadow-md rounded-lg w-fit">
                    <section>
                        
                        <component class="w-full" :is="getIrisComponent(usedTemplates.code)"
                            :fieldValue="usedTemplates.data.fieldValue" />
                    </section>
                </div>
            </div>
        </div>

    </div>

    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-2/5">
        <BlockList :onPickBlock="onPickTemplate" :webBlockTypes="data.web_block_types" scope="webpage" />
    </Modal>
</template>