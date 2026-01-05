<script setup lang="ts">
import { ref, computed, watch, watchEffect, nextTick } from "vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faTrashAlt } from "@far"
import { faPlus } from "@fal"
import { trans } from "laravel-vue-i18n"
import type { routeType } from "@/types/route"
import { PageHeadingTypes } from "@/types/PageHeading"
import Image from "@/Components/Image.vue"


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

type DataVariants = {
  variants: Variant[]
  groupBy: string
  products: Record<string, any>
}


defineProps<{
  pageHead: PageHeadingTypes
  title: string
  master_assets_route: routeType
}>()


const model = defineModel<DataVariants>({ required: true })

const createEmptyModel = (): DataVariants => ({
  variants: [],
  groupBy: "",
  products: {}
})


watchEffect(() => {
  if (!model.value) {
    model.value = createEmptyModel()
    return
  }

  model.value.variants ??= []
  model.value.groupBy ??= ""
  model.value.products ??= {}
})


const optionRefs = ref<HTMLInputElement[][]>([])
const expanded = ref<Record<string, boolean>>({})


const normalizeKey = (k: Record<string, string>) =>
  Object.keys(k)
    .sort()
    .map(key => `${key}=${k[key]}`)
    .join("|")

const keyToString = (k: Record<string, string>) =>
  JSON.stringify(k)

const toggleExpand = (k: string) => {
  expanded.value[k] = !expanded.value[k]
}


const validVariants = computed(() =>
  model.value.variants
    .filter(v => v.label?.trim())
    .map(v => ({
      label: v.label,
      options: v.options.filter(o => o?.trim())
    }))
)

const getCombinations = (list: { label: string; options: string[] }[]) => {
  const recur = (i: number, cur: any): any[] =>
    i === list.length
      ? [cur]
      : list[i].options.flatMap(o =>
        recur(i + 1, { ...cur, [list[i].label]: o })
      )

  return recur(0, {})
}


const validCombinationKeys = computed<Set<string>>(() => {
  if (!validVariants.value.length) return new Set()
  return new Set(
    getCombinations(validVariants.value).map(c => normalizeKey(c))
  )
})

const syncProducts = () => {
  if (hasActiveVariant.value) return

  const validKeys = validCombinationKeys.value

  Object.keys(model.value.products).forEach(id => {
    const stored = model.value.products[id]

    const keyOnly = Object.fromEntries(
      Object.entries(stored).filter(([k]) => k !== "product" && k !== "is_leader")
    )

    if (!validKeys.has(normalizeKey(keyOnly))) {
      delete model.value.products[id]
    }
  })
}


watch(() => model.value.variants, syncProducts, { deep: true })
watch(() => model.value.groupBy, syncProducts)


const isLeaderByKey = (keyObj: Record<string, string>) => {
  return Object.values(model.value.products).some(
    p =>
      p.is_leader === true &&
      Object.keys(keyObj).every(k => p[k] === keyObj[k])
  )
}


const buildNodes = computed<Node[]>(() => {
  const variants = validVariants.value
  if (!variants.length) return []

  const getProduct = (keyObj: Record<string, string>) =>
    Object.values(model.value.products).find(p =>
      Object.keys(keyObj).every(k => p[k] === keyObj[k])
    )?.product ?? null

  if (variants.length === 1) {
    const v = variants[0]

    return v.options.map(opt => {
      const keyObj = { [v.label]: opt }

      return {
        key: keyObj,
        label: opt,
        product: getProduct(keyObj),
        is_leader: isLeaderByKey(keyObj)
      }
    })
  }

  const base = variants.find(v => v.label === model.value.groupBy)
  if (!base) return []

  const others = variants.filter(v => v.label !== base.label)

  return base.options.map(opt => {
    const parentKey = { [base.label]: opt }

    return {
      key: parentKey,
      label: opt,
      product: getProduct(parentKey),
      is_leader: isLeaderByKey(parentKey),
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
          product: getProduct(keyObj),
          is_leader: isLeaderByKey(keyObj)
        }
      })
    }
  })
})




