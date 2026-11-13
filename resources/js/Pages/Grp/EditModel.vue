<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 03:19:52 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, inject } from "vue"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"
import { ctrans } from "@/Composables/useTrans"
import Action from "@/Components/Forms/Fields/Action.vue"
import FieldForm from "@/Components/Forms/FieldForm.vue"
import { get as getLodash } from "lodash-es"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGoogle, faFacebook, faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { routeType } from "@/types/route"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { router } from "@inertiajs/vue3"
import {
    faMusic,
    faMicrophoneAltSlash,
    faImage,
    faTag,
    faBrowser,
    faPowerOff,
    faDoorClosed,
    faUserLock,
    faEnvelope,
    faShoePrints,
    faShoppingBag,
    faBell,
    faCopyright,
    faUserCircle,
    faMobileAndroidAlt,
    faKey,
    faClone,
    faPaintBrush,
    faMoonStars,
    faLightbulbOn,
    faFlask,
    faBan as falBan,
    faCheck,
    faPhone,
    faIdCard,
    faFingerprint,
    faLanguage,
    faAddressBook,
    faTrashAlt,
    faSlidersH,
    faCog,
    faFlagCheckered,
    faBracketsCurly,
    faFileInvoice,
    faFileSignature,
    faFilePdf,
    faTransporter,
    faCode,
    faExchange,
    faBoxes,
    faAtom,
    faAtomAlt,
    faMoneyBill,
    faTags,
    faUserTag,
    faBookOpen,
    faNotesMedical,
    faBiohazard,
    faFolder,
    faCartArrowDown,
    faWeight,
    faShapes,
    faCommentAlt,
    faDollyFlatbedAlt,
    faMapMarkedAlt,
    faMedal,
    faBullseyePointer,
    faClock,
    faCalendarCheck,
    faPercent,
    faBusinessTime,
    faChessClock,
    faCameraRetro,
    faTemperatureLow,
    faBalanceScale,
    faNotEqual,
    faCamera,
    faStamp,
    faChevronDown,
    faGem,
    faHashtag,
    faPrint,
    faVolumeUp, faComments, faBooks, faCoins, faSignOut, faVideo, faCalendarAlt, faUser, faColumns, faBoxOpen, faStar, faSearch, faTimesCircle
} from "@fal"
import { faOctopusDeploy, faMeta } from "@fortawesome/free-brands-svg-icons"
import { faExclamationTriangle, faBrowser as faBrowserSolid } from "@fas"
import { faBan } from "@far"
import { faTh, faFolderTree } from "@fad"
import { Head, usePage } from "@inertiajs/vue3"
import axios from "axios"
import Message from 'primevue/message';

library.add(
    faPrint,
    faVolumeUp,
    faWhatsapp,
    faHashtag,
    faChevronDown,
    faGem,
    faTemperatureLow,
    faNotEqual,
    faMusic,
    faShapes,
    faTags,
    faBiohazard,
    faUserTag,
    faBookOpen,
    faNotesMedical,
    faOctopusDeploy,
    faMeta,
    faExclamationTriangle,
    faAtom,
    faAtomAlt,
    faTag,
    faMicrophoneAltSlash,
    faImage,
    faBan,
    faEnvelope,
    faPowerOff,
    faShoePrints,
    faShoppingBag,
    faBrowser,
    faUserLock,
    faBell,
    faCopyright,
    faUserCircle,
    faMobileAndroidAlt,
    faKey,
    faClone,
    faPaintBrush,
    faExchange,
    faMoonStars,
    faLightbulbOn,
    faFlask,
    falBan,
    faCheck,
    faPhone,
    faIdCard,
    faFingerprint,
    faLanguage,
    faAddressBook,
    faTrashAlt,
    faSlidersH,
    faCog,
    faGoogle,
    faFacebook,
    faFlagCheckered,
    faBracketsCurly,
    faFileInvoice,
    faFileSignature,
    faFilePdf,
    faTransporter,
    faCode,
    faDoorClosed,
    faBoxes,
    faMoneyBill,
    faFolder,
    faCartArrowDown,
    faWeight,
    faCommentAlt,
    faUserLock,
    faDollyFlatbedAlt,
    faMapMarkedAlt,
    faBullseyePointer,
    faMedal,
    faBrowserSolid,
    faTh,
    faFolderTree,
    faClock,
    faCalendarCheck,
    faPercent,
    faBusinessTime,
    faChessClock,
    faCameraRetro,
    faBalanceScale,
    faCamera,
    faStamp, faComments, faBooks, faCoins, faSignOut, faVideo, faCalendarAlt, faUser, faColumns, faBoxOpen, faStar, faSearch, faTimesCircle
)

