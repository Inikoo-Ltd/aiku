<script setup lang="ts">
import { ref, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import SelectQuery from "@/Components/SelectQuery.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import { set } from "lodash-es"
import { Panel } from "primevue"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
  show: boolean
  attribut?: {
    href: string
    type: "internal" | "external"
    id: string | null
    workshop: string | null
    target: "_self" | "_blank"
    rel?: "follow" | "nofollow" | null
  }
}>()
const emit = defineEmits(["close", "update"])

const visible = ref(props.show)
const selectKey = ref(0)
const selectedOption = ref<any>(null)

watch(() => props.show, val => (visible.value = val))

const form = useForm({
  href: props.attribut?.href ?? "",
  type: props.attribut?.type ?? "internal",
  workshop: props.attribut?.workshop ?? null,
  id: props.attribut?.id ?? null,
  target: props.attribut?.target ?? "_self",
  rel: props.attribut?.rel ?? null, // ✅ DEFAULT NULL
})

watch(
  () => props.attribut,
  val => {
    if (!val) return

    form.href = val.href ?? ""
    form.type = val.type ?? "internal"
    form.workshop = val.workshop ?? null
    form.id = val.id ?? null
    form.target = val.target ?? "_self"
    form.rel = val.rel ?? null // ✅ KEEP NULL IF NOT SET

    selectedOption.value =
      form.type === "internal"
        ? {
          id: val.id,
          href: val.href,
          path: val.href,
          workshop: val.workshop,
        }
        : null

    selectKey.value++
  },
  { immediate: true, deep: true }
)

function getRoute() {
  const params = {
    organisation: route().params.organisation,
    website: route().params.website,
  }

  return route().params.fulfilment
    ? route("grp.org.fulfilments.show.web.webpages.index", {
      ...params,
      fulfilment: route().params.fulfilment,
    })
    : route("grp.org.shops.show.web.webpages.index", {
      ...params,
      shop: route().params.shop,
    })
}

function handleSelect(e: any) {
  selectedOption.value = e
  set(form, "href", e?.href ?? null)
  set(form, "id", e?.id ?? null)
  set(form, "workshop", e?.workshop ?? null)
}

function closeDialog() {
  visible.value = false
  emit("close")
}

function update() {
  emit("update", form.data())
  emit("close")
}

const typeOptions = [
  { label: "Internal", value: "internal" },
  { label: "External", value: "external" },
]

const targetOptions = [
  { label: "In this page", value: "_self" },
  { label: "New page", value: "_blank" },
]

const relOptions = [
  { label: "Follow", value: "follow" },
  { label: "No Follow", value: "nofollow" },
]

const cleanCanonicalPath = (url) => {
  if (!url) return ''

  try {
    const parsed = new URL(url)
    return parsed.pathname.replace(/\/$/, '')
  } catch {
    // fallback jika bukan URL lengkap
    return url.replace(/^https?:\/\/[^/]+/, '').replace(/\/$/, '')
  }
}

</script>

<template>
  <Dialog v-model:visible="visible" modal header="Link Settings" class="w-full sm:w-[520px]" @hide="closeDialog"
    :contentStyle="{ overflowY: 'visible' }">
    <div class="flex flex-col space-y-6">

      <!-- TYPE -->
      <div class="space-y-3">
        <div class="text-sm font-semibold text-gray-700">Link Type</div>

        <div class="grid grid-cols-2 gap-3">
          <label v-for="option in typeOptions" :key="option.value" class="group flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition
             hover:border-gray-400" :class="form.type === option.value
              ? 'border-black bg-gray-50'
              : 'border-gray-200'">
            <input type="radio" class="hidden" v-model="form.type" :value="option.value" />

            <div class="flex h-4 w-4 items-center justify-center rounded-full border" :class="form.type === option.value
              ? 'border-black'
              : 'border-gray-300'">
              <div v-if="form.type === option.value" class="h-2 w-2 rounded-full bg-black" />
            </div>

            <span class="text-sm font-medium text-gray-800">
              {{ option.label }}
            </span>
          </label>
        </div>
      </div>

      <!-- TARGET -->
      <div class="space-y-3">
        <div class="text-sm font-semibold text-gray-700">Open Link In</div>

        <div class="grid grid-cols-2 gap-3">
          <label v-for="option in targetOptions" :key="option.value" class="group flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition
             hover:border-gray-400" :class="form.target === option.value
              ? 'border-black bg-gray-50'
              : 'border-gray-200'">
            <input type="radio" class="hidden" v-model="form.target" :value="option.value" />

            <div class="flex h-4 w-4 items-center justify-center rounded-full border" :class="form.target === option.value
              ? 'border-black'
              : 'border-gray-300'">
              <div v-if="form.target === option.value" class="h-2 w-2 rounded-full bg-black" />
            </div>

            <span class="text-sm font-medium text-gray-800">
              {{ option.label }}
            </span>
          </label>
        </div>
      </div>

      <!-- LINK -->
      <div class="space-y-2">
        <div class="text-sm font-medium text-gray-700">Link</div>

        <!-- Internal -->
        <div v-if="form.type === 'internal'">
          <SelectQuery :key="selectKey" :urlRoute="getRoute()" :object="true" fieldName="href" :value="selectedOption"
            :closeOnSelect="true" :searchable="true" label="href" :canClear="true" :clearOnSearch="true"
            :onChange="handleSelect">
            <template #placeholder>
              <div class="flex items-center justify-start w-full px-2 text-gray-800 truncate">
                {{ form?.path || form?.href || 'Select a page' }}
              </div>
            </template>
            <template #singlelabel="{ value }">
              <div class="flex items-center justify-start w-full px-2 text-gray-800 truncate">
                {{ value?.path || value?.href || 'Select a page' }}
              </div>
            </template>

            <template #option="{ option, isSelected, isPointed, search }">
              <div class="flex items-center justify-start w-full px-2 text-gray-800 truncate">
                {{ option?.path || option?.href }}

                <span v-if="option?.canonical_url && option?.path" class="ml-1 text-gray-500 text-xs">
                  – {{ cleanCanonicalPath(option.canonical_url) }}
                </span>
              </div>
            </template>
          </SelectQuery>
        </div>
        <!-- External -->
        <PureInput v-else v-model="form.href" placeholder="https://example.com" />
      </div>

      <!-- ADVANCED TOGGLE -->
      <Panel toggleable collapsed>
        <template #header>
          <div class="flex w-full items-center justify-between">
            <span class="text-sm font-medium text-gray-700">
              {{ trans('Advanced Settings') }}
            </span>
          </div>
        </template>

        <div class="space-y-4 rounded-lg bg-gray-50  mt-3">

          <!-- SEO -->
          <div class="space-y-2">
            <div class="text-sm font-medium text-gray-700">
              SEO (rel attribute)
            </div>

            <PureMultiselect v-model="form.rel" :options="relOptions" label="label" valueProp="value" mode="single"
              placeholder="Default (no rel)" clearable />

            <p class="text-xs text-gray-500">
              Leave empty for default behavior.
            </p>
          </div>

        </div>
      </Panel>

    </div>

    <!-- ACTIONS -->
    <template #footer>
      <div class="flex justify-end gap-3 pt-4 border-t w-full">
        <Button type="white" label="Cancel" @click="closeDialog" />
        <Button type="black" label="Apply" @click="update" />
      </div>
    </template>
  </Dialog>
</template>
