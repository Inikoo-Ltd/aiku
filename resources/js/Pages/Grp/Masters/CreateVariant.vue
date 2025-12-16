<script setup lang="ts">
import { ref, computed } from "vue"
import { Head, useForm, } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import PureInput from "@/Components/Pure/PureInput.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTrashAlt } from "@far"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { faPlus } from "@fal"

type Variant = {
    label: string
    options: string[]
    active?: boolean
}

type Node = {
    key: Record<string, string>
    label: string
    product: any | null
    children?: Node[]
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    master_asset: {}
    master_assets_route: routeType
    save_route: routeType
}>()



const form = useForm({
    product_leader: null as any,
    data_variants: {
        variants: [
            { label: "Color", options: ["Red", "Blue"], active: false },
            { label: "Size", options: ["L", "XL"], active: false }
        ] as Variant[],
        groupBy: "Color",
        products: {} as Record<string, any>
    }
})

const expanded = ref<Record<string, boolean>>({})



const toggleExpand = (k: string) => {
    expanded.value[k] = !expanded.value[k]
}

const normalizeKey = (k: Record<string, string>) =>
    Object.keys(k)
        .sort()
        .map(key => `${key}=${k[key]}`)
        .join("|")

const validVariants = computed(() => {
    return (form.data_variants.variants ?? [])
        .filter(v => v?.label?.trim())
        .map(v => ({
            label: v.label,
            options: (v.options ?? []).filter(o => o?.trim())
        }))
})

const getCombinations = (list: { label: string; options: string[] }[]) => {
    const recur = (i: number, cur: any): any[] =>
        i === list.length
            ? [cur]
            : list[i].options.flatMap(o =>
                  recur(i + 1, { ...cur, [list[i].label]: o })
              )
    return recur(0, {})
}



const buildNodes = computed<Node[]>(() => {
    const variants = validVariants.value
    if (!variants.length) return []

    const getProduct = (keyObj: Record<string, string>) =>
        form.data_variants.products[normalizeKey(keyObj)] ?? null

    if (variants.length === 1) {
        const v = variants[0]
        return v.options.map(opt => ({
            key: { [v.label]: opt },
            label: opt,
            product: getProduct({ [v.label]: opt })
        }))
    }

    const base = variants.find(v => v.label === form.data_variants.groupBy)
    const others = variants.filter(v => v.label !== form.data_variants.groupBy)
    if (!base) return []

    return base.options.map(opt => ({
        key: { [base.label]: opt },
        label: opt,
        product: getProduct({ [base.label]: opt }),
        children: getCombinations([
            { label: base.label, options: [opt] },
            ...others
        ]).map(c => {
            const keyObj = variants.reduce((acc, v) => {
                acc[v.label] = c[v.label]
                return acc
            }, {} as Record<string, string>)

            return {
                key: keyObj,
                label: Object.values(keyObj).join(" — "),
                product: getProduct(keyObj)
            }
        })
    }))
})


const setProduct = (node: Node, val: any | null) => {
    if (!val?.id) return

    const key = normalizeKey(node.key)

    form.data_variants.products[val.id] = {
        ...node.key,
        product: {
            id: val.id,
            name: val.name,
            code: val.code,
            image: val.image_thumbnail,
            slug: val.slug
        }
    }
}


const toggleActive = (i: number) =>
    form.data_variants.variants.forEach((v, idx) => (v.active = idx === i ? !v.active : false))

const addVariant = () => {
    form.data_variants.variants.forEach(v => (v.active = false))
    form.data_variants.variants.push({ label: "", options: [""], active: true })
}

const addOption = (i: number) => form.data_variants.variants[i].options.push("")
const removeOption = (vi: number, oi: number) =>
    form.data_variants.variants[vi].options.splice(oi, 1)

const deleteVariant = (i: number) => {
    form.data_variants.variants.splice(i, 1)

    const first = form.data_variants.variants[0]
    form.data_variants.groupBy = first ? first.label : ""
}

const keyToString = (k: Record<string, string>) => JSON.stringify(k)



const isValid = computed(() => {
    if (!form.product_leader) return true
    if (!form.data_variants.variants.length) return true
    return false
})


const save = () => {
    if (isValid.value) return


    form.post(route(props.save_route.name, props.save_route.parameters))
}

</script>


