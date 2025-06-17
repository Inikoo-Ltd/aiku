<script setup lang="ts">
import { ref, watch, IframeHTMLAttributes, onMounted, provide} from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { Switch } from '@headlessui/vue'
import Button from '@/Components/Elements/Buttons/Button.vue';
import Modal from '@/Components/Utils/Modal.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue';
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue';
import { notify } from "@kyvg/vue3-notification"
import axios from 'axios'
import { debounce, isArray } from 'lodash-es'
import Publish from '@/Components/Publish.vue'
import ScreenView from "@/Components/ScreenView.vue"
import Image from '@/Components/Image.vue'
import HeaderListModal from '@/Components/CMS/Fields/ListModal.vue'
import { trans } from "laravel-vue-i18n"
import { getBlueprint } from '@/Composables/getBlueprintWorkshop'
import { setIframeView } from "@/Composables/Workshop"
import ProgressSpinner from 'primevue/progressspinner';

import { routeType } from "@/types/route"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faIcons, faMoneyBill, faUpload, faThLarge } from '@fas';
import { faLineColumns, faLowVision } from '@far';
import { faExternalLink, faTimes } from '@fal';
import { faEye } from '@fad';
import { library } from '@fortawesome/fontawesome-svg-core'


library.add(faExternalLink, faTimes, faLineColumns, faIcons, faMoneyBill, faUpload, faThLarge, faLowVision)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {
        data: Object
    }
    status:boolean
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
const comment = ref('')
const iframeClass = ref('w-full h-full')
const saveCancelToken = ref<Function | null>(null)
const isIframeLoading = ref(true)
const iframeSrc = 
    route('grp.websites.footer.preview', [
        route().params['website'],
        {
            organisation: route().params["organisation"],
            shop: route().params["shop"],
            fulfilment : route().params["fulfilment"]
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
            layout: {... usedTemplates.value,  status : status.value}
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
            onFinish: () => {
                saveCancelToken.value = null
                sendToIframe({ key: 'reload', value: {} })
                if(isIframeLoading.value){
                    isIframeLoading.value = false
                 /*    location.reload(); */
                }
            },
            onCancelToken: (cancelToken) => {
                saveCancelToken.value = cancelToken.cancel
            },
            onCancel: () => {
                console.log('The saving progress canceled.')
            },
            onError: (error) => {
                notify({
                    title: trans('Something went wrong.'),
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


const _iframe = ref<IframeHTMLAttributes | null>(null)
const sendToIframe = (data: any) => {
    _iframe.value?.contentWindow.postMessage(data, '*')
}

const openWebsite = () => {
  window.open('https://'+ props.domain, "_blank")
}

const panelOpen = ref()
const handleIframeMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return;
    const { data } = event;

    if (data.key === 'autosave') {
        if (saveCancelToken.value) saveCancelToken.value()
        usedTemplates.value = data.value
    } if (data.key === 'panelOpen') {
        panelOpen.value = data.value
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

const currentView = ref('desktop')
provide('currentView',currentView )
watch(currentView, (newValue) => {
	iframeClass.value = setIframeView(newValue)
})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-publish="{ action }">
            <Publish :isLoading="isLoading" :is_dirty="true" v-model="comment"
                @onPublish="(popover) => onPublish(action.route, popover)" >
                <!-- Move this to Advanced Setting later -->
                <!-- <template #form-extend>
                    <div class="flex items-center gap-2 mb-3">
                    <div class="items-start leading-none flex-shrink-0">
                        <FontAwesomeIcon :icon="'fas fa-asterisk'" class="font-light text-[12px] text-red-400 mr-1" />
                        <span class="capitalize">{{ trans('Status') }} :</span>
                    </div>
                    <div class="flex items-center gap-4 w-full">
                        <div class="flex overflow-hidden border-2 cursor-pointer w-full sm:w-auto"
                            :class="status ? 'border-green-500' : 'border-red-500'" @click="()=>status=!status">
                        <div class="flex-1 text-center py-1 px-1 sm:px-2 text-xs font-semibold transition-all duration-200 ease-in-out"
                                :class="status ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500'">
                            Active
                        </div>

                        <div class="flex-1 text-center py-1 px-1 sm:px-2 text-xs font-semibold transition-all duration-200 ease-in-out"
                                :class="!status ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-500'">
                            Inactive
                        </div>
                        </div>
                    </div>
                    </div>
                </template> -->
            </Publish>
        </template>
        <template #other>
            <div class=" px-2 cursor-pointer" v-tooltip="'go to website'" @click="openWebsite" >
                <FontAwesomeIcon :icon="faExternalLink" aria-hidden="true" size="xl" />
            </div>
        </template>
    </PageHeading>

    <div class="h-[84vh] grid grid-flow-row-dense grid-cols-4">
        <div v-if="usedTemplates" class="col-span-1 bg-[#F9F9F9] flex flex-col h-full border-r border-gray-300">
            <div class="h-full">
                <div class="w-full overflow-y-auto">
                    <div class="px-3 py-0.5 sticky top-0 bg-gray-50 z-20 text-lg font-semibold flex items-center justify-end gap-3 border-b border-gray-300">
                        <div class="py-1 px-2 cursor-pointer" title="template" v-tooltip="'Template'"
                                @click="isModalOpen = true">
                                <FontAwesomeIcon :icon="faThLarge" aria-hidden='true' />
                            </div>
                    </div>
                    <div class="">
                    <SideEditor 
                        v-model="usedTemplates.data.fieldValue" 
                        :blueprint="getBlueprint(usedTemplates.code)" 
                        :panel-open="panelOpen" 
                        :uploadImageRoute="uploadImageRoute"
                        @update:model-value="e => usedTemplates.data.fieldValue = e"
                    />
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-gray-100 h-full" :class="usedTemplates?.data ? 'col-span-3' : 'col-span-4'">
            <div class="h-full w-full bg-white">
                <div v-if="usedTemplates?.data" class="w-full h-full">
                    <div class="flex justify-between bg-slate-200 border border-b-gray-300">
                        <div class="flex">
                            <ScreenView @screenView="(e) => {currentView = e}" v-model="currentView" />
                            <div class="py-1 px-2 cursor-pointer text-gray-500 hover:text-amber-600" v-tooltip="trans('Open preview in new tab')"
                                @click="openFullScreenPreview">
						        <FontAwesomeIcon :icon="faEye" fixed-width aria-hidden="true" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-xs" :class="[
                                previewMode ? 'text-slate-600' : 'text-slate-300'
                            ]">Preview</div>
                            <Switch @click="previewMode = !previewMode" :class="[
                                previewMode ? 'bg-slate-600' : 'bg-slate-300'
                            ]"
                                class="pr-1 relative inline-flex h-3 w-6 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75">
                                <span aria-hidden="true" :class="previewMode ? 'translate-x-3' : 'translate-x-0'"
                                    class="pointer-events-none inline-block h-full w-1/2 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out">
                                </span>
                            </Switch>

                          
                           
                        </div>
                    </div>

                    <div v-if="isIframeLoading" class="loading-overlay">
                        <ProgressSpinner />
                    </div>
                    <iframe 
                        :src="iframeSrc" 
                        :title="props.title" 
                        :class="[iframeClass, isIframeLoading ? 'hidden' : '']"
                        @error="handleIframeError"
                        @load="isIframeLoading = false" 
                        ref="_iframe"
                     />
                </div>
                <div v-else>
                    <EmptyState
                        :data="{ description: 'You need pick a template from list', title: 'Pick Footer Templates' }">
                        <template #button-empty-state>
                            <div class="mt-4 block">
                                <Button type="secondary" label="Templates" icon="fas fa-th-large"
                                    @click="isModalOpen = true"></Button>
                            </div>
                        </template>
                    </EmptyState>
                </div>
            </div>
        </div>
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
</template>


<style scoped lang="scss">
:deep(.loading-overlay) {
    position: block;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.8);
    z-index: 1000;
}

:deep(.spinner) {
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top: 4px solid #3498db;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
}
</style>
