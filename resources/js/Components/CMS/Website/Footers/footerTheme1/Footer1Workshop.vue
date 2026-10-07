<script setup lang="ts">
import { ref, provide, inject, computed } from "vue"

import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import draggable from "vuedraggable"
import { v4 as uuidv4 } from "uuid"
import ContextMenu from "primevue/contextmenu"
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/vue"
import { getStyles } from "@/Composables/styles"
import Image from "@common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { sendMessageToParent } from "@/Composables/Workshop"
import { isObject } from "lodash-es"
import { FieldValue } from "@/types/Website/Website/footer1"

import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShieldAlt, faPlus, faTrash, faTriangle } from "@fas"
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faBars, faImage } from "@fal"
import { ctrans } from "@/Composables/useTrans"


library.add(faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faShieldAlt, faBars, faPlus, faTrash, faWhatsapp)

type MenuColumnKey = "column_1" | "column_2" | "column_3"

const props = defineProps<{
    modelValue: FieldValue,
    keyTemplate?: String
    colorThemed?: Object
    screenType?: "mobile" | "tablet" | "desktop"
    previewMode?: boolean
}>()

const emits = defineEmits<{
    (e: "update:modelValue", value: FieldValue): void
}>()

const menuColumnKeys: MenuColumnKey[] = ["column_1", "column_2", "column_3"]

const layout = inject("layout", {})

const editorKey = ref(uuidv4())
const editable = ref(true)
const selectedMenu = ref(null)
const selectedIndex = ref<number | null>(null)
const selectedColumn = ref(null)
const menuContext = ref()
const subMenuContext = ref()

const menuContextItems = [
    {
        label: ctrans("Add sub menu"),
        icon: "fas fa-plus",
        command: () => addSubMenu(selectedMenu.value)
    },
    {
        label: ctrans("Delete"),
        icon: "fas fa-trash",
        command: () => deleteMenu(selectedColumn.value, selectedIndex.value)
    }
]

const subMenuContextItems = [
    {
        label: ctrans("Delete"),
        icon: "fas fa-trash",
        command: () => deleteSubMenu(selectedMenu.value, selectedIndex.value)
    }
]

const logoStyles = computed(() => getStyles(props.modelValue?.logo?.properties, props.screenType, false) ?? {})
const logoBoxStyles = computed(() => ({
    width: "auto",
    height: logoStyles.value.height || "96px",
    maxWidth: "100%",
}))

const emitUpdate = () => {
    emits("update:modelValue", props.modelValue)
}

const onDrag = () => {
    editorKey.value = uuidv4()
    editable.value = false
}

const onDrop = () => {
    editorKey.value = uuidv4()
    editable.value = true
}

const addSubMenu = (menu) => {
    const newSubMenu = { name: ctrans("New Sub Menu"), id: uuidv4() }

    if (menu.data) {
        menu.data.push(newSubMenu)
    } else {
        menu.data = [newSubMenu]
    }
    emitUpdate()
}

const deleteMenu = (column, index: number) => {
    column.splice(index, 1)
    emitUpdate()
}

const deleteSubMenu = (menu, index: number) => {
    menu.data.splice(index, 1)
    emitUpdate()
}

const addMenuToColumn = (column) => {
    column.push({
        name: ctrans("New Menu"),
        id: uuidv4(),
        data: [
            { name: ctrans("New Sub Menu"), id: uuidv4() }
        ]
    })
    emitUpdate()
}

const onRightClickMenu = (event: MouseEvent, menu, column, index: number) => {
    selectedMenu.value = menu
    selectedIndex.value = index
    selectedColumn.value = column
    menuContext.value.show(event)
}

const onRightClickSubMenu = (event: MouseEvent, menu, index: number) => {
    selectedMenu.value = menu
    selectedIndex.value = index
    subMenuContext.value.show(event)
}

const selectAllEditor = (editor: any) => {
    editor.commands.selectAll()
}

const openPanel = (panel: string) => {
    sendMessageToParent("panelOpen", panel)
}

provide("onSaveWorkshopFromId", () => {})
provide("onSaveWorkshop", () => {})
</script>


