<script setup lang="ts">
import { computed, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowUp, faArrowDown, faTrashAlt, faPlus, faImage, faLock } from '@fal'
import TiptapImageDialog from '@/Components/Forms/Fields/BubleTextEditor/TiptapImageDialog.vue'
import EditorV2 from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import EmailWorkshopField from './EmailWorkshopField.vue'
import EmailWorkshopPadding from './EmailWorkshopPadding.vue'
import EmailWorkshopSection from './EmailWorkshopSection.vue'
import { ctrans } from '@/Composables/useTrans'
import { routeType } from '@/types/route'
import {
    DEFAULT_SOCIAL_ICON_SET, EmailBody, EmailModule, EmailRow, MODULE_TYPES, SOCIAL_ICON_SETS, SOCIAL_NETWORKS, addTableColumn, addTableRow, applySocialIconSet,
    createIconItem, createSocialIcon, getModuleText, isTableModule, isUnsubscribeModule, moduleTextToggles, removeTableColumn, removeTableRow, setModuleText,
    socialIconSetOf, socialIconSrc, tableColumnCount, videoThumbnailFromUrl, vimeoVideoId, isVideoPageUrl, dynamicContentSource,
} from './emailWorkshopBlocks'

library.add(faArrowUp, faArrowDown, faTrashAlt, faPlus, faImage, faLock)

const props = defineProps<{
    module?: EmailModule | null
    row?: EmailRow | null
    body: EmailBody
    imagesUploadRoute?: routeType
    imageCategories?: Array<{ key: string, label: string, route: routeType }>
    mergeTags?: Array<{ name: string, value: string }>
    textRevision?: number
    videoThumbnailState?: 'loading' | 'error'
}>()

const emits = defineEmits<{
    (e: 'replaceDynamicContent'): void
    (e: 'textEdited'): void
}>()

const fontWeightOptions = [
    { label: ctrans('Regular'), value: '400' },
    { label: ctrans('Bold'), value: '700' },
]

const headingLevelOptions = ['h1', 'h2', 'h3'].map((level) => ({ label: level.toUpperCase(), value: level }))

const standardFontFamilyOptions = [
    { label: 'Arial', value: 'Arial, Helvetica Neue, Helvetica, sans-serif' },
    { label: 'Georgia', value: 'Georgia, Times, Times New Roman, serif' },
    { label: 'Helvetica', value: 'Helvetica Neue, Helvetica, Arial, sans-serif' },
    { label: 'Montserrat', value: "'Montserrat', 'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif" },
    { label: 'Tahoma', value: 'Tahoma, Verdana, Segoe, sans-serif' },
    { label: 'Times New Roman', value: 'Times New Roman, Times, serif' },
    { label: 'Trebuchet MS', value: "'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif" },
    { label: 'Verdana', value: 'Verdana, Geneva, sans-serif' },
]
const fontFamilyOptions = computed(() => [
    ...standardFontFamilyOptions,
    ...(props.body.webFonts ?? [])
        .filter((font) => !standardFontFamilyOptions.some((option) => option.value === font.fontFamily))
        .map((font) => ({ label: font.name, value: font.fontFamily })),
])
const inheritableFontOptions = computed(() => [{ label: ctrans('Default'), value: 'inherit' }, ...fontFamilyOptions.value])

const verticalAlignOptions = [
    { label: ctrans('Top'), value: 'top' },
    { label: ctrans('Middle'), value: 'middle' },
    { label: ctrans('Bottom'), value: 'bottom' },
]

const descriptor = computed(() => props.module?.descriptor ?? {})
const moduleType = computed(() => props.module?.type ?? '')

const ensureObject = (target: Record<string, any>, key: string): Record<string, any> => {
    if (!target[key] || typeof target[key] !== 'object' || Array.isArray(target[key])) {
        target[key] = {}
    }

    return target[key]
}

const localTextRevision = ref(0)

const moduleText = computed({
    get: () => props.module ? getModuleText(props.module) : '',
    set: (html: string) => {
        if (!props.module) {
            return
        }
        setModuleText(props.module, html)
        emits('textEdited')
    },
})

const moduleStyle = computed(() => ensureObject(descriptor.value, 'style'))
const moduleComputedStyle = computed(() => ensureObject(descriptor.value, 'computedStyle'))
const hasModulePadding = computed(() => moduleType.value !== MODULE_TYPES.spacer)

const imageAlignment = computed({
    get: () => {
        const className = String(moduleComputedStyle.value.class ?? 'center')
        return ['left', 'right'].find((align) => className.includes(align)) ?? 'center'
    },
    set: (align: string) => {
        moduleComputedStyle.value.class = `${align} fixedwidth`
    },
})

const imagePercWidth = computed({
    get: () => descriptor.value.image?.percWidth ?? 100,
    set: (value: number) => {
        descriptor.value.image.percWidth = value
        moduleComputedStyle.value.class = `${imageAlignment.value} fixedwidth`
    },
})

const dividerBorderParts = (): [string, string, string] => {
    const [thickness = '1px', lineStyle = 'solid', color = '#BBBBBB'] = String(descriptor.value.divider?.style?.['border-top'] ?? '').split(' ')
    return [thickness, lineStyle, color]
}

const dividerThickness = computed({
    get: () => dividerBorderParts()[0],
    set: (value: string) => {
        const [, lineStyle, color] = dividerBorderParts()
        descriptor.value.divider.style['border-top'] = `${value} ${lineStyle} ${color}`
    },
})

const dividerLineStyle = computed({
    get: () => dividerBorderParts()[1],
    set: (value: string) => {
        const [thickness, , color] = dividerBorderParts()
        descriptor.value.divider.style['border-top'] = `${thickness} ${value} ${color}`
    },
})

