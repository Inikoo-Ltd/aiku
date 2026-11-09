<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowLeft, faCheck, faImage, faInfoCircle } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { InputNumber, InputText, Select, Textarea } from "primevue"
import Image from "@common/Components/Image.vue"
import RichTextField from "@/Components/Catalogue/ProductSetting/RichTextField.vue"
import ProductChecklistCard from "@/Components/Catalogue/ProductSetting/ProductChecklistCard.vue"
import ImageCropSquare from "@/Components/Forms/Fields/ImageCropSquare.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

type Dimensions = { l: number | null, w: number | null, h: number | null }

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    canEdit: boolean
    back_route: routeType
    back_label: string
    update_route: routeType
    images_route: routeType
    product: {
        code: string
        name: string
        image: Record<string, string> | null
        price: number | null
        currency_code: string
        description: string
        description_extra: string
        barcode: string
    }
    seo: {
        meta_description: string
        seo_image: { original: string } | null
        seo_image_url: string | null
        seo_image_alt: string | null
    }
    information: {
        trade_unit: { code: string, route: routeType } | null
        number_trade_units: number
        marketing_weight: number | null
        gross_weight: number | null
        dimensions_cm: Dimensions
        marketing_ingredients: string
        origin_country_id: number | null
    }
    countries: Array<{ value: number, label: string }>
    shared: { shipping: string, return_policy: string, number_products: number, family_name: string } | null
    checklist: { complete: number, total: number, items: Array<{ key: string, label: string, done: boolean }>, missing: string[] }
}>()

type EditForm = {
    code: string
    name: string
    price: number | null
    description: string
    description_extra: string
    barcode: string
    meta_description: string
    marketing_weight: number | null
    gross_weight: number | null
    dimensions_cm: Dimensions
    marketing_ingredients: string
    origin_country_id: number | null
    shared: { shipping: string, return_policy: string }
}

const formFromProps = (): EditForm => ({
    code: props.product.code,
    name: props.product.name,
    price: props.product.price,
    description: props.product.description,
    description_extra: props.product.description_extra,
    barcode: props.product.barcode,
    meta_description: props.seo.meta_description,
    marketing_weight: props.information.marketing_weight,
    gross_weight: props.information.gross_weight,
    dimensions_cm: { ...props.information.dimensions_cm },
    marketing_ingredients: props.information.marketing_ingredients,
    origin_country_id: props.information.origin_country_id,
    shared: { shipping: props.shared?.shipping ?? "", return_policy: props.shared?.return_policy ?? "" },
})

const form = ref<EditForm>(formFromProps())
const savedForm = ref<EditForm>(formFromProps())
const serverErrors = ref<Record<string, string>>({})
const isSaving = ref(false)
const saveStatus = ref<{ isSaved: boolean, message: string } | null>(null)
const formKey = ref(0)

const META_DESCRIPTION_MIN_LENGTH = 120
const META_DESCRIPTION_MAX_LENGTH = 155
const metaDescriptionLength = computed(() => form.value.meta_description.length)
const isMetaDescriptionLengthOff = computed(() => metaDescriptionLength.value > 0
    && (metaDescriptionLength.value < META_DESCRIPTION_MIN_LENGTH || metaDescriptionLength.value > META_DESCRIPTION_MAX_LENGTH)
)

const shareImageFromProps = () => ({ seo_image: props.seo.seo_image as { original: string } | File | null, seo_image_url: props.seo.seo_image_url, seo_image_alt: props.seo.seo_image_alt, errors: {} as Record<string, string | null> })
const shareImageForm = ref(shareImageFromProps())
const shareImageField = {
    hasOther: [
        {
            name: "seo_image_alt",
            type: "alt" as const,
            label: ctrans("Share image alt text"),
            placeholder: ctrans("Describe the image"),
            information: ctrans("Alternative text of the share image, used by screen readers and shown when the image cannot be loaded. Shops that follow the master get it translated."),
        },
        {
            name: "seo_image_url",
            type: "url" as const,
            label: ctrans("Or paste an image link"),
            placeholder: "https://",
            information: ctrans("Use an externally hosted image as the share image instead of uploading one. Uploading a new image clears it."),
        },
    ],
    options: { minAspectRatio: 1, maxAspectRatio: 3 },
}