const props = defineProps<{
    title: string
    embedded?: boolean
    warning?: {
        text: string
        title: string
        icon: string
        type: string
    }
    pageHead: {
        title: string
        exitEdit: {
            route: {
                name: string
                parameters: string[]
            }
        }
    }
    formData: {
        current?: string
        blueprint: {
            [key: string]: {
                // sectionData
                key?: string
                label: string
                title: string
                subtitle?: string
                information?: string // Tooltip information
                warning?: string // Warning information
                icon: string
                fields: {
                    // FieldData
                    name: string
                    type: string
                    label: string
                    value: string | object
                    icon?: string
                    action?: {
                        data?: any
                        method?: string
                    }
                }
                button: {
                    title: string
                    route: string
                    disable: boolean
                }
            }
        }
        fullLayout: boolean
        args: {
            updateRoute: routeType
        }
        title?: string
    }
}>()

const firstSectionWithVisibleFields = Object.keys(props.formData?.blueprint ?? {}).find(
    (sectionKey) => !Object.values(props.formData.blueprint[sectionKey].fields || {}).every((field: any) => field.hidden)
)
const sectionFromQuery = (section: string | number) =>
    Object.keys(props.formData?.blueprint ?? {}).find((sectionKey) => props.formData.blueprint[sectionKey].key === section) ?? section
const paramsSection = (route().params['section'] && sectionFromQuery(route().params['section'])) || firstSectionWithVisibleFields || 0
// const layout = useLayoutStore()
const layout: any = inject("layout")
const currentTab = ref<string | number>(
    props.formData?.current || paramsSection
)
 // if formData.current not exist, take first navigation
const _buttonRefs = ref([]) // For click linked to Navigation

const collapsedMobileSections = ref<(string | number)[]>(
    props.embedded
        ? Object.keys(props.formData?.blueprint ?? {}).filter((sectionKey) => String(sectionKey) !== String(currentTab.value))
        : []
)

const isMobileSectionCollapsed = (sectionKey: string | number) => collapsedMobileSections.value.map(String).includes(String(sectionKey))

const toggleMobileSection = (sectionKey: string | number) => {
    collapsedMobileSections.value = isMobileSectionCollapsed(sectionKey)
        ? collapsedMobileSections.value.filter((key) => String(key) !== String(sectionKey))
        : [...collapsedMobileSections.value, sectionKey]
}
const isMobile = ref(false)
const tabActive: any = ref({})
const fieldGroupAnimateSection = ref()
const _fieldForm = ref()

const savedFieldValue = (fieldName: string) => {
    const section: any = Object.values(props.formData?.blueprint ?? {}).find((sectionData: any) => sectionData?.fields && fieldName in sectionData.fields)

    return section?.fields[fieldName]?.value
}

const boxedRowClass = (fields: Record<string, any>, fieldName: string) => {
    if (!fields[fieldName]?.boxed) {
        return ''
    }

    const names = Object.keys(fields)
    const position = names.indexOf(fieldName)
    const isFirst = !fields[names[position - 1]]?.boxed
    const isLast = !fields[names[position + 1]]?.boxed

    return [
        'max-w-2xl border-x border-gray-200 bg-white px-4 !py-0',
        isFirst && !fields[fieldName].boxedTitle ? 'border-t rounded-t-md' : fields[fieldName].boxedGroup ? '!mt-0' : '!mt-0 border-t border-t-gray-100',
        isLast ? 'border-b rounded-b-md' : '',
    ].join(' ')
}

