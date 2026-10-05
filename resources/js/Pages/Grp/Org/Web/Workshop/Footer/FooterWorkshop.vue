<script setup lang="ts">
import { ref, watch, computed, IframeHTMLAttributes, onMounted, provide, inject, nextTick } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Button from '@/Components/Elements/Buttons/Button.vue';
import Modal from '@/Components/Utils/Modal.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue';
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue';
import { notify } from "@kyvg/vue3-notification"
import axios from 'axios'
import { debounce, isArray } from 'lodash-es'
import Publish from '@/Components/Publish.vue'
import ScreenView from "@/Components/ScreenView.vue"
import Image from "@common/Components/Image.vue"
import HeaderListModal from '@/Components/CMS/Fields/ListModal.vue'
import { ctrans } from "@/Composables/useTrans"
import { getBlueprint } from '@/Composables/getBlueprintWorkshop'
import { getFieldKey } from '@/Composables/SideEditorHelper'
import Icon from '@/Components/Icon.vue'
import { setIframeView } from "@/Composables/Workshop"
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Drawer from 'primevue/drawer';
import { getTranslationComponent } from '@/Composables/getWorkshopComponents'

import { routeType } from "@/types/route"
import { PageHeadingTypes } from '@/types/PageHeading'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faIcons, faMoneyBill, faUpload, faThLarge } from '@fas';
import { faLineColumns, faLowVision } from '@far';
import { faExternalLink, faLanguage, faTimes, faPencil, faChevronDoubleLeft, faChevronDoubleRight } from '@fal';
import { faEye } from '@fad';
import { library } from '@fortawesome/fontawesome-svg-core'


library.add(faExternalLink, faTimes, faLineColumns, faIcons, faMoneyBill, faUpload, faThLarge, faLowVision, faPencil)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {
        data: Object
    }
    status: boolean
    autosaveRoute: routeType
    webBlockTypes: Object
    uploadImageRoute: routeType
    domain: string
}>()
const status = ref(!props.status)
const previewMode = ref(false)
const isModalOpen = ref(false)
const usedTemplates = ref(isArray(props.data.data) ? null : props.data.data)
const isLoading = ref(false)
const isSaving = ref(false)
const comment = ref('')
const iframeClass = ref('w-full h-full')
const saveCancelToken = ref<Function | null>(null)
const isIframeLoading = ref(true)
const locale = inject('locale', aikuLocaleStructure)
const langOptions = Object.values(locale.languageOptions)
const darwerRight = ref(false)
const iframeSrc =
    route('grp.websites.footer.preview', [
        route().params['website'],
        {
            organisation: route().params["organisation"],
            shop: route().params["shop"],
            fulfilment: route().params["fulfilment"]
        }
    ])


const onPickTemplate = (footer: Object) => {
    isModalOpen.value = false
    usedTemplates.value = footer
    isIframeLoading.value = true
}

const onPublish = async (action: routeType, popover: Function) => {
    try {
        if (!action || !action.method || !action.name || !action.parameters) {
            throw new Error('Invalid action parameters')
        }
        isLoading.value = true
        const response = await axios[action.method](route(action.name, action.parameters), {
            comment: comment.value,
            layout: { ...usedTemplates.value, status: status.value }
        })
        popover.close()
    } catch (error) {
        const errorMessage = error.response?.data?.message || error.message || 'Unknown error occurred'
        notify({
            title: 'Something went wrong.',
            text: errorMessage,
            type: 'error',
        })
    } finally {
        isLoading.value = false
    }
};


