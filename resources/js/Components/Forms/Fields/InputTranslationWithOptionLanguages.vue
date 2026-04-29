<script setup lang="ts">
import { ref, computed, watch } from "vue"
import InputText from "primevue/inputtext"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"

interface Language {
  code: string
  name?: string
}

const props = defineProps<{
  form: Record<string, any>
  fieldName: string
  fieldData: {
    languages: Record<string, Language>
  }
}>()

/* OPTIONS */
const languageOptions = computed(() => {
  return Object.values(props.fieldData.languages || {})
})

/* LOCAL STATE */
const localValues = ref<Record<string, string>>({})

/* 🔥 GUARD FLAG */
const isSyncing = ref(false)

/* OPTIONAL: equality check */
const isEqual = (a: any, b: any) => {
  return JSON.stringify(a) === JSON.stringify(b)
}

/* FROM FORM → LOCAL */
watch(
  () => props.form[props.fieldName],
  (val) => {
    if (isSyncing.value) return

    if (val && typeof val === "object") {
      if (isEqual(val, localValues.value)) return

      isSyncing.value = true
      localValues.value = { ...val }
      isSyncing.value = false
    }
  },
  { immediate: true, deep: true }
)

/* FROM LOCAL → FORM */
watch(
  localValues,
  (val) => {
    if (isSyncing.value) return

    if (isEqual(val, props.form[props.fieldName])) return

    isSyncing.value = true
    props.form[props.fieldName] = { ...val }
    isSyncing.value = false
  },
  { deep: true }
)

/* TAG DATA */
const editedLanguages = computed(() => {
  return Object.keys(localValues.value)
})

const getLabel = (code: string) => {
  const lang = languageOptions.value.find(l => l.code === code)
  return lang?.name || code
}

const isFilled = (code: string) => {
  const v = localValues.value[code]
  return typeof v === "string" && v.trim().length > 0
}

/* DIALOG */
const showDialog = ref(false)
const tempLanguage = ref<string | null>(null)
const tempValue = ref("")

const openDialog = (code: string | null = null) => {
  if (code) {
    tempLanguage.value = code
    tempValue.value = localValues.value[code] || ""
  } else {
    tempLanguage.value = null
    tempValue.value = ""
  }

  showDialog.value = true
}

const saveLanguage = () => {
  if (!tempLanguage.value) return

  localValues.value = {
    ...localValues.value,
    [tempLanguage.value]: tempValue.value
  }

  showDialog.value = false
}

const removeLanguage = (code: string) => {
  const newVal = { ...localValues.value }
  delete newVal[code]
  localValues.value = newVal
}
</script>

<template>
  <div class="space-y-4">

    <!-- HEADER -->
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-700">
          Translations
        </p>
        <p class="text-xs text-gray-400">
          Add and manage language content
        </p>
      </div>

      <Button
        label="Language"
        type="create"
        @click="openDialog()"
      />
    </div>

    <!-- TAGS -->
    <div
      v-if="editedLanguages.length"
      class="flex flex-wrap gap-2"
    >
      <div
        v-for="code in editedLanguages"
        :key="code"
        class="flex items-center gap-2 px-3 py-1.5 text-xs rounded-full border"
        :class="isFilled(code)
          ? 'bg-green-50 border-green-200 text-green-700'
          : 'bg-gray-50 border-gray-200 text-gray-500'"
      >
        <!-- EDIT -->
        <span
          class="cursor-pointer font-medium"
          @click="openDialog(code)"
        >
          {{ getLabel(code) }}
        </span>

        <!-- STATUS -->
        <span class="text-[10px] opacity-70">
          {{ isFilled(code) ? 'Filled' : 'Empty' }}
        </span>

        <!-- DELETE -->
        <span
          class="cursor-pointer text-red-400 hover:text-red-600"
          @click.stop="removeLanguage(code)"
        >
          ✕
        </span>
      </div>
    </div>

    <!-- EMPTY -->
    <div
      v-else
      class="text-sm text-gray-400 border border-dashed rounded p-3 text-center"
    >
      No translations added yet
    </div>

    <!-- DIALOG -->
    <Dialog
      v-model:visible="showDialog"
      modal
      header="Language Content"
      :style="{ width: '420px' }"
      :content-style="{ overflow: 'visible', paddingLeft: '20px', paddingRight: '20px' }"
    >
      <div class="space-y-4">

        <!-- SELECT -->
        <div>
          <label class="text-xs text-gray-500 block mb-1">
            Language
          </label>

          <PureMultiselect
            v-model="tempLanguage"
            :options="languageOptions"
            label="name"
            valueProp="code"
            :multiple="false"
            class="w-full"
            :searchable="true"
          />
        </div>

        <!-- INPUT -->
        <div>
          <label class="text-xs text-gray-500 block mb-1">
            Content
          </label>

          <InputText
            v-model="tempValue"
            :placeholder="tempLanguage
              ? `Input for ${tempLanguage}`
              : 'Enter content'"
            class="w-full"
          />
        </div>

        <!-- ACTION -->
        <div class="flex justify-end gap-2 pt-2">
          <Button as="div" type="gray" label="Cancel" @click="showDialog = false" />
          <Button
            as="div"
            label="Save"
            type="save"
            :disabled="!tempLanguage"
            @click="saveLanguage"
          />
        </div>

      </div>
    </Dialog>

  </div>
</template>