<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3"
import { computed, nextTick, onMounted, ref, watch } from "vue"
import { cloneDeep, isEqual } from "lodash-es"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Select from "primevue/select"
import MultiSelect from "primevue/multiselect"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faPlus, faCheckCircle, faTrashAlt, faExclamationTriangle } from "@fal"
import { notify } from "@kyvg/vue3-notification"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TextEditor from "@/Components/Forms/Fields/TextEditor.vue"
import InputFaqMaster from "@/Components/Forms/Fields/InputFaqMaster.vue"
import ImageCropSquare from "@/Components/Forms/Fields/ImageCropSquare.vue"
import { getComponent } from "@/Composables/Listing/FieldFormList"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

type FieldDefinition = { type: string, label: string, value: unknown, full?: boolean, options?: unknown, [key: string]: unknown }

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    warning: string | null
    masterFamily: {
        code: string
        name: string
        description_title: string | null
        description: string | null
        description_extra: string | null
        master_department_id: number | null
        master_sub_department_id: number | null
        master_collection_ids: number[]
        trade_unit_family: string | null
        faq: { question: string, answer: string }[] | null
        meta_description: string | null
        seo_image: { original: string } | null
        seo_image_url: string | null
        seo_image_alt: string | null
        vol_gr_offer: { item_quantity: number, percentage_off: number }
    }
    departments: { id: number, name: string }[]
    subDepartments: { id: number, name: string, master_department_id: number | null }[]
    masterCollections: { id: number, code: string, name: string }[]
    isGoldRewardEligible: boolean
    faqField: { master_product_category_id: number }
    extraSections: { label: string, fields: Record<string, FieldDefinition> }[]
    completeness: {
        score: number
        complete: number
        total: number
        items: { key: string, label: string, is_complete: boolean }[]
    }
    shopsCount: number
    familiesRoute: routeType
    updateRoute: routeType
}>()

const DESCRIPTION_WORD_LIMIT = 100
const DESCRIPTION_EXTRA_WORD_LIMIT = 250
const META_DESCRIPTION_MIN_LENGTH = 120
const META_DESCRIPTION_MAX_LENGTH = 155
const editorToggle = ["bold", "italic", "fontSize", "underline", "bulletList", "undo", "redo", "highlight", "color", "clear"]
const parentFields = ["master_department_id", "master_sub_department_id"]

const extraFieldValues = Object.fromEntries(
    props.extraSections.flatMap(section => Object.entries(section.fields).map(([fieldName, field]) => [fieldName, cloneDeep(field.value)]))
)

const form = useForm<Record<string, any>>({
    name: props.masterFamily.name,
    description_title: props.masterFamily.description_title,
    description: props.masterFamily.description,
    description_extra: props.masterFamily.description_extra,
    master_department_id: props.masterFamily.master_department_id,
    master_sub_department_id: props.masterFamily.master_sub_department_id,
    master_collection_ids: [...props.masterFamily.master_collection_ids],
    faq: cloneDeep(props.masterFamily.faq ?? []),
    meta_description: props.masterFamily.meta_description,
    seo_image: props.masterFamily.seo_image,
    seo_image_url: props.masterFamily.seo_image_url,
    seo_image_alt: props.masterFamily.seo_image_alt,
    ...(props.isGoldRewardEligible ? { vol_gr_offer: cloneDeep(props.masterFamily.vol_gr_offer) } : {}),
    ...extraFieldValues,
})

const savedValues = ref<Record<string, unknown> | null>(null)

onMounted(async () => {
    await nextTick()
    savedValues.value = cloneDeep(form.data())
})

const changedFields = computed(() => savedValues.value
    ? Object.keys(form.data()).filter(fieldName => !isEqual(form[fieldName], savedValues.value?.[fieldName]))
    : []
)

const subDepartmentOptions = computed(() => props.subDepartments.filter(subDepartment => subDepartment.master_department_id === form.master_department_id))

watch(() => form.master_department_id, () => {
    if (!subDepartmentOptions.value.some(subDepartment => subDepartment.id === form.master_sub_department_id)) {
        form.master_sub_department_id = null
    }
})

const parentError = computed(() => form.errors.master_department_or_master_sub_department_id
    ?? form.errors.master_department_id
    ?? form.errors.master_sub_department_id
)

const wordCount = (html?: string | null) => (html ?? "")
    .replace(/<[^>]*>/g, " ")
    .replace(/&nbsp;/g, " ")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .length

