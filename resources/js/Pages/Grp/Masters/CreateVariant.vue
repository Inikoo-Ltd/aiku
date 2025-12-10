<script setup lang="ts">
import { ref, computed } from "vue"
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

const state = ref({
    variants: [
        { label: "Color", options: ["Red", "Blue", "Green"], active: false },
        { label: "Size", options: ["L", "XL", "XXL"], active: false },
    ] as Variant[],
    groupBy: "Color",
})


const expanded = ref<Record<string, boolean>>({})
const toggleExpand = (k: string) => {
    expanded.value[k] = !expanded.value[k]
}


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
        const key = JSON.stringify(keyObj)
        return Object.keys(productMap.value).find(pid => productMap.value[pid] === key) || null
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

const setProduct = (node: Node, val: string | null) => {
    console.log("Set product", val)
    if (!val.id) return

    const key = { ...node.key, product: val }

    // Reverse mapping: productId → key JSON
    productMap.value[val.id] = key
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
        },
        {
            onFinish: () => (saving.value = false)
        }
    )
}

</script>



<template>

    <Head :title="capitalize(props.title)" />
    <PageHeading :data="props.pageHead" />

    <div class="flex justify-center mt-6">
        <div class="w-full max-w-2xl p-4 rounded-lg shadow bg-white space-y-2">

            <!-- Variants section -->
            <h2 class="text-sm font-semibold">Create Variant</h2>

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

            <!-- Add Variant -->
            <Button type="dashed" @click="addVariant" size="xs" :icon="faPlus" label="Add Variant"></Button>

            <!-- Grouping -->
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
                <div class="overflow-x-auto border rounded mt-4" :style="{ overflow: 'visible' }">
                    <table class="min-w-full divide-y">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-sm w-1/2">Variant</th>
                                <th class="px-4 py-2 text-left text-sm w-1/2">Product</th>
                            </tr>
                        </thead>

                        <tbody class="bg-white divide-y">
                            <template v-if="state.variants.length > 1 && state.groupBy">
                                <template v-for="node in buildNodes" :key="keyToString(node.key)">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 w-1/2">
                                            <button class="mr-2 text-sm" @click="toggleExpand(keyToString(node.key))">
                                                <span v-if="node.children?.length">
                                                    <span v-if="expanded[keyToString(node.key)]">−</span>
                                                    <span v-else>+</span>
                                                </span>
                                            </button>
                                            {{ node.label }}
                                        </td>
                                        <td class="px-4 py-2 w-1/2"></td>
                                    </tr>

                                    <tr v-for="child in node.children" v-if="expanded[keyToString(node.key)]"
                                        :key="keyToString(child.key)" class="bg-white hover:bg-gray-50">

                                        <td class="px-8 py-2 text-sm text-gray-700 w-1/2">
                                            ↳ {{ child.label }}
                                        </td>

                                        <td class="px-4 py-2 w-1/2">
                                            <PureMultiselectInfiniteScroll :model-value="child.product"
                                                @update:model-value="(val) => setProduct(child, val)"
                                                :fetchRoute="props.master_assets_route" :classes="'w-full'"
                                                :placeholder="trans('Select Product')" valueProp="id" label-prop="name"
                                                :object="true">
                                                <template #singlelabel="{ value }">
                                                    <div class="flex items-center gap-3 p-2 rounded-lg transition border border-transparent hover:border-gray-200 hover:bg-gray-50">
                                                        <!-- Image wrapper -->
                                                        <div
                                                            class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center">
                                                            <Image v-if="value?.image" :src="value.image.thumbnail"
                                                                alt="Product image"
                                                                class="w-full h-full object-cover" />
                                                            <FontAwesomeIcon v-else icon="fal fa-image"
                                                                class="text-gray-400 text-lg" />
                                                        </div>
                                                        <!-- Text content -->
                                                        <div class="flex flex-col flex-1 min-w-0">
                                                            <div
                                                                class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                                                                <span class="truncate">{{ value?.name }}</span>
                                                                <span class="text-gray-500 font-mono truncate">
                                                                    ({{ value?.code || '-' }})
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </PureMultiselectInfiniteScroll>

                                        </td>
                                    </tr>
                                </template>
                            </template>

                            <!-- Flat Mode -->
                            <template v-else>
                                <tr v-for="node in buildNodes" :key="keyToString(node.key)" class="hover:bg-gray-50">
                                    <td class="px-4 py-2 w-1/2">{{ node.label }}</td>
                                    <td class="px-4 py-2 w-1/2">
                                        <PureMultiselectInfiniteScroll :model-value="node.product"
                                            @update:model-value="(val) => setProduct(node, val)"
                                            :fetchRoute="props.master_assets_route" :object="true"
                                            :placeholder="trans('Select Product')" valueProp="id" label-prop="name" />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
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
