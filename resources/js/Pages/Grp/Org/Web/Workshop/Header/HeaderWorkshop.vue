<script setup lang="ts">
import { ref, watch, computed, toRaw, onMounted, onBeforeUnmount, IframeHTMLAttributes, provide, nextTick } from 'vue'
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
import { PageHeadingTypes } from '@/types/PageHeading'

import { faPresentation, faSearch, faCube, faText, faPaperclip, faRectangleWide, faDotCircle, faSignInAlt, faSignOutAlt, faHeart as falHeart, faExternalLink, faBrowser, faMobile, faSignIn as falSignIn, faSignOut, faUser, faImage, faInfo, faChevronDoubleLeft, faChevronDoubleRight } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHeading, faHeart, faLowVision, faSignIn, faThLarge } from '@fas'
import { faEye } from '@fad'

import { ctrans } from '@/Composables/useTrans'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

library.add(faBrowser, faPresentation, faSearch, faCube, faText, faHeart, faPaperclip, faRectangleWide, faDotCircle, faSignInAlt, faSignOutAlt, falHeart, faLowVision, faMobile, falSignIn, faSignOut, faUser, faImage, faInfo, faExternalLink, faHeading, faSignIn, faThLarge)

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
const iframeSrc = route('grp.websites.header.preview', {
    website: route().params.website,
    organisation: route().params.organisation,
    shop: route().params.shop,
})
const tabs = [
    {
        label: ctrans("Top bar"),
        componentName: "topbar",
        key: 'topBar',
        icon: faSignIn,
        scope: 'topbar'
    },
    {
        label: ctrans("Header"),
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
const currentView = ref('desktop')
provide('currentView', currentView)

const hasAnyTemplate = computed(() => Boolean(usedTemplates.value?.topBar?.code || usedTemplates.value?.header?.code))
const selectedFieldValue = computed(() => usedTemplates.value?.[selectedTab.value.key]?.data?.fieldValue)

const loginModes = [
    { label: ctrans('Logged out'), icon: faSignOut, isLoggedIn: false },
    { label: ctrans('Logged in'), icon: falSignIn, isLoggedIn: true },
]

const isLoadingTemplate = ref(false)
const onSelectBlock = async (selectedBlock: object) => {
    isLoadingTemplate.value = true

    setTimeout(() => {
        const selectedKey = selectedTab.value.key
        const currentTemplate = toRaw(usedTemplates.value[selectedKey])
        const newTemplate = { ...toRaw(selectedBlock) }

        newTemplate.data.fieldValue = {
            ...currentTemplate?.data?.fieldValue,
            ...newTemplate?.data?.fieldValue
        }

        usedTemplates.value[selectedKey] = newTemplate
        keySidebar.value++
        nextTick(() => {
            isLoadingTemplate.value = false
            isModalOpen.value = false
        })
    }, 500)
}

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
                    title: ctrans('Something went wrong.'),
                    text: error.message,
                    type: 'error',
                })
            }
        }
    )
}