<template>

    <Head :title="capitalize(props.title)" />
    <PageHeading :data="props.pageHead" />

    <div class="flex justify-center mt-6">
        <div class="w-full max-w-2xl p-4 bg-white rounded-lg shadow space-y-4">

            <!-- Product Leader -->
            <div>
                <label class="text-xs font-medium">
                    Product Leader <span class="text-red-500">*</span>
                </label>
                <PureMultiselectInfiniteScroll v-model="form.product_leader" :fetchRoute="props.master_assets_route" :required="true"
                    valueProp="id" label-prop="name" :caret="false" :placeholder="trans('Select Product Leader')" />
            </div>

            <!-- Variants -->
            <div>
                <label class="text-xs font-medium">
                    Variants <span class="text-red-500">*</span>
                </label>

                <div v-for="(v, vi) in form.data_variants.variants" :key="vi" class="border rounded mt-2">
                    <div v-if="!v.active" class="p-2 cursor-pointer" @click="toggleActive(vi)">
                        <div class="text-sm font-medium">{{ v.label || "Untitled Variant" }}</div>
                        <div class="text-xs text-gray-500">
                            {{v.options.filter(o => o).join(", ") || "No options"}}
                        </div>
                    </div>

                    <div v-else class="p-3 bg-gray-50 space-y-2">
                        <div>
                            <label class="text-xs font-medium">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <PureInput v-model="v.label" placeholder="Color, Size" />
                        </div>

                        <div>
                            <label class="text-xs font-medium">
                                Options <span class="text-red-500">*</span>
                            </label>

                            <div v-for="(opt, oi) in v.options" :key="oi" class="flex gap-2 mt-2">
                                <PureInput v-model="v.options[oi]" placeholder="Value" class="flex-1" />
                                <button @click="removeOption(vi, oi)" class="text-red-500">
                                    <FontAwesomeIcon :icon="faTrashAlt" />
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-between mt-3">
                            <Button type="dashed" size="xs" @click="addOption(vi)">+ Add</Button>
                            <div class="flex gap-2">
                                <Button type="red_outline" size="xs" @click="deleteVariant(vi)">
                                    Delete
                                </Button>
                                <Button size="xs" @click="toggleActive(vi)">Done</Button>
                            </div>
                        </div>
                    </div>
                </div>

                <Button v-if="form?.data_variants?.variants?.length < 2" type="dashed" size="xs" class="mt-2" :icon="faPlus"
                    @click="addVariant">
                    Add Variant
                </Button>
                <div class="border-t mt-6">
                    <div v-if="validVariants.length" class="flex items-center gap-2 mt-3">
                        <span class="text-sm">Group by</span>
                        <select v-model="form.data_variants.groupBy" class="border rounded px-2 py-1 text-sm w-[90px]">
                            <option v-for="v in validVariants" :key="v.label" :value="v.label">
                                {{ v.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Table -->
                    <div class="border rounded mt-4" :style="{ overflow: 'visible' }">
                        <div class="border rounded-xl">
                            <table class="min-w-full table-fixed divide-y">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-1/2">
                                            Variant
                                        </th>
                                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-1/2">
                                            Product
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y">
                                    <template v-for="node in buildNodes" :key="keyToString(node.key)">
                                        <tr class="hover:bg-gray-50 h-[70px]">
                                            <td class="px-4">
                                                <div class="flex items-center">
                                                    <button v-if="form.data_variants.variants.length > 1 && node.children"
                                                        class="w-5 h-5 mr-2 border rounded bg-gray-100 flex items-center justify-center leading-none text-sm font-medium"
                                                        @click="toggleExpand(keyToString(node.key))">
                                                        {{ expanded[keyToString(node.key)] ? "−" : "+" }}
                                                    </button>

                                                    <div class="truncate">{{ node.label }}</div>
                                                </div>
                                            </td>

                                            <td class="px-4" v-if="form.data_variants.variants.length === 1">
                                                <PureMultiselectInfiniteScroll :model-value="node.product"
                                                    @update:model-value="val => setProduct(node, val)"
                                                    :fetchRoute="props.master_assets_route" valueProp="id"
                                                    label-prop="name" :object="true" :caret="false"
                                                    :placeholder="trans('Select Product')" />
                                            </td>

                                            <td v-else />
                                        </tr>

                                        <tr v-for="child in node.children"
                                            v-if="form.data_variants.variants.length > 1 && expanded[keyToString(node.key)]"
                                            :key="keyToString(child.key)" class="hover:bg-gray-50 h-[70px]">
                                            <td class="px-8 text-sm text-gray-700">
                                                ↳ {{ child.label }}
                                            </td>
                                            <td class="px-4">
                                                <PureMultiselectInfiniteScroll :model-value="child.product"
                                                    @update:model-value="val => setProduct(child, val)"
                                                    :fetchRoute="props.master_assets_route" valueProp="id"
                                                    label-prop="name" :object="true" :caret="false"
                                                    :placeholder="trans('Select Product')" />
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SAVE -->
            <Button full class="bg-blue-600 text-white disabled:opacity-50" :loading="form.processing" @click="save" :disabled="isValid">
                Save Variants
            </Button>

        </div>
    </div>
</template>

<style scoped>
:deep(.multiselect-wrapper) {
    justify-content: space-between;
}
</style>