const isShownByCondition = (fieldData: any) => {
    if (!fieldData?.showIf) {
        return true
    }

    const fieldForms = Array.isArray(_fieldForm.value) ? _fieldForm.value : []
    const sourceField = fieldForms.find((fieldForm: any) => fieldForm?.form && fieldData.showIf.field in fieldForm.form)
    const currentValue = sourceField ? sourceField.form[fieldData.showIf.field] : savedFieldValue(fieldData.showIf.field)

    return currentValue === fieldData.showIf.value
}

const updateViewportWidth = () => {
    isMobile.value = window.innerWidth <= 768
}

const handleIntersection = (element: Element, index: number) => (entries) => {
    const [entry] = entries
    tabActive.value[`${index}`] = entry.isIntersecting
}

const switchTab = (key: string) => {
    currentTab.value = key

    // Update URL with query parameter
    router.visit('', {
        data: {
            // ...route().params, // Keep existing route parameters
            // section: props.formData.blueprint[key].label.toLowerCase()      // Add section query parameter
            section: key
        },
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [] // Don't reload any data, just update URL
    })
}

onMounted(() => {
    updateViewportWidth()
    window.addEventListener("resize", updateViewportWidth)

    // Animate the selected section
    route().v().query?.section
        ? ((currentTab.value = sectionFromQuery(getLodash(route().v().query, "section"))),
            setTimeout(() => {
                fieldGroupAnimateSection.value = ["bg-yellow-500/20"]
                setTimeout(() => {
                    fieldGroupAnimateSection.value = []
                }, 600)
            }, 100))
        : ""

    // To indicate active state that on viewport
    _buttonRefs.value.forEach((element: any, index: number) => {
        const observer = new IntersectionObserver(handleIntersection(element, index))
        observer.observe(element)

        // Clean up the observer when the component is unmounted
        element.cleanupObserver = () => {
            observer.disconnect()
        }
    })

    // Clean up all the observers when the component is unmounted
    return () => {
        _buttonRefs.value.forEach((button: any) => button.cleanupObserver())
    }
})

onBeforeUnmount(() => {
    window.removeEventListener("resize", updateViewportWidth)
})

// Error
// const errorInModels = usePage().props?.errors?.error_in_models
// const splitError = usePage().props?.errors?.error_in_models?.match(/^(\d{3}):\s(.+)$/)?.[1]
// const statusError = splitError?.[1]
// const messageError = splitError?.[2]

function connectToPlatform(routeName, parameters) {
    axios.post(route(routeName, parameters)).then((response) => {
        window.location.href = response.data
    })
}

const showWarningMessage = ref(true)

const severityMap: Record<string, string> = {
  warning: "warn",
  success: "success",
  info: "info",
  error: "error"
}

const getSeverity = (type?: string) => {
  return type ? severityMap[type.toLowerCase()] || "info" : "info"
}

const searchQuery = ref("")
const normalizedSearchQuery = computed(() => searchQuery.value.trim().toLowerCase())
const isSearching = computed(() => normalizedSearchQuery.value.length > 0)

const collectSearchableTexts = (value: unknown, depth = 0): string[] => {
    if (value === null || value === undefined || typeof value === "boolean" || depth > 4) {
        return []
    }

    if (typeof value === "string" || typeof value === "number") {
        return [String(value).replace(/<[^>]*>/g, " ")]
    }

    if (typeof value === "object") {
        return Object.values(value as Record<string, unknown>).flatMap((nestedValue) => collectSearchableTexts(nestedValue, depth + 1))
    }

    return []
}

const selectedOptionTexts = (fieldData: any): string[] => {
    const options = fieldData?.options
    if (!options || typeof options !== "object") {
        return []
    }

    const valueProp = fieldData.valueProp ?? "value"
    const labelProp = fieldData.labelProp ?? "label"
    const selectedValues = Array.isArray(fieldData.value) ? fieldData.value : [fieldData.value]

    return selectedValues.flatMap((selectedValue: unknown) => {
        if (selectedValue === null || selectedValue === undefined || typeof selectedValue === "object") {
            return []
        }

        const selectedOption = Array.isArray(options)
            ? options.find((option: any) => String(option?.[valueProp] ?? option?.id ?? option) === String(selectedValue))
            : options[String(selectedValue)]

        if (selectedOption === null || selectedOption === undefined) {
            return []
        }

        return typeof selectedOption === "object"
            ? collectSearchableTexts(selectedOption[labelProp] ?? selectedOption.label ?? selectedOption.name)
            : collectSearchableTexts(selectedOption)
    })
}