const volGrOfferField = (key: "item_quantity" | "percentage_off") => computed({
    get: () => form.vol_gr_offer?.[key] ?? null,
    set: (value: number | null) => {
        form.vol_gr_offer = { item_quantity: 0, percentage_off: 0, ...form.vol_gr_offer, [key]: value }
    },
})
const triggerQuantity = volGrOfferField("item_quantity")
const discountPercentage = volGrOfferField("percentage_off")
const hasVolGrOffer = computed(() => !!(form.vol_gr_offer?.item_quantity && form.vol_gr_offer?.percentage_off))

const sectionKeys = computed(() => [
    "category",
    "content",
    "seo",
    ...(props.isGoldRewardEligible ? ["gold_reward"] : []),
    "faq",
    ...props.extraSections.map(section => section.label),
])
const sectionNumber = (key: string) => sectionKeys.value.indexOf(key) + 1

const metaDescriptionLength = computed(() => form.meta_description?.length ?? 0)
const isMetaDescriptionLengthOff = computed(() => metaDescriptionLength.value > 0
    && (metaDescriptionLength.value < META_DESCRIPTION_MIN_LENGTH || metaDescriptionLength.value > META_DESCRIPTION_MAX_LENGTH)
)

const shareImageField = {
    hasOther: [
        {
            name: "seo_image_alt",
            type: "alt",
            label: ctrans("Share image alt text"),
            placeholder: ctrans("Describe the image"),
            information: ctrans("Alternative text of the share image, used by screen readers and shown when the image cannot be loaded. Shops that follow the master get it translated."),
        },
        {
            name: "seo_image_url",
            type: "url",
            label: ctrans("Or paste an image link"),
            placeholder: "https://",
            information: ctrans("Use an externally hosted image as the share image instead of uploading one. Uploading a new image clears it."),
        },
    ],
    options: { minAspectRatio: 1, maxAspectRatio: 3 },
}

const faqInput = ref<InstanceType<typeof InputFaqMaster> | null>(null)

