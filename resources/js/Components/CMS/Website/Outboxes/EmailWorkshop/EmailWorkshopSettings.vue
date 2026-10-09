<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { isEqual } from 'lodash-es'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faChevronDown, faCheck, faGlobe } from '@fal'
import { useColorTheme } from '@/Composables/useStockList'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import TiptapImageDialog from '@/Components/Forms/Fields/BubleTextEditor/TiptapImageDialog.vue'
import EmailWorkshopField from './EmailWorkshopField.vue'
import EmailWorkshopSection from './EmailWorkshopSection.vue'
import EmailWorkshopProperties from './EmailWorkshopProperties.vue'
import { ctrans } from '@/Composables/useTrans'
import { routeType } from '@/types/route'
import { EmailJson, MailshotMetadata } from './emailWorkshopBlocks'
import { EmailTheme, WebsiteTheme, applyEmailTheme, primaryFontName, websiteThemeAsEmailTheme } from './emailWorkshopTheme'

library.add(faChevronDown, faCheck, faGlobe)

const props = defineProps<{
    email: EmailJson
    mailshot?: MailshotMetadata | null
    updateMailshotRoute?: routeType
    imagesUploadRoute?: routeType
    imageCategories?: Array<{ key: string, label: string, route: routeType }>
    websiteTheme?: WebsiteTheme | null
}>()

const emits = defineEmits<{
    (e: 'mailshotSaved', value: MailshotMetadata): void
}>()

const metadataForm = reactive<MailshotMetadata>({ subject: '', name: null, preview_text: null, ...props.mailshot })
const isSavingMetadata = ref(false)

watch(() => props.mailshot, (mailshot) => Object.assign(metadataForm, mailshot), { deep: true })

const saveMetadata = async () => {
    if (!props.updateMailshotRoute || !metadataForm.subject) {
        return
    }
    isSavingMetadata.value = true
    try {
        await axios.patch(route(props.updateMailshotRoute.name, props.updateMailshotRoute.parameters), metadataForm)
        emits('mailshotSaved', { ...metadataForm })
        notify({ title: ctrans('Success'), text: ctrans('Email metadata saved'), type: 'success' })
    } catch {
        notify({ title: ctrans('Something went wrong'), text: ctrans('Failed to save the email metadata'), type: 'error' })
    } finally {
        isSavingMetadata.value = false
    }
}

const colorThemes = [...useColorTheme]
const isThemeListOpen = ref(false)
const websiteEmailTheme = computed(() => websiteThemeAsEmailTheme(props.websiteTheme))
const currentTheme = computed<EmailTheme | null>(() => props.email.page.aikuTheme ?? null)

const currentThemeLabel = computed(() => {
    if (!currentTheme.value) {
        return ctrans('No theme')
    }
    if (currentTheme.value.source === 'website') {
        return ctrans('Website theme')
    }
    const index = colorThemes.findIndex((colors) => isEqual(colors, currentTheme.value?.color))

    return index === -1 ? ctrans('Custom theme') : `${ctrans('Theme')} ${index + 1}`
})

const isCurrentTheme = (theme: EmailTheme): boolean =>
    !!currentTheme.value && currentTheme.value.source === theme.source && isEqual(currentTheme.value.color, theme.color)

const chooseTheme = (theme: EmailTheme) => {
    applyEmailTheme(props.email, { ...theme, fontFamily: currentTheme.value?.fontFamily ?? theme.fontFamily })
    isThemeListOpen.value = false
}

const chooseWebsiteTheme = () => {
    if (websiteEmailTheme.value) {
        applyEmailTheme(props.email, websiteEmailTheme.value)
    }
    isThemeListOpen.value = false
}

const paletteTheme = (colors: string[]): EmailTheme => ({ color: [...colors], fontFamily: null, source: 'palette' })

const isFaviconPickerOpen = ref(false)

const onFaviconPicked = (url: string) => {
    props.email.page.favicon = url
    isFaviconPickerOpen.value = false
}

const removeFavicon = () => {
    props.email.page.favicon = null
}
</script>