const dividerColor = computed({
    get: () => dividerBorderParts()[2],
    set: (value: string) => {
        const [thickness, lineStyle] = dividerBorderParts()
        descriptor.value.divider.style['border-top'] = `${thickness} ${lineStyle} ${value}`
    },
})

const dividerWidth = computed({
    get: () => parseFloat(String(descriptor.value.divider?.style?.width ?? '100')) || 100,
    set: (value: number) => {
        descriptor.value.divider.style.width = `${value}%`
    },
})

const videoSource = computed({
    get: () => descriptor.value.video?.src ?? '',
    set: (url: string) => {
        descriptor.value.video.src = url
        const thumbnail = videoThumbnailFromUrl(url)
        if (thumbnail) {
            descriptor.value.video.thumbSrc = thumbnail
        }
    },
})

const videoThumbnailSource = computed({
    get: () => descriptor.value.video?.thumbSrc ?? '',
    set: (url: string) => {
        if (isVideoPageUrl(url)) {
            videoSource.value = url
            return
        }
        descriptor.value.video.thumbSrc = url
    },
})

const isVimeoWithoutThumbnail = computed(() => !!vimeoVideoId(descriptor.value.video?.src ?? '') && !descriptor.value.video?.thumbSrc)

const isPlayButtonVisible = computed({
    get: () => String(descriptor.value.video?.iconType ?? 1) !== '0',
    set: (isVisible: boolean) => {
        descriptor.value.video.iconType = isVisible ? 1 : 0
    },
})

const videoRatioOptions = [
    { label: '16:9', value: '16-9' },
    { label: '4:3', value: '4-3' },
    { label: '1:1', value: '1-1' },
]

const listType = computed({
    get: () => (descriptor.value.list?.tag ?? (String(descriptor.value.list?.html ?? '').trim().startsWith('<ol') ? 'ol' : 'ul')),
    set: (tag: 'ul' | 'ol') => {
        const list = descriptor.value.list
        list.tag = tag
        list.html = String(list.html ?? '').replace(/<(\/?)(ul|ol)(?=[\s>])/gi, `<$1${tag}`)
        ensureObject(list, 'computedStyle').listStyleType = tag === 'ol' ? 'decimal' : 'disc'
        localTextRevision.value += 1
        emits('textEdited')
    },
})

const listTypeOptions = [
    { label: ctrans('Bullets'), value: 'ul' },
    { label: ctrans('Numbers'), value: 'ol' },
]

const listMarkerOptions = computed(() => listType.value === 'ol'
    ? [
        { label: '1, 2, 3', value: 'decimal' },
        { label: 'a, b, c', value: 'lower-alpha' },
        { label: 'A, B, C', value: 'upper-alpha' },
        { label: 'i, ii, iii', value: 'lower-roman' },
        { label: 'I, II, III', value: 'upper-roman' },
    ]
    : [
        { label: '● ' + ctrans('Disc'), value: 'disc' },
        { label: '○ ' + ctrans('Circle'), value: 'circle' },
        { label: '■ ' + ctrans('Square'), value: 'square' },
    ])

const listMarkerType = computed({
    get: () => {
        const listStyleType = descriptor.value.list?.computedStyle?.listStyleType
        return listStyleType && listStyleType !== 'revert' ? listStyleType : (listType.value === 'ol' ? 'decimal' : 'disc')
    },
    set: (value: string) => {
        ensureObject(descriptor.value.list, 'computedStyle').listStyleType = value
    },
})

const listMarkerPosition = computed({
    get: () => descriptor.value.list?.computedStyle?.listStylePosition === 'inside' ? 'inside' : 'outside',
    set: (value: string) => {
        ensureObject(descriptor.value.list, 'computedStyle').listStylePosition = value
    },
})

const listMarkerPositionOptions = [
    { label: ctrans('Hanging'), value: 'outside' },
    { label: ctrans('Inline with text'), value: 'inside' },
]

const textComputedStyle = computed(() => descriptor.value.text ? ensureObject(descriptor.value.text, 'computedStyle') : {})

const listComputedStyle = computed(() => descriptor.value.list ? ensureObject(descriptor.value.list, 'computedStyle') : {})

const isImagePickerOpen = ref(false)
let imagePickedHandler: ((url: string, alt?: string) => void) | null = null

const openImagePicker = (handler: (url: string, alt?: string) => void) => {
    imagePickedHandler = handler
    isImagePickerOpen.value = true
}

const onImagePicked = (url: string, alt?: string) => {
    imagePickedHandler?.(url, alt)
    imagePickedHandler = null
    isImagePickerOpen.value = false
}

const pickModuleImage = () => openImagePicker((url, alt) => {
    descriptor.value.image.src = url
    if (!descriptor.value.image.alt) {
        descriptor.value.image.alt = alt ?? ''
    }
})

const pickVideoThumbnail = () => openImagePicker((url, alt) => {
    descriptor.value.video.thumbSrc = url
    if (!descriptor.value.video.thumbAlt) {
        descriptor.value.video.thumbAlt = alt ?? ''
    }
})

const pickIconImage = (icon: Record<string, any>) => openImagePicker((url) => {
    icon.image = url
    const image = new Image()
    image.onload = () => {
        icon.width = `${image.naturalWidth}px`
        icon.height = `${image.naturalHeight}px`
    }
    image.src = url
})

const iconItems = computed<Record<string, any>[]>(() => descriptor.value.iconsList?.icons ?? [])
const iconSpacing = computed(() => ensureObject(moduleComputedStyle.value, 'iconSpacing'))

const iconsStackOnMobile = computed({
    get: () => moduleComputedStyle.value.stackOnMobile !== false,
    set: (isStacked: boolean) => {
        moduleComputedStyle.value.stackOnMobile = isStacked
    },
})

const iconLayoutOptions = [
    { label: ctrans('Horizontal'), value: 'horizontal' },
    { label: ctrans('Vertical'), value: 'vertical' },
]

