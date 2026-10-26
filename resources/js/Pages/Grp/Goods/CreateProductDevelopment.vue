<!--
  - Author Louis Perez
  - Created on 15-10-2026-19h-50m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import axios from "axios"
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import RadioButton from "primevue/radiobutton"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import { useFitToViewport } from "@/Components/Goods/ProductDevelopment/useFitToViewport"
import ProductDevelopmentRemoteSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRemoteSelect.vue"
import ProductDevelopmentUserSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentUserSelect.vue"
import ProductDevelopmentMemberAccessWarning from "@/Components/Goods/ProductDevelopment/ProductDevelopmentMemberAccessWarning.vue"
import ProductDevelopmentMarketSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentMarketSelect.vue"
import ProductDevelopmentRoleSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRoleSelect.vue"
import { peopleForRole, type MemberUserOption } from "@/Components/Goods/ProductDevelopment/productDevelopmentMembers"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faTimes, faLightbulbOn, faArrowLeft, faSparkles, faPaintBrush, faTag, faPaperclip, faFilePdf, faImage, faFilm, faBoxOpen, faFlask } from "@fal"
import { faSave } from "@fas"
library.add(faPlus, faTimes, faSave, faLightbulbOn, faArrowLeft, faSparkles, faPaintBrush, faTag, faPaperclip, faFilePdf, faImage, faFilm, faBoxOpen, faFlask)

interface Option {
    value: string | number
    label: string
    agent_id?: number | null
    icon?: string
    color?: string
    avatar?: Record<string, string> | null
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    currentUserId: number
    options: {
        kinds: { value: "product" | "recipe", label: string, description: string, icon: string }[]
        productions: { value: number, label: string, organisation: string | null }[]
        types: Option[]
        roles: Option[]
        markets: Option[]
        users: Option[]
        brands: Option[]
        agents: Option[]
        suppliers: Option[]
    }
    searchRoutes: Record<"trade_units" | "trade_unit_families" | "packaging_families" | "sku_code", routeType>
    storeRoute: routeType
}>()

interface ComponentRow {
    trade_unit_id: number | null
    code: string
    name: string
    quantity: number | null
    note: string
}

interface SkuRow {
    mode: "new" | "existing"
    code: string
    name: string
    trade_unit_id: number | null
    brief: string
    images: { file: File, url: string }[]
    code_error?: string | null
    checking?: boolean
}

const form = reactive({
    kind: "product" as "product" | "recipe",
    name: "",
    type: "new",
    brand_id: null as number | null,
    trade_unit_family_id: null as number | null,
    supplier_id: null as number | null,
    agent_id: null as number | null,
    production_id: null as number | null,
    owner_id: props.currentUserId as number | null,
    target_landing_date: null as Date | null,
    target_online_date: null as Date | null,
    markets: [] as string[],
    brief: "",
})

const components = ref<ComponentRow[]>([])
const addComponentRow = () => components.value.push({ trade_unit_id: null, code: "", name: "", quantity: null, note: "" })
const onComponentPicked = (row: ComponentRow, option: { code?: string, name?: string, label: string }) => {
    row.code = option.code ?? option.label
    row.name = option.name ?? ""
}

const newSku = (): SkuRow => ({ mode: "new", code: "", name: "", trade_unit_id: null, brief: "", images: [] })
const skus = ref<SkuRow[]>([newSku()])

/* Brief references (PDF) for the range, and reference images per SKU, uploaded with the development */
const briefFiles = ref<File[]>([])
const briefInput = ref<HTMLInputElement | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const imagesFor = ref<SkuRow | null>(null)

function onBriefFilesPicked(event: Event) {
    const input = event.target as HTMLInputElement
    briefFiles.value.push(...(input.files ?? []))
    input.value = ""
}

function pickImages(sku: SkuRow) {
    imagesFor.value = sku
    imageInput.value?.click()
}

function onImagesPicked(event: Event) {
    const input = event.target as HTMLInputElement
    imagesFor.value?.images.push(...[...(input.files ?? [])].map((file) => ({ file, url: URL.createObjectURL(file) })))
    input.value = ""
}