<template>
    <div class="text-sm">
        <EmailWorkshopSection v-if="mailshot && updateMailshotRoute" :title="ctrans('Email metadata')">
            <EmailWorkshopField v-model="metadataForm.subject" :label="ctrans('Subject')" />
            <EmailWorkshopField v-model="metadataForm.preview_text" :label="ctrans('Preview text')" />
            <EmailWorkshopField v-model="metadataForm.name" :label="ctrans('Internal name')" />
            <button type="button"
                class="my-2 w-full rounded bg-[var(--theme-color-4)] py-2 text-[13px] font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)] disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!metadataForm.subject || isSavingMetadata" @click="saveMetadata">
                {{ isSavingMetadata ? ctrans('Saving') + '…' : ctrans('Save metadata') }}
            </button>
        </EmailWorkshopSection>

        <EmailWorkshopSection :title="ctrans('Theme')">
            <p class="pt-2 text-[12px] text-gray-500">
                {{ ctrans('Colours headings, text, links, buttons and dividers, and sets the font. New blocks follow the theme. Undo with Ctrl+Z.') }}
            </p>
            <div class="relative py-3">
                <button type="button" class="flex w-full items-center justify-between gap-x-2 rounded border border-gray-300 bg-white p-2.5 hover:bg-gray-50"
                    :aria-expanded="isThemeListOpen" @click="isThemeListOpen = !isThemeListOpen">
                    <span class="flex items-center gap-x-2">
                        <span v-if="currentTheme" class="flex overflow-hidden rounded ring-1 ring-gray-300">
                            <span v-for="(color, index) in currentTheme.color" :key="index" class="h-4 w-4" :style="{ backgroundColor: color }" />
                        </span>
                        <span class="text-[13px] text-gray-700">{{ currentThemeLabel }}</span>
                    </span>
                    <FontAwesomeIcon icon="fal fa-chevron-down" class="text-gray-400 transition" :class="{ 'rotate-180': isThemeListOpen }" fixed-width aria-hidden="true" />
                </button>

                <div v-if="isThemeListOpen" class="absolute left-0 right-0 top-full z-10 mt-1 max-h-72 overflow-y-auto rounded border border-gray-300 bg-white p-1.5 shadow-lg">
                    <button v-if="websiteEmailTheme" type="button"
                        class="flex w-full items-center justify-between gap-x-2 rounded border-2 border-transparent p-2 hover:bg-gray-50"
                        :class="{ 'border-[var(--theme-color-4)] bg-[color-mix(in_srgb,var(--theme-color-4)_8%,white)]': isCurrentTheme(websiteEmailTheme) }"
                        @click="chooseWebsiteTheme">
                        <span class="flex items-center gap-x-2">
                            <span class="flex overflow-hidden rounded ring-1 ring-gray-300">
                                <span v-for="(color, index) in websiteEmailTheme.color" :key="index" class="h-4 w-4" :style="{ backgroundColor: color }" />
                            </span>
                            <span class="text-left">
                                <span class="block text-[13px] font-medium text-gray-800">
                                    <FontAwesomeIcon icon="fal fa-globe" class="mr-1 text-gray-400" fixed-width aria-hidden="true" />{{ ctrans('Website theme') }}
                                </span>
                                <span v-if="websiteEmailTheme.fontFamily" class="block text-[11px] text-gray-500">{{ primaryFontName(websiteEmailTheme.fontFamily) }}</span>
                            </span>
                        </span>
                        <FontAwesomeIcon v-if="isCurrentTheme(websiteEmailTheme)" icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    </button>
                    <div v-if="websiteEmailTheme" class="my-1 h-px bg-gray-100" />
                    <button v-for="(colors, index) in colorThemes" :key="index" type="button"
                        class="flex w-full items-center justify-between gap-x-2 rounded border-2 border-transparent p-2 hover:bg-gray-50"
                        :class="{ 'border-[var(--theme-color-4)] bg-[color-mix(in_srgb,var(--theme-color-4)_8%,white)]': isCurrentTheme(paletteTheme(colors)) }"
                        @click="chooseTheme(paletteTheme(colors))">
                        <span class="flex items-center gap-x-2">
                            <span class="flex overflow-hidden rounded ring-1 ring-gray-300">
                                <span v-for="(color, colorIndex) in colors" :key="colorIndex" class="h-4 w-4" :style="{ backgroundColor: color }" />
                            </span>
                            <span class="text-[13px] font-medium text-gray-700">{{ ctrans('Theme') }} {{ index + 1 }}</span>
                        </span>
                        <FontAwesomeIcon v-if="isCurrentTheme(paletteTheme(colors))" icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </div>
        </EmailWorkshopSection>

        <EmailWorkshopSection :title="ctrans('Favicon')">
            <p class="pt-2 text-[12px] text-gray-500">{{ ctrans('Shown in the browser tab when the email is opened in a web browser.') }}</p>
            <div class="flex items-center gap-x-3 py-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded border border-dashed border-gray-300 bg-gray-50">
                    <img v-if="email.page.favicon" :src="email.page.favicon" :alt="ctrans('Favicon')" class="h-8 w-8 object-contain" />
                    <span v-else class="text-[10px] text-gray-400">{{ ctrans('None') }}</span>
                </div>
                <div class="flex flex-1 flex-col gap-y-1.5">
                    <button type="button"
                        class="rounded bg-[var(--theme-color-4)] py-1.5 text-[13px] font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]"
                        @click="isFaviconPickerOpen = true">
                        {{ email.page.favicon ? ctrans('Change favicon') : ctrans('Upload or browse favicon') }}
                    </button>
                    <button v-if="email.page.favicon" type="button" class="text-[12px] text-red-500 hover:text-red-700" @click="removeFavicon">
                        {{ ctrans('Remove favicon') }}
                    </button>
                </div>
            </div>
        </EmailWorkshopSection>

        <EmailWorkshopProperties :body="email.page.body" :imagesUploadRoute="imagesUploadRoute" :imageCategories="imageCategories" />

        <TiptapImageDialog v-if="isFaviconPickerOpen" :show="isFaviconPickerOpen" :uploadImageRoute="imagesUploadRoute"
            :imagesUploadedRoute="{ name: 'grp.gallery.uploaded-images.email.index' }" :imageCategories="imageCategories"
            @insert="onFaviconPicked" @close="isFaviconPickerOpen = false" />
    </div>
</template>
