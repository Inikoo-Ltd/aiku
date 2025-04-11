<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref } from "vue"
import Button from '@/Components/Elements/Buttons/Button.vue';
import { getComponent } from "./Content"
import FamilyList from "./FamilyList"
import Modal from '@/Components/Utils/Modal.vue'

library.add(faCube, faLink, faStar, faCircle)

const props = defineProps<{
    modelValue: any
}>()

const isModalOpen = ref(false)
const selectedProduct = ref(0)
const usedTemplates = ref({ key: "template1" })
const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
    (e: 'autoSave'): void
}>()


const onPickTemplate = (family) => {
    isModalOpen.value = false
    usedTemplates.value = { key: family.key }
}

</script>

<template>
    <div id="app" class="mx-10 my-10  text-gray-600">
        <div class="py-3">
            <Button label="Templates" @click="isModalOpen = true"></Button>
        </div>
        <div class="grid grid-cols-4 gap-x-10">
            <div class="col-span-1">
                <component :is="getComponent(usedTemplates.key)" />
            </div>

            <div class="col-span-3 border-2 p-4 rounded-lg">
            </div>
        </div>
    </div>


    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-2/5">
        <div tag="div"
            class="relative grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-y-3 gap-x-4 overflow-y-auto overflow-x-hidden">
            <div v-for="family in FamilyList.listTemplate" :key="family.key" @click="() => onPickTemplate(family)"
                class="group flex items-center gap-x-2 relative border border-gray-300 px-3 py-2 rounded cursor-pointer hover:bg-gray-100">
                <div class="flex items-center justify-center">
                    <FontAwesomeIcon :icon='family.icon' class='' fixed-width aria-hidden='true' />
                </div>
                <h3 class="text-sm font-medium">
                    {{ family.name }}
                </h3>
            </div>
        </div>
    </Modal>

</template>