<script setup lang="ts">
import { computed, inject, nextTick, ref, watch } from "vue"
import axios from "axios"
import { createReusableTemplate } from "@vueuse/core"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faExclamationTriangle, faExternalLink, faLanguage, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { InputGroup, InputGroupAddon, InputNumber, InputText, Textarea, ToggleSwitch } from "primevue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import { seoFieldHelp } from "@/Composables/seoFieldHelp"
import ToggleStateWebpage from "@/Components/Forms/Fields/ToggleStateWebpage.vue"
import RichTextField from "@/Components/Catalogue/ProductSetting/RichTextField.vue"
import FieldForm from "@/Components/Forms/FieldForm.vue"
import { productSettingActionsId, settingFieldId, type MasterProductData, type SharedInformationValue, type ShopProductSetting } from "@/Components/Catalogue/ProductSetting/types"

const props = defineProps<{
    master: MasterProductData
    shop: ShopProductSetting
    canEdit: boolean
}>()

const emit = defineEmits<{
    saved: []
}>()

const locale = inject("locale", aikuLocaleStructure)

type ContentKey = "name" | "description" | "description_extra"
type FollowMode = "follow" | "customise"

type SettingForm = {
    state_data: { state: string, redirect_webpage_id: number | null }
    title: string
    description: string
    url: string
    breadcrumb_label: string
    index_page: boolean
    follow_link: boolean
    content: Record<ContentKey, { mode: FollowMode, value: string }>
    price: { mode: FollowMode, value: number | null }
}

const contentUpdateKeys: Record<ContentKey, string> = {
    name: "product_name",
    description: "product_description",
    description_extra: "product_description_extra",
}

const suggestionFieldOfContent: Record<string, ContentKey> = {
    product_name: "name",
    top_desc: "description",
    extra_desc: "description_extra",
}

const webpageKeys = ["title", "description", "url", "breadcrumb_label", "index_page", "follow_link"] as const

const formFromShop = (shop: ShopProductSetting): SettingForm => {
    const contentOf = (key: ContentKey) => ({
        mode: (shop.product.follows_master[key] ? "follow" : "customise") as FollowMode,
        value: shop.product[key] ?? "",
    })

    return {
        state_data: { state: shop.webpage?.state ?? "", redirect_webpage_id: shop.webpage?.state_field.redirect_webpage_id ?? null },
        title: shop.webpage?.title ?? "",
        description: shop.webpage?.description ?? "",
        url: shop.webpage?.url ?? "",
        breadcrumb_label: shop.webpage?.breadcrumb_label ?? "",
        index_page: shop.webpage?.index_page ?? false,
        follow_link: shop.webpage?.follow_link ?? false,
        content: { name: contentOf("name"), description: contentOf("description"), description_extra: contentOf("description_extra") },
        price: {
            mode: shop.product.follows_master.price ? "follow" : "customise",
            value: shop.product.price === null || shop.product.price === "" ? null : Number(shop.product.price),
        },
    }
}

const form = ref<SettingForm>(formFromShop(props.shop))
const savedForm = ref<SettingForm>(formFromShop(props.shop))
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)

watch(() => props.shop, (shop) => {
    form.value = formFromShop(shop)
    savedForm.value = formFromShop(shop)
    errors.value = {}
})

const isFirstPublish = computed(() => ["in_process", "ready"].includes(props.shop.webpage?.state ?? ""))

const isStateChanged = computed(() => JSON.stringify(form.value.state_data) !== JSON.stringify(savedForm.value.state_data))

const needsPublish = computed(() => form.value.state_data.state === "live" && isFirstPublish.value)

const isRedirectMissing = computed(() => form.value.state_data.state === "closed" && !form.value.state_data.redirect_webpage_id)

