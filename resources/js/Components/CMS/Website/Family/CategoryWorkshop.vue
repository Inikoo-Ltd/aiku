<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import Button from '@/Components/Elements/Buttons/Button.vue';
import Modal from '@/Components/Utils/Modal.vue'
import BlockList from '@/Components/CMS/Webpage/BlockList.vue'
import { getIrisComponent } from "@/Composables/getIrisComponents"

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
    props.data.web_block_types.data.find((template) => template.code === props.modelValue.family?.code)
)

const onPickTemplate = (family: any) => {
    isModalOpen.value = false
    usedTemplates.value = family
    props.modelValue.family = {
        code: family.code,
        settings: null
    }
}

</script>

<template>
    <div id="app" class="mx-10 my-10  text-gray-600">
        <div class="py-3">
            <Button label="Templates" @click="isModalOpen = true"></Button>
        </div>
        <div v-if="modelValue?.family?.code" class="grid grid-cols-4 gap-x-10">
            <div class="col-span-1">
                <component class="w-full" :is="getIrisComponent(modelValue.family.code)"
                    :fieldValue="usedTemplates.data.fieldValue" />
            </div>
        </div>
    </div>

    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-2/5">
        <BlockList :onPickBlock="onPickTemplate" :webBlockTypes="data.web_block_types" scope="webpage" />
    </Modal>

</template>