const textOf = (html: string) => html.replace(/<[^>]*>/g, " ").replace(/&nbsp;/g, " ").trim()
const wordCount = (html: string) => (textOf(html) ? textOf(html).split(/\s+/).length : 0)
const isPositive = (value: number | null) => value !== null && Number(value) > 0

const clientErrors = computed(() => {
    const errors: Record<string, string> = {}
    const value = form.value

    if (!value.code.trim()) {
        errors.code = ctrans("The product code is required")
    } else if (!/^[A-Za-z0-9-]+$/.test(value.code)) {
        errors.code = ctrans("Letters, numbers and hyphens only")
    }
    if (!value.name.trim()) {
        errors.name = ctrans("The product name is required")
    }
    if (!isPositive(value.price)) {
        errors.price = ctrans("The price must be more than 0")
    }
    if (value.barcode && !/^(\d{8}|\d{12}|\d{13})$/.test(value.barcode)) {
        errors.barcode = ctrans("A barcode has 8, 12 or 13 digits")
    }

    return errors
})

const errorOf = (field: string) => clientErrors.value[field] ?? serverErrors.value[field] ?? serverErrors.value[`${field}.l`] ?? null

const isFilled = computed<Record<string, boolean>>(() => {
    const value = form.value

    return {
        description: textOf(value.description) !== "",
        description_extra: textOf(value.description_extra) !== "",
        price: isPositive(value.price),
        marketing_weight: isPositive(value.marketing_weight),
        gross_weight: isPositive(value.gross_weight),
        marketing_dimensions: (["l", "w", "h"] as const).some((side) => isPositive(value.dimensions_cm[side])),
        marketing_ingredients: value.marketing_ingredients.trim() !== "",
        barcode: value.barcode.trim() !== "",
        origin_country: value.origin_country_id !== null,
        meta_description: value.meta_description.trim() !== "",
        ...(shareImageForm.value.seo_image instanceof File || shareImageForm.value.seo_image_url ? { seo_image: true } : {}),
    }
})

const liveChecklist = computed(() => {
    const items = props.checklist.items.map((item) => ({ ...item, done: isFilled.value[item.key] ?? item.done }))
    const missing = items.filter((item) => !item.done).map((item) => item.label)

    return { complete: items.length - missing.length, total: items.length, items, missing }
})

const changedPayload = computed(() => {
    const payload: Record<string, unknown> = {}
    const value = form.value
    const saved = savedForm.value
    const isChanged = (key: keyof EditForm) => JSON.stringify(value[key]) !== JSON.stringify(saved[key])

    for (const key of ["code", "name", "price", "description", "description_extra", "barcode", "meta_description"] as const) {
        if (isChanged(key)) {
            payload[key] = value[key]
        }
    }
    if (props.shared && isChanged("shared")) {
        payload.shared = value.shared
    }
    if (shareImageForm.value.seo_image instanceof File) {
        payload.seo_image = shareImageForm.value.seo_image
    }
    if ((shareImageForm.value.seo_image_url ?? null) !== props.seo.seo_image_url) {
        payload.seo_image_url = shareImageForm.value.seo_image_url
    }
    if ((shareImageForm.value.seo_image_alt || null) !== props.seo.seo_image_alt) {
        payload.seo_image_alt = shareImageForm.value.seo_image_alt
    }

    return payload
})

const changeCount = computed(() => Object.keys(changedPayload.value).length)
const canSave = computed(() => props.canEdit && changeCount.value > 0 && !isSaving.value)