const textsMatchSearch = (texts: string[]) =>
    texts.some((text) => text.toLowerCase().includes(normalizedSearchQuery.value))

const isSectionVisible = (sectionData: any) =>
    !Object.values(sectionData?.fields || {}).every((field: any) => field.hidden)

const fieldMatchesSearch = (fieldData: any) => {
    if (!fieldData || fieldData.hidden || !isShownByCondition(fieldData)) {
        return false
    }

    return textsMatchSearch([
        ...collectSearchableTexts(fieldData.label),
        ...collectSearchableTexts(fieldData.value),
        ...selectedOptionTexts(fieldData),
    ])
}

const searchResults = computed(() => {
    const results: Record<string, { isSectionMatched: boolean, matchedFieldNames: string[] }> = {}

    if (!isSearching.value) {
        return results
    }

    Object.entries(props.formData?.blueprint ?? {}).forEach(([sectionKey, sectionData]: [string, any]) => {
        if (!isSectionVisible(sectionData)) {
            return
        }

        const isSectionMatched = textsMatchSearch(collectSearchableTexts([sectionData.label, sectionData.title, sectionData.subtitle]))
        const matchedFieldNames = Object.entries(sectionData.fields || {})
            .filter(([, fieldData]) => fieldMatchesSearch(fieldData))
            .map(([fieldName]) => fieldName)

        if (isSectionMatched || matchedFieldNames.length) {
            results[sectionKey] = { isSectionMatched, matchedFieldNames }
        }
    })

    return results
})

const matchedSectionKeys = computed(() => Object.keys(searchResults.value))

const sectionSearchResult = (sectionKey: string | number) => searchResults.value[String(sectionKey)]

const isFieldHighlighted = (sectionKey: string | number, fieldName: string | number) =>
    sectionSearchResult(sectionKey)?.matchedFieldNames.includes(String(fieldName)) ?? false

watch(matchedSectionKeys, (sectionKeys) => {
    if (!isSearching.value || !sectionKeys.length) {
        return
    }

    if (!sectionKeys.includes(String(currentTab.value))) {
        currentTab.value = sectionKeys[0]
    }

    collapsedMobileSections.value = Object.keys(props.formData?.blueprint ?? {}).filter((sectionKey) => !sectionKeys.includes(sectionKey))
})

const searchHighlightName = "edit-model-search"
const _searchableArea = ref<HTMLElement | null>(null)

const supportsSearchHighlight = () => typeof CSS !== "undefined" && "highlights" in CSS

const rangesMatchingSearch = (root: Node): Range[] => {
    const ranges: Range[] = []
    const query = normalizedSearchQuery.value
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)

    for (let textNode = walker.nextNode(); textNode; textNode = walker.nextNode()) {
        const text = textNode.textContent?.toLowerCase() ?? ""
        let matchStart = text.indexOf(query)

        while (matchStart !== -1) {
            const range = new Range()
            range.setStart(textNode, matchStart)
            range.setEnd(textNode, matchStart + query.length)
            ranges.push(range)
            matchStart = text.indexOf(query, matchStart + query.length)
        }
    }

    return ranges
}

const rangesForLabelsMatchedByContent = (root: HTMLElement): Range[] =>
    Array.from(root.querySelectorAll("[data-search-label], [data-search-field-matched] dt"))
        .filter((labelElement) => !(labelElement.textContent ?? "").toLowerCase().includes(normalizedSearchQuery.value))
        .map((labelElement) => {
            const range = new Range()
            range.selectNodeContents(labelElement)
            return range
        })

const applySearchHighlight = () => {
    if (!supportsSearchHighlight()) {
        return
    }

    CSS.highlights.delete(searchHighlightName)

    if (!isSearching.value || !_searchableArea.value) {
        return
    }

    CSS.highlights.set(
        searchHighlightName,
        new Highlight(...rangesMatchingSearch(_searchableArea.value), ...rangesForLabelsMatchedByContent(_searchableArea.value))
    )
}

watch([normalizedSearchQuery, currentTab, collapsedMobileSections, isMobile], applySearchHighlight, { flush: "post" })