const isLoadingSave = ref(false)
const autoSave = async (data: {}) => {
    router.patch(
        route(props.autosaveRoute.name, props.autosaveRoute.parameters),
        { layout: data },
        {
            onStart: () => isLoadingSave.value = true,
            onFinish: () => {
                isLoadingSave.value = false
                saveCancelToken.value = null
                sendToIframe({ key: 'reload', value: {} })
            },
            onCancelToken: (cancelToken) => {
                saveCancelToken.value = cancelToken.cancel
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
const openFullScreenPreview = () => {
    const url = new URL(iframeSrc, window.location.origin)
    url.searchParams.set('isInWorkshop', 'true')
    url.searchParams.set('mode', 'iris')
    url.searchParams.set('organisation', route().params['organisation'])
    window.open(url.toString(), '_blank')
}

const handleIframeError = () => {
    console.error('Failed to load iframe content.')
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
        if (route().params["fulfilment"]) {
            return data.filter((item) => item.code.includes('fulfilment'))
        }
        return data.filter((item) => !item.code.includes('fulfilment'))
    }
    return data
})

const isModalOpen = ref(false)
const sendToIframe = (data: any) => {
    _iframe.value?.contentWindow.postMessage(data, '*')
}

const setPreviewLoggedIn = (isLoggedIn: boolean) => {
    isPreviewLoggedIn.value = isLoggedIn
    sendToIframe({ key: 'isPreviewLoggedIn', value: isLoggedIn })
}

const onIframeLoad = () => {
    isIframeLoading.value = false
    sendToIframe({ key: 'isPreviewLoggedIn', value: isPreviewLoggedIn.value })
}

const panelActive = ref()
const _sideEditor = ref<HTMLElement | null>(null)
const sideEditorStorageKey = 'header-workshop-side-editor-open'
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

const selectTab = (tab: typeof tabs[number]) => {
    selectedTab.value = tab
    isSideEditorOpen.value = true
}

const openSidePanel = async (tab: typeof tabs[number], panel: string) => {
    selectTab(tab)
    panelActive.value = null
    await nextTick()
    panelActive.value = panel
    await nextTick()
    _sideEditor.value?.querySelector('.p-accordionpanel-active')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const handleIframeMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) {
        return
    }
    const { data } = event
    if (data.key === 'openModalBlockList') {
        isModalOpen.value = true
    } else if (data.key === 'TopbarPanelOpen') {
        openSidePanel(tabs[0], data.value)
    } else if (data.key === 'HeaderPanelOpen') {
        openSidePanel(tabs[1], data.value)
    } else if (data.key === 'autosave') {
        if (saveCancelToken.value) {
            saveCancelToken.value()
        }
        usedTemplates.value = data.value
    }
}

onMounted(() => {
    if (!get(props.data, 'theme.color', false)) {
        set(props.data, 'theme.color', [...useColorTheme[0]])
    }
    irisStyleVariables(props.data.theme?.color)
    window.addEventListener('message', handleIframeMessage)
})

onBeforeUnmount(() => {
    window.removeEventListener('message', handleIframeMessage)
})

watch(currentView, (newValue) => {
    iframeClass.value = setIframeView(newValue)
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
                @onPublish="(popover) => onPublish(action.route, popover)" />
        </template>
        <template #other>
            <div class="px-2 cursor-pointer" v-tooltip="ctrans('Go to website')" @click="openWebsite">
                <FontAwesomeIcon :icon="faExternalLink" fixed-width aria-hidden="true" size="xl" />
            </div>
        </template>
    </PageHeading>

    <div class="h-[84vh] flex">
        <aside v-if="isSideEditorOpen" class="w-80 shrink-0 bg-[#F9F9F9] flex flex-col border-r border-gray-300">
            <div class="pl-1.5 pr-1.5 h-9 bg-gray-50 flex items-center justify-between gap-2 border-b border-gray-300 text-sm">
                <div class="inline-flex rounded-md bg-white p-0.5 ring-1 ring-gray-300 text-xs">
                    <button v-for="tab in tabs" :key="tab.key" type="button"
                        class="flex items-center gap-1.5 rounded px-2.5 py-1 font-medium transition-colors"
                        :class="selectedTab.key === tab.key ? 'bg-slate-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        @click="selectTab(tab)">
                        <FontAwesomeIcon :icon="tab.icon" fixed-width aria-hidden="true" />
                        {{ tab.label }}
                    </button>
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
                <SideEditor v-if="selectedFieldValue" :key="`${selectedTab.key}-${keySidebar}`"
                    v-model="usedTemplates[selectedTab.key].data.fieldValue"
                    :blueprint="getBlueprint(usedTemplates[selectedTab.key].code)"
                    :uploadImageRoute="uploadImageRoute" :panelOpen="panelActive"
                    @update:model-value="e => usedTemplates[selectedTab.key].data.fieldValue = e" />

                <div v-else class="h-full flex items-center justify-center px-4">
                    <EmptyState
                        :data="{ title: ctrans('No :section template', { section: selectedTab.label.toLowerCase() }), description: ctrans('Pick a template to start editing') }">
                        <template #button-empty-state>
                            <div class="mt-4 block">
                                <Button type="secondary" :label="ctrans('Templates')" :icon="faThLarge"
                                    @click="isModalOpen = true" />
                            </div>
                        </template>
                    </EmptyState>
                </div>
            </div>
        </aside>

        <aside v-else class="w-10 shrink-0 bg-[#F9F9F9] flex flex-col items-center border-r border-gray-300">
            <button type="button" class="h-9 w-full border-b border-gray-300 bg-gray-50 text-gray-500 hover:text-gray-700"
                v-tooltip="ctrans('Expand editor')" @click="isSideEditorOpen = true">
                <FontAwesomeIcon :icon="faChevronDoubleRight" fixed-width aria-hidden="true" />
            </button>
            <div class="flex flex-col items-center gap-0.5 py-1.5">
                <button v-for="tab in tabs" :key="tab.key" type="button"
                    class="h-8 w-8 rounded text-gray-600 hover:bg-gray-200 hover:text-gray-900"
                    v-tooltip="{ value: tab.label, showDelay: 100 }" @click="selectTab(tab)">
                    <FontAwesomeIcon :icon="tab.icon" fixed-width aria-hidden="true" />
                </button>
            </div>
            <button type="button" class="mt-auto mb-1.5 h-8 w-8 rounded text-gray-600 hover:bg-gray-200"
                v-tooltip="ctrans('Change template')" @click="isModalOpen = true">
                <FontAwesomeIcon :icon="faThLarge" fixed-width aria-hidden="true" />
            </button>
        </aside>

        <section class="flex-1 min-w-0 bg-gray-100">
            <div v-if="hasAnyTemplate" class="h-full flex flex-col">
                <div class="flex items-center justify-between gap-3 bg-slate-200 border-b border-gray-300 pr-3">
                    <div class="flex items-center">
                        <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
                        <div class="py-1 px-2 cursor-pointer text-gray-500 hover:text-amber-600"
                            v-tooltip="ctrans('Open preview in new tab')" @click="openFullScreenPreview">
                            <FontAwesomeIcon :icon="faEye" fixed-width aria-hidden="true" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-xs">
                        <span class="text-gray-500 tabular-nums">
                            {{ isLoadingSave ? ctrans("Saving…") : ctrans("All changes saved") }}
                        </span>
                        <div class="inline-flex rounded-md bg-white p-0.5 ring-1 ring-gray-300">
                            <button v-for="loginMode in loginModes" :key="loginMode.label" type="button"
                                class="flex items-center gap-1.5 rounded px-2.5 py-1 font-medium transition-colors"
                                :class="isPreviewLoggedIn === loginMode.isLoggedIn ? 'bg-slate-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                                v-tooltip="ctrans('Preview the header as a visitor who is :state', { state: loginMode.label.toLowerCase() })"
                                @click="setPreviewLoggedIn(loginMode.isLoggedIn)">
                                <FontAwesomeIcon :icon="loginMode.icon" fixed-width aria-hidden="true" />
                                {{ loginMode.label }}
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
                    <iframe ref="_iframe" :src="iframeSrc" :title="props.title"
                        class="bg-white transition-all" :class="[iframeClass, currentView === 'desktop' ? '' : 'shadow-lg']"
                        @error="handleIframeError" @load="onIframeLoad" />
                </div>
            </div>

            <div v-else class="h-full flex items-center justify-center bg-white">
                <EmptyState
                    :data="{ title: ctrans('Pick a header template'), description: ctrans('Pick a top bar or header template to start editing') }">
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
        <HeaderListModal :onSelectBlock :webBlockTypes="selectedWebBlock" :currentTopbar="usedTemplates.topBar"
            :isLoading="isLoadingTemplate" />
    </Modal>
</template>


<style lang="scss" scoped>
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