function removeImage(sku: SkuRow, index: number) {
    URL.revokeObjectURL(sku.images[index].url)
    sku.images.splice(index, 1)
}
const members = ref<{ user_id: number | null, role: string | null }[]>([{ user_id: null, role: null }])
const errors = ref<Record<string, string>>({})
const saving = ref(false)

const skuModes = [
    { value: "new", label: ctrans("New SKU") },
    { value: "existing", label: ctrans("Existing") },
]

function onSupplierChange(value: unknown) {
    const supplierId = value as number | null
    form.supplier_id = supplierId
    const supplier = props.options.suppliers.find((option) => option.value === supplierId)
    if (supplier) {
        form.agent_id = supplier.agent_id ?? null
    }
}

function peopleFor(role: string | null) {
    return peopleForRole(props.options.users as MemberUserOption[], role, form.agent_id)
}

function personPlaceholder(role: string | null) {
    if (!role) {
        return ctrans("Choose a role first")
    }
    if (role === "agent" && !form.agent_id) {
        return ctrans("Choose the agent first")
    }
    return peopleFor(role).length ? ctrans("Person") : ctrans("Nobody works for this agent")
}

function onRoleChange(member: { user_id: number | null, role: string | null }) {
    const people = peopleFor(member.role)
    if (member.user_id && !people.some((person) => person.value === member.user_id)) {
        member.user_id = null
    }
    if (!member.user_id && member.role === "agent" && people.length === 1) {
        member.user_id = people[0].value
    }
}

watch(() => form.agent_id, (agentId) => {
    const agentRows = members.value.filter((member) => member.role === "agent")
    agentRows.forEach(onRoleChange)
    if (!agentId || agentRows.length) {
        return
    }
    const people = peopleFor("agent")
    const row = { role: "agent", user_id: people.length === 1 ? people[0].value : null }
    const emptyIndex = members.value.findIndex((member) => !member.role && !member.user_id)
    if (emptyIndex >= 0) {
        members.value.splice(emptyIndex, 1, row)
    } else {
        members.value.push(row)
    }
})

const supplierOptions = computed(() => form.agent_id
    ? props.options.suppliers.filter((option) => option.agent_id === form.agent_id)
    : props.options.suppliers)