const setProduct = (node: Node, val: any | null) => {
  const entryId = Object.keys(model.value.products).find(id =>
    Object.keys(node.key).every(k => model.value.products[id][k] === node.key[k])
  )


  if (!val) {
    if (!entryId) return

    delete model.value.products[entryId]
    return
  }

  model.value.products[val.id] = {
    ...node.key,
    product: {
      id: val.id,
      name: val.name,
      code: val.code,
      image: val.image_thumbnail,
      slug: val.slug
    },
    is_leader: false
  }
}


const setLeader = (node: Node, checked: boolean) => {
  Object.values(model.value.products).forEach(p => {
    p.is_leader = false
  })

  if (!checked) return

  const entry = Object.values(model.value.products).find(p =>
    Object.keys(node.key).every(k => p[k] === node.key[k])
  )

  if (entry) {
    entry.is_leader = true
  }
}

const isVariantValid = (v: Variant) => {
  const hasLabel = v.label?.trim().length > 0
  const validOptions = v.options.filter(o => o?.trim())

  return hasLabel && validOptions.length > 0
}

const toggleActive = (i: number) => {

  model.value.variants = model.value.variants.filter(
    v => v.label && v.label.trim() !== ""
  )


  model.value.variants.forEach(variant => {
    if (Array.isArray(variant.options)) {
      variant.options = variant.options.filter(o => o?.trim())
    }
  })


  const v = model.value.variants[i]
  if (!v) return


  if (!v.active) {
    if (!isVariantValid(v)) {
      alert("Variant name and at least one option are required")
      return
    }
  }


  model.value.variants.forEach((variant, idx) => {
    variant.active = idx === i ? !variant.active : false
  })


  if (model.value.variants.length === 1) {
    model.value.groupBy = model.value.variants[0].label
  }
}


const addVariant = () => {
  model.value.variants.forEach(v => (v.active = false))

  model.value.variants.push({
    label: "",
    options: [""],
    active: true
  })
}

const hasActiveVariant = computed(() =>
  model.value.variants.some(v => v.active)
)

const expandAll = () => {
  const map: Record<string, boolean> = {}
  buildNodes.value.forEach(node => {
    if (node.children?.length) {
      map[keyToString(node.key)] = true
    }
  })
  expanded.value = map
}

const collapseAll = () => {
  expanded.value = {}
}

watch(
  buildNodes,
  () => {
    expandAll()
  },
  { immediate: true }
)


const addOption = async (vi: number) => {
  model.value.variants[vi].options.push("")

  await nextTick()


  const inputs = optionRefs.value[vi]
  console.log('inputs', inputs)
  inputs?.[inputs.length - 1]?.focus()
}
const removeOption = (vi: number, oi: number) => {
  model.value.variants[vi].options.splice(oi, 1)
  syncProducts()
}

const deleteVariant = (i: number) => {
  model.value.variants.splice(i, 1)

  if (
    !model.value.groupBy ||
    !model.value.variants.some(v => v.label === model.value.groupBy)
  ) {
    model.value.groupBy = model.value.variants[0]?.label ?? ""
  }

  syncProducts()
}

const isAllExpanded = computed(() =>
  buildNodes.value.every(
    n => !n.children || expanded.value[keyToString(n.key)]
  )
)


const noLeader = computed(() => {
  return !Object.values(model.value.products).some(p => p.is_leader)
})


</script>

