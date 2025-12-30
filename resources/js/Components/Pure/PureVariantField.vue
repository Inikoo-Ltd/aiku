<script setup lang="ts">
import { ref, computed, watch, watchEffect } from "vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTrashAlt } from "@far"
import { faPlus } from "@fal"
import { trans } from "laravel-vue-i18n"
import type { routeType } from "@/types/route"
import Image from "@/Components/Image.vue"

/* ---------------- types ---------------- */

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

/* ---------------- props ---------------- */

defineProps<{
  pageHead: PageHeadingTypes
  title: string
  master_assets_route: routeType
}>()

/* ---------------- model ---------------- */

const model = defineModel<DataVariants>({ required: true })

const createEmptyModel = (): DataVariants => ({
  variants: [],
  groupBy: "",
  products: {}
})

/* ✅ INIT SAAT MODEL NULL */
watchEffect(() => {
  if (!model.value) {
    model.value = createEmptyModel()
    return
  }

  model.value.variants ??= []
  model.value.groupBy ??= ""
  model.value.products ??= {}
})

/* ---------------- state ---------------- */

const expanded = ref<Record<string, boolean>>({})

/* ---------------- helpers ---------------- */

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

/* ---------------- variants ---------------- */

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

/* ---------------- sync products ---------------- */

const validCombinationKeys = computed<Set<string>>(() => {
  if (!validVariants.value.length) return new Set()
  return new Set(
    getCombinations(validVariants.value).map(c => normalizeKey(c))
  )
})

const syncProducts = () => {
  const validKeys = validCombinationKeys.value

  Object.keys(model.value.products).forEach(id => {
    const stored = model.value.products[id]

    const keyOnly = Object.fromEntries(
      Object.entries(stored).filter(([k]) => k !== "product")
    )

    if (!validKeys.has(normalizeKey(keyOnly))) {
      delete model.value.products[id]
    }
  })
}

watch(() => model.value.variants, syncProducts, { deep: true })
watch(() => model.value.groupBy, syncProducts)

/* ---------------- build nodes ---------------- */

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

  // ===== 1 VARIANT =====
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

  // ===== MULTI VARIANT =====
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


/* ---------------- actions ---------------- */