const autoSave = async (data: Object) => {
    router.patch(
        route(props.autosaveRoute.name, props.autosaveRoute.parameters),
        { layout: data },
        {
            onStart: () => {
                isSaving.value = true
            },
            onFinish: () => {
                isSaving.value = false
                saveCancelToken.value = null
                sendToIframe({ key: 'reload', value: {} })
                if (isIframeLoading.value) {
                    isIframeLoading.value = false
                    /*    location.reload(); */
                }
            },
            onCancelToken: (cancelToken) => {
                saveCancelToken.value = cancelToken.cancel
            },
            onCancel: () => {
            },
            onError: (error) => {
                notify({
                    title: ctrans('Something went wrong.'),
                    text: error.message,
                    type: 'error',
                })
            },
            preserveScroll: true,
            preserveState: true,
        }
    )
}

const debouncedSendUpdate = debounce((data) => autoSave(data), 1000, { leading: false, trailing: true })

const handleIframeError = () => {
    console.error('Failed to load iframe content.');
}

watch(usedTemplates, (newVal) => {
    if (saveCancelToken.value) saveCancelToken.value()
    if (newVal) debouncedSendUpdate(newVal)
}, { deep: true })


watch(previewMode, (newVal) => {
    sendToIframe({ key: 'isPreviewMode', value: newVal })
}, { deep: true })


const viewModes = [
    { key: 'edit', label: ctrans('Edit'), icon: faPencil, isPreview: false, tooltip: ctrans('Edit the footer directly in the preview') },
    { key: 'preview', label: ctrans('Preview'), icon: faEye, isPreview: true, tooltip: ctrans('See the footer exactly as on the website') },
]

const _iframe = ref<IframeHTMLAttributes | null>(null)
const sendToIframe = (data: any) => {
    _iframe.value?.contentWindow.postMessage(data, '*')
}

const onIframeLoad = () => {
    isIframeLoading.value = false
    sendToIframe({ key: 'isPreviewMode', value: previewMode.value })
}

const openWebsite = () => {
    window.open('https://' + props.domain, "_blank")
}

const panelOpen = ref()
const _sideEditor = ref<HTMLElement | null>(null)
const sideEditorStorageKey = 'footer-workshop-side-editor-open'
const readSideEditorOpen = (): boolean => {
    try {
        return localStorage.getItem(sideEditorStorageKey) !== 'false'
    } catch {
        return true
    }
}
const isSideEditorOpen = ref(readSideEditorOpen())
watch(isSideEditorOpen, (isOpen) => {
    try {
        localStorage.setItem(sideEditorStorageKey, String(isOpen))
    } catch {
    }
})

const sideEditorSections = computed(() =>
    (usedTemplates.value ? getBlueprint(usedTemplates.value.code) : [])
        .filter((section) => section.name && section.type != 'hidden')
        .map((section) => ({
            name: section.name,
            icon: section.icon,
            panelKey: section.accordion_key ?? getFieldKey(section.key, section.name),
        }))
)

const openSidePanel = async (panel: string) => {
    isSideEditorOpen.value = true
    panelOpen.value = null
    await nextTick()
    panelOpen.value = panel
    await nextTick()
    _sideEditor.value?.querySelector('.p-accordionpanel-active')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
const handleIframeMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return;
    const { data } = event;

    if (data.key === 'autosave') {
        if (saveCancelToken.value) saveCancelToken.value()
        usedTemplates.value = data.value
    } if (data.key === 'panelOpen') {
        openSidePanel(data.value)
    }
};

const openFullScreenPreview = () => {
    const url = new URL(iframeSrc, window.location.origin);
    url.searchParams.set('isInWorkshop', 'true');
    url.searchParams.set('mode', 'iris');
    window.open(url.toString(), '_blank');
}

onMounted(() => {
    window.addEventListener('message', handleIframeMessage);
});



const selectedLang = ref<string | null>(null)
const currentView = ref('desktop')
provide('currentView', currentView)
watch(currentView, (newValue) => {
    iframeClass.value = setIframeView(newValue)
})

