<script setup lang="ts">
import { ref, computed } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import PureInput from "@/Components/Pure/PureInput.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTrashAlt } from "@far"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    master_asset: {}
}>()

type Variant = { label: string; options: string[]; active?: boolean }
type Node = {
    key: string[]
    label: string
    text: string
    product: string | null
    children?: Node[]
}

const state = ref({
    variants: [
        { label: "Color", options: ["Red", "Blue", "Green"], active: false },
        { label: "Size", options: ["L", "XL", "XXL"], active: false },
    ] as Variant[],
    groupBy: "Color",
})

// Expand/collapse state for grouped rows
const expanded = ref<Record<string, boolean>>({})

const toggleExpand = (k: string) => {
    expanded.value[k] = !expanded.value[k]
}

// Helpers
const validVariants = computed(() =>
    state.value.variants
        .filter((v) => v.label.trim())
        .map((v) => ({ label: v.label, options: v.options.filter((o) => o.trim()) }))
)

const getCombinations = (list: { label: string; options: string[] }[]) => {
    const recur = (i: number, cur: any) =>
        i === list.length
            ? [cur]
            : list[i].options.flatMap((o: string) => recur(i + 1, { ...cur, [list[i].label]: o }))
    return recur(0, {})
}

const buildNodes = computed<Node[]>(() => {
    const variants = validVariants.value
    if (!variants.length) return []

    if (variants.length === 1) {
        const v = variants[0]
        return v.options.map((opt) => ({
            key: { [v.label]: opt },
            label: opt,
        }))
    }

    if (!state.value.groupBy) {
        return getCombinations(variants).map((row) => {
            const keyObj = variants.reduce((acc, v) => {
                acc[v.label] = row[v.label]
                return acc
            }, {} as Record<string, string>)
            const label = Object.values(keyObj).join(" — ")
            return {
                key: keyObj,
                label,
            }
        })
    }

    const base = variants.find((v) => v.label === state.value.groupBy)
    const others = variants.filter((v) => v.label !== state.value.groupBy)
    if (!base) return []

    return base.options.map((opt) => {
        const parent: Node = {
            key: { [base.label]: opt },
            label: opt,
            children: getCombinations([{ label: base.label, options: [opt] }, ...others]).map(
                (c: any) => {
                    const keyObj = variants.reduce((acc, v) => {
                        acc[v.label] = c[v.label]
                        return acc
                    }, {} as Record<string, string>)
                    return {
                        key: keyObj,
                        label: Object.values(keyObj).join(" — "),
                    }
                }
            ),
        }
        return parent
    })
})


// Variant controls
const toggleActive = (i: number) =>
    state.value.variants.forEach((v, idx) => (v.active = idx === i ? !v.active : false))
const addVariant = () => {
    state.value.variants.forEach((v) => (v.active = false))
    state.value.variants.push({ label: "", options: [""], active: true })
}
const addOption = (i: number) => state.value.variants[i].options.push("")
const removeOption = (vi: number, oi: number) => state.value.variants[vi].options.splice(oi, 1)
const deleteVariant = (index: number) => {
    const removed = state.value.variants.splice(index, 1)
    if (removed[0]?.label === state.value.groupBy) {
        if (state.value.variants.length > 0) {
            state.value.groupBy = state.value.variants[0].label
        } else {
            state.value.groupBy = ""
        }
    }
}

const setProduct = (node: Node, val: string) => (node.product = val || null)
const keyToString = (k: string[] | string) => (Array.isArray(k) ? k.join("::") : String(k))


</script>

<template>

    <Head :title="capitalize(props.title)" />
    <PageHeading :data="props.pageHead" />
    <pre>{{ buildNodes }}</pre>

    <div class="flex justify-center mt-6">
        <div class="w-full max-w-2xl p-4 rounded-lg shadow bg-white space-y-2">
            <!-- Variants editor -->
            <h2 class="text-sm font-semibold">Create Variant</h2>
            <div v-for="(v, vi) in state.variants" :key="vi" class="border rounded">
                <div v-if="!v.active" class="p-2 cursor-pointer" @click="toggleActive(vi)">
                    <div class="text-sm font-medium">{{ v.label || "Untitled Variant" }}</div>
                    <div class="text-xs text-gray-500">
                        {{v.options.filter((o) => o).join(", ") || "No options"}}
                    </div>
                </div>
                <div v-else class="p-3 space-y-1 bg-gray-50 relative">
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

            <!-- Add variant -->
            <Button full class="text-white bg-green-600" @click="addVariant">+ Add Variant</Button>

            <div class="border-t mt-6">
                 <div v-if="validVariants.length" class="flex items-center gap-2 mt-3">
                <span class="text-sm">Group by</span>
                <select v-model="state.groupBy" class="border rounded px-2 py-1 text-sm w-[90px]">
                    <option v-for="v in validVariants" :key="v.label" :value="v.label">
                        {{ v.label }}
                    </option>
                </select>
            </div>
            
             <div class="overflow-x-auto border rounded mt-4">
                <table class="min-w-full divide-y">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm">Variant</th>
                            <th class="px-4 py-2 text-left text-sm">Product</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y">
                        <!-- Grouped -->
                        <template v-if="state.groupBy">
                            <template v-for="node in buildNodes" :key="keyToString(node.key)">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <button class="mr-2 text-sm" @click="toggleExpand(keyToString(node.key))">
                                            <span v-if="node.children && node.children.length">
                                                <span v-if="expanded[keyToString(node.key)]">−</span>
                                                <span v-else>+</span>
                                            </span>
                                        </button>
                                        {{ node.label }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <div v-if="state.variants.length == 1">
                                            <input class="border rounded px-2 py-1 text-sm w-full"
                                                :value="child?.product ?? ''"
                                                @input="(e) => setProduct(child, e.target.value)" />
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="child in node.children" v-if="expanded[keyToString(node.key)]"
                                    :key="keyToString(child.key)" class="bg-white hover:bg-gray-50">
                                    <td class="px-8 py-2 text-sm text-gray-700">
                                        ↳ {{ child.label }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <input class="border rounded px-2 py-1 text-sm w-full"
                                            :value="child.product ?? ''"
                                            @input="(e) => setProduct(child, e.target.value)" />
                                    </td>
                                </tr>
                            </template>
                        </template>
                        <!-- Flat -->
                        <template v-else>
                            <tr v-for="node in buildNodes" :key="keyToString(node.key)" class="hover:bg-gray-50">
                                <td class="px-4 py-2">{{ node.label }}</td>
                                <td class="px-4 py-2">
                                    <input class="border rounded px-2 py-1 text-sm w-full" :value="node.product ?? ''"
                                        @input="(e) => setProduct(node, e.target.value)" />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>


            </div>
        </div>
    </div>
</template>
