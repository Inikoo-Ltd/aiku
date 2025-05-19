<script setup lang="ts">
import { ref, watch, computed, toRaw, onMounted, IframeHTMLAttributes, provide, nextTick } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { notify } from "@kyvg/vue3-notification"
import Publish from '@/Components/Publish.vue'
import ScreenView from "@/Components/ScreenView.vue"
import HeaderListModal from '@/Components/CMS/Fields/ListModal.vue'
import { getBlueprint } from '@/Composables/getBlueprintWorkshop'
import { irisStyleVariables, setIframeView } from '@/Composables/Workshop'
import { useColorTheme } from '@/Composables/useStockList'
import { set, get, debounce } from 'lodash-es'

import { routeType } from "@/types/route"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'

import { faPresentation, faCube, faText, faPaperclip, faRectangleWide, faDotCircle, faSignInAlt, faHeart as falHeart, faExternalLink, faBrowser, faMobile } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHeading, faHeart, faLowVision, faSignIn } from '@far'

import { trans } from 'laravel-vue-i18n'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue';
import Toggle from '@/Components/Pure/Toggle.vue'
import LoadingText from '@/Components/Utils/LoadingText.vue'

library.add(faBrowser, faPresentation, faCube, faText, faHeart, faPaperclip, faRectangleWide, faDotCircle, faSignInAlt, falHeart, faLowVision, faMobile)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    uploadImageRoute: routeType
    data: {
        data: {
            header: Object,
            topBar: Object
        }
    }
    status: boolean
    autosaveRoute: routeType
    web_block_types: {}
    route_list: {
        upload_image: routeType
        uploaded_images_list: routeType
        stock_images_list: routeType
    }
    domain: string
}>()

provide('route_list', props.route_list)
const usedTemplates = ref({
    header: props?.data?.data?.header,
    topBar: props?.data?.data?.topBar
})
const isLoading = ref(false)
const comment = ref('')
const status = ref(!props.status)
const iframeClass = ref('w-full h-full')
const isIframeLoading = ref(true)
const iframeSrc = route('grp.websites.header.preview', [route().params['website']])
const tabs = [
    {
        label: "TopBars settings",
        componentName: "topbar",
        key: 'topBar',
        icon: faSignIn,
        scope: 'topbar'
    },
    {
        label: "Website header",
        componentName: "header",
        key: 'header',
        icon: faHeading,
        scope: 'header'
    }
]
const keySidebar = ref(0)
const selectedTab = ref(tabs[0])
const saveCancelToken = ref<Function | null>(null)
const isPreviewLoggedIn = ref(false)
const _iframe = ref<IframeHTMLAttributes | null>(null)

const isLoadingTemplate = ref(false)
const onSelectBlock = async (selectedBlock: object) => {
    isLoadingTemplate.value = true

    setTimeout(() => {
        const selectedKey = selectedTab.value.key;
        const currentTemplate = toRaw(usedTemplates.value[selectedKey])
        const newTemplate = { ...toRaw(selectedBlock) }

        newTemplate.data.fieldValue = {
            ...currentTemplate?.data?.fieldValue,
            ...newTemplate?.data?.fieldValue
        };

        usedTemplates.value[selectedKey] = newTemplate
        keySidebar.value++
        nextTick(() => {
            isLoadingTemplate.value = false
            isModalOpen.value = false
        })
    }, 500)
}

// Method: Publish
const publishCancelToken = ref<{ cancel: Function } | null>(null)
const onPublish = async (action: routeType, popover: Function) => {
    router[action.method || 'post'](
        route(action.name, action.parameters),
        {
            comment: comment.value,
            layout: { ...usedTemplates.value, status: status.value }
        },
        {
            onStart: () => isLoading.value = true,
            onCancelToken: (cancelToken) => publishCancelToken.value = cancelToken,
            onFinish: () => {
                isLoading.value = false
                publishCancelToken.value = null
            },
            onSuccess: () => {
                comment.value = ""
            },
            onError: (error) => {
                notify({
                    title: trans('Something went wrong.'),
                    text: error.message,
                    type: 'error',
                })
            }
        }
    )
}

