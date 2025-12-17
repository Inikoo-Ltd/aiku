<script setup lang="ts">
import { onMounted, ref, inject } from "vue";
import axios from "axios"
import BeefreeSDK from '@beefree.io/sdk'
import { routeType } from "@/types/route";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

const props = withDefaults(defineProps<{
    updateRoute: routeType;
    imagesUploadRoute: routeType
    snapshot: any
    mergeTags: Array<any>
    organisationSlug: string
}>(), {});

const locale = inject('locale', aikuLocaleStructure)
const showBee = ref(false)
const isLoading = ref(false)
const beeInstance = ref<BeefreeSDK | null>(null)


const emits = defineEmits<{
    (e: 'onSave', value: string | number): void
    (e: 'sendTest', value: string | number): void
    (e: 'saveTemplate', value: string | number): void
    (e: 'autoSave', value: string | number): void
}>()

const initializeBeefree = async () => {
    try {
        // Check if organisation prop exists
        if (!props.organisationSlug) {
            console.error('Organisation parameter is required')
            showBee.value = false
            return
        }

        isLoading.value = true
        showBee.value = true

        // Get authentication token
        const response = await axios.post(
            route("grp.json.beefree.authenticate", {
                organisation: props.organisationSlug,
            }),
            { uid: 'CmsUserName' }
        )

        // Check if response is successful
        if (!response.data || response.status !== 200) {
            throw new Error('Authentication failed: Invalid response from server')
        }

        const token = response.data

        // Configure Beefree with all options
        const beeConfig = {
            uid: 'CmsUserName',
            container: 'beefree-container',
            language: 'en-US',
            loadingSpinnerDisableOnDialog: true,
            saveRows: true,
            disableBaseColors: true,
            disableColorHistory: true,
            templateLanguageAutoTranslation: true,
            mergeTags: props.mergeTags,
            customAttributes: {
                attributes: [
                    {
                        key: "data-segment",
                        value: [
                            "1.2",
                            "1.3"
                        ],
                        target: "link"
                    },
                    {
                        key: "class",
                        target: "tag"
                    },
                    {
                        key: "class",
                        target: "link"
                    }
                ]
            },
            autosave: 20,
            onSend: (htmlFile: string, jsonFile: string) => {
                emits('sendTest', { jsonFile, htmlFile })
            },
            onSave: (pageJson: string, pageHtml: string, ampHtml: string | null, templateVersion: number, language: string | null) => {
                emits('onSave', { jsonFile: pageJson, htmlFile: pageHtml })
            },
            onSaveAsTemplate: (jsonFile: string, htmlFile: string) => {
                emits('saveTemplate', { jsonFile, htmlFile })
            },
            onAutoSave: (jsonFile: string) => {
                emits('autoSave', jsonFile)
            },
            onError: (error: unknown) => {
                console.error('Beefree Error:', error)
            }
        }

        // Initialize Beefree SDK
        beeInstance.value = new BeefreeSDK(token)

        // Start with template if available
        const template = props.snapshot?.layout ? JSON.stringify(props.snapshot.layout) : {}
        await beeInstance.value.start(beeConfig, template)

        isLoading.value = false
    } catch (error) {
        isLoading.value = false
        showBee.value = false

        console.error('Initialization error:', error)
    }
}

onMounted(() => {
    initializeBeefree()
})

defineExpose({
    beeInstance,
})

</script>

<template>
    <div v-if="showBee" class="beefree-wrapper">
        <!-- Loading Animation -->
        <div v-if="isLoading" class="loading-overlay">
            <div class="loading-spinner" >
                <LoadingIcon class="text-7xl" />
                <p class="loading-text">Loading Workshop Editor...</p>
            </div>
        </div>

        <!-- Beefree Container -->
        <div
            id="beefree-container"
            ref="containerRef"
            class="editor-container"
            :class="{ 'loading': isLoading }">
        </div>
    </div>

    <div v-else>
        <EmptyState :data="{
            title: 'You Need Register Your Beefree api key',
            action: {
                tooltip: 'Setting',
                type: 'create',
                route: {
                    name: 'grp.sysadmin.settings.edit'
                },
                icon: ['fas', 'user-cog']
            }
        }" />
    </div>
</template>

<style scoped>
.beefree-wrapper {
    position: relative;
    height: calc(100vh - 177px);
}

.editor-container {
    height: 100%;
    transition: opacity 0.3s ease;
}

.editor-container.loading {
    opacity: 0.3;
    pointer-events: none;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(255, 255, 255, 0.95);
    z-index: 1000;
}

.loading-spinner {
    text-align: center;
}

.loading-text {
    font-size: 16px;
    color: #555;
    font-weight: 500;
    margin: 0;
}

.top-bar {
    display: none;
}
</style>