const toIso = (date: Date | string | null) => {
    if (!date) {
        return null
    }
    const value = typeof date === "string" ? new Date(date) : date

    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}-${String(value.getDate()).padStart(2, "0")}`
}

const error = (key: string) => errors.value[key]

watch(() => [form.target_landing_date, form.target_online_date], () => {
    delete errors.value.target_online_date
    if (form.target_landing_date && form.target_online_date && form.target_online_date < form.target_landing_date) {
        form.target_online_date = null
    }
})

const codeTimers = new Map<SkuRow, ReturnType<typeof setTimeout>>()

function onCodeInput(sku: SkuRow, index: number) {
    delete errors.value[`items.${index}.code`]
    sku.code_error = null
    clearTimeout(codeTimers.get(sku))
    const code = sku.code.trim()
    if (!code) {
        sku.checking = false
        return
    }
    sku.checking = true
    codeTimers.set(sku, setTimeout(async () => {
        try {
            const { data } = await axios.get(route(props.searchRoutes.sku_code.name, props.searchRoutes.sku_code.parameters), { params: { code } })
            if (sku.code.trim() === code) {
                sku.code_error = data.valid ? null : data.message
            }
        } finally {
            if (sku.code.trim() === code) {
                sku.checking = false
            }
        }
    }, 500))
}

const duplicateCodes = computed(() => {
    const seen = new Map<string, number>()
    skus.value.filter((sku) => sku.mode === "new" && sku.code.trim()).forEach((sku) => {
        const key = sku.code.trim().toLowerCase()
        seen.set(key, (seen.get(key) ?? 0) + 1)
    })
    return new Set([...seen.entries()].filter(([, count]) => count > 1).map(([code]) => code))
})

const hasCodeProblems = computed(() => skus.value.some((sku, index) => sku.mode === "new" && (sku.checking || !!codeError(sku, index))))

function codeError(sku: SkuRow, index: number): string | null {
    if (sku.code.trim() && duplicateCodes.value.has(sku.code.trim().toLowerCase())) {
        return ctrans("This code is used twice in this form")
    }
    return sku.code_error ?? error(`items.${index}.code`) ?? null
}

const formElement = ref<HTMLFormElement | null>(null)
const { height: formHeight } = useFitToViewport(formElement)

function submit() {
    if (hasCodeProblems.value) {
        return
    }
    const sentRows = skus.value
        .map((sku, index) => ({ sku, index }))
        .filter(({ sku }) => sku.mode === "existing" ? sku.trade_unit_id : (sku.code || sku.name))
        .map(({ index }) => index)
    const isRecipe = form.kind === "recipe"
    const payload = {
        ...form,
        supplier_id: isRecipe ? null : form.supplier_id,
        agent_id: isRecipe ? null : form.agent_id,
        production_id: isRecipe ? form.production_id : null,
        components: isRecipe
            ? components.value.filter((row) => row.trade_unit_id).map((row) => ({ trade_unit_id: row.trade_unit_id, quantity: row.quantity, note: row.note || null }))
            : [],
        target_landing_date: toIso(form.target_landing_date),
        target_online_date: toIso(form.target_online_date),
        brief: form.brief || null,
        items: sentRows.map((index) => {
            const sku = skus.value[index]
            const details = { brief: sku.brief || null, images: sku.images.map((image) => image.file) }
            return sku.mode === "existing" ? { trade_unit_id: sku.trade_unit_id, ...details } : { code: sku.code, name: sku.name, ...details }
        }),
        brief_files: briefFiles.value,
        members: members.value.filter((member) => member.user_id && member.role),
    }

    const hasFiles = briefFiles.value.length > 0 || skus.value.some((sku) => sku.images.length > 0)
    router.post(route(props.storeRoute.name, props.storeRoute.parameters), payload, {
        forceFormData: hasFiles,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onError: (validationErrors) => {
            errors.value = Object.fromEntries(Object.entries(validationErrors).map(([key, message]) => {
                const match = key.match(/^items\.(\d+)\.(.+)$/)
                return [match ? `items.${sentRows[Number(match[1])]}.${match[2]}` : key, message]
            }))
        },
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <form ref="formElement" class="product-development-form flex flex-col bg-white" :style="{ height: formHeight }" @submit.prevent="submit">
        <div class="flex-1 lg:grid lg:min-h-0 lg:grid-cols-[minmax(0,65fr)_minmax(0,35fr)] lg:grid-rows-[minmax(0,1fr)]">
            <div class="space-y-8 p-4 md:p-6 lg:overflow-y-auto lg:[scrollbar-width:thin]">
                <section class="space-y-2">
                    <h2 id="development-kind" class="text-sm font-semibold text-gray-800">{{ ctrans("What are you developing?") }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-labelledby="development-kind">
                        <label
                            v-for="kind in options.kinds"
                            :key="kind.value"
                            :for="`development-kind-${kind.value}`"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border-2 p-4 transition-colors"
                            :class="form.kind === kind.value ? 'border-[--app-accent] bg-[--app-accent-soft]' : 'border-gray-200 bg-white hover:border-[--app-accent-muted]'"
                        >
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-xl"
                                :class="form.kind === kind.value ? 'bg-[--app-accent] text-[--app-accent-text]' : 'bg-gray-100 text-gray-500'"
                            >
                                <FontAwesomeIcon :icon="kind.icon" fixed-width aria-hidden="true" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-base font-semibold" :class="form.kind === kind.value ? 'text-[--app-accent-deep]' : 'text-gray-900'">{{ kind.label }}</span>
                                <span class="mt-0.5 block text-xs text-gray-600">{{ kind.description }}</span>
                            </span>
                            <RadioButton v-model="form.kind" :value="kind.value" :inputId="`development-kind-${kind.value}`" name="kind" class="mt-0.5" />
                        </label>
                    </div>
                    <p v-if="error('kind')" class="text-xs text-red-600">{{ error("kind") }}</p>
                </section>

                <section class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-800">{{ ctrans("Product family") }}</h2>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Family name") }} <span class="text-red-500">*</span></label>
                        <InputText v-model="form.name" class="w-full" :invalid="!!error('name')" />
                        <p v-if="error('name')" class="mt-1 text-xs text-red-600">{{ error("name") }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Type") }}</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="type in options.types"
                                    :key="type.value"
                                    type="button"
                                    class="flex h-10 items-center gap-1.5 rounded-md border px-3 text-sm"
                                    :class="form.type === type.value ? productDevelopmentColor(type.color).active : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                                    @click="form.type = String(type.value)"
                                >
                                    <FontAwesomeIcon v-if="type.icon" :icon="type.icon" :class="productDevelopmentColor(type.color).icon" fixed-width aria-hidden="true" />
                                    {{ type.label }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Brand") }}</label>
                            <Select v-model="form.brand_id" :options="options.brands" optionLabel="label" optionValue="value" filter showClear :placeholder="ctrans('Select a brand')" class="w-full" overlayClass="text-sm" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Trade unit family") }}</label>
                            <ProductDevelopmentRemoteSelect v-model="form.trade_unit_family_id" :searchRoute="searchRoutes.trade_unit_families" :placeholder="ctrans('Search trade unit families')" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("General brief") }}</label>
                        <Textarea v-model="form.brief" rows="6" autoResize class="w-full" :placeholder="ctrans('Write the general brief for the range here…')" />
                        <div class="mt-2 rounded-md border border-dashed border-gray-300 px-3 py-2.5">
                            <div class="flex flex-wrap items-center gap-3">
                                <FontAwesomeIcon icon="fal fa-paperclip" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                                <div class="min-w-0 flex-1 text-xs text-gray-500">
                                    <span class="text-sm text-gray-900">{{ ctrans("Attach files") }}</span> {{ ctrans("(optional)") }} · {{ ctrans("a concept, mood board or other reference for the designer: PDF, image or video") }}
                                </div>
                                <Button type="tertiary" size="xs" :label="ctrans('Upload')" @click="briefInput?.click()">
                                    <template #icon><FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" /></template>
                                </Button>
                            </div>
                            <ul v-if="briefFiles.length" class="mt-2 space-y-1">
                                <li v-for="(file, fileIndex) in briefFiles" :key="fileIndex" class="flex items-center gap-2 rounded bg-gray-50 px-2 py-1 text-sm">
                                    <FontAwesomeIcon :icon="file.type.startsWith('image/') ? 'fal fa-image' : file.type.startsWith('video/') ? 'fal fa-film' : 'fal fa-file-pdf'" :class="file.type === 'application/pdf' ? 'text-red-500' : 'text-gray-500'" fixed-width aria-hidden="true" />
                                    <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
                                    <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-red-500 hover:bg-red-50" :aria-label="ctrans('Remove')" @click="briefFiles.splice(fileIndex, 1)">
                                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                                    </button>
                                </li>
                            </ul>
                            <p v-if="error('brief_files.0')" class="mt-1 text-xs text-red-600">{{ error("brief_files.0") }}</p>
                        </div>
                        <input ref="briefInput" type="file" accept="application/pdf,image/*,video/*" multiple class="hidden" @change="onBriefFilesPicked" />
                    </div>
                </section>

                <section v-if="form.kind === 'recipe'" class="rounded-lg border border-gray-200">
                    <header class="flex flex-wrap items-start gap-3 px-4 py-3">
                        <FontAwesomeIcon icon="fal fa-box-open" class="mt-0.5 text-xl text-[--app-accent]" fixed-width aria-hidden="true" />
                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-semibold text-gray-800">{{ ctrans("Packaging & components") }}</h2>
                            <p class="text-xs text-gray-500">{{ ctrans("Bottles, pumps, caps, decorations and other components needed to make it. Every SKU gets them; each SKU's list can be changed later in Formulation & samples.") }}</p>
                        </div>
                        <Button type="tertiary" size="xs" :label="ctrans('Add component')" @click="addComponentRow">
                            <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </header>
                    <div v-if="components.length" class="overflow-x-auto border-t border-gray-100">
                        <table class="w-full min-w-[40rem] text-sm">
                            <thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-medium text-gray-500">
                                <tr>
                                    <th class="w-52 px-3 py-2">{{ ctrans("Product code") }}</th>
                                    <th class="px-3 py-2">{{ ctrans("Product name") }}</th>
                                    <th class="w-36 px-3 py-2">{{ ctrans("Needed to start production") }}</th>
                                    <th class="w-48 px-3 py-2">{{ ctrans("Notes") }}</th>
                                    <th class="w-12 px-3 py-2" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(row, index) in components" :key="index" class="align-top">
                                    <td class="px-3 py-2">
                                        <ProductDevelopmentRemoteSelect v-model="row.trade_unit_id" :searchRoute="searchRoutes.trade_units" :placeholder="ctrans('Search by code')" @picked="(option) => onComponentPicked(row, option)" />
                                        <p v-if="error(`components.${index}.trade_unit_id`)" class="mt-1 text-xs text-red-600">{{ error(`components.${index}.trade_unit_id`) }}</p>
                                    </td>
                                    <td class="px-3 py-2 pt-4 text-gray-700">{{ row.name || "—" }}</td>
                                    <td class="px-3 py-2">
                                        <InputNumber v-model="row.quantity" :min="0" :maxFractionDigits="3" class="w-full" inputClass="w-full" :invalid="!!error(`components.${index}.quantity`)" />
                                        <p v-if="error(`components.${index}.quantity`)" class="mt-1 text-xs text-red-600">{{ error(`components.${index}.quantity`) }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <InputText v-model="row.note" maxlength="255" class="w-full" :placeholder="ctrans('e.g. Main bottle')" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <Button type="tertiary" :tooltip="ctrans('Remove')" class="h-10 w-10 justify-center" @click="components.splice(index, 1)">
                                            <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="border-t border-gray-100 px-4 py-3 text-xs text-gray-400">{{ ctrans("No components yet") }}</p>
                </section>

                <section class="border-t border-gray-200 pt-6">
                    <h2 class="text-sm font-semibold text-gray-800">{{ ctrans("SKUs") }}</h2>
                    <p class="text-xs text-gray-500">{{ ctrans("New SKUs are created as trade units in process") }}</p>

                    <div class="mt-3 space-y-3">
                        <div v-for="(sku, index) in skus" :key="index" class="space-y-2.5 rounded-lg border border-gray-200 p-3">
                            <div class="grid gap-2 md:grid-cols-[auto_minmax(0,1fr)_auto] md:items-start">
                                <SegmentedToggle v-model="sku.mode" :options="skuModes" :ariaLabel="ctrans('New or existing SKU')" />

                                <div v-if="sku.mode === 'new'" class="grid gap-2 sm:grid-cols-[10rem_minmax(0,1fr)]">
                                    <div>
                                        <InputText
                                            v-model="sku.code"
                                            :placeholder="ctrans('Code')"
                                            class="w-full"
                                            :class="codeError(sku, index) ? '!border-red-500 focus:!border-red-500 focus:!ring-red-200' : ''"
                                            :invalid="!!codeError(sku, index)"
                                            @update:modelValue="onCodeInput(sku, index)"
                                        />
                                        <p v-if="codeError(sku, index)" class="mt-1 text-xs text-red-600">{{ codeError(sku, index) }}</p>
                                        <p v-else-if="sku.checking" class="mt-1 text-xs text-gray-400">{{ ctrans("Checking…") }}</p>
                                    </div>
                                    <div>
                                        <InputText v-model="sku.name" :placeholder="ctrans('Name')" class="w-full" :class="error(`items.${index}.name`) ? '!border-red-500' : ''" :invalid="!!error(`items.${index}.name`)" />
                                        <p v-if="error(`items.${index}.name`)" class="mt-1 text-xs text-red-600">{{ error(`items.${index}.name`) }}</p>
                                    </div>
                                </div>
                                <ProductDevelopmentRemoteSelect v-else v-model="sku.trade_unit_id" :searchRoute="searchRoutes.trade_units" :placeholder="ctrans('Search trade units')" />

                                <Button type="tertiary" :tooltip="ctrans('Remove SKU')" class="h-10 w-10 justify-center justify-self-end" @click="skus.splice(index, 1)">
                                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                                </Button>
                            </div>

                            <Textarea v-model="sku.brief" rows="2" autoResize class="w-full text-sm" :placeholder="ctrans('Short brief for this product (optional): fragrance, colour, look…')" />

                            <div class="flex flex-wrap items-center gap-2">
                                <div v-for="(image, imageIndex) in sku.images" :key="image.url" class="group relative h-10 w-10 overflow-hidden rounded-md border border-gray-200">
                                    <img :src="image.url" :alt="image.file.name" class="h-full w-full object-cover" />
                                    <button type="button" class="absolute right-0.5 top-0.5 hidden h-4 w-4 items-center justify-center rounded-full bg-white/90 text-[10px] text-red-500 shadow group-hover:flex" :aria-label="ctrans('Remove')" @click="removeImage(sku, imageIndex)">
                                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                                    </button>
                                </div>
                                <button type="button" class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-[--app-accent]" @click="pickImages(sku)">
                                    <FontAwesomeIcon icon="fal fa-image" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                                    {{ sku.images.length ? ctrans("Add more images") : ctrans("Add reference images (optional)") }}
                                </button>
                                <p v-if="error(`items.${index}.images.0`)" class="w-full text-xs text-red-600">{{ error(`items.${index}.images.0`) }}</p>
                            </div>
                        </div>
                    </div>

                    <input ref="imageInput" type="file" accept="image/*" multiple class="hidden" @change="onImagesPicked" />
                    <Button type="tertiary" :label="ctrans('Add SKU')" class="mt-3" @click="skus.push(newSku())">
                        <template #icon><FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" /></template>
                    </Button>
                </section>
            </div>

            <aside class="border-t border-gray-200 lg:overflow-y-auto lg:border-l lg:border-t-0 lg:[scrollbar-width:thin]">
                <div class="space-y-8 p-4 md:p-6">
                    <section class="space-y-4">
                        <h2 class="text-sm font-semibold text-gray-800">{{ form.kind === "recipe" ? ctrans("Production and planning") : ctrans("Sourcing and planning") }}</h2>

                        <div v-if="form.kind === 'recipe'">
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Made by") }} <span class="text-red-500">*</span></label>
                            <Select v-model="form.production_id" :options="options.productions" optionLabel="label" optionValue="value" filter :filterFields="['label', 'organisation']" :placeholder="ctrans('Select the production unit')" class="w-full" overlayClass="text-sm" :invalid="!!error('production_id')">
                                <template #option="{ option }">
                                    <span class="flex flex-col">
                                        <span>{{ option.label }}</span>
                                        <span class="text-xs text-gray-500">{{ option.organisation }}</span>
                                    </span>
                                </template>
                            </Select>
                            <p v-if="error('production_id')" class="mt-1 text-xs text-red-600">{{ error("production_id") }}</p>
                        </div>

                        <template v-else>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Agent") }}</label>
                            <Select v-model="form.agent_id" :options="options.agents" optionLabel="label" optionValue="value" filter :showClear="!form.supplier_id" :disabled="!!form.supplier_id" :placeholder="ctrans('Select an agent')" class="w-full" overlayClass="text-sm" />
                            <p v-if="form.supplier_id" class="mt-1 text-xs text-gray-500">{{ ctrans("Set by the supplier. Clear the supplier to change it.") }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Supplier") }}</label>
                            <Select :modelValue="form.supplier_id" :options="supplierOptions" optionLabel="label" optionValue="value" filter showClear :placeholder="form.agent_id ? ctrans('Select a supplier of this agent') : ctrans('Select a supplier')" class="w-full" overlayClass="text-sm" @update:modelValue="onSupplierChange" />
                        </div>
                        </template>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Owner") }}</label>
                            <Select v-model="form.owner_id" :options="options.users" optionLabel="label" optionValue="value" filter class="w-full" overlayClass="text-sm" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Markets") }}</label>
                            <ProductDevelopmentMarketSelect v-model="form.markets" :options="options.markets as any" :placeholder="ctrans('Select markets')" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Target landing date") }}</label>
                                <DatePicker v-model="form.target_landing_date" dateFormat="dd M yy" showIcon iconDisplay="input" showButtonBar class="w-full" panelClass="text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Target online date") }}</label>
                                <DatePicker v-model="form.target_online_date" dateFormat="dd M yy" showIcon iconDisplay="input" showButtonBar class="w-full" panelClass="text-sm" :minDate="form.target_landing_date ?? undefined" :invalid="!!error('target_online_date')" />
                                <p v-if="error('target_online_date')" class="mt-1 text-xs text-red-600">{{ error("target_online_date") }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="border-t border-gray-200 pt-6">
                        <h2 class="text-sm font-semibold text-gray-800">{{ ctrans("Members") }}</h2>
                        <p class="text-xs text-gray-500">{{ ctrans("One person can hold several roles: add a row for each role") }}</p>

                        <div class="mt-3 space-y-2">
                            <div v-for="(member, index) in members" :key="index" class="space-y-1.5">
                            <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                                    <div class="grid min-w-0 gap-2 sm:grid-cols-[11rem_minmax(0,1fr)]">
                                        <ProductDevelopmentRoleSelect v-model="member.role" :options="options.roles as any" :placeholder="ctrans('Role')" @update:modelValue="onRoleChange(member)" />
                                        <ProductDevelopmentUserSelect v-model="member.user_id" :options="peopleFor(member.role) as any" :disabled="!peopleFor(member.role).length" :placeholder="personPlaceholder(member.role)" />
                                    </div>
                                    <Button type="tertiary" :tooltip="ctrans('Remove')" class="h-10 w-10 justify-center" @click="members.splice(index, 1)">
                                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                                    </Button>
                                </div>
                                <ProductDevelopmentMemberAccessWarning :userId="member.user_id" :role="member.role" />
                            </div>
                        </div>

                        <Button type="tertiary" :label="ctrans('Add member')" class="mt-3" @click="members.push({ user_id: null, role: null })">
                            <template #icon><FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </section>
                </div>
            </aside>
        </div>

        <div class="sticky bottom-0 z-10 flex items-center justify-end gap-3 border-t border-gray-200 bg-white px-4 py-3 md:px-6">
            <Button
                type="primary"
                nativeType="submit"
                icon="fas fa-save"
                :label="ctrans('Create development')"
                :loading="saving"
                :disabled="hasCodeProblems"
                class="disabled:cursor-not-allowed disabled:opacity-50"
            />
        </div>
    </form>
</template>

<style scoped>
.product-development-form :deep(.p-inputtext),
.product-development-form :deep(.p-select-label),
.product-development-form :deep(.p-multiselect-label),
.product-development-form :deep(.p-textarea) {
    font-size: 0.875rem;
}
.product-development-form :deep(.p-select),
.product-development-form :deep(.p-multiselect),
.product-development-form :deep(.p-datepicker),
.product-development-form :deep(input.p-inputtext) {
    height: 2.5rem;
}
.product-development-form :deep(.p-select-label),
.product-development-form :deep(.p-multiselect-label) {
    display: flex;
    align-items: center;
}
</style>
