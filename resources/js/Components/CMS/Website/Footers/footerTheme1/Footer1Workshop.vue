<script setup lang="ts">
import { ref, watch, provide} from 'vue'

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


const onSaveWorkshop = (c) => {
	console.log(c)
}

const onSaveWorkshopFromId = (blockId: number, from?: string) => {
	console.log(blockId,from)
}

provide('onSaveWorkshopFromId', onSaveWorkshopFromId)
provide('onSaveWorkshop', onSaveWorkshop)

</script>


<template>
    <div id="app" class="-mx-2 md:mx-0 pb-24 pt-4 md:pt-8 md:px-16 text-white"
        :style="getStyles(modelValue?.container?.properties)">
        <div
            class="w-full flex flex-col md:flex-row gap-4 md:gap-8 pt-2 pb-4 md:pb-6 mb-4 md:mb-10 border-0 border-b border-gray-700">
            <div class="flex-1 flex items-center justify-center md:justify-start border-solid hover-dashed"
                @click="() => sendMessageToParent('panelOpen', 'logo')">
                <Image v-if="modelValue?.logo?.source" :src="modelValue?.logo?.source" :imageCover="true"
                    :alt="modelValue?.logo?.alt" :imgAttributes="modelValue?.logo?.attributes"
                    :style="getStyles(modelValue?.logo?.properties)" />
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
                        @update:model-value="(e) => { modelValue.columns['column_1']['data'] = e; emits('update:modelValue', modelValue); }"
                        class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <template #item="{ element: item, index: index }">
                            <div>
                                <!-- Desktop View -->
                                <div
                                    class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                    <div class="flex text-xl font-semibold leading-6"
                                        @contextmenu="onRightClickMenu($event, item, modelValue.columns['column_1']['data'], index)">
                                        <FontAwesomeIcon icon="fal fa-bars" v-if="!previewMode"
                                            class="handle text-white cursor-grab pr-3 mr-2" />
                                        <div class="w-full">
                                            <Editor :key="editorKey" v-model="item.name" :editable="editable"
                                                @onEditClick="selectAllEditor"
                                                @update:model-value="(e) => { item.name = e; emits('update:modelValue', modelValue) }" />
                                        </div>
                                        <ContextMenu ref="menu" :model="Menuitems">
                                            <template #itemicon="item">
                                                <FontAwesomeIcon :icon="item.item.icon" />
                                            </template>
                                        </ContextMenu>
                                    </div>
                                    <draggable v-model="item.data" group="sub-row" itemKey="id" :animation="200"
                                        handle=".handle-sub" @start="onDrag" @end="onDrop"
                                        @update:model-value="(e) => { item.data = e; emits('update:modelValue', modelValue) }"
                                        :ghost-class="'ghost-item'">
                                        <template #item="{ element: sub, index: subIndex }">
                                            <div class="flex w-full items-center gap- mt-2">
                                                <div class="flex items-center w-full"
                                                    @contextmenu="onRightClickSubMenu($event, item, modelValue.columns['column_1']['data'], subIndex)">
                                                    <FontAwesomeIcon icon="fal fa-bars"
                                                        class="handle-sub text-sm text-white cursor-grab pr-3 mr-2" />
                                                    <div class="w-full">
                                                        <Editor :key="editorKey" v-model="sub.name" :editable="editable"
                                                            @onEditClick="selectAllEditor"
                                                            @update:model-value="(e) => { sub.name = e; emits('update:modelValue', modelValue) }" />
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

                                <!-- Mobile View -->
                                <div class="block md:hidden">
                                    <Disclosure v-slot="{ open }" class="m-2">
                                        <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                            <DisclosureButton
                                                class="p-2 md:p-0 transition-all flex justify-between cursor-default w-full">
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
                        @update:model-value="(e) => { modelValue.columns['column_2']['data'] = e; emits('update:modelValue', modelValue); }"
                        class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <template #item="{ element: item, index: index }">
                            <div>
                                <!-- Desktop View -->
                                <div
                                    class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                    <div class="flex text-xl font-semibold leading-6"
                                        @contextmenu="onRightClickMenu($event, item, modelValue.columns['column_2']['data'], index)">
                                        <FontAwesomeIcon icon="fal fa-bars" v-if="!previewMode"
                                            class="handle text-white cursor-grab pr-3 mr-2" />
                                        <div class="w-full">
                                            <Editor :key="editorKey" v-model="item.name" :editable="editable" @onEditClick="selectAllEditor"
                                                @update:model-value="(e) => { item.name = e; emits('update:modelValue', modelValue) }" />
                                        </div>
                                        <ContextMenu ref="menu" :model="Menuitems">
                                            <template #itemicon="item">
                                                <FontAwesomeIcon :icon="item.item.icon" />
                                            </template>
                                        </ContextMenu>
                                    </div>
                                    <draggable v-model="item.data" group="sub-row" itemKey="id" :animation="200"
                                        handle=".handle-sub" @start="onDrag" @end="onDrop"
                                        @update:model-value="(e) => { item.data = e; emits('update:modelValue', modelValue) }"
                                        :ghost-class="'ghost-item'">
                                        <template #item="{ element: sub, index: subIndex }">
                                            <div class="flex w-full items-center gap- mt-2">
                                                <div class="flex items-center w-full"
                                                    @contextmenu="onRightClickSubMenu($event, item, modelValue.columns['column_2']['data'], subIndex)">
                                                    <FontAwesomeIcon icon="fal fa-bars"
                                                        class="handle-sub text-sm text-white cursor-grab pr-3 mr-2" />
                                                    <div class="w-full">
                                                        <Editor :key="editorKey" v-model="sub.name" :editable="editable"  @onEditClick="selectAllEditor"
                                                            @update:model-value="(e) => { sub.name = e; emits('update:modelValue', modelValue) }" />
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

                                <!-- Mobile View -->
                                <div class="block md:hidden">
                                    <Disclosure v-slot="{ open }" class="m-2">
                                        <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                            <DisclosureButton
                                                class="p-2 md:p-0 transition-all flex justify-between cursor-default w-full">
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
                        @update:model-value="(e) => { modelValue.columns['column_3']['data'] = e; emits('update:modelValue', modelValue); }"
                        class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <template #item="{ element: item, index: index }">
                            <div>
                                <!-- Desktop View -->
                                <div
                                    class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                    <div class="flex text-xl font-semibold leading-6"
                                        @contextmenu="onRightClickMenu($event, item, modelValue.columns['column_3']['data'], index)">
                                        <FontAwesomeIcon icon="fal fa-bars" v-if="!previewMode"
                                            class="handle text-white cursor-grab pr-3 mr-2" />
                                        <div class="w-full">
                                            <Editor :key="editorKey" v-model="item.name" :editable="editable"     @onEditClick="selectAllEditor"
                                                @update:model-value="(e) => { item.name = e; emits('update:modelValue', modelValue) }" />
                                        </div>
                                        <ContextMenu ref="menu" :model="Menuitems">
                                            <template #itemicon="item">
                                                <FontAwesomeIcon :icon="item.item.icon" />
                                            </template>
                                        </ContextMenu>
                                    </div>
                                    <draggable v-model="item.data" group="sub-row" itemKey="id" :animation="200"
                                        handle=".handle-sub" @start="onDrag" @end="onDrop"
                                        @update:model-value="(e) => { item.data = e; emits('update:modelValue', modelValue) }"
                                        :ghost-class="'ghost-item'">
                                        <template #item="{ element: sub, index: subIndex }">
                                            <div class="flex w-full items-center gap- mt-2">
                                                <div class="flex items-center w-full"
                                                    @contextmenu="onRightClickSubMenu($event, item, modelValue.columns['column_3']['data'], subIndex)">
                                                    <FontAwesomeIcon icon="fal fa-bars"
                                                        class="handle-sub text-sm text-white cursor-grab pr-3 mr-2" />
                                                    <div class="w-full">
                                                        <Editor :key="editorKey" v-model="sub.name" :editable="editable"     @onEditClick="selectAllEditor"
                                                            @update:model-value="(e) => { sub.name = e; emits('update:modelValue', modelValue) }" />
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

                                <!-- Mobile View -->
                                <div class="block md:hidden">
                                    <Disclosure v-slot="{ open }" class="m-2">
                                        <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                            <DisclosureButton
                                                class="p-2 md:p-0 transition-all flex justify-between cursor-default w-full">
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
                            <Editor :key="editorKey" v-model="modelValue.columns.column_4.data.textBox1"
                                :editable="editable"     @onEditClick="selectAllEditor"
                                @update:model-value="(e) => { modelValue.columns.column_4.data.textBox1 = e, emits('update:modelValue', modelValue) }" />
                        </address>

                        <div class="mt-10 md:mt-0 mb-4 w-full">
                            <Editor :key="editorKey" v-model="modelValue.columns.column_4.data.textBox2"
                                :editable="editable"     @onEditClick="selectAllEditor"
                                @update:model-value="(e) => { modelValue.columns.column_4.data.textBox2 = e, emits('update:modelValue', modelValue) }" />
                        </div>

                        <div class="w-full">
                            <Editor :key="editorKey" v-model="modelValue.paymentData.label" :editable="editable"     @onEditClick="selectAllEditor"
                                @update:model-value="(e) => { modelValue.paymentData.label = e, emits('update:modelValue', modelValue) }" />
                        </div>

                        <div class="flex flex-col items-center gap-y-6 mt-4"
                            @click="() => sendMessageToParent('panelOpen', 'payments')">
                            <div v-for="payment of modelValue.paymentData.data" :key="payment.key">
                                <img :src="payment.image" :alt="payment.alt" class="h-auto max-h-6 md:max-h-8 max-w-full w-full object-contain">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
        <div v-if="modelValue?.subscribe?.is_show"
            @click="() => sendMessageToParent('panelOpen', 'subscribe')"
            class="mt-16 border-t border-white/10 px-8 md:px-0 pt-8 md:mt-8 flex flex-col md:flex-row items-center md:justify-between">
            <div class="w-fit text-center md:text-left ">
                <h3 class="text-sm/6 font-semibold text-white hover-dashed" v-html="modelValue.subscribe?.headline ?? 'Subscribe to our newsletter'"></h3>
                <p class="mt-2 text-sm/6 text-gray-300 hover-dashed"  v-html="modelValue.subscribe?.description ?? 'The latest news, articles, and resources, sent to your inbox weekly.'"></p>
            </div>
            
            <!-- <Transition> -->
                <div xv-if="currentState != 'success'" class="flex flex-col items-start">
                    <form @submit.prevent="() => false" class="w-full max-w-md md:w-fit mt-6 sm:flex sm:max-w-md lg:mt-0 ">
                        <label for="email-address" class="sr-only">Email address</label>
                        <input
                            xv-model="inputEmail"
                            type="email"
                            name="email-address"
                            id="email-address"
                            autocomplete="email"
                            required
                            class="w-full min-w-0 rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 md:w-56 md:text-sm/6"
                            :placeholder="modelValue?.subscribe?.placeholder ?? 'Enter your email'"
                        />
                        <div class="mt-4 sm:ml-4 sm:mt-0 sm:shrink-0">
                            <button type="submit" class="flex w-full items-center justify-center rounded-md bg-indigo-500 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                                <!-- <LoadingIcon v-if="isLoadingSubmit" class="mr-2" /> -->
                                Subscribe
                            </button>
                        </div>
                    </form>

                    <!-- <div v-if="currentState === 'error'" class="text-red-500 mt-2 italic">
                        *{{ errorMessage }}
                    </div> -->
                </div>

                <!-- <div v-else class="ml-auto mt-6 text-center text-green-500 flex flex-col items-center gap-y-2">
                    <FontAwesomeIcon icon="fas fa-check-circle" class="text-4xl" fixed-width aria-hidden="true" />
                    {{ trans("You have successfully subscribed") }}!
                </div> -->
            <!-- </Transition> -->
        </div>

        <div
            class="mt-8 border-0 border-t border-solid border-white/10 flex flex-col md:flex-row-reverse justify-between pt-6 items-center gap-y-8 ">
            <div class="grid gap-y-2 text-center md:text-left ">
                <div class="group relative flex gap-x-6 justify-center hover-dashed">
                    <a v-for="item of modelValue.socialMedia" target="_blank" :key="item.icon"><font-awesome-icon
                            :icon="item.icon" class="text-2xl"
                            @click="() => sendMessageToParent('panelOpen', 'social-media')" /></a>
                </div>
            </div>

            <div id="footer_copyright" class="text-[14px] md:text-[12px] text-center">
                <Editor :class="'model border border-transparent hover-text-input border-dashed cursor-text'"
                    :key="editorKey" v-model="modelValue.copyright" :editable="editable"
                    @update:model-value="(e) => { modelValue.copyright = e, emits('update:modelValue', props.modelValue) }" />
            </div>
        </div>
    </div>
</template>



<style scss>
.ghost-item {
    opacity: 0.5;
    transform: scale(1.05);
    transition: transform 0.2s ease-in-out;
}
</style>