watch(selectedLang, (val) => {
    if (val !== null) {
        previewMode.value = true
    } else {
        previewMode.value = false
    }

    sendToIframe({ key: 'active_language', value: val })
})

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #mainIcon v-if="isSaving">
            <LoadingIcon size="sm" />
        </template>
        <template #button-publish="{ action }">
            <Publish :isLoading="isLoading || isSaving" :is_dirty="true" v-model="comment"
                @onPublish="(popover) => onPublish(action.route, popover)" />
        </template>
        <template #other>
            <div class="px-2 cursor-pointer" v-tooltip="ctrans('Go to website')" @click="openWebsite">
                <FontAwesomeIcon :icon="faExternalLink" fixed-width aria-hidden="true" size="xl" />
            </div>
        </template>
    </PageHeading>

    <div class="h-[84vh] flex">
        <aside v-if="usedTemplates && isSideEditorOpen" class="w-80 shrink-0 bg-[#F9F9F9] flex flex-col border-r border-gray-300">
            <div class="pl-3 pr-1.5 h-9 bg-gray-50 flex items-center justify-between gap-2 border-b border-gray-300 text-sm">
                <div class="flex items-center gap-2 font-semibold">
                    <FontAwesomeIcon :icon="faLineColumns" fixed-width aria-hidden="true" />
                    {{ ctrans("Footer") }}
                </div>
                <div class="flex items-center gap-1">
                    <Button type="tertiary" size="xxs" :icon="faThLarge" :label="ctrans('Template')"
                        @click="isModalOpen = true" />
                    <button type="button" class="h-7 w-7 rounded text-gray-500 hover:bg-gray-200 hover:text-gray-700"
                        v-tooltip="ctrans('Collapse editor')" @click="isSideEditorOpen = false">
                        <FontAwesomeIcon :icon="faChevronDoubleLeft" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </div>
            <div ref="_sideEditor" class="compact-side-editor flex-1 overflow-y-auto">
                <SideEditor v-model="usedTemplates.data.fieldValue"
                    :blueprint="getBlueprint(usedTemplates.code)" :panel-open="panelOpen"
                    :uploadImageRoute="uploadImageRoute"
                    @update:model-value="e => usedTemplates.data.fieldValue = e" />
            </div>
        </aside>

        <aside v-else-if="usedTemplates" class="w-10 shrink-0 bg-[#F9F9F9] flex flex-col items-center border-r border-gray-300">
            <button type="button" class="h-9 w-full border-b border-gray-300 bg-gray-50 text-gray-500 hover:text-gray-700"
                v-tooltip="ctrans('Expand editor')" @click="isSideEditorOpen = true">
                <FontAwesomeIcon :icon="faChevronDoubleRight" fixed-width aria-hidden="true" />
            </button>
            <div class="flex flex-col items-center gap-0.5 py-1.5">
                <button v-for="section in sideEditorSections" :key="section.panelKey" type="button"
                    class="h-8 w-8 rounded text-gray-600 hover:bg-gray-200 hover:text-gray-900"
                    v-tooltip="{ value: section.name, showDelay: 100 }" @click="openSidePanel(section.panelKey)">
                    <Icon v-if="section.icon" :data="{ ...section.icon, tooltip: undefined }" />
                    <span v-else class="text-xs font-semibold">{{ section.name.charAt(0) }}</span>
                </button>
            </div>
            <button type="button" class="mt-auto mb-1.5 h-8 w-8 rounded text-gray-600 hover:bg-gray-200"
                v-tooltip="ctrans('Change template')" @click="isModalOpen = true">
                <FontAwesomeIcon :icon="faThLarge" fixed-width aria-hidden="true" />
            </button>
        </aside>

        <section class="flex-1 min-w-0 bg-gray-100">
            <div v-if="usedTemplates?.data" class="h-full flex flex-col">
                <div class="flex items-center justify-between gap-3 bg-slate-200 border-b border-gray-300 pr-3">
                    <div class="flex items-center">
                        <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
                        <div class="py-1 px-2 cursor-pointer text-gray-500 hover:text-amber-600"
                            v-tooltip="ctrans('Open preview in new tab')" @click="openFullScreenPreview">
                            <FontAwesomeIcon :icon="faEye" fixed-width aria-hidden="true" />
                        </div>
                        <div v-if="selectedLang" class="py-1 px-2 cursor-pointer text-gray-500 hover:text-amber-600"
                            v-tooltip="ctrans('Open translation')" @click="darwerRight = !darwerRight">
                            <FontAwesomeIcon :icon="faLanguage" fixed-width aria-hidden="true" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-xs">
                        <span class="text-gray-500 tabular-nums">
                            {{ isSaving ? ctrans("Saving…") : ctrans("All changes saved") }}
                        </span>
                        <div class="inline-flex rounded-md bg-white p-0.5 ring-1 ring-gray-300">
                            <button v-for="mode in viewModes" :key="mode.key" type="button"
                                :disabled="selectedLang !== null"
                                class="flex items-center gap-1.5 rounded px-2.5 py-1 font-medium transition-colors disabled:cursor-not-allowed"
                                :class="previewMode === mode.isPreview ? 'bg-slate-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                                v-tooltip="mode.tooltip"
                                @click="previewMode = mode.isPreview">
                                <FontAwesomeIcon :icon="mode.icon" fixed-width aria-hidden="true" />
                                {{ mode.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="relative flex-1 min-h-0 overflow-hidden"
                    :class="currentView === 'desktop' ? '' : 'py-4'">
                    <div v-if="isIframeLoading"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-4 bg-white/80">
                        <LoadingIcon class="text-4xl" />
                        <span class="text-sm text-gray-500">{{ ctrans("Loading preview") }}</span>
                    </div>
                    <iframe :src="iframeSrc" :title="props.title" ref="_iframe"
                        class="bg-white transition-all" :class="[iframeClass, currentView === 'desktop' ? '' : 'shadow-lg']"
                        @error="handleIframeError" @load="onIframeLoad" />
                </div>
            </div>

            <div v-else class="h-full flex items-center justify-center bg-white">
                <EmptyState
                    :data="{ description: ctrans('Pick a footer template to start editing'), title: ctrans('Pick footer template') }">
                    <template #button-empty-state>
                        <div class="mt-4 block">
                            <Button type="secondary" :label="ctrans('Templates')" :icon="faThLarge"
                                @click="isModalOpen = true" />
                        </div>
                    </template>
                </EmptyState>
            </div>
        </section>
    </div>

    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false">
        <HeaderListModal :onSelectBlock="onPickTemplate"
            :webBlockTypes="webBlockTypes.data.filter((item) => item.component == 'footer')"
            :currentTopbar="usedTemplates">
            <template #image="{ block }">
                <div @click="() => onPickTemplate(block)"
                    class="min-h-16 w-full aspect-[2/1] overflow-hidden flex items-center bg-gray-100 justify-center border border-gray-300 hover:border-indigo-500 rounded cursor-pointer">
                    <div class="w-auto shadow-md">
                        <Image :src="block.screenshot" class="object-contain" />
                    </div>
                </div>
            </template>
        </HeaderListModal>
    </Modal>

    <Drawer v-model:visible="darwerRight" :dismissable="false" :header="`Translation ${selectedLang}`" position="right"
        :pt="{ root: { style: 'width: 80vw' } }">
        <div>
            <component :is="getTranslationComponent(usedTemplates?.code)" v-model="usedTemplates"
                :translation="selectedLang" />
        </div>
    </Drawer>
</template>


<style scoped lang="scss">
.compact-side-editor {
    :deep(.p-accordionheader) {
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
    }

    :deep(.p-accordionpanel) {
        border-width: 0 0 1px 0;
    }

    :deep(.p-accordioncontent-content) {
        padding: 0.625rem 0.75rem !important;
        font-size: 0.8125rem;
    }
}
</style>
