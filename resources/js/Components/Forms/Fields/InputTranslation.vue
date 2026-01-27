<script setup lang="ts">
import { ref, computed } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLanguage } from "@far"
import { faMale } from "@fas"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import { trans } from "laravel-vue-i18n"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Toggle from "@/Components/Pure/Toggle.vue"

interface Language {
  code: string
  [key: string]: any
}

const props = defineProps<{
  form: Record<string, any>
  fieldName: string
  fieldData: {
    main?: string
    reviewed?: boolean
    disable?: boolean
    show_follow_master?: boolean
    follow_master?: boolean
    language_from?: string
    language_to?: string
    languages: Record<string, Language>
  }
  updateRoute: {
    name: string
    parameters: []
  }
}>()

const emits = defineEmits()

const loading = ref(false)


const languagesTo = ref<Language>(
  Object.values(props.fieldData.languages).find(
    (l: Language) => l.code === props.fieldData.language_to
  ) || Object.values(props.fieldData.languages)[0]
)

if (typeof props.form[props.fieldName] !== "string") {
  props.form[props.fieldName] = ""
}


const isDisabled = computed(() =>
  props.fieldData.disable || !props.fieldData.main || loading.value
)


const langLabel = computed(() => languagesTo.value.code)
const isLoadingFollowMaster = ref(false);

const generateTranslateAI = async () => {
  if (isDisabled.value) return

  loading.value = true
  try {
    const { data } = await axios.post(
      route("grp.models.translate", {
        languageFrom: props.fieldData.language_from || "en",
        languageTo: languagesTo.value.code || "en",
      }),
      { text: props.fieldData.main },
      { timeout: 10000 } // 10 seconds
    )

    if (data) {
      props.form[props.fieldName] = data
      emits("update:form", { ...props.form })

      notify({
        title: trans("Translation Completed"),
        text: trans("Translation generated successfully."),
        type: "success",
      })
    }
  } catch (error: any) {
    const isTimeout = error.code === "ECONNABORTED"
    notify({
      title: trans("Translation Error"),
      text: isTimeout
        ? trans("Translation request timed out. Please try again.")
        : error.response?.data?.message || trans("Failed to generate translation."),
      type: "error",
    })
  } finally {
    loading.value = false
  }
}

const capitalizeFirstLetter = (text: string) => {
  if (text.length === 0) {
    return "";
  }
  return text.charAt(0).toUpperCase() + text.slice(1);
}

const changeValue = (async () => {
  props.fieldData.follow_master = !props.fieldData.follow_master;
  isLoadingFollowMaster.value = true;
  await axios.patch(route(props.updateRoute.name, props.updateRoute.parameters), {
      ["follow_master_"+props.fieldName]: props.fieldData.follow_master
  }).finally(() => {
    isLoadingFollowMaster.value = false;
    let textDisplay = props.fieldData.follow_master ? 
      trans(':_fieldname will follow master', {_fieldname: capitalizeFirstLetter(props.fieldName)}) 
      : trans(':_fieldname stops following master', {_fieldname: capitalizeFirstLetter(props.fieldName)});
    notify({
      title: trans('Success'),
      text: textDisplay,
      type: 'success'
    })
  });
})

</script>

<template>
  <div class="space-y-3">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
      <div v-if="fieldData.show_follow_master" class="px-3 py-1 flex col-span-2 items-end justify-items-center align-middle w-full">
        <span class="align-middle h-full w-full text-end mr-3 font-semibold">
          {{ trans('Follow Master') }}
        </span>
        <Toggle
          v-tooltip="trans('Turning this option on would make it so that this item will follow its master counterpart')"
          :modelValue="fieldData.follow_master"
          @update:modelValue="changeValue()"
          :loading="isLoadingFollowMaster"
        >
        </Toggle>
      </div>
      <div class="rounded-md border p-2 bg-white relative">
        <div
          class="absolute left-3 top-1/2 -translate-y-1/2
                 h-5 w-5 flex items-center justify-center
                 bg-indigo-100 text-[#4B0082] rounded-md"
        >
          <FontAwesomeIcon
            :icon="faOctopusDeploy"
            v-tooltip="trans(':_fieldName of the Master', {_fieldName: capitalizeFirstLetter(props.fieldName)})"
            class="h-3.5 w-3.5"
          />
        </div>
        <div class="h-8 pl-9 pr-2 text-sm bg-gray-50 border border-gray-200 rounded-md flex items-center text-gray-700">
          {{ props.fieldData.main }}
        </div>
      </div>

      <!-- TRANSLATION INPUT -->
      <div class="rounded-md border p-2 bg-white flex items-center gap-2">
        <p class="w-fit text-[11px] font-medium text-gray-500 uppercase tracking-wide"  v-tooltip="languagesTo?.name">
          {{ langLabel }}
        </p>

        <div class="relative flex-1">
          <input
            v-model="props.form[props.fieldName]"
            :disabled="isDisabled"
            type="text"
            placeholder="Translation..."
            class="h-8 w-full pr-16 text-sm bg-gray-50 border border-gray-200 rounded-md
                   focus:outline-none focus:bg-white focus:border-primary-500
                   disabled:opacity-60"
          />

          <button
            v-if="props.fieldData.reviewed"
            type="button"
            class="absolute right-8 top-1/2 -translate-y-1/2 h-6 w-6 flex items-center justify-center bg-white text-gray-600"
            v-tooltip="trans('already reviewed by user')"
          >
            <FontAwesomeIcon :icon="faMale" class="h-3.5 w-3.5 button-primary" />
          </button>

          <button
            type="button"
            :disabled="loading"
            @click="generateTranslateAI"
            class="absolute right-1 top-1/2 -translate-y-1/2 h-6 w-6 flex items-center justify-center rounded-md border bg-white text-gray-600 hover:bg-gray-100 disabled:opacity-50"
            v-tooltip="trans('get translation from AI')"
            v-if="fieldData.main"
          >
            <FontAwesomeIcon v-if="!loading" :icon="faLanguage" class="h-3.5 w-3.5" />
            <LoadingIcon v-else />
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.button-primary {
  color: var(--theme-color-4) !important;
}
</style>