const webpagePayload = computed(() => {
    const payload: Record<string, unknown> = {}

    for (const key of webpageKeys) {
        if (form.value[key] !== savedForm.value[key]) {
            payload[key] = form.value[key]
        }
    }

    if (isStateChanged.value && !needsPublish.value) {
        payload.state_data = form.value.state_data.state === "closed" ? { ...form.value.state_data } : { state: form.value.state_data.state }
    }

    for (const key of Object.keys(contentUpdateKeys) as ContentKey[]) {
        const field = form.value.content[key]
        const target = field.mode === "follow" ? props.master[key] ?? "" : field.value
        if (target !== (props.shop.product[key] ?? "")) {
            payload[contentUpdateKeys[key]] = target
        }
    }

    return payload
})

const productPayload = computed(() => {
    const price = form.value.price
    const savedPrice = savedForm.value.price

    if (price.mode !== savedPrice.mode) {
        return price.mode === "follow" ? { not_follow_master_prices: false } : { not_follow_master_prices: true, price: price.value }
    }

    return price.mode === "customise" && price.value !== savedPrice.value ? { price: price.value } : {}
})

const META_MAX_LENGTH = { title: 60, description: 160 } as const

const isOverMaxLength = (field: keyof typeof META_MAX_LENGTH) => form.value[field].length > META_MAX_LENGTH[field]

const changeCount = computed(() => Object.keys(webpagePayload.value).length + Object.keys(productPayload.value).length + (needsPublish.value ? 1 : 0))