const iconTextPositionOptions = [
    { label: ctrans('Below icon'), value: 'bottom' },
    { label: ctrans('Above icon'), value: 'top' },
    { label: ctrans('Right of icon'), value: 'right' },
    { label: ctrans('Left of icon'), value: 'left' },
]

const addIconItem = () => {
    ensureObject(descriptor.value, 'iconsList')
    descriptor.value.iconsList.icons = [...iconItems.value, createIconItem()]
}

const moveIconItem = (index: number, direction: -1 | 1) => {
    const icons = descriptor.value.iconsList.icons
    const target = index + direction
    if (target < 0 || target >= icons.length) {
        return
    }
    icons.splice(target, 0, icons.splice(index, 1)[0])
}

const removeIcon = (index: number) => {
    descriptor.value.iconsList.icons.splice(index, 1)
}

const socialIconSet = computed({
    get: () => iconItems.value.map((icon) => socialIconSetOf(icon)).find(Boolean) ?? DEFAULT_SOCIAL_ICON_SET,
    set: (iconSet: string) => applySocialIconSet(iconItems.value, iconSet),
})

const socialNetworkToAdd = ref('')

const addSocialIcon = (network: string) => {
    if (!network) {
        return
    }
    ensureObject(descriptor.value, 'iconsList')
    descriptor.value.iconsList.icons = [...iconItems.value, createSocialIcon(network, socialIconSet.value)]
    socialNetworkToAdd.value = ''
}

const socialNetworkLabel = (icon: Record<string, any>, index: number): string =>
    SOCIAL_NETWORKS.find((network) => network.name === icon.name)?.label ?? icon.name ?? icon.image?.title ?? String(index + 1)

const isCustomSocialIcon = (icon: Record<string, any>): boolean => !socialIconSetOf(icon)

const pickSocialIconImage = (icon: Record<string, any>) => openImagePicker((url) => {
    ensureObject(icon, 'image').src = url
    icon.iconSet = null
})

const resetSocialIconImage = (icon: Record<string, any>) => {
    icon.iconSet = socialIconSet.value
    icon.image.src = socialIconSrc(icon.name, socialIconSet.value)
}

const rowContentStyle = computed(() => props.row ? ensureObject(props.row.content, 'style') : {})
const rowContentComputedStyle = computed(() => props.row ? ensureObject(props.row.content, 'computedStyle') : {})
const rowContainerStyle = computed(() => props.row ? ensureObject(props.row.container, 'style') : {})

const bodyContentStyle = computed(() => ensureObject(props.body.content, 'style'))
const bodyComputedStyle = computed(() => ensureObject(props.body.content, 'computedStyle'))
const bodyContainerStyle = computed(() => ensureObject(props.body.container, 'style'))

const dynamicContentChooseLabel = computed(() => ({
    products: 'Choose products',
    blocks: 'Choose a dynamic block',
}[dynamicContentSource(props.module) ?? ''] ?? 'Choose products or a dynamic block'))

const contentSectionTitle = computed(() => ({
    [MODULE_TYPES.heading]: ctrans('Heading options'),
    [MODULE_TYPES.paragraph]: ctrans('Paragraph options'),
    [MODULE_TYPES.text]: ctrans('Text options'),
    [MODULE_TYPES.list]: ctrans('List options'),
    [MODULE_TYPES.image]: ctrans('Image options'),
    [MODULE_TYPES.button]: ctrans('Button options'),
    [MODULE_TYPES.divider]: ctrans('Divider options'),
    [MODULE_TYPES.spacer]: ctrans('Spacer options'),
    [MODULE_TYPES.social]: ctrans('Social options'),
    [MODULE_TYPES.icons]: ctrans('Icons options'),
    [MODULE_TYPES.video]: ctrans('Video options'),
    [MODULE_TYPES.html]: isUnsubscribeModule(props.module) ? ctrans('Unsubscribe options') : (isTableModule(props.module) ? ctrans('Table options') : ctrans('HTML options')),
    [MODULE_TYPES.mergeContent]: dynamicContentSource(props.module) === 'products' ? ctrans('Products') : ctrans('Dynamic content'),
}[moduleType.value] ?? ctrans('Content options')))
</script>

