<script setup lang="ts">
import { ref, watch } from 'vue'

import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import draggable from "vuedraggable";
import { v4 as uuidv4 } from 'uuid';
import ContextMenu from 'primevue/contextmenu';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { getStyles } from '@/Composables/styles';
import Image from '@/Components/Image.vue';
import { sendMessageToParent } from '@/Composables/Workshop';

import { FieldValue } from '@/types/Website/Website/footer1'

import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShieldAlt, faPlus, faTrash, faAngleUp, faAngleDown, faTriangle } from "@fas"
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faWhatsapp } from "@fortawesome/free-brands-svg-icons";
import { faBars } from '@fal'

library.add(faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faShieldAlt, faBars, faPlus, faTrash, faWhatsapp)

const props = defineProps<{
    modelValue: FieldValue,
    keyTemplate: String
    colorThemed?: Object
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

const editorKey = ref(uuidv4())
const editable = ref(true)
const selectedData = ref(null)
const selectedIndex = ref(null)
const selectedColumn = ref(null)
const menu = ref();
const subMenu = ref();
const Menuitems = ref([
    {
        label: 'Sub Menu',
        icon: 'fas fa-plus',
        command: () => {
            addSubmenu()
        }
    },
    {
        label: 'Delete',
        icon: 'fas fa-trash',
        command: () => {
            deleteMenu()
        }
    }
]);

const subMenuitems = ref([
    {
        label: 'Delete',
        icon: 'fas fa-trash',
        command: () => {
            deleteSubMenu()
        }
    }
]);

const onDrag = () => {
    editorKey.value = uuidv4()
    editable.value = false;
}

const onDrop = () => {
    editorKey.value = uuidv4()
    editable.value = true;
    /*     emits('update:modelValue', cloneDeep({...props.modelValue})); */
}

const addSubmenu = () => {
    if (selectedData.value.data) {
        selectedData.value.data.push(
            {
                name: "New Sub Menu",
                id: uuidv4(),
            },
        )
    } else {
        selectedData.value.data = [
            {
                name: "New Sub Menu",
                id: uuidv4(),
            }
        ]
    }
    emits('update:modelValue', props.modelValue)
}

const deleteMenu = () => {
    selectedColumn.value.splice(selectedIndex.value, 1)
    emits('update:modelValue', props.modelValue)
}

const deleteSubMenu = () => {
    selectedData.value.data.splice(selectedIndex.value, 1)
    emits('update:modelValue', props.modelValue)
}

const onRightClickMenu = (event, data, column, index) => {
    selectedData.value = data;
    selectedIndex.value = index,
        selectedColumn.value = column
    menu.value.show(event);
};

const onRightClickSubMenu = (event, data, column, index) => {
    selectedData.value = data;
    selectedIndex.value = index,
        selectedColumn.value = column
    subMenu.value.show(event);
};

const selectAllEditor = (editor: any) => {
    editor.commands.selectAll()
}

const addMenuToColumn = (data) => {
    data.push(
        {
            name: "New Menu",
            id: uuidv4(),
            data: [
                { name: "New Sub Menu", id: uuidv4(), },
            ],
        },
    )
    emits('update:modelValue', props.modelValue)
}

watch(() => props.previewMode, (newStatus, oldStatus) => {
    editable.value = !newStatus
});


</script>


<template>
    <div id="app" class="-mx-2 md:mx-0 pb-24 pt-4 md:pt-8 md:px-16 text-white"
        :style="getStyles(modelValue?.container?.properties)">
        <div
            class="w-full flex flex-col md:flex-row gap-4 md:gap-8 pt-2 pb-4 md:pb-6 mb-4 md:mb-10 border-0 border-b border-gray-700">
            <div class="flex-1 flex items-center justify-center md:justify-start border-solid "
                @click="() => sendMessageToParent('panelOpen', 'logo')">
                <!--  <img v-if="modelValue?.logo?.source && !isObject(modelValue.logo?.source)" :src="modelValue.logo.source"
                    :alt="modelValue.logo.alt" class="h-auto max-h-20 w-auto min-w-16" />
                <img v-if="modelValue?.logo?.source?.original"  :src="modelValue?.logo?.source?.original" :alt="modelValue.logo.alt"
                    class="h-auto max-h-20 w-auto min-w-16"> -->
                <Image v-if="modelValue?.logo?.source" :src="modelValue?.logo?.source" :imageCover="true" :alt="modelValue?.logo?.alt"
                    :imgAttributes="modelValue?.logo?.attributes" :style="getStyles(modelValue?.logo?.properties)" />
            </div>

            <div v-if="modelValue?.email" @click="() => sendMessageToParent('panelOpen', 'email')"
                class="relative group flex-1 flex justify-center md:justify-start items-center hover-dashed">
                <a style="font-size: 17px">{{ modelValue?.email }}</a>
            </div>

            <div v-if="modelValue?.whatsapp?.number" @click="() => sendMessageToParent('panelOpen', 'whatsapp')"
                class="relative group flex-1 flex gap-x-1.5 justify-center md:justify-start items-center hover-dashed">
                <a class="flex gap-x-2 items-center">
                    <FontAwesomeIcon class="text-[#00EE52]" icon="fab fa-whatsapp" style="font-size: 22px" />
                    <span style="font-size: 17px">{{ modelValue?.whatsapp?.number }}</span>
                </a>

            </div>

            <div class="group relative flex-1 flex flex-col items-center md:items-end justify-center hover-dashed"
                @click="() => sendMessageToParent('panelOpen', 'phone')">
                <a v-for="phone of modelValue.phone.numbers" style="font-size: 17px">
                    {{ phone }}
                </a>

                <span class="" style="font-size: 15px">{{ modelValue.phone.caption }}</span>

            </div>
        </div>
        <div>
            <div class=" grid grid-cols-1 md:grid-cols-4 gap-3 md:gap-8">
                <!--  column 1 -->
                <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                    <draggable v-model="modelValue.columns['column_1']['data']" group="row" itemKey="id"
                        :animation="200" handle=".handle" @start="onDrag" @end="onDrop"
                        @update:model-value="(e) => { modelValue.columns['column_1']['data'] = e, emits('update:modelValue', modelValue) }"
                        class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <template #item="{ element: item, index: index }">
                            <div>
                                <!--  desktop -->
                                <div
                                    class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                    <div class="flex text-xl font-semibold  leading-6"
                                        @contextmenu="onRightClickMenu($event, item, modelValue.columns['column_1']['data'], index)">
                                        <FontAwesomeIcon icon="fal fa-bars" v-if="!previewMode"
                                            class="handle text-white cursor-grab pr-3 mr-2" />
                                        <div class="w-full">
                                            <Editor
                                                :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                                                :key="editorKey" v-model="item.name" :editable="editable"
                                                @update:model-value="(e) => { item.name = e, emits('update:modelValue', modelValue) }" />

                                        </div>
                                        <ContextMenu ref="menu" :model="Menuitems">
                                            <template #itemicon="item">
                                                <FontAwesomeIcon :icon="item.item.icon" />
                                            </template>
                                        </ContextMenu>
                                    </div>
                                    <draggable v-model="item.data" group="sub-row" itemKey="id" :animation="200"
                                        handle=".handle-sub" @start="onDrag" @end="onDrop"
                                        @update:model-value="(e) => { item.data = e, emits('update:modelValue', modelValue) }">
                                        <template #item="{ element: sub, index: subIndex }"
                                            class="hidden md:block space-y-3">
                                            <div class="flex w-full items-center gap- mt-2">
                                                <div class="flex items-center w-full"
                                                    @contextmenu="onRightClickSubMenu($event, item, modelValue.columns['column_1']['data'], subIndex)">
                                                    <FontAwesomeIcon icon="fal fa-bars"
                                                        class="handle-sub text-sm text-white cursor-grab pr-3 mr-2" />
                                                    <div class="w-full">
                                                        <Editor
                                                            :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                                                            :key="editorKey" v-model="sub.name" :editable="editable"
                                                            @update:model-value="(e) => { sub.name = e, emits('update:modelValue', modelValue) }" />
                                                    </div>
                                                    <ContextMenu ref="subMenu" :model="subMenuitems">
                                                        <template #itemicon="item">
                                                            <FontAwesomeIcon :icon="item.item.icon" />
                                                        </template>
                                                    </ContextMenu>
                                                </div>
                                            </div>
                                        </template>
                                    </draggable>
                                </div>

                                <!--  mobile  -->
                                <div class="block md:hidden">
                                    <Disclosure v-slot="{ open }" class="m-2">
                                        <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                            <DisclosureButton
                                                class="p-2 md:p-0 transition-all flex justify-between cursor-default  w-full">
                                                <div class="flex justify-between w-full">
                                                    <span
                                                        class="mb-2 md:mb-0 pl-0 md:pl-[2.2rem] text-xl font-semibold leading-6">
                                                        <div v-html="item.name"></div>
                                                    </span>
                                                    <div>
                                                        <FontAwesomeIcon :icon="faTriangle"
                                                            :class="['w-2 h-2 transition-transform', open ? 'rotate-180' : '']" />
                                                    </div>
                                                </div>
                                            </DisclosureButton>

                                            <DisclosurePanel class="p-2 md:p-0 transition-all cursor-default w-full">
                                                <ul class="block space-y-4 pl-0 md:pl-[2.2rem]">
                                                    <li v-for="menu of item.data" :key="menu.name"
                                                        class="flex items-center text-sm">
                                                        <div v-html="menu.name"></div>
                                                    </li>
                                                </ul>
                                            </DisclosurePanel>
                                        </div>
                                    </Disclosure>
                                </div>

                            </div>
                        </template>
                    </draggable>
                    <div v-if="editable" @click="addMenuToColumn(modelValue.columns['column_1']['data'])"
                        class="border border-dashed w-[80%] p-2 rounded-xl flex items-center justify-center gap-3 shadow-lg hover:shadow-xl hover:bg-gray-50 transition-all duration-300 ease-in-out cursor-pointer transform hover:scale-105 hidden hidden md:flex">
                        <FontAwesomeIcon :icon="['fas', 'plus']" class="text-blue-600 text-2xl"></FontAwesomeIcon>
                        <span class="text-gray-700 font-semibold text-lg">Add Menu</span>
                    </div>
                </div>
                <!--  column 2 -->
                <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                    <draggable v-model="modelValue.columns['column_2']['data']" group="row" itemKey="id"
                        :animation="200" handle=".handle" @start="onDrag" @end="onDrop"
                        @update:model-value="(e) => { modelValue.columns['column_2']['data'] = e, emits('update:modelValue', modelValue) }"
                        class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <template #item="{ element: item, index: index }">
                            <div>
                                <!--  desktop -->
                                <div
                                    class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                    <div class="flex text-xl font-semibold  leading-6"
                                        @contextmenu="onRightClickMenu($event, item, modelValue.columns['column_2']['data'], index)">
                                        <FontAwesomeIcon icon="fal fa-bars"
                                            class="handle text-white cursor-grab pr-3 mr-2" />
                                        <div class="w-full">
                                            <Editor :key="editorKey"
                                                :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                                                v-model="item.name" :editable="editable" class=""
                                                @update:model-value="(e) => { item.name = e, emits('update:modelValue', modelValue) }" />

                                        </div>
                                        <ContextMenu ref="menu" :model="Menuitems">
                                            <template #itemicon="item">
                                                <FontAwesomeIcon :icon="item.item.icon" />
                                            </template>
                                        </ContextMenu>
                                    </div>
                                    <draggable v-model="item.data" group="sub-row" itemKey="id" :animation="200"
                                        handle=".handle-sub" @start="onDrag" @end="onDrop"
                                        @update:model-value="(e) => { item.data = e, emits('update:modelValue', modelValue) }">
                                        <template #item="{ element: sub, index: subIndex }"
                                            class="hidden md:block space-y-3">
                                            <div class="flex w-full items-center gap- mt-2">
                                                <div class="flex items-center w-full"
                                                    @contextmenu="onRightClickSubMenu($event, item, modelValue.columns['column_2']['data'], subIndex)">
                                                    <FontAwesomeIcon icon="fal fa-bars"
                                                        class="handle-sub text-sm text-white cursor-grab pr-3 mr-2" />
                                                    <div class="w-full">
                                                        <Editor
                                                            :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                                                            :key="editorKey" v-model="sub.name" :editable="editable"
                                                            @update:model-value="(e) => { sub.name = e, emits('update:modelValue', modelValue) }" />
                                                    </div>
                                                    <ContextMenu ref="subMenu" :model="subMenuitems">
                                                        <template #itemicon="item">
                                                            <FontAwesomeIcon :icon="item.item.icon" />
                                                        </template>
                                                    </ContextMenu>
                                                </div>
                                            </div>
                                        </template>
                                    </draggable>
                                </div>

                                <!--  mobile  -->
                                <div class="block md:hidden">
                                    <Disclosure v-slot="{ open }" class="m-2">
                                        <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                            <DisclosureButton
                                                class="p-3 pb-0  transition-all flex justify-between cursor-default  w-full">
                                                <div class="flex justify-between w-full">
                                                    <span
                                                        class="mb-0 pl-0 md:pl-[2.2rem] text-xl font-semibold leading-6">
                                                        <div v-html="item.name"></div>
                                                    </span>
                                                    <div>
                                                        <FontAwesomeIcon :icon="faTriangle"
                                                            :class="['w-2 h-2 transition-transform', open ? 'rotate-180' : '']" />
                                                    </div>
                                                </div>
                                            </DisclosureButton>

                                            <DisclosurePanel class="p-3 pb-0  transition-all cursor-default w-full">
                                                <ul class="mt-0 block space-y-4 pl-0 md:pl-[2.2rem]">
                                                    <li v-for="menu of item.data" :key="menu.name"
                                                        class="flex items-center text-sm">
                                                        <div v-html="menu.name"></div>
                                                    </li>
                                                </ul>
                                            </DisclosurePanel>
                                        </div>
                                    </Disclosure>
                                </div>

                            </div>
                        </template>
                    </draggable>
                    <div v-if="editable" @click="addMenuToColumn(modelValue.columns['column_2']['data'])"
                        class="border border-dashed w-[80%] p-2 rounded-xl flex items-center justify-center gap-3 shadow-lg hover:shadow-xl hover:bg-gray-50 transition-all duration-300 ease-in-out cursor-pointer transform hover:scale-105 hidden md:flex">
                        <FontAwesomeIcon :icon="['fas', 'plus']" class="text-blue-600 text-2xl"></FontAwesomeIcon>
                        <span class="text-gray-700 font-semibold text-lg">Add Menu</span>
                    </div>
                </div>
                <!--  column 3 -->
                <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                    <draggable v-model="modelValue.columns['column_3']['data']" group="row" itemKey="id"
                        :animation="200" handle=".handle" @start="onDrag" @end="onDrop"
                        @update:model-value="(e) => { modelValue.columns['column_3']['data'] = e, emits('update:modelValue', modelValue) }"
                        class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <template #item="{ element: item, index: index }">
                            <div>
                                <!--  desktop -->
                                <div
                                    class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                    <div class="flex text-xl font-semibold  leading-6"
                                        @contextmenu="onRightClickMenu($event, item, modelValue.columns['column_3']['data'], index)">
                                        <FontAwesomeIcon icon="fal fa-bars"
                                            class="handle text-white cursor-grab pr-3 mr-2" />
                                        <div class="w-full">
                                            <Editor
                                                :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                                                :key="editorKey" v-model="item.name" :editable="editable"
                                                @update:model-value="(e) => { item.name = e, emits('update:modelValue', modelValue) }" />

                                        </div>
                                        <ContextMenu ref="menu" :model="Menuitems">
                                            <template #itemicon="item">
                                                <FontAwesomeIcon :icon="item.item.icon" />
                                            </template>
                                        </ContextMenu>
                                    </div>
                                    <draggable v-model="item.data" group="sub-row" itemKey="id" :animation="200"
                                        handle=".handle-sub" @start="onDrag" @end="onDrop"
                                        @update:model-value="(e) => { item.data = e, emits('update:modelValue', modelValue) }">
                                        <template #item="{ element: sub, index: subIndex }"
                                            class="hidden md:block space-y-3">
                                            <div class="flex w-full items-center gap- mt-2">
                                                <div class="flex items-center w-full"
                                                    @contextmenu="onRightClickSubMenu($event, item, modelValue.columns['column_3']['data'], subIndex)">
                                                    <FontAwesomeIcon icon="fal fa-bars"
                                                        class="handle-sub text-sm text-white cursor-grab pr-3 mr-2" />
                                                    <div class="w-full">
                                                        <Editor
                                                            :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                                                            :key="editorKey" v-model="sub.name" :editable="editable"
                                                            @update:model-value="(e) => { sub.name = e, emits('update:modelValue', modelValue) }" />
                                                    </div>
                                                    <ContextMenu ref="subMenu" :model="subMenuitems">
                                                        <template #itemicon="item">
                                                            <FontAwesomeIcon :icon="item.item.icon" />
                                                        </template>
                                                    </ContextMenu>
                                                </div>
                                            </div>
                                        </template>
                                    </draggable>
                                </div>

                                <!--  mobile  -->
                                <div class="block md:hidden">
                                    <Disclosure v-slot="{ open }" class="m-2">
                                        <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                            <DisclosureButton
                                                class="p-2 md:p-0 transition-all flex justify-between cursor-default  w-full">
                                                <div class="flex justify-between w-full">
                                                    <span
                                                        class="mb-2 md:mb-0 pl-0 md:pl-[2.2rem] text-xl font-semibold leading-6">
                                                        <div v-html="item.name"></div>
                                                    </span>
                                                    <div>
                                                        <FontAwesomeIcon :icon="faTriangle"
                                                            :class="['w-2 h-2 transition-transform', open ? 'rotate-180' : '']" />
                                                    </div>
                                                </div>
                                            </DisclosureButton>

                                            <DisclosurePanel class="p-2 md:p-0 transition-all cursor-default w-full">
                                                <ul class="block space-y-4 pl-0 md:pl-[2.2rem]">
                                                    <li v-for="menu of item.data" :key="menu.name"
                                                        class="flex items-center text-sm">
                                                        <div v-html="menu.name"></div>
                                                    </li>
                                                </ul>
                                            </DisclosurePanel>
                                        </div>
                                    </Disclosure>
                                </div>

                            </div>
                        </template>
                    </draggable>
                    <div v-if="editable" @click="addMenuToColumn(modelValue.columns['column_3']['data'])"
                        class="border border-dashed w-[80%] p-2 rounded-xl flex items-center justify-center gap-3 shadow-lg hover:shadow-xl hover:bg-gray-50 transition-all duration-300 ease-in-out cursor-pointer transform hover:scale-105 hidden md:flex">
                        <FontAwesomeIcon :icon="['fas', 'plus']" class="text-blue-600 text-2xl"></FontAwesomeIcon>
                        <span class="text-gray-700 font-semibold text-lg">Add Menu</span>
                    </div>
                </div>

                <!--  column 4 -->
                <div class="flex flex-col flex-col-reverse gap-y-6 md:block">
                    <div>
                        <address class="mt-10 md:mt-0 mb-4">
                            <Editor :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                                :key="editorKey" v-model="modelValue.columns.column_4.data.textBox1"
                                :editable="editable"
                                @update:model-value="(e) => { modelValue.columns.column_4.data.textBox1 = e, emits('update:modelValue', modelValue) }" />
                        </address>

                        <div class="mt-10 md:mt-0 mb-4 w-full">
                            <Editor :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                                :key="editorKey" v-model="modelValue.columns.column_4.data.textBox2"
                                :editable="editable"
                                @update:model-value="(e) => { modelValue.columns.column_4.data.textBox2 = e, emits('update:modelValue', modelValue) }" />
                        </div>

                        <div class="w-full">
                            <Editor :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                                :key="editorKey" v-model="modelValue.paymentData.label" :editable="editable"
                                @update:model-value="(e) => { modelValue.paymentData.label = e, emits('update:modelValue', modelValue) }" />
                        </div>

                        <div class="group relative flex flex-col items-center gap-y-6 mt-8 hover-dashed"
                            @click="() => sendMessageToParent('panelOpen', 'payments')">
                            <div v-for="payment of modelValue.paymentData.data" :key="payment.key">
                                <img :src="payment.image" :alt="payment.alt"
                                    class="h-auto max-h-7 md:max-h-8 max-w-full w-fit">
                            </div>
                        </div>
                    </div>


                    <!-- <div
                            class="hidden md:block mb-6 md:mb-5 bg-[#9c7c64] md:bg-transparent text-center md:text-left pt-4 pb-6 space-y-4 md:py-0 md:space-y-0">
                            <h2 class=" tracking-wider font-semibold md:mt-8 md:mb-4">Get Social with Us!</h2>
                            <div class="flex md:space-x-6 md:mb-4 justify-around md:justify-start">
                                <a v-for="item of modelValue.socialmedia" :key="item.icon" target="_blank"
                                    :href="item.link"><font-awesome-icon :icon="item.icon" class="text-2xl" /></a>
                            </div>
                        </div> -->
                </div>
            </div>
        </div>
        <div
            class="mt-8 border-0 border-t border-solid border-gray-700 flex flex-col md:flex-row-reverse justify-between pt-6 items-center gap-y-8 ">
            <div class="grid gap-y-2 text-center md:text-left ">
                <div class="group relative flex gap-x-6 justify-center hover-dashed">
                    <a v-for="item of modelValue.socialMedia" target="_blank" :key="item.icon"><font-awesome-icon
                            :icon="item.icon" class="text-2xl"
                            @click="() => sendMessageToParent('panelOpen', 'social-media')" /></a>
                </div>
            </div>

            <div id="footer_copyright" class="text-[14px] md:text-[12px] text-center">
                <Editor :class="'model border border-transparent hover-dashed border-dashed cursor-text'"
                    :key="editorKey" v-model="modelValue.copyright" :editable="editable"
                    @update:model-value="(e) => { modelValue.copyright = e, emits('update:modelValue', props.modelValue) }" />
            </div>
        </div>
    </div>
</template>



<style scss></style>