onBeforeUnmount(() => {
    if (supportsSearchHighlight()) {
        CSS.highlights.delete(searchHighlightName)
    }
})
</script>


<template>
    <Head v-if="!embedded" :title="capitalize(title)" />
    <PageHeading v-if="!embedded" :data="pageHead" />

    <div v-if="!embedded" class="px-2 py-3 flex flex-wrap items-center gap-x-3 gap-y-1">
        <IconField class="w-full sm:w-80">
            <InputIcon>
                <FontAwesomeIcon icon="fal fa-search" class="text-gray-400" fixed-width aria-hidden="true" />
            </InputIcon>
            <InputText
                v-model="searchQuery"
                size="small"
                fluid
                :placeholder="ctrans('Search settings or values')"
                :aria-label="ctrans('Search settings or values')"
                class="focus:!border-[var(--app-accent)]"
                @keydown.esc="searchQuery = ''" />
            <InputIcon v-if="searchQuery">
                <button type="button" :aria-label="ctrans('Clear search')" class="text-gray-400 hover:text-gray-600"
                    @click="searchQuery = ''">
                    <FontAwesomeIcon icon="fal fa-times-circle" fixed-width aria-hidden="true" />
                </button>
            </InputIcon>
        </IconField>
        <span v-if="isSearching && !matchedSectionKeys.length" class="text-xs text-gray-500">
            {{ ctrans("No matching settings") }}
        </span>
    </div>

    <div v-if="warning && showWarningMessage">
        <Message v-if="warning && showWarningMessage" :severity="getSeverity(warning.type)" xclosable="true"
            @close="showWarningMessage = false">
            <div class="flex items-start gap-3">
                <!-- Icon -->
                <FontAwesomeIcon v-if="warning.icon" :icon="warning.icon" class="w-4 h-4 flex-shrink-0 mt-0.5" :class="[
                    getSeverity(warning.type) === 'warn' ? 'text-yellow-800' :
                        getSeverity(warning.type) === 'success' ? 'text-green-800' :
                            getSeverity(warning.type) === 'error' ? 'text-red-800' :
                                'text-blue-500'
                ]" fixed-width />

                <!-- Content -->
                <div class="flex flex-col">
                    <div class="text-sm font-semibold">
                        {{ warning?.title }}
                    </div>
                    <div v-if="warning?.text" :class="[
                        getSeverity(warning.type) === 'warn' ? 'text-yellow-600/80' :
                            getSeverity(warning.type) === 'success' ? 'text-green-500' :
                                getSeverity(warning.type) === 'error' ? 'text-red-500' :
                                    'text-blue-500'
                    ]" class="text-xs">
                        <div v-html="warning?.text"/>
                    </div>
                </div>
            </div>
        </Message>
    </div>
    <!-- If overflow-hidden, affect to Multiselect on Address -->
    <div ref="_searchableArea" :class="embedded ? 'lg:h-full lg:min-h-0' : (isMobile ? '' : 'rounded-lg shadow')">
        <div v-if="!isMobile"
            class="divide-y divide-gray-200 lg:grid grid-flow-col lg:grid-cols-12 lg:divide-y-0 lg:divide-x"
            :class="embedded ? 'lg:h-full lg:min-h-0' : ''">
            <!-- Tab: Navigation -->
            <aside v-if="!formData.fullLayout" class="bg-gray-50/50 py-0 lg:col-span-3 lg:h-full"
                :class="embedded ? 'lg:min-h-0 lg:overflow-y-auto' : ''">
                <div :class="embedded ? '' : 'sticky top-16'">
                    <template v-for="(sectionData, key) in formData.blueprint">
                        <!-- If Section: all fields is not hidden -->
                        <div v-if="!(Object.values(sectionData.fields || {}).every((field: any) => field.hidden))"
                            @click="switchTab(key)" :class="[
								key == currentTab ? `navigationSecondActive` : `navigationSecond`,
								'cursor-pointer group px-3 py-2 flex items-center text-sm font-medium',
							]" :style="[
								key == currentTab
									? {
											'border-left': `4px solid ${layout.app?.theme[2]}`,
											'background-color': `color-mix(in srgb, ${layout?.app?.theme[2]} 20%, white)`,
											color: `color-mix(in srgb, ${layout?.app?.theme[3]} 50%, black)`,
									  }
									: { 'border-left': `4px solid transparent` },
							]">
                            <FontAwesomeIcon v-if="sectionData.icon" fixed-width aria-hidden="true"
                                class="flex-shrink-0 -ml-1 mr-2 h-4 w-4" :class="[
									tabActive[key]
										? 'text-gray-400 group-hover:text-gray-500'
										: 'text-gray-400',
								]" :icon="sectionData.icon" />
                            <span class="truncate" :data-search-label="sectionSearchResult(key) ? '' : undefined">{{ sectionData.label }}</span>
                            <FontAwesomeIcon v-if="sectionData.information" v-tooltip="sectionData.information"
                                icon="fal fa-info-circle" class="ml-1 text-gray-400 hover:text-gray-700" fixed-width
                                aria-hidden="true" />
                            <FontAwesomeIcon v-if="sectionData.warning" v-tooltip="sectionData.warning"
                                icon="fas fa-exclamation-triangle" class="ml-1 text-amber-500 hover:text-amber-600" fixed-width
                                aria-hidden="true" />
                        </div>
                    </template>
                </div>
            </aside>

            <!-- Section: Fields Form -->
            <div :class="[
					'px-4 sm:px-6 md:px-4',
					formData.fullLayout ? 'col-span-12' : 'col-span-9',
					embedded ? 'lg:h-full lg:min-h-0 lg:overflow-y-auto' : '',
				]">
                <!-- Section: Error in models -->
                <Transition name="spin-to-down">
                    <div v-if="usePage().props?.errors?.error_in_models"
                        class="mt-3 flex gap-x-1 items-center bg-red-500 w-full p-3 text-white rounded">
                        <FontAwesomeIcon v-if="
								usePage().props?.errors?.error_in_models?.match(
									/^(\d{3}):\s(.+)$/
								)?.[1] === '403'
							" icon="far fa-ban" class="text-lg" fixed-width aria-hidden="true" />
                        <div class="">{{ usePage().props.errors.error_in_models }}</div>
                    </div>
                </Transition>

                <template v-for="(sectionData, sectionIdx) in formData.blueprint" :key="sectionIdx">
                    <!-- If Section: all fields is not hidden -->
                    <template v-if="!(Object.values(sectionData.fields || {}).every((field: any) => field.hidden))">
                        <div v-show="sectionIdx == currentTab" class="pt-4">
                            <div class="sr-only absolute -top-16" :id="`field${sectionIdx}`" />
                            <!-- Title -->
                            <div v-if="sectionData.title || sectionData.subtitle" class="mb-4 flex items-center gap-x-2"
                                ref="_buttonRefs">
                                <h3 v-if="sectionData.title"
                                    class="text-lg leading-6 font-medium text-gray-700 capitalize">
                                    {{ sectionData.title }}
                                </h3>
                                <p v-if="sectionData.subtitle" class="max-w-2xl text-sm text-gray-500">
                                    {{ sectionData.subtitle }}
                                </p>
                            </div>

                            <!-- Looping Field -->
                            <div class="my-2 space-y-5 transition-all duration-1000 ease-in-out"
                                :class="fieldGroupAnimateSection">
                                <template v-for="(fieldData, fieldName, index) in sectionData.fields" :key="index">
                                    <div v-if="fieldData?.boxedTitle && !fieldData.hidden"
                                        class="max-w-2xl rounded-t-md border border-b-0 border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        {{ fieldData.boxedTitle }}
                                    </div>
                                    <div v-if="fieldData?.boxedGroup && !fieldData.hidden"
                                        class="!mt-0 max-w-2xl border-x border-t border-gray-200 px-4 pt-3 pb-1 text-xs font-medium text-gray-400">
                                        {{ fieldData.boxedGroup }}
                                    </div>
                                    <!-- Field: is not hidden and skip price when TBC -->

                                    <div v-if="fieldData && !fieldData?.hidden && isShownByCondition(fieldData) && !( ['price','territories'].includes(fieldName) && sectionData.fields?.price?.value?.type === 'TBC')"
                                        class="py-2 mt-1 flex text-sm text-gray-700 sm:mt-0"
                                        :class="boxedRowClass(sectionData.fields, fieldName)"
                                        :data-search-field-matched="isFieldHighlighted(sectionIdx, fieldName) ? '' : undefined">

                                        <Action v-if="fieldData.type === 'action'" :action="fieldData.action"
                                            :dataToSubmit="fieldData.action?.data" />
                                        <FieldForm v-else :key="fieldName + index" ref="_fieldForm" :field="fieldName"
                                            :fieldData="fieldData" :args="formData.args" :refForms="_fieldForm" />
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </template>

                <!-- For button Authorize -->
                <div class="py-2 px-3 flex justify-end max-w-2xl" v-if="formData.blueprint?.[currentTab]?.button"
                    :id="formData.title">
                    <component :is="'button'" @click="
							connectToPlatform(
								formData.blueprint[currentTab].button.route.name,
								formData.blueprint[currentTab].button.route.parameters
							)
						" class="px-3 py-1.5 rounded" :class="[
							formData.blueprint[currentTab].button.disable
								? 'bg-orange-200 cursor-default text-white'
								: 'text-gray-100 bg-green-500 hover:bg-green-600',
						]">
                        {{ formData.blueprint[currentTab].button.title }}
                    </component>
                </div>
            </div>
        </div>

        <!-- Mobile view -->
        <ul v-else class="divide-y divide-gray-200 border-y border-gray-200 bg-gray-50/50" :class="embedded ? 'border-t-0' : 'mb-6'">
            <template v-for="(sectionData, key) in formData.blueprint">
                <!-- If Section: all fields is not hidden -->
                <li v-if="!(Object.values(sectionData.fields || {}).every((field: any) => field.hidden))"
                    class="group font-medium" :aria-current="key === currentTab ? 'page' : undefined">
                    <button type="button" @click="toggleMobileSection(key)"
                        :aria-expanded="!isMobileSectionCollapsed(key)"
                        class="w-full px-3 py-3 flex items-center text-left text-sm font-medium"
                        :class="isMobileSectionCollapsed(key) ? 'navigationSecond' : 'navigationSecondActive'"
                        :style="isMobileSectionCollapsed(key)
                            ? { 'border-left': '4px solid transparent' }
                            : {
                                'border-left': `4px solid ${layout.app?.theme[2]}`,
                                'background-color': `color-mix(in srgb, ${layout?.app?.theme[2]} 20%, white)`,
                                color: `color-mix(in srgb, ${layout?.app?.theme[3]} 50%, black)`,
                            }">
                        <FontAwesomeIcon v-if="sectionData.icon" fixed-width aria-hidden="true" :icon="sectionData.icon"
                            class="flex-shrink-0 -ml-1 mr-2 h-4 w-4 text-gray-400" />
                        <span class="capitalize truncate" :data-search-label="sectionSearchResult(key) ? '' : undefined">{{ sectionData.label }}</span>
                        <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width aria-hidden="true"
                            class="ml-auto h-3 w-3 text-gray-400 transition-transform duration-200"
                            :class="isMobileSectionCollapsed(key) ? '' : 'rotate-180'" />
                    </button>
                    <div v-show="!isMobileSectionCollapsed(key)" class="bg-white px-4">
                        <template v-for="(fieldData, fieldName, index) in formData.blueprint[key].fields">
                            <!-- Field: is not hidden and skip price when TBC -->
                            <div v-if="!fieldData?.hidden && isShownByCondition(fieldData) && !(['price', 'territories'].includes(fieldName) && sectionData.fields?.price?.value?.type === 'TBC')"
                                class="py-4"
                                :data-search-field-matched="isFieldHighlighted(key, fieldName) ? '' : undefined">
                                <Action v-if="fieldData.type === 'action'" :action="fieldData.action"
                                    :dataToSubmit="fieldData.action?.data" />
                                <FieldForm v-else :key="index" :field="fieldName" :fieldData="fieldData"
                                    :args="formData.args" :id="fieldData.name" :refForms="_fieldForm" />
                            </div>
                        </template>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</template>

<style>
::highlight(edit-model-search) {
    background-color: var(--app-accent-muted);
}
</style>