<template>
    <div id="footer_1_iris" class="md:mx-0 pb-12 lg:pb-24 pt-4 md:pt-8 md:px-16 text-white" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        margin: 0,
        ...getStyles(modelValue.container?.properties, screenType)
    }">
        <div
            class="w-full flex flex-col md:flex-row gap-4 md:gap-8 pt-2 pb-4 md:pb-6 mb-4 md:mb-10 border-0 border-b border-solid border-gray-700">
            <div v-if="modelValue?.logo?.source" class="shrink-0 mx-auto md:mx-0 hover-dashed" :style="logoBoxStyles"
                @click="openPanel('logo')">
                <span class="block w-full h-full pt-3">
                    <Image
                        :style="{ ...logoStyles, width: '100%', height: '100%', objectFit: 'contain' }"
                        :alt="modelValue?.logo?.alt"
                        :imageCover="true"
                        :src="modelValue?.logo?.source"
                        :height="logoStyles.height || undefined"
                        :width="logoStyles.width || undefined"
                    />
                </span>
            </div>
            <div v-else
                class="shrink-0 mx-auto md:mx-0 h-24 w-24 flex flex-col items-center justify-center gap-1 rounded border border-dashed border-white/30 text-xs opacity-60 hover:opacity-100 cursor-pointer"
                @click="openPanel('logo')">
                <FontAwesomeIcon :icon="faImage" class="text-xl" fixed-width aria-hidden="true" />
                {{ ctrans("Add logo") }}
            </div>

            <div v-if="modelValue?.email" @click="openPanel('email')"
                class="relative group flex-1 flex justify-center md:justify-start items-center hover-dashed">
                <a style="font-size: 17px">{{ modelValue?.email }}</a>
            </div>

            <div v-if="modelValue?.whatsapp?.number" @click="openPanel('whatsapp')"
                class="relative group flex-1 flex gap-x-1.5 justify-center md:justify-start items-center hover-dashed">
                <a class="flex gap-x-2 items-center">
                    <FontAwesomeIcon class="text-[#00EE52]" icon="fab fa-whatsapp" style="font-size: 22px" fixed-width />
                    WA: <span style="font-size: 17px">{{ modelValue?.whatsapp?.number }}</span>
                </a>
            </div>

            <div class="group relative flex-1 flex flex-col items-center md:items-end justify-center hover-dashed"
                @click="openPanel('phone')">
                <a v-for="phone of modelValue?.phone?.numbers" style="font-size: 17px">
                    {{ phone }}
                </a>
                <span style="font-size: 15px">{{ modelValue?.phone?.caption }}</span>
            </div>
        </div>


        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 md:gap-8">
            <div v-for="columnKey in menuColumnKeys" :key="columnKey" class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                    <draggable v-model="modelValue.columns[columnKey].data" group="row" itemKey="id"
                        :animation="200" handle=".handle" @start="onDrag" @end="onDrop"
                        @update:model-value="(e) => { modelValue.columns[columnKey].data = e; emitUpdate() }"
                        class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <template #item="{ element: item, index }">
                            <section>
                                <div
                                    class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                    <div class="group/menu relative flex text-xl font-semibold w-fit min-w-[4rem] leading-6"
                                        @contextmenu.prevent="onRightClickMenu($event, item, modelValue.columns[columnKey].data, index)">
                                        <FontAwesomeIcon icon="fal fa-bars"
                                            class="handle absolute -left-6 top-1 text-sm cursor-grab opacity-0 group-hover/menu:opacity-60 hover:!opacity-100 transition-opacity"
                                            v-tooltip="ctrans('Drag to reorder')" fixed-width />
                                        <Editor :key="editorKey" v-model="item.name" :editable="editable"
                                            @onEditClick="selectAllEditor"
                                            @update:model-value="(e) => { item.name = e; emitUpdate() }" />
                                        <div
                                            class="absolute left-full top-0 ml-2 flex items-center gap-1 text-xs font-normal opacity-0 group-hover/menu:opacity-100 transition-opacity">
                                            <button type="button" v-tooltip="ctrans('Add sub menu')"
                                                class="h-6 w-6 rounded bg-black/40 hover:bg-black/70"
                                                @click="addSubMenu(item)">
                                                <FontAwesomeIcon icon="fas fa-plus" fixed-width />
                                            </button>
                                            <button type="button" v-tooltip="ctrans('Delete menu')"
                                                class="h-6 w-6 rounded bg-black/40 hover:bg-red-600"
                                                @click="deleteMenu(modelValue.columns[columnKey].data, index)">
                                                <FontAwesomeIcon icon="fas fa-trash" fixed-width />
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <draggable v-model="item.data" tag="ul" group="sub-row" itemKey="id"
                                            :animation="200" handle=".handle-sub" @start="onDrag" @end="onDrop"
                                            @update:model-value="(e) => { item.data = e; emitUpdate() }"
                                            ghost-class="ghost-item" class="hidden md:block space-y-3">
                                            <template #item="{ element: sub, index: subIndex }">
                                                <li class="group/sub relative flex w-full items-center gap-2"
                                                    @contextmenu.prevent="onRightClickSubMenu($event, item, subIndex)">
                                                    <FontAwesomeIcon icon="fal fa-bars"
                                                        class="handle-sub absolute -left-5 text-xs cursor-grab opacity-0 group-hover/sub:opacity-60 hover:!opacity-100 transition-opacity"
                                                        fixed-width />
                                                    <div class="text-sm block min-w-[3rem]">
                                                        <Editor :key="editorKey" v-model="sub.name" :editable="editable"
                                                            @onEditClick="selectAllEditor"
                                                            @update:model-value="(e) => { sub.name = e; emitUpdate() }" />
                                                    </div>
                                                    <button type="button" v-tooltip="ctrans('Delete sub menu')"
                                                        class="ml-auto h-5 w-5 shrink-0 rounded text-[10px] bg-black/40 hover:bg-red-600 opacity-0 group-hover/sub:opacity-100 transition-opacity"
                                                        @click="deleteSubMenu(item, subIndex)">
                                                        <FontAwesomeIcon icon="fas fa-trash" fixed-width />
                                                    </button>
                                                </li>
                                            </template>
                                        </draggable>
                                    </div>
                                </div>

                                <div class="block md:hidden">
                                    <Disclosure v-slot="{ open }" class="m-2">
                                        <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                            <DisclosureButton
                                                class="p-3 pb-0 md:p-0 transition-all flex justify-between cursor-default w-full">
                                                <div class="flex justify-between w-full">
                                                    <span class="mb-0 pl-0 md:pl-[2.2rem] text-xl font-semibold leading-6">
                                                        <div v-html="item.name"></div>
                                                    </span>
                                                    <div>
                                                        <FontAwesomeIcon :icon="faTriangle"
                                                            :class="['w-2 h-2 transition-transform', open ? 'rotate-180' : '']" fixed-width />
                                                    </div>
                                                </div>
                                            </DisclosureButton>

                                            <DisclosurePanel class="p-3 md:p-0 transition-all cursor-default w-full">
                                                <ul class="mt-0 block space-y-4 pl-4 md:pl-[2.2rem]" style="margin-top: 0">
                                                    <li v-for="menu of item.data" :key="menu.id ?? menu.name"
                                                        class="flex items-center text-sm">
                                                        <div v-html="menu.name"></div>
                                                    </li>
                                                </ul>
                                            </DisclosurePanel>
                                        </div>
                                    </Disclosure>
                                </div>
                            </section>
                        </template>
                    </draggable>

                    <button v-if="editable" type="button"
                        class="hidden md:flex w-fit items-center gap-2 rounded border border-dashed border-white/30 px-3 py-1.5 text-sm opacity-50 hover:opacity-100 transition-opacity"
                        @click="addMenuToColumn(modelValue.columns[columnKey].data)">
                        <FontAwesomeIcon icon="fas fa-plus" fixed-width aria-hidden="true" />
                        {{ ctrans("Add menu") }}
                    </button>
                </div>
            </div>

            <div class="flex flex-col flex-col-reverse gap-y-6 md:block">
                <div>
                    <address
                        class="mt-10 md:mt-0 not-italic mb-4 text-center md:text-left text-xs md:text-sm text-gray-300">
                        <Editor v-if="!isObject(modelValue?.columns.column_4.data.textBox1)" :key="editorKey"
                            v-model="modelValue.columns.column_4.data.textBox1" :editable="editable"
                            @onEditClick="selectAllEditor"
                            @update:model-value="(e) => { modelValue.columns.column_4.data.textBox1 = e; emitUpdate() }" />
                        <Editor v-else :key="editorKey" v-model="modelValue.columns.column_4.data.textBox1.text"
                            :editable="editable" @onEditClick="selectAllEditor"
                            @update:model-value="(e) => { modelValue.columns.column_4.data.textBox1.text = e; emitUpdate() }" />
                    </address>

                    <div class="flex justify-center gap-x-8 text-gray-300 md:block">
                        <Editor v-if="!isObject(modelValue?.columns.column_4.data.textBox2)" :key="editorKey"
                            v-model="modelValue.columns.column_4.data.textBox2" :editable="editable"
                            @onEditClick="selectAllEditor"
                            @update:model-value="(e) => { modelValue.columns.column_4.data.textBox2 = e; emitUpdate() }" />
                        <Editor v-else :key="editorKey" v-model="modelValue.columns.column_4.data.textBox2.text"
                            :editable="editable" @onEditClick="selectAllEditor"
                            @update:model-value="(e) => { modelValue.columns.column_4.data.textBox2.text = e; emitUpdate() }" />
                    </div>

                    <div class="w-full mt-8">
                        <Editor :key="editorKey" v-model="modelValue.paymentData.label" :editable="editable"
                            @onEditClick="selectAllEditor"
                            @update:model-value="(e) => { modelValue.paymentData.label = e; emitUpdate() }" />
                    </div>

                    <div class="flex flex-col items-center gap-y-6 mt-4 hover-dashed" @click="openPanel('payments')">
                        <div v-for="(payment, paymentIndex) of modelValue.paymentData.data" :key="payment.key"
                            class="w-full h-6 md:h-8 flex items-center justify-center">
                            <img
                                :src="payment?.image"
                                :alt="payment?.alt || 'payment' + paymentIndex"
                                class="h-full w-auto max-w-full object-contain"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="modelValue?.subscribe?.is_show && !layout.iris?.is_logged_in" @click="openPanel('subscribe')"
            class="mt-16 border-t border-white/10 px-8 md:px-0 pt-8 md:mt-8 flex flex-col md:flex-row items-center md:justify-between hover-dashed">
            <div class="w-fit text-center md:text-left">
                <h3 class="text-sm/6 font-semibold text-white"
                    v-html="modelValue.subscribe?.headline ?? 'Subscribe to our newsletter'"></h3>
                <p class="mt-2 text-sm/6 text-gray-300"
                    v-html="modelValue.subscribe?.description ?? 'The latest news, articles, and resources, sent to your inbox weekly.'">
                </p>
            </div>

            <div class="relative flex flex-col items-start">
                <form @submit.prevent class="w-full max-w-md md:w-fit mt-6 sm:flex items-center sm:max-w-md lg:mt-0">
                    <label for="email-address" class="sr-only">Email address</label>
                    <input type="email" name="email-address" id="email-address" autocomplete="off" readonly
                        class="w-full min-w-0 rounded-md bg-white/5 px-3 py-1 text-base text-white outline outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 md:w-56 md:text-sm/6"
                        :placeholder="modelValue?.subscribe?.placeholder ?? ctrans('Enter your email')" />

                    <div class="mt-4 sm:ml-4 sm:mt-0 sm:shrink-0">
                        <Button :label="ctrans('Subscribe')" full @click.prevent />
                    </div>
                </form>
            </div>
        </div>

        <div
            class="mt-8 w-full border-0 border-t border-solid border-white/10 flex flex-col md:flex-row-reverse justify-between pt-6 items-center gap-y-8">
            <div class="grid gap-y-2 text-center md:text-left">
                <div v-if="modelValue?.socialMedia?.length" class="flex gap-x-5 justify-center hover-dashed"
                    @click="openPanel('social-media')">
                    <a v-for="(socmed, socmedIndex) of modelValue?.socialMedia" :key="socmed.icon + socmedIndex"
                        :aria-label="socmed.type || 'socmed' + socmedIndex">
                        <FontAwesomeIcon :icon="socmed.icon" class="text-4xl md:text-2xl" fixed-width />
                    </a>
                </div>
                <button v-else type="button"
                    class="flex items-center gap-2 rounded border border-dashed border-white/30 px-3 py-1.5 text-sm opacity-50 hover:opacity-100 transition-opacity"
                    @click="openPanel('social-media')">
                    <FontAwesomeIcon icon="fas fa-plus" fixed-width aria-hidden="true" />
                    {{ ctrans("Add social media") }}
                </button>
            </div>

            <div id="footer_copyright"
                class="text-[13px] leading-5 md:text-[12px] text-center md:w-fit mx-auto md:mx-0 hover-text-input">
                <Editor :key="editorKey" v-model="modelValue.copyright" :editable="editable"
                    @update:model-value="(e) => { modelValue.copyright = e; emitUpdate() }" />
            </div>
        </div>

        <ContextMenu ref="menuContext" :model="menuContextItems">
            <template #itemicon="{ item }">
                <FontAwesomeIcon :icon="item.icon" fixed-width />
            </template>
        </ContextMenu>
        <ContextMenu ref="subMenuContext" :model="subMenuContextItems">
            <template #itemicon="{ item }">
                <FontAwesomeIcon :icon="item.icon" fixed-width />
            </template>
        </ContextMenu>
    </div>
</template>


<style scoped lang="scss">
.editor-class ul {
    margin-left: 0rem;
    margin-top: 0.5rem;
    list-style-position: outside;
}

.ghost-item {
    opacity: 0.5;
}
</style>