const save = () => {
    if (!canSave.value) {
        return
    }

    router.post(route(props.update_route.name, props.update_route.parameters), { ...changedPayload.value, _method: "patch" }, {
        preserveScroll: true,
        onStart: () => {
            isSaving.value = true
            serverErrors.value = {}
            saveStatus.value = null
        },
        onSuccess: () => {
            form.value = formFromProps()
            savedForm.value = formFromProps()
            shareImageForm.value = shareImageFromProps()
            formKey.value++
            saveStatus.value = { isSaved: true, message: ctrans("Saved") }
        },
        onError: (errors) => {
            serverErrors.value = errors as Record<string, string>
            saveStatus.value = { isSaved: false, message: Object.values(errors)[0] ?? ctrans("The product could not be updated") }
        },
        onFinish: () => (isSaving.value = false),
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-4 p-4">      

        <section class="rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="mb-3 text-lg font-semibold text-gray-900">{{ ctrans("Edit product") }} · {{ product.name }}</h2>
            <div class="flex flex-wrap gap-4">
                <div class="w-36 shrink-0">
                    <div class="mb-1 text-sm font-medium text-gray-700">{{ ctrans("Product image") }}</div>
                    <div class="flex aspect-square items-center justify-center overflow-hidden rounded-md bg-gray-100 text-xs text-gray-500">
                        <Image v-if="product.image" :src="product.image" :alt="product.name" class="h-full w-full object-cover" />
                        <span v-else>{{ ctrans("No image") }}</span>
                    </div>
                    <Link :href="route(images_route.name, images_route.parameters)" class="mt-2 block">
                        <Button type="tertiary" size="xs" :label="product.image ? ctrans('Change image') : ctrans('Upload image')">
                            <template #icon>
                                <FontAwesomeIcon :icon="faImage" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </Link>
                </div>
                <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label for="product-code" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Product code") }} <span class="text-red-500">*</span></label>
                        <InputText id="product-code" v-model="form.code" class="font-mono" fluid disabled :invalid="!!errorOf('code')" />
                        <p class="mt-1 text-xs" :class="errorOf('code') ? 'text-red-600' : 'text-gray-500'">
                            {{ errorOf("code") ?? ctrans("Letters, numbers and hyphens, e.g. :code", { code: product.code }) }}
                        </p>
                    </div>
                    <div>
                        <label for="product-name" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Product name") }} <span class="text-red-500">*</span></label>
                        <InputText id="product-name" v-model="form.name" fluid :disabled="!canEdit" :invalid="!!errorOf('name')" />
                        <p v-if="errorOf('name')" class="mt-1 text-xs text-red-600">{{ errorOf("name") }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div :key="formKey" class="space-y-4">
                <section class="rounded-lg border border-gray-200 bg-white p-4">
                    <header class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900">1 · {{ ctrans("Price") }}</h3>
                        <span class="text-xs text-gray-500">{{ ctrans("Master price, before shop-specific pricing") }}</span>
                    </header>
                    <label for="product-price" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Price (:currency)", { currency: product.currency_code }) }}</label>
                    <div class="max-w-xs">
                        <InputNumber v-model="form.price" inputId="product-price" :prefix="`${product.currency_code} `" :min="0" :minFractionDigits="2" :maxFractionDigits="2" fluid disabled :invalid="!!errorOf('price')" />
                    </div>
                    <p v-if="errorOf('price')" class="mt-1 text-xs text-red-600">{{ errorOf("price") }}</p>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-4">
                    <header class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900">2 · {{ ctrans("Product content") }}</h3>
                        <span class="text-xs text-gray-500">{{ ctrans("Shown on the product page") }}</span>
                    </header>
                    <div class="space-y-3">
                        <div v-for="field in [{ key: 'description' as const, label: ctrans('Description') }, { key: 'description_extra' as const, label: ctrans('Extra description') }]" :key="field.key">
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="font-medium text-gray-700">{{ field.label }}</span>
                                <span class="text-xs text-gray-500">{{ ctrans(":count words", { count: String(wordCount(form[field.key])) }) }}</span>
                            </div>
                            <RichTextField v-model="form[field.key]" :disabled="!canEdit" :isError="!!errorOf(field.key)" />
                            <p v-if="errorOf(field.key)" class="mt-1 text-xs text-red-600">{{ errorOf(field.key) }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-4">
                    <header class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900">3 · {{ ctrans("Product information") }}</h3>
                        <span class="text-xs text-gray-500">{{ ctrans("Specific to this product") }}</span>
                    </header>

                    <p v-if="information.trade_unit" class="mb-3 flex items-start gap-2 rounded bg-gray-50 px-3 py-2 text-xs text-gray-600">
                        <FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                        <span>
                            {{ ctrans("Weights, dimensions, material and origin come from the trade unit") }}
                            <Link :href="route(information.trade_unit.route.name, information.trade_unit.route.parameters)" class="font-medium text-[var(--app-accent)] hover:underline">{{ information.trade_unit.code }}</Link>,
                            {{ ctrans("shared by the dropshipping and wholesale products made of it: edit them there.") }}
                        </span>
                    </p>
                    <p v-else class="mb-3 flex items-start gap-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5" fixed-width aria-hidden="true" />
                        {{ ctrans("This product is made of :count trade units: weights, dimensions, material and origin are edited in each trade unit.", { count: String(information.number_trade_units) }) }}
                    </p>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label for="net-weight" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Net weight (g)") }}</label>
                            <InputNumber v-model="form.marketing_weight" inputId="net-weight" :min="0" :useGrouping="false" fluid disabled :invalid="!!errorOf('marketing_weight')" />
                            <p class="mt-1 text-xs" :class="errorOf('marketing_weight') ? 'text-red-600' : 'text-gray-500'">{{ errorOf("marketing_weight") ?? ctrans("Weight of the product itself") }}</p>
                        </div>
                        <div>
                            <label for="shipping-weight" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Shipping weight (g)") }}</label>
                            <InputNumber v-model="form.gross_weight" inputId="shipping-weight" :min="0" :useGrouping="false" fluid disabled :invalid="!!errorOf('gross_weight')" />
                            <p class="mt-1 text-xs" :class="errorOf('gross_weight') ? 'text-red-600' : 'text-gray-500'">{{ errorOf("gross_weight") ?? ctrans("Weight including packaging") }}</p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="mb-1 text-sm font-medium text-gray-700">{{ ctrans("Dimensions (cm)") }}</div>
                        <div class="grid grid-cols-3 gap-3">
                            <div v-for="side in [{ key: 'l' as const, label: ctrans('Length') }, { key: 'w' as const, label: ctrans('Width') }, { key: 'h' as const, label: ctrans('Height') }]" :key="side.key">
                                <label :for="`dimension-${side.key}`" class="mb-1 block text-xs text-gray-500">{{ side.label }}</label>
                                <InputNumber v-model="form.dimensions_cm[side.key]" :inputId="`dimension-${side.key}`" :min="1" :maxFractionDigits="2" :useGrouping="false" fluid disabled :invalid="!!errorOf('dimensions_cm')" />
                            </div>
                        </div>
                        <p v-if="errorOf('dimensions_cm')" class="mt-1 text-xs text-red-600">{{ errorOf("dimensions_cm") }}</p>
                    </div>

                    <div class="mt-3">
                        <label for="material" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Material / ingredients") }}</label>
                        <Textarea
                            id="material"
                            v-model="form.marketing_ingredients"
                            :rows="3"
                            autoResize
                            fluid
                            :placeholder="ctrans('e.g. Glass reservoir, ABS plastic base')"
                            disabled
                        />
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label for="barcode" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Barcode (EAN)") }}</label>
                            <InputText id="barcode" v-model="form.barcode" :placeholder="ctrans('8, 12 or 13 digits')" fluid disabled :invalid="!!errorOf('barcode')" />
                            <p class="mt-1 text-xs" :class="errorOf('barcode') ? 'text-red-600' : 'text-gray-500'">
                                {{ errorOf("barcode") ?? ctrans("8, 12 or 13 digits, and a free barcode of the barcode pool (not used by another product or trade unit)") }}
                            </p>
                        </div>
                        <div>
                            <label for="origin-country" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Origin country") }}</label>
                            <Select
                                v-model="form.origin_country_id"
                                inputId="origin-country"
                                :options="countries"
                                optionLabel="label"
                                optionValue="value"
                                filter
                                showClear
                                fluid
                                :placeholder="ctrans('Not set')"
                                disabled
                            />
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-4">
                    <header class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900">4 · {{ ctrans("SEO") }}</h3>
                        <span class="text-xs text-gray-500">{{ ctrans("Shown in search results and social shares") }}</span>
                    </header>
                    <div class="space-y-3">
                        <div>
                            <div class="mb-1 flex items-baseline justify-between">
                                <label for="meta-description" class="text-sm font-medium text-gray-700">{{ ctrans("Meta description") }}</label>
                                <span class="text-xs tabular-nums" :class="isMetaDescriptionLengthOff ? 'font-medium text-amber-600' : 'text-gray-500'">
                                    {{ ctrans(":count / :min-:max characters", { count: String(metaDescriptionLength), min: String(META_DESCRIPTION_MIN_LENGTH), max: String(META_DESCRIPTION_MAX_LENGTH) }) }}
                                </span>
                            </div>
                            <Textarea id="meta-description" v-model="form.meta_description" :rows="3" autoResize fluid :disabled="!canEdit" :invalid="!!errorOf('meta_description')" />
                            <p v-if="errorOf('meta_description')" class="mt-1 text-xs text-red-600">{{ errorOf("meta_description") }}</p>
                        </div>
                        <div>
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ ctrans("Share image") }}</div>
                            <p class="mb-2 text-xs text-gray-500">{{ ctrans("The preview image (og:image) shown when the page is shared on social media. Crop ratio from 1:1 to 3:1, served scaled down to fit 1200x1200 pixels.") }}</p>
                            <ImageCropSquare v-if="canEdit" :form="shareImageForm" fieldName="seo_image" :fieldData="shareImageField" />
                            <Image v-else-if="seo.seo_image" :src="seo.seo_image" :alt="ctrans('Share image')" class="h-40 rounded-md object-cover" />
                            <p v-if="errorOf('seo_image') ?? errorOf('seo_image_url') ?? errorOf('seo_image_alt')" class="mt-1 text-xs text-red-600">{{ errorOf("seo_image") ?? errorOf("seo_image_url") ?? errorOf("seo_image_alt") }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="shared" class="rounded-lg border border-gray-200 bg-white p-4">
                    <header class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-900">5 · {{ ctrans("Shared information") }}</h3>
                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                            {{ ctrans("Applies to all :count products of :family", { count: String(shared.number_products), family: shared.family_name }) }}
                        </span>
                    </header>
                    <div class="space-y-3">
                        <div>
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ ctrans("Shipping information") }}</div>
                            <RichTextField v-model="form.shared.shipping" :disabled="!canEdit" />
                        </div>
                        <div>
                            <div class="mb-1 text-sm font-medium text-gray-700">{{ ctrans("Return policy") }}</div>
                            <RichTextField v-model="form.shared.return_policy" :disabled="!canEdit" />
                        </div>
                    </div>
                </section>
            </div>

            <aside class="h-fit space-y-3 lg:sticky lg:top-4">
                <ProductChecklistCard :checklist="liveChecklist" />
                <Button
                    type="tertiary"
                    full
                    :label="changeCount ? ctrans('Save (:count)', { count: String(changeCount) }) : ctrans('Save')"
                    :disabled="!canSave"
                    :loading="isSaving"
                    @click="save"
                >
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <p v-if="saveStatus" class="text-xs" :class="saveStatus.isSaved ? 'text-green-600' : 'text-red-600'">{{ saveStatus.message }}</p>
            </aside>
        </div>
    </div>
</template>