// Method: auto save
const isLoadingSave = ref(false)
const onProgress = ref(false)
const autoSave = async (data: {}) => {
    router.patch(
        route(props.autosaveRoute.name, props.autosaveRoute.parameters),
        { layout: data },
        {
            onStart: () => isLoadingSave.value = true,
            onFinish: () => {
                isLoadingSave.value = false,
                    saveCancelToken.value = null
                sendToIframe({ key: 'reload', value: {} })
            },
            onProgress: (progress) => {
                onProgress.value = progress
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
const openFullScreenPreview = () => {
    const url = new URL(iframeSrc, window.location.origin);
    url.searchParams.set('isInWorkshop', 'true');
    url.searchParams.set('mode', 'iris');
    window.open(url.toString(), '_blank');
}

const handleIframeError = () => {
    console.error('Failed to load iframe content.');
}

const openWebsite = () => {
    window.open('https://' + props.domain, "_blank")
}

watch(usedTemplates, (newVal) => {
    if (newVal) {
        if (saveCancelToken.value) {
            saveCancelToken.value()
        }
        debouncedSendUpdate(toRaw(newVal))
    }
}, { deep: true })


const selectedWebBlock = computed(() => {
    const data = props.web_block_types.filter(item => item.data.component === selectedTab.value.componentName)
    if (selectedTab.value.componentName == "topbar") {
        if (route().params["fulfilment"]) return data.filter((item) => item.code.includes('fulfilment'))
        else return data.filter((item) => !item.code.includes('fulfilment'))
    } else return data
})


const isModalOpen = ref(false)
const sendToIframe = (data: any) => {
    _iframe.value?.contentWindow.postMessage(data, '*')
}
const panelActive = ref()

onMounted(() => {
    if (!get(props.data, 'theme.color', false)) {
        set(props.data, 'theme.color', [...useColorTheme[0]])
    }
    irisStyleVariables(props.data.theme?.color)

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;
        const { data } = event;
        if (event.data.key === 'openModalBlockList') {
            isModalOpen.value = true
        } else if (data.key === 'TopbarPanelOpen') {
            panelActive.value = event.data.value
            selectedTab.value = tabs[0]
        } else if (data.key === 'HeaderPanelOpen') {
            panelActive.value = event.data.value
            selectedTab.value = tabs[1]
        } else if (data.key === 'autosave') {
            if (saveCancelToken.value) saveCancelToken.value()
            usedTemplates.value = data.value
        }
    })
})


</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #mainIcon v-if="isLoadingSave">
            <LoadingIcon size="sm" />
        </template>

        <template #button-publish="{ action }">
            <Publish v-model="comment" :isLoading="isLoading || isLoadingSave" :is_dirty="true"
                @onPublish="(popover) => onPublish(action.route, popover)">
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
            <div class=" px-2 cursor-pointer" v-tooltip="'go to website'" @click="openWebsite">
                <FontAwesomeIcon :icon="faExternalLink" aria-hidden="true" size="xl" />
            </div>
        </template>
    </PageHeading>
    <div class="h-[84vh] flex">
        <div v-if="usedTemplates" class="col-span-2 bg-[#F9F9F9] flex flex-col h-full border-r border-gray-300">
            <!-- Section: Side editor -->
            <div class="flex h-full w-96">
                <div class="min-w-fit w-[10%] bg-slate-200 ">
                    <div v-for="(tab, index) in tabs" class="py-2 px-3 cursor-pointer" :title="tab.label"
                        @click="selectedTab = tab"
                        :class="[selectedTab.key == tab.key ? 'bg-indigo-500 text-white' : 'hover:bg-gray-200/60']"
                        v-tooltip="tab.label">
                        <FontAwesomeIcon :icon="tab.icon" aria-hidden='true' />
                    </div>
                </div>

                <div class="w-full overflow-y-auto"
                    :class="!usedTemplates?.[selectedTab.key]?.data?.fieldValue ? 'bg-gray-300' : ''">
                    <div
                        class="px-3 py-0.5 sticky top-0 bg-gray-50 z-20 text-lg font-semibold flex items-center justify-between gap-3 border-b border-gray-300">
                        <div class="flex items-center gap-3">
                            <FontAwesomeIcon :icon="selectedTab.icon" aria-hidden="true" />
                            <span>{{ selectedTab.label }}</span>
                        </div>

                        <div class="py-1 px-2 cursor-pointer" title="template" v-tooltip="'Template'"
                            @click="isModalOpen = true">
                            <FontAwesomeIcon icon="fas fa-th-large" aria-hidden='true' />
                        </div>
                    </div>

                    <div class="">
                        <SideEditor v-if="usedTemplates?.[selectedTab.key]?.data?.fieldValue" :key="keySidebar"
                            v-model="usedTemplates[selectedTab.key].data.fieldValue"
                            :blueprint="getBlueprint(usedTemplates[selectedTab.key].code)"
                            :uploadImageRoute="uploadImageRoute" :panelOpen="panelActive" />
                    </div>
                </div>
            </div>
        </div>

        <div :class="usedTemplates ? 'col-span-8' : 'col-span-10'" class="w-full">
            <div v-if="usedTemplates?.topBar?.code || usedTemplates?.header?.code" class="bg-white h-full">
                <div class="flex justify-between max-w-7xl mx-auto bg-slate-200 border border-b-gray-300 pr-6">
                    <div class="flex">
                        <ScreenView @screenView="(e) => iframeClass = setIframeView(e)" />
                        <div class="py-1 px-2 cursor-pointer" title="Desktop view" v-tooltip="'Preview'"
                            @click="openFullScreenPreview">
                            <FontAwesomeIcon :icon='faLowVision' aria-hidden='true' />
                        </div>
                    </div>

                    <div class="flex items-center gap-x-2">
                        <span :class="!isPreviewLoggedIn ? 'text-gray-600' : 'text-gray-400'">Logged out</span>
                        <Toggle v-model="isPreviewLoggedIn"
                            @update:modelValue="(newVal) => sendToIframe({ key: 'isPreviewLoggedIn', value: newVal })" />
                        <span :class="isPreviewLoggedIn ? 'text-gray-600' : 'text-gray-400'">Logged in</span>
                    </div>
                </div>

                <div v-if="isIframeLoading"
                    class="flex justify-center items-center gap-y-6 flex-col top-0 left-0 w-full h-full z-[1000]">
                    <LoadingIcon class="text-5xl" />
                    <LoadingText :text="trans('Loading iframe')" class="text-2xl" />
                </div>

                <!-- Workshop Preview -->
                <iframe v-show="!isIframeLoading" ref="_iframe" :src="iframeSrc" :title="props.title"
                    :class="iframeClass" @error="handleIframeError" @load="isIframeLoading = false" />
            </div>

            <section v-else>
                <EmptyState :data="{ description: 'You need pick a template from list', title: 'Pick Templates' }">
                    <template #button-empty-state>
                        <div class="mt-4 block">
                            <Button type="secondary" :label="`Templates ${selectedTab.scope}`" icon="fas fa-th-large"
                                @click="isModalOpen = true" />
                        </div>
                    </template>
                </EmptyState>
            </section>
        </div>
    </div>
    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false">
        <HeaderListModal :onSelectBlock :webBlockTypes="selectedWebBlock" :currentTopbar="usedTemplates.topBar"
            :isLoading="isLoadingTemplate" />
    </Modal>
</template>


<style lang="scss" scoped>
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