const setProduct = (node: Node, val: any | null) => {
  const entryId = Object.keys(model.value.products).find(id =>
    Object.keys(node.key).every(k => model.value.products[id][k] === node.key[k])
  )

  // ❌ PRODUCT DI-CLEAR
  if (!val) {
    if (!entryId) return

    delete model.value.products[entryId]
    return
  }

  // ✅ PRODUCT DISET
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



const isLeader = (node: Node) => {
  return Object.values(model.value.products).some(
    p =>
      p.is_leader &&
      Object.keys(node.key).every(k => p[k] === node.key[k])
  )
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

const toggleActive = (i: number) => {
  model.value.variants.forEach(
    (v, idx) => (v.active = idx === i ? !v.active : false)
  )
}

const addVariant = () => {
  model.value.variants.forEach(v => (v.active = false))
  model.value.variants.push({ label: "", options: [""], active: true })
}

const addOption = (i: number) => {
  model.value.variants[i].options.push("")
}

const removeOption = (vi: number, oi: number) => {
  model.value.variants[vi].options.splice(oi, 1)
  syncProducts()
}

const deleteVariant = (i: number) => {
  model.value.variants.splice(i, 1)
  model.value.groupBy = model.value.variants[0]?.label ?? ""
  syncProducts()
}

const noLeader = computed(() => {
  return !Object.values(model.value.products).some(p => p.is_leader)
})

</script>

<template>
  <div class="flex justify-center mt-6">
    <div class="w-full max-w-2xl p-4 bg-white rounded-lg shadow space-y-4">

      <!-- VARIANTS -->
      <div>
        <label class="text-xs font-medium block">
          {{ trans('Variant Options') }} <span class="text-red-500">*</span>
        </label>
        <span v-if="model.variants.length < 1" class="text-xs text-gray-500 font-medium italic w-full block text-red-500">
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
                {{ trans('Name') }} <span class="text-red-500">*</span>
              </label>
              <PureInput v-model="v.label" placeholder="Color, Size"/>
            </div>

            <div>
              <label class="text-xs font-medium">
                {{ trans('Options') }} <span class="text-red-500">*</span>
              </label>

              <div v-for="(opt, oi) in v.options" :key="oi" class="flex gap-2 mt-2">
                <PureInput v-model="v.options[oi]" placeholder="Value" class="flex-1" />
                <button class="text-red-500" @click="removeOption(vi, oi)">
                  <FontAwesomeIcon :icon="faTrashAlt" />
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
                <Button size="xs" @click="toggleActive(vi)">
                  {{ trans('Done') }}
                </Button>
              </div>
            </div>
          </div>
        </div>

        <!-- ADD VARIANT -->
        <Button v-if="model.variants.length < 2" type="dashed" size="xs" class="mt-2" :icon="faPlus"
          @click="addVariant">
          {{ trans('Add Variant') }}
        </Button>

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
            A product leader must be selected
          </span>
          
          <!-- TABLE -->
          <div class="border rounded mt-4 overflow-visible">
            <table class="min-w-full table-fixed divide-y">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-[120px] text-center">
                    {{ trans('Leader') }}
                  </th>
                  <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-1/2">
                    {{ trans('Variant') }}
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
                    <td class="px-4 text-center">
                      <input v-if="!node.children" type="checkbox" :disabled="!node.product" :checked="node.is_leader"
                        @change="setLeader(node, $event.target.checked)"
                        class="w-4 h-4 accent-blue-600 disabled:opacity-40 cursor-pointer" />
                    </td>

                    <td class="px-4">
                      <div class="flex items-center">
                        <div v-if="node.children"
                          class="w-5 h-5 mr-2 border rounded bg-gray-100 flex items-center justify-center text-sm font-medium"
                          @click="toggleExpand(keyToString(node.key))">
                          {{ expanded[keyToString(node.key)] ? "−" : "+" }}
                        </div>

                        <span class="truncate">{{ node.label }}</span>
                      </div>
                    </td>

                    <td v-if="!node.children" class="px-4">
                      <PureMultiselectInfiniteScroll :model-value="node.product"
                        @update:model-value="val => setProduct(node, val)" :fetchRoute="master_assets_route"
                        valueProp="id" label-prop="name" :object="true" :caret="false"
                        :placeholder="trans('Select Product')">
                        <template #singlelabel="{ value }">
                          <div class="flex items-center gap-3 p-2">
                            <Image v-if="value.image_thumbnail" :src="value.image_thumbnail.main.original"
                              class="w-12 h-12 rounded object-cover" />
                            <div>
                              <div class="font-medium leading-none">{{ value.name }}</div>
                              <div class="flex justify-beetween mt-1 gap-5">
                                <div class="flex justify-between mt-1 text-xs text-gray-500">
                                  <span>{{ value.code || '-' }}</span>
                                </div>
                              </div>
                            </div>
                          </div>

                        </template>

                      </PureMultiselectInfiniteScroll>
                    </td>
                    <td v-else />
                  </tr>

                  <!-- CHILD -->
                  <tr v-for="child in node.children" v-if="expanded[keyToString(node.key)]" :key="keyToString(child.key)"
                    class="hover:bg-gray-50 h-[70px]">
                    <td class="px-4 text-center">
                      <input type="checkbox" :disabled="!child.product" :checked="child.is_leader"
                        @change="setLeader(child, $event.target.checked)"
                        class="w-4 h-4 accent-blue-600 disabled:opacity-40 cursor-pointer" />
                    </td>
                    <td class="px-8 text-sm text-gray-700">
                      ↳ {{ child.label }}
                    </td>
                    <td class="p-4">
                      <PureMultiselectInfiniteScroll :model-value="child.product"
                        @update:model-value="val => setProduct(child, val)" :fetchRoute="master_assets_route"
                        valueProp="id" label-prop="name" :object="true" :caret="false"
                        :placeholder="trans('Select Product')">
                        <template #singlelabel="{ value }">
                          <div class="flex items-center gap-3 p-2">
                            <Image v-if="value.image_thumbnail" :src="value.image_thumbnail.main.original"
                              class="w-12 h-12 rounded object-cover" />
                            <div>
                              <div class="font-medium leading-none">{{ value.name }}</div>
                              <div class="flex justify-beetween mt-1 gap-5">
                                <div class="flex justify-between mt-1 text-xs text-gray-500">
                                  <span>{{ value.code || '-' }}</span>
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