<template>
    <div class="text-sm">
        <template v-if="module">
            <EmailWorkshopSection :title="contentSectionTitle">
                <template v-if="moduleType === MODULE_TYPES.heading">
                    <div class="my-2 rounded border border-gray-300 px-2 py-1.5 text-[13px]">
                        <EditorV2 :key="`${module.uuid}-${textRevision}-${localTextRevision}`" v-model="moduleText" :toggle="moduleTextToggles(module)" :mergeTags="mergeTags" />
                    </div>
                    <EmailWorkshopField v-model="descriptor.heading.title" type="select" :options="headingLevelOptions" :label="ctrans('Title')" />
                    <EmailWorkshopField v-model="descriptor.heading.style['font-family']" type="select" :options="inheritableFontOptions" :label="ctrans('Font')" />
                    <EmailWorkshopField v-model="descriptor.heading.style['font-size']" type="px" :label="ctrans('Font size')" />
                    <EmailWorkshopField v-model="descriptor.heading.style['font-weight']" type="select" :options="fontWeightOptions" :label="ctrans('Font weight')" />
                    <EmailWorkshopField v-model="descriptor.heading.style.color" type="color" :label="ctrans('Text color')" />
                    <EmailWorkshopField v-model="descriptor.heading.style['link-color']" type="color" :label="ctrans('Link color')" />
                    <EmailWorkshopField v-model="descriptor.heading.style['text-align']" type="align" :label="ctrans('Align')" />
                    <EmailWorkshopField v-model="descriptor.heading.style['letter-spacing']" type="px" :label="ctrans('Letter spacing')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.paragraph">
                    <div class="my-2 rounded border border-gray-300 px-2 py-1.5 text-[13px]">
                        <EditorV2 :key="`${module.uuid}-${textRevision}-${localTextRevision}`" v-model="moduleText" :toggle="moduleTextToggles(module)" :mergeTags="mergeTags" />
                    </div>
                    <EmailWorkshopField v-model="descriptor.paragraph.style['font-family']" type="select" :options="inheritableFontOptions" :label="ctrans('Font')" />
                    <EmailWorkshopField v-model="descriptor.paragraph.style['font-size']" type="px" :label="ctrans('Font size')" />
                    <EmailWorkshopField v-model="descriptor.paragraph.style['font-weight']" type="select" :options="fontWeightOptions" :label="ctrans('Font weight')" />
                    <EmailWorkshopField v-model="descriptor.paragraph.style.color" type="color" :label="ctrans('Text color')" />
                    <EmailWorkshopField v-if="descriptor.paragraph.computedStyle" v-model="descriptor.paragraph.computedStyle.linkColor" type="color" :label="ctrans('Link color')" />
                    <EmailWorkshopField v-model="descriptor.paragraph.style['text-align']" type="align" :label="ctrans('Align')" />
                    <EmailWorkshopField v-model="descriptor.paragraph.style['line-height']" type="select"
                        :options="['100%', '120%', '150%', '180%', '200%'].map((value) => ({ label: value, value }))" :label="ctrans('Line height')" />
                    <EmailWorkshopField v-if="descriptor.paragraph.computedStyle" v-model="descriptor.paragraph.computedStyle.paragraphSpacing" type="px" :label="ctrans('Paragraph spacing')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.text">
                    <div class="my-2 rounded border border-gray-300 px-2 py-1.5 text-[13px]">
                        <EditorV2 :key="`${module.uuid}-${textRevision}-${localTextRevision}`" v-model="moduleText" :toggle="moduleTextToggles(module)" :mergeTags="mergeTags" />
                    </div>
                    <EmailWorkshopField v-model="descriptor.text.style['font-family']" type="select" :options="inheritableFontOptions" :label="ctrans('Font')" />
                    <EmailWorkshopField v-model="descriptor.text.style['font-size']" type="px" :label="ctrans('Font size')" />
                    <EmailWorkshopField v-model="descriptor.text.style['font-weight']" type="select" :options="fontWeightOptions" :label="ctrans('Font weight')" />
                    <EmailWorkshopField v-model="descriptor.text.style.color" type="color" :label="ctrans('Text color')" />
                    <EmailWorkshopField v-model="textComputedStyle.linkColor" type="color" :label="ctrans('Link color')" />
                    <EmailWorkshopField v-model="descriptor.text.style['text-align']" type="align" :label="ctrans('Align')" />
                    <EmailWorkshopField v-model="descriptor.text.style['line-height']" type="select"
                        :options="['100%', '120%', '150%', '180%', '200%'].map((value) => ({ label: value, value }))" :label="ctrans('Line height')" />
                    <EmailWorkshopField v-model="descriptor.text.style['letter-spacing']" type="px" :label="ctrans('Letter spacing')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.list">
                    <div class="my-2 rounded border border-gray-300 px-2 py-1.5 text-[13px]">
                        <EditorV2 :key="`${module.uuid}-${textRevision}-${localTextRevision}`" v-model="moduleText" :toggle="moduleTextToggles(module)" :mergeTags="mergeTags" />
                    </div>
                    <EmailWorkshopField v-model="listType" type="select" :options="listTypeOptions" :label="ctrans('List type')" />
                    <EmailWorkshopField v-model="listMarkerType" type="select" :options="listMarkerOptions" :label="ctrans('Marker style')" />
                    <EmailWorkshopField v-model="listMarkerPosition" type="select" :options="listMarkerPositionOptions" :label="ctrans('Marker position')" />
                    <EmailWorkshopField v-model="listComputedStyle.liSpacing" type="px" :label="ctrans('Space between items')" />
                    <EmailWorkshopField v-model="listComputedStyle.liIndent" type="px" :step="4" :label="ctrans('Indent')" />
                    <EmailWorkshopField v-model="descriptor.list.style['font-family']" type="select" :options="inheritableFontOptions" :label="ctrans('Font')" />
                    <EmailWorkshopField v-model="descriptor.list.style['font-size']" type="px" :label="ctrans('Font size')" />
                    <EmailWorkshopField v-model="descriptor.list.style['line-height']" type="select"
                        :options="['100%', '120%', '150%', '180%', '200%'].map((value) => ({ label: value, value }))" :label="ctrans('Line height')" />
                    <EmailWorkshopField v-model="descriptor.list.style.color" type="color" :label="ctrans('Text color')" />
                    <EmailWorkshopField v-model="listComputedStyle.linkColor" type="color" :label="ctrans('Link color')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.image">
                    <div class="my-2 flex h-32 items-center justify-center rounded border border-dashed border-gray-300 bg-gray-50">
                        <img v-if="descriptor.image.src" :src="descriptor.image.src" :alt="descriptor.image.alt ?? ''" class="max-h-full max-w-full object-contain" />
                        <span v-else class="text-xs text-gray-400">{{ ctrans('No image') }}</span>
                    </div>
                    <button type="button" class="mb-1 w-full rounded bg-[var(--theme-color-4)] py-2 text-[13px] font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]"
                        @click="pickModuleImage">
                        {{ descriptor.image.src ? ctrans('Change image') : ctrans('Upload or browse image') }}
                    </button>
                    <EmailWorkshopField v-model="descriptor.image.src" :label="ctrans('Image URL')" />
                    <EmailWorkshopField v-model="imagePercWidth" type="range" :min="5" :max="100" :label="ctrans('Width')" />
                    <EmailWorkshopField v-model="imageAlignment" type="align" :label="ctrans('Align')" />
                    <EmailWorkshopField v-model="descriptor.image.alt" :label="ctrans('Alternate text')" />
                    <EmailWorkshopField v-model="descriptor.image.href" :label="ctrans('Image link')" />
                    <EmailWorkshopField v-model="moduleStyle['border-radius']" type="px" :label="ctrans('Border radius')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.button">
                    <div class="my-2 rounded border border-gray-300 px-2 py-1.5 text-[13px]">
                        <EditorV2 :key="`${module.uuid}-${textRevision}-${localTextRevision}`" v-model="moduleText" :toggle="moduleTextToggles(module)" :mergeTags="mergeTags" />
                    </div>
                    <EmailWorkshopField v-model="descriptor.button.href" :label="ctrans('Link')" />
                    <EmailWorkshopField v-model="descriptor.button.style['background-color']" type="color" :label="ctrans('Background color')" />
                    <EmailWorkshopField v-model="descriptor.button.style.color" type="color" :label="ctrans('Text color')" />
                    <EmailWorkshopField v-model="descriptor.button.style['font-family']" type="select" :options="inheritableFontOptions" :label="ctrans('Font')" />
                    <EmailWorkshopField v-model="descriptor.button.style['font-size']" type="px" :label="ctrans('Font size')" />
                    <EmailWorkshopField v-model="moduleStyle['text-align']" type="align" :label="ctrans('Align')" />
                    <EmailWorkshopField v-model="descriptor.button.style['border-radius']" type="px" :label="ctrans('Border radius')" />
                    <EmailWorkshopPadding :target="descriptor.button.style" :label="ctrans('Internal padding')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.divider">
                    <EmailWorkshopField v-model="dividerWidth" type="range" :min="5" :max="100" :label="ctrans('Width')" />
                    <EmailWorkshopField v-model="dividerThickness" type="px" :label="ctrans('Line height')" />
                    <EmailWorkshopField v-model="dividerLineStyle" type="select"
                        :options="[{ label: ctrans('Solid'), value: 'solid' }, { label: ctrans('Dashed'), value: 'dashed' }, { label: ctrans('Dotted'), value: 'dotted' }]" :label="ctrans('Line style')" />
                    <EmailWorkshopField v-model="dividerColor" type="color" :label="ctrans('Color')" />
                    <EmailWorkshopField v-model="moduleComputedStyle.align" type="align" :label="ctrans('Align')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.spacer">
                    <EmailWorkshopField v-model="descriptor.spacer.style.height" type="px" :step="5" :label="ctrans('Height')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.icons">
                    <EmailWorkshopField v-model="moduleStyle['text-align']" type="align" :label="ctrans('Align')" />
                    <EmailWorkshopField v-model="moduleComputedStyle.layout" type="select" :options="iconLayoutOptions" :label="ctrans('Layout')" />
                    <EmailWorkshopField v-if="moduleComputedStyle.layout !== 'vertical'" v-model="iconsStackOnMobile" type="toggle" :label="ctrans('Stack on mobile')" />
                    <EmailWorkshopField v-model="moduleComputedStyle.iconHeight" type="px" :step="4" :label="ctrans('Icon size')" />
                    <EmailWorkshopField v-model="moduleStyle['font-size']" type="px" :label="ctrans('Text size')" />
                    <EmailWorkshopField v-model="moduleStyle['font-weight']" type="select" :options="fontWeightOptions" :label="ctrans('Text weight')" />
                    <EmailWorkshopField v-model="moduleStyle.color" type="color" :label="ctrans('Text color')" />
                    <EmailWorkshopPadding :target="iconSpacing" :label="ctrans('Space around each item')" />

                    <div class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-gray-700">{{ ctrans('Items') }} ({{ iconItems.length }})</div>
                    <div v-for="(icon, index) in iconItems" :key="icon.id ?? index" class="my-2 rounded border border-gray-200 px-3 py-2">
                        <div class="flex items-center gap-x-2">
                            <button type="button" class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded border border-dashed border-gray-300 bg-gray-50 hover:border-[var(--theme-color-4)]"
                                v-tooltip="ctrans('Upload or browse icon image')" @click="pickIconImage(icon)">
                                <img v-if="icon.image" :src="icon.image" :alt="icon.title ?? icon.text ?? ''" class="max-h-full max-w-full object-contain" />
                                <FontAwesomeIcon v-else icon="fal fa-image" class="text-gray-400" fixed-width aria-hidden="true" />
                            </button>
                            <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-gray-700">{{ icon.text || ctrans('Item') + ' ' + (index + 1) }}</span>
                            <div class="flex shrink-0 items-center text-gray-500">
                                <button type="button" class="h-6 w-6 rounded hover:bg-gray-100 disabled:opacity-30" :disabled="index === 0" :aria-label="ctrans('Move up')" @click="moveIconItem(index, -1)">
                                    <FontAwesomeIcon icon="fal fa-arrow-up" fixed-width aria-hidden="true" />
                                </button>
                                <button type="button" class="h-6 w-6 rounded hover:bg-gray-100 disabled:opacity-30" :disabled="index === iconItems.length - 1" :aria-label="ctrans('Move down')" @click="moveIconItem(index, 1)">
                                    <FontAwesomeIcon icon="fal fa-arrow-down" fixed-width aria-hidden="true" />
                                </button>
                                <button type="button" class="h-6 w-6 rounded hover:bg-gray-100 hover:text-red-500" :aria-label="ctrans('Remove')" @click="removeIcon(index)">
                                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                        <EmailWorkshopField v-model="icon.text" :label="ctrans('Text')" />
                        <EmailWorkshopField v-model="icon.href" :label="ctrans('Link')" />
                        <EmailWorkshopField v-model="icon.title" :label="ctrans('Alternate text')" />
                        <EmailWorkshopField v-model="icon.textPosition" type="select" :options="iconTextPositionOptions" :label="ctrans('Text position')" />
                    </div>
                    <button type="button" class="mb-2 flex w-full items-center justify-center gap-x-1.5 rounded border border-dashed border-gray-300 py-2 text-[13px] text-gray-600 hover:border-[var(--theme-color-4)] hover:text-[var(--theme-color-4)]"
                        @click="addIconItem">
                        <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                        {{ ctrans('Add item') }}
                    </button>
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.social">
                    <EmailWorkshopField v-model="moduleStyle['text-align']" type="align" :label="ctrans('Align')" />
                    <EmailWorkshopField v-model="socialIconSet" type="select" :options="SOCIAL_ICON_SETS" :label="ctrans('Icon style')" />
                    <EmailWorkshopField v-model="moduleComputedStyle.iconsDefaultWidth" type="number" :min="16" :max="96" :step="4" :label="ctrans('Icon size')" />

                    <div class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-gray-700">{{ ctrans('Social networks') }} ({{ iconItems.length }})</div>
                    <div v-for="(icon, index) in iconItems" :key="icon.id ?? index" class="my-2 rounded border border-gray-200 px-3 py-2">
                        <div class="flex items-center gap-x-2">
                            <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded border border-dashed border-gray-300 bg-gray-50 hover:border-[var(--theme-color-4)]"
                                v-tooltip="ctrans('Upload or browse icon image')" @click="pickSocialIconImage(icon)">
                                <img v-if="icon.image?.src" :src="icon.image.src" :alt="icon.image.alt ?? ''" class="max-h-full max-w-full object-contain" />
                                <FontAwesomeIcon v-else icon="fal fa-image" class="text-gray-400" fixed-width aria-hidden="true" />
                            </button>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[13px] font-medium text-gray-700">{{ socialNetworkLabel(icon, index) }}</div>
                                <button v-if="isCustomSocialIcon(icon) && icon.name" type="button" class="text-[11px] text-gray-500 hover:text-[var(--theme-color-4)]" @click="resetSocialIconImage(icon)">
                                    {{ ctrans('Use default icon') }}
                                </button>
                            </div>
                            <div class="flex shrink-0 items-center text-gray-500">
                                <button type="button" class="h-6 w-6 rounded hover:bg-gray-100 disabled:opacity-30" :disabled="index === 0" :aria-label="ctrans('Move up')" @click="moveIconItem(index, -1)">
                                    <FontAwesomeIcon icon="fal fa-arrow-up" fixed-width aria-hidden="true" />
                                </button>
                                <button type="button" class="h-6 w-6 rounded hover:bg-gray-100 disabled:opacity-30" :disabled="index === iconItems.length - 1" :aria-label="ctrans('Move down')" @click="moveIconItem(index, 1)">
                                    <FontAwesomeIcon icon="fal fa-arrow-down" fixed-width aria-hidden="true" />
                                </button>
                                <button type="button" class="h-6 w-6 rounded hover:bg-gray-100 hover:text-red-500" :aria-label="ctrans('Remove')" @click="removeIcon(index)">
                                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                        <template v-if="icon.image && typeof icon.image === 'object'">
                            <EmailWorkshopField v-model="icon.image.href" :label="ctrans('Link')" />
                            <EmailWorkshopField v-model="icon.image.alt" :label="ctrans('Alternate text')" />
                        </template>
                        <EmailWorkshopField v-else v-model="icon.href" :label="ctrans('Link')" />
                    </div>
                    <select v-model="socialNetworkToAdd" :aria-label="ctrans('Add social network')"
                        class="mb-2 h-9 w-full rounded border-dashed border-gray-300 py-0 pl-3 pr-7 text-[13px] text-gray-600 hover:border-[var(--theme-color-4)] focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]"
                        @change="addSocialIcon(socialNetworkToAdd)">
                        <option value="" disabled>+ {{ ctrans('Add social network') }}</option>
                        <option v-for="network in SOCIAL_NETWORKS" :key="network.name" :value="network.name">{{ network.label }}</option>
                    </select>
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.video">
                    <p class="my-2 rounded bg-[color-mix(in_srgb,var(--theme-color-4)_8%,white)] px-3 py-2 text-[12px] text-gray-700">
                        {{ ctrans('Email clients cannot play videos. The email shows the thumbnail with a play button that opens the video.') }}
                    </p>
                    <EmailWorkshopField v-model="videoSource" :label="ctrans('YouTube or Vimeo URL')" />
                    <div class="my-2 flex aspect-video items-center justify-center overflow-hidden rounded border border-dashed border-gray-300 bg-gray-900">
                        <img v-if="descriptor.video.thumbSrc" :src="descriptor.video.thumbSrc" :alt="descriptor.video.thumbAlt ?? ''" class="h-full w-full object-cover" />
                        <span v-else class="text-xs text-gray-400">{{ ctrans('No thumbnail') }}</span>
                    </div>
                    <p v-if="isVimeoWithoutThumbnail" class="mb-1 text-[12px] text-amber-600">{{ ctrans('Vimeo thumbnails cannot be fetched automatically. Please upload one.') }}</p>
                    <button type="button" class="mb-1 w-full rounded bg-[var(--theme-color-4)] py-2 text-[13px] font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]"
                        @click="pickVideoThumbnail">
                        {{ descriptor.video.thumbSrc ? ctrans('Change thumbnail') : ctrans('Upload or browse thumbnail') }}
                    </button>
                    <EmailWorkshopField v-model="videoThumbnailSource" :label="ctrans('Thumbnail URL')" />
                    <EmailWorkshopField v-model="descriptor.video.thumbAlt" :label="ctrans('Alternate text')" />
                    <EmailWorkshopField v-model="descriptor.video.thumbRatio" type="select" :options="videoRatioOptions" :label="ctrans('Aspect ratio')" />
                    <EmailWorkshopField v-model="isPlayButtonVisible" type="toggle" :label="ctrans('Play button')" />
                    <template v-if="isPlayButtonVisible">
                        <EmailWorkshopField v-model="descriptor.video.iconSize" type="number" :min="24" :max="160" :label="ctrans('Play button size')" />
                        <EmailWorkshopField v-model="descriptor.video.iconColor2" type="color" :label="ctrans('Play button color')" />
                        <EmailWorkshopField v-model="descriptor.video.iconColor1" type="color" :label="ctrans('Play icon color')" />
                    </template>
                    <p v-if="videoThumbnailState === 'loading'" class="mt-2 text-[12px] text-gray-500">{{ ctrans('Preparing the email image…') }}</p>
                    <p v-else-if="videoThumbnailState === 'error'" class="mt-2 text-[12px] text-red-600">{{ ctrans('The email image could not be prepared. Check the video link or upload a thumbnail.') }}</p>
                </template>

                <template v-else-if="isTableModule(module)">
                    <p class="my-2 rounded bg-[color-mix(in_srgb,var(--theme-color-4)_8%,white)] px-3 py-2 text-[12px] text-gray-700">
                        {{ ctrans('Type into the cells directly in the canvas.') }}
                    </p>
                    <div class="flex items-center justify-between border-b border-gray-100 py-2.5">
                        <span class="text-[13px] text-gray-600">{{ ctrans('Size') }}</span>
                        <span class="text-[13px] text-gray-800">{{ descriptor.aikuTable.rows.length }} × {{ tableColumnCount(descriptor.aikuTable) }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 border-b border-gray-100 py-2.5">
                        <button type="button" class="rounded border border-gray-300 py-1.5 text-[12px] text-gray-700 hover:border-[var(--theme-color-4)] hover:text-[var(--theme-color-4)]"
                            @click="addTableRow(descriptor.aikuTable)">+ {{ ctrans('Add row') }}</button>
                        <button type="button" class="rounded border border-gray-300 py-1.5 text-[12px] text-gray-700 hover:border-[var(--theme-color-4)] hover:text-[var(--theme-color-4)]"
                            @click="addTableColumn(descriptor.aikuTable)">+ {{ ctrans('Add column') }}</button>
                        <button type="button" class="rounded border border-gray-300 py-1.5 text-[12px] text-gray-700 hover:border-red-400 hover:text-red-500 disabled:opacity-40"
                            :disabled="descriptor.aikuTable.rows.length <= 1"
                            @click="removeTableRow(descriptor.aikuTable, descriptor.aikuTable.rows.length - 1)">− {{ ctrans('Remove last row') }}</button>
                        <button type="button" class="rounded border border-gray-300 py-1.5 text-[12px] text-gray-700 hover:border-red-400 hover:text-red-500 disabled:opacity-40"
                            :disabled="tableColumnCount(descriptor.aikuTable) <= 1"
                            @click="removeTableColumn(descriptor.aikuTable, tableColumnCount(descriptor.aikuTable) - 1)">− {{ ctrans('Remove last column') }}</button>
                    </div>
                    <EmailWorkshopField v-model="descriptor.aikuTable.hasHeader" type="toggle" :label="ctrans('Header row')" />
                    <template v-if="descriptor.aikuTable.hasHeader">
                        <EmailWorkshopField v-model="descriptor.aikuTable.headerBackgroundColor" type="color" :label="ctrans('Header background')" />
                        <EmailWorkshopField v-model="descriptor.aikuTable.headerTextColor" type="color" :label="ctrans('Header text color')" />
                        <EmailWorkshopField v-model="descriptor.aikuTable.headerBold" type="toggle" :label="ctrans('Bold header')" />
                    </template>
                    <EmailWorkshopField v-model="descriptor.aikuTable.backgroundColor" type="color" :label="ctrans('Cell background')" />
                    <EmailWorkshopField v-model="descriptor.aikuTable.striped" type="toggle" :label="ctrans('Striped rows')" />
                    <EmailWorkshopField v-if="descriptor.aikuTable.striped" v-model="descriptor.aikuTable.stripeColor" type="color" :label="ctrans('Stripe color')" />
                    <EmailWorkshopField v-model="descriptor.aikuTable.textColor" type="color" :label="ctrans('Text color')" />
                    <EmailWorkshopField v-model="descriptor.aikuTable.fontFamily" type="select" :options="fontFamilyOptions" :label="ctrans('Font')" />
                    <EmailWorkshopField v-model="descriptor.aikuTable.fontSize" type="px" :label="ctrans('Font size')" />
                    <EmailWorkshopField v-model="descriptor.aikuTable.align" type="align" :label="ctrans('Text align')" />
                    <EmailWorkshopField v-model="descriptor.aikuTable.cellPadding" type="px" :label="ctrans('Cell padding')" />
                    <EmailWorkshopField v-model="descriptor.aikuTable.borderWidth" type="px" :label="ctrans('Border width')" />
                    <EmailWorkshopField v-model="descriptor.aikuTable.borderColor" type="color" :label="ctrans('Border color')" />
                </template>

                <template v-else-if="isUnsubscribeModule(module)">
                    <div class="my-2 flex items-start gap-x-2 rounded bg-gray-50 px-3 py-2 text-[12px] text-gray-600">
                        <FontAwesomeIcon icon="fal fa-lock" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                        <span>{{ ctrans('The link always points to the recipient\'s personal unsubscribe page and is added when the email is sent. You can change how it looks, not where it goes.') }}</span>
                    </div>
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.text" :label="ctrans('Text before the link')" />
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.linkLabel" :label="ctrans('Link text')" />
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.backgroundColor" type="color" :label="ctrans('Background color')" />
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.textColor" type="color" :label="ctrans('Text color')" />
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.linkColor" type="color" :label="ctrans('Link color')" />
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.fontFamily" type="select" :options="fontFamilyOptions" :label="ctrans('Font')" />
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.fontSize" type="px" :label="ctrans('Font size')" />
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.align" type="align" :label="ctrans('Align')" />
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.underline" type="toggle" :label="ctrans('Underline link')" />
                    <EmailWorkshopField v-model="descriptor.aikuUnsubscribe.linkBold" type="toggle" :label="ctrans('Bold link')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.html">
                    <EmailWorkshopField v-model="descriptor.html.html" type="textarea" :label="ctrans('HTML')" />
                </template>

                <template v-else-if="moduleType === MODULE_TYPES.mergeContent">
                    <div v-if="descriptor.mergeContent?.name" class="my-2 rounded bg-gray-50 px-3 py-2 text-[13px] text-gray-700">{{ descriptor.mergeContent.name }}</div>
                    <button type="button" class="my-2 w-full rounded border border-[var(--theme-color-4)] py-1.5 text-[13px] text-[var(--theme-color-4)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_8%,white)]"
                        @click="emits('replaceDynamicContent')">
                        {{ descriptor.mergeContent?.name ? ctrans('Replace dynamic content') : ctrans(dynamicContentChooseLabel) }}
                    </button>
                </template>
            </EmailWorkshopSection>

            <EmailWorkshopSection v-if="hasModulePadding" :title="ctrans('Block options')">
                <EmailWorkshopPadding :target="moduleStyle" />
            </EmailWorkshopSection>

            <EmailWorkshopSection :title="ctrans('Responsive design')">
                <EmailWorkshopField v-model="moduleComputedStyle.hideContentOnMobile" type="toggle" :label="ctrans('Hide on mobile')" />
                <EmailWorkshopField v-model="moduleComputedStyle.hideContentOnDesktop" type="toggle" :label="ctrans('Hide on desktop')" />
            </EmailWorkshopSection>
        </template>

        <template v-else-if="row">
            <EmailWorkshopSection :title="ctrans('Row properties')">
                <EmailWorkshopField v-model="rowContainerStyle['background-color']" type="color" :label="ctrans('Row background color')" />
                <EmailWorkshopField v-model="rowContentStyle['background-color']" type="color" :label="ctrans('Content area background')" />
                <EmailWorkshopField v-model="rowContentStyle['border-radius']" type="px" :label="ctrans('Rounded corners')" />
                <EmailWorkshopField v-model="rowContentComputedStyle.verticalAlign" type="select" :options="verticalAlignOptions" :label="ctrans('Vertical align')" />
            </EmailWorkshopSection>

            <EmailWorkshopSection :title="ctrans('Column structure')">
                <div class="space-y-2 py-2">
                    <div class="flex gap-x-1">
                        <div v-for="(column, index) in row.columns" :key="column.uuid ?? index"
                            class="flex h-8 items-center justify-center rounded bg-[color-mix(in_srgb,var(--theme-color-4)_8%,white)] text-[11px] text-[var(--theme-color-4)]"
                            :style="{ width: `${column['grid-columns'] / 12 * 100}%` }">
                            {{ Math.round(column['grid-columns'] / 12 * 100) }}%
                        </div>
                    </div>
                    <div v-for="(column, index) in row.columns" :key="`settings-${column.uuid ?? index}`" class="rounded border border-gray-200 px-3 py-1">
                        <div class="pt-1.5 text-[12px] font-semibold text-gray-700">{{ ctrans('Column') }} {{ index + 1 }}</div>
                        <EmailWorkshopField v-model="column.style['background-color']" type="color" :label="ctrans('Background color')" />
                        <EmailWorkshopPadding :target="column.style" />
                    </div>
                </div>
            </EmailWorkshopSection>

            <EmailWorkshopSection :title="ctrans('Responsive design')">
                <EmailWorkshopField v-model="rowContentComputedStyle.rowColStackOnMobile" type="toggle" :label="ctrans('Stack on mobile')" />
                <EmailWorkshopField v-model="rowContentComputedStyle.hideContentOnMobile" type="toggle" :label="ctrans('Hide on mobile')" />
                <EmailWorkshopField v-model="rowContentComputedStyle.hideContentOnDesktop" type="toggle" :label="ctrans('Hide on desktop')" />
            </EmailWorkshopSection>
        </template>

        <template v-else>
            <EmailWorkshopSection :title="ctrans('General options')">
                <EmailWorkshopField v-model="bodyComputedStyle.messageWidth" type="px" :step="10" :label="ctrans('Content area width')" />
                <EmailWorkshopField v-model="bodyContainerStyle['background-color']" type="color" :label="ctrans('Background color')" />
                <EmailWorkshopField v-model="bodyComputedStyle.messageBackgroundColor" type="color" :label="ctrans('Content area background')" />
                <EmailWorkshopField v-model="bodyContentStyle['font-family']" type="select" :options="fontFamilyOptions" :label="ctrans('Default font')" />
                <EmailWorkshopField v-model="bodyContentStyle.color" type="color" :label="ctrans('Text color')" />
                <EmailWorkshopField v-model="bodyComputedStyle.linkColor" type="color" :label="ctrans('Link color')" />
            </EmailWorkshopSection>
        </template>

        <TiptapImageDialog v-if="isImagePickerOpen" :show="isImagePickerOpen" :uploadImageRoute="imagesUploadRoute"
            :imagesUploadedRoute="{ name: 'grp.gallery.uploaded-images.email.index' }" :imageCategories="imageCategories"
            @insert="onImagePicked" @close="isImagePickerOpen = false" />
    </div>
</template>
