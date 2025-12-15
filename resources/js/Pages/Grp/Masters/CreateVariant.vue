<script setup lang="ts">
import { ref, computed, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
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

type Variant = { label: string; options: string[]; active?: boolean }
type Node = {
    key: Record<string, string>
    label: string
    product: string | null
    children?: Node[]
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    master_asset: {}
    master_assets_route: routeType
    save_route: routeType
}>()

const state = ref({ variants: [{ label: "Color", options: ["Red", "Blue", "Green"], active: false }, { label: "Size", options: ["L", "XL", "XXL"], active: false },] as Variant[], groupBy: "Color" })
const product_leader = ref<null | any>(null)
/* const variant_code = ref("") */

const expanded = ref<Record<string, boolean>>({})
const toggleExpand = (k: string) => {
    expanded.value[k] = !expanded.value[k]
}

const normalizeKey = (k: Record<string, string>) =>
    Object.keys(k)
        .sort()
        .map(key => `${key}=${k[key]}`)
        .join("|")


const validVariants = computed(() =>
    state.value.variants
        .filter(v => v.label.trim())
        .map(v => ({
            label: v.label,
            options: v.options.filter(o => o.trim())
        }))
)


const getCombinations = (list: { label: string; options: string[] }[]) => {
    const recur = (i: number, cur: any) =>
        i === list.length
            ? [cur]
            : list[i].options.flatMap(o =>
                recur(i + 1, { ...cur, [list[i].label]: o })
            )
    return recur(0, {})
}


const productMap = ref<Record<string, string | null>>({})

const buildNodes = computed<Node[]>(() => {
    const variants = validVariants.value
    if (!variants.length) return []

    const getProduct = (keyObj: Record<string, string>) => {
        const key = normalizeKey(keyObj)
        return productMap.value[key] ?? null
    }



    if (variants.length === 1) {
        const v = variants[0]
        return v.options.map(opt => ({
            key: { [v.label]: opt },
            label: opt,
            product: getProduct({ [v.label]: opt })
        }))
    }

    if (!state.value.groupBy) {
        return getCombinations(variants).map(row => {
            const keyObj = variants.reduce((acc, v) => {
                acc[v.label] = row[v.label]
                return acc
            }, {} as Record<string, string>)
            return {
                key: keyObj,
                label: Object.values(keyObj).join(" — "),
                product: getProduct(keyObj)
            }
        })
    }

    const base = variants.find(v => v.label === state.value.groupBy)
    const others = variants.filter(v => v.label !== state.value.groupBy)
    if (!base) return []

    return base.options.map(opt => {
        const parentKey = { [base.label]: opt }
        const parent: Node = {
            key: parentKey,
            label: opt,
            product: getProduct(parentKey),
            children: getCombinations([{ label: base.label, options: [opt] }, ...others])
                .map(c => {
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
        }
        return parent
    })
})

const setProduct = (node: Node, val: any | null) => {
    if (!val?.id) return

    const key = normalizeKey(node.key)

    productMap.value[key] = {
        id: val.id,
        name: val.name,
        code: val.code,
        image: val.image_thumbnail,
        slug: val.slug
    }
}

const toggleActive = (i: number) =>
    state.value.variants.forEach((v, idx) => (v.active = idx === i ? !v.active : false))

const addVariant = () => {
    state.value.variants.forEach(v => (v.active = false))
    state.value.variants.push({ label: "", options: [""], active: true })
}

const addOption = (i: number) => state.value.variants[i].options.push("")
const removeOption = (vi: number, oi: number) => state.value.variants[vi].options.splice(oi, 1)

const deleteVariant = (index: number) => {
    const removed = state.value.variants.splice(index, 1)
    if (removed[0]?.label === state.value.groupBy) {
        state.value.groupBy = state.value.variants[0]?.label ?? ""
    }
}

const keyToString = (k: Record<string, string>) => JSON.stringify(k)

const saving = ref(false)

const buildPayload = () => ({
    variants: validVariants.value,
    groupBy: state.value.groupBy,
    products: productMap.value
})

const save = () => {
    saving.value = true
    router.post(
        route(props.save_route.name, props.save_route.parameters),
        {
            data: buildPayload(),
            product_leader: product_leader.value
        },
        {
            onFinish: () => (saving.value = false)
        }
    )
}


watch(validVariants, () => {
    const validKeys = new Set(
        getCombinations(validVariants.value).map(k => normalizeKey(k))
    )

    Object.keys(productMap.value).forEach(k => {
        if (!validKeys.has(k)) {
            delete productMap.value[k]
        }
    })
})

</script>



<template>

    <Head :title="capitalize(props.title)" />
    <PageHeading :data="props.pageHead" />

    <div class="flex justify-center mt-6">
        <div class="w-full max-w-2xl p-4 rounded-lg shadow bg-white space-y-2">

            <!-- Variants section -->
            <h2 class="text-sm font-semibold">Create Variant</h2>

            <div>
                <label class="text-xs">Product Leader</label>
                <PureMultiselectInfiniteScroll v-model="product_leader" :fetchRoute="props.master_assets_route"
                    valueProp="id" label-prop="name"  :caret="false"
                    :placeholder="trans('Select Product Leader')" />
            </div>

            <!-- <div>
                <label class="text-xs">Variant Code</label>
                <PureInput v-model="variant_code" placeholder="e.g. SKU12345" />
            </div> -->


            <div class="mt-4 space-y-1">
                <label class="text-xs">Variants</label>
                <div v-for="(v, vi) in state.variants" :key="vi" class="border rounded">
                    <div v-if="!v.active" class="p-2 cursor-pointer" @click="toggleActive(vi)">
                        <div class="text-sm font-medium">{{ v.label || "Untitled Variant" }}</div>
                        <div class="text-xs text-gray-500">
                            {{v.options.filter(o => o).join(", ") || "No options"}}
                        </div>
                    </div>

                    <div v-else class="p-3 space-y-1 bg-gray-50">
                        <div>
                            <label class="text-xs">Name</label>
                            <PureInput v-model="v.label" placeholder="Color, Size" />
                        </div>

                        <div>
                            <label class="text-xs">Options</label>
                            <div v-for="(opt, oi) in v.options" :key="oi" class="flex gap-2 mt-2">
                                <PureInput v-model="v.options[oi]" placeholder="Value" class="flex-1" />
                                <button @click="removeOption(vi, oi)" class="text-red-500 text-sm">
                                    <FontAwesomeIcon :icon="faTrashAlt" />
                                </button>
                            </div>

                            <div class="flex justify-between mt-3">
                                <Button type="dashed" @click="addOption(vi)" label="+ Add" size="xs" />
                                <div class="flex gap-2">
                                    <Button type="red_outline" :icon="faTrashAlt" label="Delete" size="xs"
                                        @click="deleteVariant(vi)" />
                                    <Button label="Done" @click="toggleActive(vi)" size="xs" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <Button v-if="state.variants.length < 2" type="dashed" @click="addVariant" size="xs" :icon="faPlus"
                    label="Add Variant"></Button>

                <div class="border-t mt-6">
                    <div v-if="validVariants.length" class="flex items-center gap-2 mt-3">
                        <span class="text-sm">Group by</span>
                        <select v-model="state.groupBy" class="border rounded px-2 py-1 text-sm w-[90px]">
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
                                                    <button v-if="state.variants.length > 1 && node.children"
                                                        class="w-5 h-5 mr-2 border rounded bg-gray-100 flex items-center justify-center leading-none text-sm font-medium"
                                                        @click="toggleExpand(keyToString(node.key))">
                                                        {{ expanded[keyToString(node.key)] ? "−" : "+" }}
                                                    </button>

                                                    <div class="truncate">{{ node.label }}</div>
                                                </div>
                                            </td>

                                            <td class="px-4" v-if="state.variants.length === 1">
                                                <PureMultiselectInfiniteScroll :model-value="node.product"
                                                    @update:model-value="val => setProduct(node, val)"
                                                    :fetchRoute="props.master_assets_route" valueProp="id"
                                                    label-prop="name" :object="true" :caret="false"
                                                    :placeholder="trans('Select Product')" />
                                            </td>

                                            <td v-else />
                                        </tr>

                                        <tr v-for="child in node.children"
                                            v-if="state.variants.length > 1 && expanded[keyToString(node.key)]"
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

            <!-- SAVE BUTTON -->
            <div class="pt-5">
                <Button full class="bg-blue-600 text-white" :loading="saving" @click="save">
                    Save Variants
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.multiselect-wrapper) {
    justify-content: space-between;
}
</style>