const save = () => {
    const fieldsToSave = changedFields.value

    form
        .transform((data) => {
            const payload: Record<string, unknown> = { _method: "patch" }

            fieldsToSave
                .filter(fieldName => !parentFields.includes(fieldName))
                .forEach(fieldName => payload[fieldName] = data[fieldName])

            if (fieldsToSave.some(fieldName => parentFields.includes(fieldName))) {
                payload.master_department_or_master_sub_department_id = data.master_sub_department_id ?? data.master_department_id
            }

            return payload
        })
        .post(route(props.updateRoute.name, props.updateRoute.parameters), {
            preserveScroll: true,
            onSuccess: () => {
                savedValues.value = cloneDeep(form.data())
                notify({ title: ctrans("Saved"), text: ctrans("Master family updated"), type: "success" })
            },
            onError: (errors) => {
                notify({
                    title: ctrans("Not saved"),
                    text: errors.error_in_models ?? ctrans("Please check the highlighted fields"),
                    type: "error",
                })
            },
        })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-4 p-4">
        <div v-if="shopsCount" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-green-800">
                <FontAwesomeIcon :icon="faCheckCircle" class="text-green-600" fixed-width aria-hidden="true" />
                {{ ctrans(":count shop families follow this master family", { count: shopsCount }) }}
            </div>
            <Link :href="route(familiesRoute.name, familiesRoute.parameters)">
                <Button type="tertiary" size="xs" :label="ctrans('View Families in Shop')" />
            </Link>
        </div>

        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="space-y-4">
                <!-- Family and category -->
                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <header class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-semibold text-gray-900">{{ sectionNumber("category") }} · {{ ctrans("Family and category") }}</h2>
                        <span v-if="shopsCount" class="text-xs text-gray-500">{{ ctrans("Shared by all :count shops", { count: shopsCount }) }}</span>
                    </header>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans("Family code") }}</label>
                            <InputText :modelValue="masterFamily.code" class="w-full" disabled />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans("Department") }} <span class="text-red-500">*</span></label>
                            <Select v-model="form.master_department_id" :options="departments" optionLabel="name" optionValue="id" filter class="w-full" :placeholder="ctrans('Select department')" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans("Sub-department") }}</label>
                            <Select v-model="form.master_sub_department_id" :options="subDepartmentOptions" optionLabel="name" optionValue="id" filter showClear class="w-full" :placeholder="ctrans('None')" :disabled="!subDepartmentOptions.length" />
                        </div>
                    </div>
                    <p v-if="parentError" class="mt-2 text-sm text-red-600">{{ parentError }}</p>

                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans("Collections") }}</label>
                        <MultiSelect v-model="form.master_collection_ids" :options="masterCollections" optionLabel="name" optionValue="id" display="chip" filter
                            :filterFields="['name', 'code']" class="w-full" :placeholder="ctrans('Not in any collection')" />
                        <p class="mt-1 text-xs text-gray-500">{{ ctrans("A family can be in several collections. The shop collections that follow the master get it too.") }}</p>
                        <p v-if="form.errors.master_collection_ids" class="mt-1 text-sm text-red-600">{{ form.errors.master_collection_ids }}</p>
                    </div>

                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans("Trade unit family") }}</label>
                        <InputText :modelValue="masterFamily.trade_unit_family ?? ctrans('Not linked')" class="w-full md:w-2/3" disabled />
                    </div>
                </section>

                <!-- Master content -->
                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <header class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-semibold text-gray-900">{{ sectionNumber("content") }} · {{ ctrans("Master content") }}</h2>
                        <span class="text-xs text-gray-500">{{ ctrans("Copied to the shop families that follow the master") }}</span>
                    </header>

                    <p v-if="warning" class="mb-4 flex gap-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-600" fixed-width aria-hidden="true" />
                        {{ warning }}
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans("Name") }}</label>
                            <InputText v-model="form.name" class="w-full" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <div class="mb-1 flex items-baseline justify-between">
                                <label class="text-xs font-medium text-gray-700">{{ ctrans("Description title") }}</label>
                                <span class="text-xs tabular-nums text-gray-400">{{ ctrans(":count characters", { count: form.description_title?.length ?? 0 }) }}</span>
                            </div>
                            <InputText v-model="form.description_title" class="w-full" />
                            <p v-if="form.errors.description_title" class="mt-1 text-sm text-red-600">{{ form.errors.description_title }}</p>
                        </div>

                        <div>
                            <div class="mb-1 flex items-baseline justify-between">
                                <label class="text-xs font-medium text-gray-700">{{ ctrans("Top description") }}</label>
                                <span class="text-xs tabular-nums" :class="wordCount(form.description) > DESCRIPTION_WORD_LIMIT ? 'font-medium text-red-600' : 'text-gray-400'">
                                    {{ ctrans(":count / :limit words", { count: wordCount(form.description), limit: DESCRIPTION_WORD_LIMIT }) }}
                                </span>
                            </div>
                            <TextEditor :form="form" fieldName="description" :fieldData="{ toggle: editorToggle }" />
                        </div>

                        <div>
                            <div class="mb-1 flex items-baseline justify-between">
                                <label class="text-xs font-medium text-gray-700">{{ ctrans("Extra description") }}</label>
                                <span class="text-xs tabular-nums" :class="wordCount(form.description_extra) > DESCRIPTION_EXTRA_WORD_LIMIT ? 'font-medium text-red-600' : 'text-gray-400'">
                                    {{ ctrans(":count / :limit words", { count: wordCount(form.description_extra), limit: DESCRIPTION_EXTRA_WORD_LIMIT }) }}
                                </span>
                            </div>
                            <TextEditor :form="form" fieldName="description_extra" :fieldData="{ toggle: editorToggle }" />
                        </div>
                    </div>
                </section>

                <!-- SEO -->
                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <header class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-semibold text-gray-900">{{ sectionNumber("seo") }} · {{ ctrans("SEO") }}</h2>
                    </header>

                    <div class="space-y-4">
                        <div>
                            <div class="mb-1 flex items-baseline justify-between">
                                <label for="meta_description" class="text-xs font-medium text-gray-700">{{ ctrans("Meta description") }}</label>
                                <span class="text-xs tabular-nums" :class="isMetaDescriptionLengthOff ? 'font-medium text-amber-600' : 'text-gray-400'">
                                    {{ ctrans(":count / :min-:max characters", { count: metaDescriptionLength, min: META_DESCRIPTION_MIN_LENGTH, max: META_DESCRIPTION_MAX_LENGTH }) }}
                                </span>
                            </div>
                            <Textarea id="meta_description" v-model="form.meta_description" rows="3" autoResize class="w-full" />
                            <p v-if="form.errors.meta_description" class="mt-1 text-sm text-red-600">{{ form.errors.meta_description }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans("Share image") }}</label>
                            <p class="mb-2 text-xs text-gray-500">{{ ctrans("The preview image (og:image) shown when the page is shared on social media. Crop ratio from 1:1 to 3:1, served scaled down to fit 1200x1200 pixels.") }}</p>
                            <ImageCropSquare :form="form" fieldName="seo_image" :fieldData="shareImageField" />
                        </div>
                    </div>
                </section>

                <!-- Gold Reward offer -->
                <section v-if="isGoldRewardEligible" class="rounded-lg border border-gray-200 bg-white p-5">
                    <header class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-semibold text-gray-900">{{ sectionNumber("gold_reward") }} · {{ ctrans("Gold Reward offer") }}</h2>
                        <span class="text-xs text-gray-500">{{ ctrans("Quantity and discount used by every shop") }}</span>
                    </header>

                    <div class="grid items-end gap-4 md:grid-cols-[1fr_1fr_auto]">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans("Trigger quantity (Qty)") }}</label>
                            <InputNumber v-model="triggerQuantity" :min="0" class="w-full" inputClass="w-full" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">{{ ctrans("Discount (%)") }}</label>
                            <InputNumber v-model="discountPercentage" :min="0" :max="100" :maxFractionDigits="2" class="w-full" inputClass="w-full" />
                        </div>
                        <Button v-if="hasVolGrOffer" type="tertiary" :label="ctrans('Remove offer')" @click="form.vol_gr_offer = null">
                            <template #icon>
                                <FontAwesomeIcon :icon="faTrashAlt" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>

                    <p v-if="hasVolGrOffer" class="mt-2 text-xs text-gray-500">
                        {{ ctrans("Customers get :percentage% off when they order :quantity or more.", { percentage: form.vol_gr_offer.percentage_off, quantity: form.vol_gr_offer.item_quantity }) }}
                    </p>
                    <p v-else-if="!form.vol_gr_offer" class="mt-2 text-xs text-amber-700">{{ ctrans("The offer will be removed from every shop when you save.") }}</p>
                    <p v-if="form.errors['vol_gr_offer.item_quantity'] || form.errors['vol_gr_offer.percentage_off']" class="mt-1 text-sm text-red-600">
                        {{ form.errors["vol_gr_offer.item_quantity"] }} {{ form.errors["vol_gr_offer.percentage_off"] }}
                    </p>
                </section>

                <!-- FAQ -->
                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold text-gray-900">{{ sectionNumber("faq") }} · {{ ctrans("FAQ") }}</h2>
                        <Button type="tertiary" size="xs" :icon="faPlus" :label="ctrans('Add question')" @click="faqInput?.addFaq()" />
                    </header>

                    <InputFaqMaster ref="faqInput" :form="form" fieldName="faq" :fieldData="faqField" hideAddButton />
                </section>

                <!-- Shop specific sections -->
                <section v-for="section in extraSections" :key="section.label" class="rounded-lg border border-gray-200 bg-white p-5">
                    <header class="mb-4">
                        <h2 class="font-semibold text-gray-900">{{ sectionNumber(section.label) }} · {{ section.label }}</h2>
                    </header>

                    <div class="space-y-4">
                        <div v-for="(field, fieldName) in section.fields" :key="fieldName">
                            <label v-if="!field.full" class="mb-1 block text-xs font-medium text-gray-700">{{ field.label }}</label>
                            <component :is="getComponent(field.type)" :form="form" :fieldName="fieldName" :options="field.options" :fieldData="field" />
                        </div>
                    </div>
                </section>
            </div>

            <!-- Master checklist -->
            <aside class="space-y-3 lg:sticky lg:top-4">
                <div class="rounded-lg border border-[--app-accent-muted] bg-white p-4">
                    <h3 class="text-sm font-semibold text-gray-900">{{ ctrans("Master checklist") }}</h3>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-2xl font-semibold tabular-nums text-gray-900">{{ completeness.complete }} / {{ completeness.total }}</span>
                        <span class="text-xs text-gray-500">{{ ctrans("checks met") }}</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-[--app-accent]" :style="{ width: `${completeness.score}%` }" />
                    </div>

                    <ul class="mt-3 space-y-1">
                        <li
                            v-for="item in completeness.items"
                            :key="item.key"
                            class="flex items-center gap-2 rounded px-1.5 py-0.5 text-xs"
                            :class="item.is_complete ? 'text-gray-700' : 'bg-red-50 text-red-700'"
                        >
                            <FontAwesomeIcon :icon="item.is_complete ? faCheck : faTimes" :class="item.is_complete ? 'text-green-600' : 'text-red-500'" fixed-width aria-hidden="true" />
                            {{ item.label }}
                        </li>
                    </ul>
                    <p v-if="changedFields.length" class="mt-3 text-xs text-gray-500">{{ ctrans("The checklist updates after saving.") }}</p>
                </div>

                <Button type="tertiary" full :label="ctrans('Save')" :loading="form.processing" :disabled="!changedFields.length" @click="save">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </aside>
        </div>
    </div>
</template>