<template>
  <div class="flex justify-center mt-6">
    <div class="w-full max-w-6xl p-4 bg-white rounded-lg shadow space-y-4">

      <!-- VARIANTS -->
      <div>
        <label class="text-xs font-medium block">
          {{ trans('Variant Options') }} <span class="text-red-500">*</span>
        </label>
        <span v-if="model.variants.length < 1"
          class="text-xs text-gray-500 font-medium italic w-full block text-red-500">
          {{ trans('Variant option must be present') }}
        </span>

        <div v-for="(v, vi) in model.variants" :key="vi" class="border rounded mt-2">
          <!-- COLLAPSED -->
          <div v-if="!v.active" class="p-2 cursor-pointer" @click="toggleActive(vi)">
            <div class="text-sm font-medium">
              {{ v.label || "Untitled Variant" }}
            </div>
            <div class="text-xs text-gray-500">
              {{ v.options.filter(Boolean).join(", ") || trans("No options") }}
            </div>
          </div>

          <!-- ACTIVE -->
          <div v-else class="p-3 bg-gray-50 space-y-2">
            <div>
              <label class="text-xs font-medium">
                {{ trans('Option type name') }} <span class="text-red-500">*</span>
              </label>
              <PureInput v-model="v.label" :placeholder="trans('e.g. color, size')" />
            </div>

            <div>
              <label class="text-xs font-medium">
                {{ trans('Option value') }} <span class="text-red-500">*</span>
              </label>

              <div v-for="(opt, oi) in v.options" :key="oi" class="flex gap-2 mt-2">
                <PureInput v-model="v.options[oi]" class="flex-1"
                  @keydown.tab.prevent="oi === v.options.length - 1 && v.options[oi].trim() ? addOption(vi) : null"
                  :placeholder="trans('e.g. blue, red or S, M, L, XL')" @keydown.enter.prevent="toggleActive(vi)" :ref="el => {
                    if (!optionRefs[vi]) optionRefs[vi] = []
                    optionRefs[vi][oi] = el
                  }" />
                <button class="text-red-500" @click="removeOption(vi, oi)">
                  <FontAwesomeIcon :icon="faTimes" />
                </button>
              </div>
            </div>

            <div class="flex justify-between mt-3">
              <Button type="dashed" size="xs" @click="addOption(vi)">
                + {{ trans('Add') }}
              </Button>

              <div class="flex gap-2">
                <Button type="red_outline" size="xs" @click="deleteVariant(vi)">
                  {{ trans('Delete') }}
                </Button>
                <Button size="xs" :class="!isVariantValid(v) && 'opacity-50 cursor-not-allowed'"
                  :disabled="!isVariantValid(v)" @click="toggleActive(vi)">
                  {{ trans('Done') }}
                </Button>
              </div>
            </div>
          </div>
        </div>

        <!-- ADD VARIANT -->
        <div>
          <Button v-if="model.variants.length < 2" type="dashed" size="xs" class="mt-2" :icon="faPlus"
            @click="addVariant">
            {{ trans('Add Option') }}
          </Button>
        </div>


        <!-- GROUP BY -->
        <div class="border-t mt-6 pt-3" v-if="validVariants.length">
          <div class="flex items-center gap-2">
            <span class="text-sm"> {{ trans('Group by') }} </span>
            <select v-model="model.groupBy" class="border rounded px-2 py-1 text-sm w-[90px]">
              <option v-for="v in validVariants" :key="v.label" :value="v.label">
                {{ v.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="mt-5" v-if="model.variants.length > 0">
          <label class="text-xs font-medium block">
            {{ trans('List of Variants') }} <span class="text-red-500">*</span>
          </label>
          <span v-if="noLeader" class="text-xs text-gray-500 font-medium italic w-full block text-red-500">
            {{ trans('One of the products must be leader, it will show as default in webpage') }}
          </span>



          <!-- TABLE -->
          <div class="border rounded mt-4 overflow-visible">
            <table class="min-w-full table-fixed divide-y">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 w-[40px]">
                    <div class="w-5 h-5 border rounded bg-gray-100 flex items-center justify-center text-sm font-medium"
                      @click="isAllExpanded ? collapseAll() : expandAll()">
                      {{ isAllExpanded ? "−" : "+" }}
                    </div>
                  </th>

                  <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-1/2 text-start">
                    {{ trans('Variant') }}
                  </th>
                  <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-[120px] text-center">
                    {{ trans('Leader') }}
                  </th>
                  <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-1/2">
                    {{ trans('Product') }}
                  </th>
                </tr>
              </thead>

              <tbody class="divide-y">
                <template v-for="node in buildNodes" :key="keyToString(node.key)">
                  <!-- PARENT -->
                  <tr class="hover:bg-gray-50 h-[70px]">
                    <!-- Expand -->
                    <td class="px-4">
                      <div v-if="node.children"
                        class="w-5 h-5 border rounded bg-gray-100 flex items-center justify-center text-sm font-medium cursor-pointer"
                        @click="toggleExpand(keyToString(node.key))">
                        {{ expanded[keyToString(node.key)] ? "−" : "+" }}
                      </div>
                    </td>

                    <!-- Variant -->
                    <td class="px-4">
                      <span class="truncate font-medium">{{ node.label }}</span>
                    </td>

                    <!-- Leader -->
                    <td class="px-4 text-center">
                      <input v-if="!node.children" type="checkbox" :disabled="!node.product" :checked="node.is_leader"
                        @change="setLeader(node, $event.target.checked)"
                        class="w-4 h-4 accent-blue-600 disabled:opacity-40 cursor-pointer" />
                    </td>

                    <!-- Product -->
                    <td class="px-4">
                      <PureMultiselectInfiniteScroll v-if="!node.children" :model-value="node.product"
                        @update:model-value="val => setProduct(node, val)" :fetchRoute="master_assets_route"
                        valueProp="id" label-prop="name" :object="true" :caret="false"
                        :placeholder="trans('Select Product')">
                        <template #singlelabel="{ value }">
                          <div class="flex items-center gap-3 p-2">
                            <Image v-if="value.image_thumbnail" :src="value.image_thumbnail.main.original"
                              class="w-12 h-12 rounded object-cover" />
                            <div>
                              <div class="font-medium leading-none">{{ value.code }}</div>
                              <div class="flex justify-beetween mt-1 gap-5">
                                <div class="flex justify-between mt-1 text-xs text-gray-500">
                                  <span>{{ value.name || '-' }}</span>
                                </div>
                              </div>
                            </div>
                          </div>

                        </template>
                      </PureMultiselectInfiniteScroll>
                    </td>
                  </tr>


                  <!-- CHILD -->
                  <tr v-for="child in node.children" v-if="expanded[keyToString(node.key)]"
                    :key="keyToString(child.key)" class="hover:bg-gray-50 h-[70px]">
                    <!-- Empty expand column -->
                    <td class="px-4"></td>

                    <!-- Variant -->
                    <td class="px-4 text-sm text-gray-700 pl-10">
                      ↳ {{ child.label }}
                    </td>

                    <!-- Leader -->
                    <td class="px-4 text-center">
                      <input type="checkbox" :disabled="!child.product" :checked="child.is_leader"
                        @change="setLeader(child, $event.target.checked)"
                        class="w-4 h-4 accent-blue-600 disabled:opacity-40 cursor-pointer" />
                    </td>

                    <!-- Product -->
                    <td class="px-4">
                      <PureMultiselectInfiniteScroll :model-value="child.product"
                        @update:model-value="val => setProduct(child, val)" :fetchRoute="master_assets_route"
                        valueProp="id" label-prop="name" :object="true" :caret="false"
                        :placeholder="trans('Select Product')">
                        <template #singlelabel="{ value }">
                          <div class="flex items-center gap-3 p-2">
                            <Image v-if="value.image_thumbnail" :src="value.image_thumbnail.main.original"
                              class="w-12 h-12 rounded object-cover" />
                            <div>
                              <div class="font-medium leading-none">{{ value.code }}</div>
                              <div class="flex justify-beetween mt-1 gap-5">
                                <div class="flex justify-between mt-1 text-xs text-gray-500">
                                  <span>{{ value.name || '-' }}</span>
                                </div>
                              </div>
                            </div>
                          </div>

                        </template>
                      </PureMultiselectInfiniteScroll>
                    </td>
                  </tr>

                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>



<style scoped>
:deep(.multiselect-wrapper) {
  justify-content: space-between;
}
</style>