const errorsFrom = (error: unknown) =>
    Object.fromEntries(Object.entries((error as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors ?? {}).map(([field, messages]) => [field, messages[0]]))

const saveMessage = ref<{ isError: boolean, text: string } | null>(null)

const publish = async () => {
    const webpage = props.shop.webpage
    if (!webpage || !changeCount.value || isRedirectMissing.value) {
        return
    }

    isSaving.value = true
    errors.value = {}
    saveMessage.value = null

    try {
        if (Object.keys(webpagePayload.value).length) {
            await axios.patch(route(webpage.update_route.name, webpage.update_route.parameters), webpagePayload.value)
        }
        if (Object.keys(productPayload.value).length) {
            await axios.patch(route(props.shop.product.update_route.name, props.shop.product.update_route.parameters), productPayload.value)
        }
        if (needsPublish.value) {
            await axios.post(route(webpage.publish_route.name, webpage.publish_route.parameters), {})
        }

        saveMessage.value = { isError: false, text: ctrans("The :shop product page has been updated", { shop: props.shop.shop.code }) }
        emit("saved")
    } catch (error) {
        errors.value = errorsFrom(error)
        saveMessage.value = {
            isError: true,
            text: (error as { response?: { data?: { message?: string } } })?.response?.data?.message ?? ctrans("The product page could not be updated"),
        }
    } finally {
        isSaving.value = false
    }
}

const isTranslatingMetaDescription = ref(false)
const translateError = ref<string | null>(null)

const translateMetaDescription = async () => {
    isTranslatingMetaDescription.value = true
    translateError.value = null

    try {
        const { data } = await axios.post(
            route("grp.models.translate", { languageFrom: "en", languageTo: props.shop.shop.language_code }),
            { text: props.master.meta_description, catalogue: true },
            { timeout: 10000 },
        )
        form.value.description = data
    } catch (error) {
        translateError.value = (error as { response?: { data?: { message?: string } } })?.response?.data?.message ?? ctrans("Failed to generate translation.")
    } finally {
        isTranslatingMetaDescription.value = false
    }
}

const focusField = async (suggestionField: string) => {
    const contentKey = suggestionFieldOfContent[suggestionField]
    if (contentKey) {
        form.value.content[contentKey].mode = "customise"
    }

    await nextTick()
    const element = document.getElementById(settingFieldId(suggestionField))
    element?.scrollIntoView({ behavior: "smooth", block: "center" })
    element?.querySelector<HTMLElement>("input, textarea, [contenteditable='true']")?.focus({ preventScroll: true })
}

defineExpose({ focusField, form })

const formatMoney = (currencyCode: string, value: string | number | null) => (value === null || value === "" ? null : locale.currencyFormat(currencyCode, Number(value)))

const followOptions = [
    { label: ctrans("Follow master"), value: "follow" as FollowMode, tone: "primary" as const },
    { label: ctrans("Customise"), value: "customise" as FollowMode, tone: "primary" as const },
]

const contentFields = computed(() => [
    { key: "name" as ContentKey, suggestionField: "product_name", label: ctrans("Product name"), isLong: false },
    { key: "description" as ContentKey, suggestionField: "top_desc", label: ctrans("Description"), isLong: true },
    { key: "description_extra" as ContentKey, suggestionField: "extra_desc", label: ctrans("Extra description"), isLong: true },
])

const sourceLabel = (source: SharedInformationValue["source"] | undefined) => (source === "website" ? ctrans("From the website") : ctrans("Set by master"))

const sharedInformation = computed(() => [
    { key: "shipping", label: ctrans("Shipping information"), shared: props.shop.shared.shipping },
    { key: "return_policy", label: ctrans("Return policy"), shared: props.shop.shared.return_policy },
])

const setByLabel = computed(() => (props.master.has_master ? ctrans("Set by master") : ctrans("Set on the product")))

const isFilled = (value: string | number | null | undefined) => String(value ?? "").replace(/<[^>]*>/g, "").replace(/&nbsp;/g, " ").trim() !== ""
const contentValue = (key: ContentKey) => form.value.content[key].mode === "follow" ? props.master[key] : form.value.content[key].value

const [DefineFilledBadge, FilledBadge] = createReusableTemplate<{ value: string | number | null | undefined }>()
const [DefineMasterValue, MasterValue] = createReusableTemplate<{ value: string | number | null, isHtml?: boolean, origin?: string, emptyLabel?: string }>()
</script>

<template>
    <DefineFilledBadge v-slot="{ value }">
        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium"
            :class="isFilled(value) ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
            <FontAwesomeIcon :icon="isFilled(value) ? faCheck : faTimes" fixed-width aria-hidden="true" />
            {{ isFilled(value) ? ctrans("Filled") : ctrans("Not filled") }}
        </span>
    </DefineFilledBadge>
    <DefineMasterValue v-slot="{ value, isHtml, origin, emptyLabel }">
        <div class="rounded bg-gray-50 px-3 py-2 text-sm text-gray-700">
            <span class="mr-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ origin ?? (master.has_master ? ctrans("Master") : ctrans("Product")) }} ·</span>
            <template v-if="value !== null && value !== ''">
                <div v-if="isHtml" class="mt-1 [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-1 [&_ul]:list-disc [&_ul]:pl-5" v-html="value" />
                <span v-else class="whitespace-pre-line">{{ value }}</span>
            </template>
            <span v-else class="italic text-gray-400">{{ emptyLabel ?? ctrans("Not set in master") }}</span>
        </div>
    </DefineMasterValue>

    <div class="space-y-4">
        <Teleport defer :to="`#${productSettingActionsId}`">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft] px-4 py-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">{{ ctrans("Fill in the product information") }}</div>
                    <div class="text-sm text-gray-600">
                        {{ ctrans("Meta title, meta description, URL, breadcrumb, top and extra description and price. Then set State to Online and publish.") }}
                    </div>
                </div>
                <Button
                    type="primary"
                    :label="changeCount ? ctrans('Publish (:count)', { count: String(changeCount) }) : ctrans('Publish')"
                    :disabled="!canEdit || !shop.webpage || !changeCount || isSaving || isRedirectMissing"
                    :loading="isSaving"
                    @click="publish"/>
                <p v-if="saveMessage" class="w-full text-right text-xs" :class="saveMessage.isError ? 'text-red-600' : 'text-green-700'">{{ saveMessage.text }}</p>
            </div>
        </Teleport>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-md">
            <header class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-900">1 · {{ ctrans("Product content") }}</h3>
                <span class="text-xs text-gray-500">{{ ctrans("Follow the master or customise for the :shop shop", { shop: shop.shop.code }) }}</span>
            </header>
            <div class="space-y-3">
                <div class="rounded-md border border-gray-200 p-3">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-gray-800">{{ ctrans("Product code") }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">{{ setByLabel }}</span>
                    </div>
                    <MasterValue :value="master.code" />
                </div>

                <div v-for="field in contentFields" :id="settingFieldId(field.suggestionField)" :key="field.key" class="rounded-md border border-gray-200 p-3">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <label :for="`content-${field.key}`" class="text-sm font-medium text-gray-800">{{ field.label }}</label>
                            <HelpTip v-if="seoFieldHelp(field.suggestionField)" :text="seoFieldHelp(field.suggestionField)!" class="-ml-1" />
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="form.content[field.key].mode === 'follow' ? 'bg-gray-100 text-gray-600' : 'bg-amber-50 text-amber-700'"
                            >{{ form.content[field.key].mode === "follow" ? ctrans("Follows master") : ctrans("Customised in this shop") }}</span>
                            <FilledBadge :value="contentValue(field.key)" />
                        </div>
                        <SegmentedToggle v-if="canEdit && master.has_master" v-model="form.content[field.key].mode" :options="followOptions" :ariaLabel="field.label" />
                    </div>
                    <MasterValue :value="master[field.key]" :isHtml="field.isLong" />
                    <div v-if="form.content[field.key].mode === 'customise'" class="mt-2">
                        <InputText
                            v-if="!field.isLong"
                            :id="`content-${field.key}`"
                            v-model="form.content[field.key].value"
                            fluid
                            :disabled="!canEdit"
                            :invalid="!!errors[`product_${field.key}`]"
                        />
                        <RichTextField
                            v-else
                            :key="`${shop.id}-${field.key}`"
                            v-model="form.content[field.key].value"
                            withHeadings
                            :routeGetInternalLink="shop.webpage?.internal_link_route"
                            :disabled="!canEdit"
                            :isError="!!errors[`product_${field.key}`]"
                        />
                        <p v-if="errors[`product_${field.key}`]" class="mt-1 text-xs text-red-600">{{ errors[`product_${field.key}`] }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-md">
            <header class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-900">2 · {{ ctrans("Search appearance") }}</h3>
                <span class="text-xs text-gray-500">{{ ctrans("Set for this shop") }}</span>
            </header>
            <div v-if="shop.webpage" class="space-y-3">
                <div :id="settingFieldId('title')">
                    <div class="mb-1 flex justify-between text-sm">
                        <span class="flex items-center gap-2"><span><label for="meta-title" class="font-medium text-gray-700">{{ ctrans("Meta title") }}</label><HelpTip :text="seoFieldHelp('title')!" /></span><FilledBadge :value="form.title" /></span>
                        <span class="tabular-nums" :class="isOverMaxLength('title') ? 'text-red-600' : 'text-gray-400'">{{ form.title.length }} / {{ META_MAX_LENGTH.title }}</span>
                    </div>
                    <InputText id="meta-title" v-model="form.title" fluid :disabled="!canEdit" :invalid="!!errors.title" />
                    <p v-if="errors.title || isOverMaxLength('title')" class="mt-1 text-xs text-red-600">
                        {{ errors.title ?? ctrans("Longer than :max characters, so Google cuts it off in search results.", { max: String(META_MAX_LENGTH.title) }) }}
                    </p>
                </div>
                <div :id="settingFieldId('description')">
                    <div class="mb-1 flex justify-between text-sm">
                        <span class="flex items-center gap-2"><span><label for="meta-description" class="font-medium text-gray-700">{{ ctrans("Meta description") }}</label><HelpTip :text="seoFieldHelp('description')!" /></span><FilledBadge :value="form.description" /></span>
                        <span class="tabular-nums" :class="isOverMaxLength('description') ? 'text-red-600' : 'text-gray-400'">{{ form.description.length }} / {{ META_MAX_LENGTH.description }}</span>
                    </div>
                    <div v-if="master.has_master" class="mb-2 flex items-start gap-2">
                        <div class="flex-1"><MasterValue :value="master.meta_description" /></div>
                        <Button
                            v-if="canEdit && master.meta_description"
                            type="tertiary"
                            :icon="faLanguage"
                            :label="ctrans('Translate from master')"
                            :loading="isTranslatingMetaDescription"
                            @click="translateMetaDescription"/>
                    </div>
                    <p v-if="translateError" class="mb-2 text-xs text-red-600">{{ translateError }}</p>
                    <Textarea id="meta-description" v-model="form.description" :rows="2" autoResize fluid :disabled="!canEdit" :invalid="!!errors.description" />
                    <p v-if="errors.description || isOverMaxLength('description')" class="mt-1 text-xs text-red-600">
                        {{ errors.description ?? ctrans("Longer than :max characters, so Google cuts it off in search results.", { max: String(META_MAX_LENGTH.description) }) }}
                    </p>
                </div>
                <div :id="settingFieldId('seo_image')">
                    <FieldForm
                        field="seo_image"
                        :fieldData="{ ...shop.webpage.seo_image_field, readonly: !canEdit }"
                        :args="{ updateRoute: shop.webpage.update_route }"
                        :refForms="undefined"/>
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div :id="settingFieldId('url')">
                        <div class="mb-1 text-sm"><label for="url" class="font-medium text-gray-700">{{ ctrans("URL") }}</label><HelpTip :text="seoFieldHelp('url')!" /></div>
                        <InputGroup>
                            <InputGroupAddon>/</InputGroupAddon>
                            <InputText id="url" v-model="form.url" class="font-mono" :disabled="!canEdit" :invalid="!!errors.url" />
                        </InputGroup>
                        <p v-if="errors.url" class="mt-1 text-xs text-red-600">{{ errors.url }}</p>
                    </div>
                    <div :id="settingFieldId('breadcrumb_label')">
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="flex items-center gap-2"><span><label for="breadcrumb-label" class="font-medium text-gray-700">{{ ctrans("Breadcrumb label") }}</label><HelpTip :text="seoFieldHelp('breadcrumb_label')!" /></span><FilledBadge :value="form.breadcrumb_label" /></span>
                            <span class="tabular-nums" :class="form.breadcrumb_label.length > 40 ? 'text-red-600' : 'text-gray-400'">{{ form.breadcrumb_label.length }} / 40</span>
                        </div>
                        <InputText id="breadcrumb-label" v-model="form.breadcrumb_label" fluid :disabled="!canEdit" :invalid="!!errors.breadcrumb_label" />
                        <p v-if="errors.breadcrumb_label" class="mt-1 text-xs text-red-600">{{ errors.breadcrumb_label }}</p>
                    </div>
                </div>
            </div>
            <div v-else class="text-sm text-gray-500">{{ ctrans("This product has no webpage in the shop") }}</div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-md">
            <header class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-900">3 · {{ ctrans("Price") }}</h3>
                <span class="text-xs text-gray-500">{{ ctrans("Master price or a :shop shop price", { shop: shop.shop.code }) }}</span>
            </header>
            <div class="rounded-md border border-gray-200 p-3">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <label for="shop-price" class="text-sm font-medium text-gray-800">{{ ctrans("Price") }}</label>
                        <HelpTip :text="seoFieldHelp('price')!" class="-ml-1" />
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="form.price.mode === 'follow' ? 'bg-gray-100 text-gray-600' : 'bg-amber-50 text-amber-700'"
                        >{{ form.price.mode === "follow" ? ctrans("Follows master") : ctrans("Customised in this shop") }}</span>
                    </div>
                    <!-- <SegmentedToggle v-if="canEdit && master.has_master" v-model="form.price.mode" :options="followOptions" :ariaLabel="ctrans('Price')" /> -->
                </div>
                <MasterValue :value="formatMoney(master.currency_code, master.price)" />
                <p v-if="form.price.mode === 'follow' && shop.product.currency_code !== master.currency_code" class="mt-2 text-xs text-gray-500">
                    {{ ctrans("The shop sells it at :price, converted to :currency", { price: String(formatMoney(shop.product.currency_code, shop.product.price) ?? "-"), currency: shop.product.currency_code }) }}
                </p>
                <div v-if="form.price.mode === 'customise'" class="mt-2 max-w-xs">
                    <InputNumber
                        v-model="form.price.value"
                        inputId="shop-price"
                        :prefix="`${shop.product.currency_code} `"
                        :min="0"
                        :minFractionDigits="2"
                        :maxFractionDigits="2"
                        fluid
                        :disabled="!canEdit"
                        :invalid="!!errors.price"
                    />
                    <p v-if="errors.price" class="mt-1 text-xs text-red-600">{{ errors.price }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-md">
            <header class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-900">4 · {{ ctrans("Product information") }}</h3>
                <span class="text-xs text-gray-500">{{ master.has_master ? ctrans("Set by the master · read only") : ctrans("Read only") }}</span>
            </header>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div
                    v-for="info in [
                        { label: ctrans('Net weight (g)'), value: master.marketing_weight },
                        { label: ctrans('Shipping weight (g)'), value: master.gross_weight },
                        { label: ctrans('Dimensions (L × W × H)'), value: master.dimensions },
                        { label: ctrans('Material / ingredients'), value: master.ingredients },
                        { label: ctrans('Barcode'), value: master.barcode },
                        { label: ctrans('Origin country'), value: master.origin_country },
                    ]"
                    :key="info.label"
                    class="rounded-md border border-gray-200 p-3"
                >
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-gray-800">{{ info.label }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">{{ setByLabel }}</span>
                    </div>
                    <MasterValue :value="info.value" />
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-md">
            <header class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-900">5 · {{ ctrans("Indexing") }}<HelpTip :text="seoFieldHelp('indexing')!" /></h3>
                <span class="text-xs text-gray-500">{{ ctrans("Search engines") }}</span>
            </header>
            <div v-if="shop.webpage" class="space-y-3">
                <div class="flex items-center gap-4 text-sm font-medium text-gray-700">
                    <ToggleSwitch v-model="form.index_page" inputId="index-page" :disabled="!canEdit" />
                    <label for="index-page">{{ ctrans("Index page") }}</label>
                </div>
                <div class="flex items-center gap-4 text-sm font-medium text-gray-700">
                    <ToggleSwitch v-model="form.follow_link" inputId="follow-link" :disabled="!canEdit" />
                    <label for="follow-link">{{ ctrans("Follow link") }}</label>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-md">
            <header class="mb-3 flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-900">6 · {{ ctrans("Shared information") }}</h3>
                <span class="text-xs text-gray-500">{{ ctrans("Same for every product of the family · read only") }}</span>
            </header>
            <div class="space-y-3">
                <div v-for="info in sharedInformation" :key="info.key" class="rounded-md border border-gray-200 p-3">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-gray-800">{{ info.label }}</span>
                        <span v-if="info.shared" class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">{{ sourceLabel(info.shared.source) }}</span>
                    </div>
                    <MasterValue
                        :value="info.shared?.value ?? null"
                        isHtml
                        :origin="info.shared?.source === 'website' ? ctrans('Website') : ctrans('Master')"
                        :emptyLabel="ctrans('Not set by the master nor on the website')"
                    />
                </div>
            </div>
        </section>
    </div>
</template>
