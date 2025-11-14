<script setup lang="ts">
import { ref, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import SelectQuery from "@/Components/SelectQuery.vue"
import { set } from "lodash-es"

const props = defineProps<{
  show: boolean
  attribut?: {
    href: string
    type: string
    id: string
    workshop: string
    target: string
  }
}>()

const emit = defineEmits(["close", "update"])
const selectKey = ref(0)
// Local reactive state for dialog visibility
const visible = ref(props.show)

watch(
  () => props.show,
  (val) => {
    visible.value = val
  }
)

// Initialize form
const form = useForm({
  href: props.attribut?.href || "",
  type: props.attribut?.type || "internal",
  workshop: props.attribut?.workshop || null,
  id: props.attribut?.id || null,
  target: props.attribut?.target || "_self",
})

// Selected option state for internal link
const selectedOption = ref<any>(null)

watch(
  () => props.attribut,
  (val) => {
    if (val) {
      form.href = val.href || ""
      form.type = val.type || "internal"
      form.workshop = val.workshop || null
      form.id = val.id || null
      form.target = val.target || "_self"

      if (form.type === "internal") {
        selectedOption.value = {
          id: val.id,
          href: val.href,
          path: val.href,
          workshop: val.workshop,
        }
      } else {
        selectedOption.value = null
      }
      selectKey.value++
    }
  },
  { immediate: true, deep: true }
)

// Simplified route resolver
function getRoute() {
  const params = {
    organisation: route().params["organisation"],
    website: route().params["website"],
  }

  if (route().params["fulfilment"]) {
    return route("grp.org.fulfilments.show.web.webpages.index", {
      ...params,
      fulfilment: route().params["fulfilment"],
    })
  }

  return route("grp.org.shops.show.web.webpages.index", {
    ...params,
    shop: route().params["shop"],
  })
}

// When selecting internal link
function handleSelect(e: any) {
  selectedOption.value = e
  set(form, "url", e?.url)
  set(form, "href", e?.href)
  set(form, "canonical_url", e?.canonical_url)
  set(form, "id", e?.id)
  set(form, "workshop", e?.workshop)
  set(form, "id", e?.id)
}

// Dialog control
function closeDialog() {
  visible.value = false
  emit("close")
}

function update() {
  emit("update", form.data())
  emit("close")
}

const options = [
  { label: "Internal", value: "internal" },
  { label: "External", value: "external" },
]

const target = [
  { label: "In this page", value: "_self" },
  { label: "New Page", value: "_blank" },
]
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Link Setting" :closable="true" class="w-full sm:w-[500px]"
    @hide="closeDialog" :contentStyle="{ overflowY: 'visible' }">
    <div class="flex flex-col space-y-4">
      <!-- Type -->
      <div>
        <div class="select-none text-sm text-gray-600 mb-2">Type</div>
        <div class="flex space-x-4">
          <label v-for="option in options" :key="option.value" class="flex items-center space-x-2">
            <input type="radio" :value="option.value" v-model="form.type" class="form-radio" />
            <span>{{ option.label }}</span>
          </label>
        </div>
      </div>

      <!-- Target -->
      <div>
        <div class="select-none text-sm text-gray-600 mb-2">Target</div>
        <div class="flex space-x-4">
          <label v-for="option in target" :key="option.value" class="flex items-center space-x-2">
            <input type="radio" :value="option.value" v-model="form.target" class="form-radio" />
            <span>{{ option.label }}</span>
          </label>
        </div>
      </div>

      <!-- Internal Link -->
      <div v-if="form.type === 'internal'">
        <div class="select-none text-sm text-gray-600 mb-2">Link</div>
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
        </SelectQuery>
      </div>

      <!-- External Link -->
      <div v-if="form.type === 'external'">
        <div class="select-none text-sm text-gray-600 mb-2">Link</div>
        <PureInput v-model="form.href" placeholder="https://example.com" />
      </div>

      <!-- Buttons -->
      <div class="flex justify-end space-x-3 pt-3">
        <Button type="white" label="Cancel" @click="closeDialog" />
        <Button type="black" label="Apply" @click="update" />
      </div>
    </div>
  </Dialog>
</template>
