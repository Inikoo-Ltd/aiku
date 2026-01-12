<script setup lang="ts">
import { computed, watch } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationCircle, faCheckCircle } from "@fas"
import { faCopy } from "@fal"
import { faSpinnerThird, faSave } from "@fad"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

import PureVariantField from "@/Components/Pure/PureVariantField.vue"

library.add(
  faExclamationCircle,
  faCheckCircle,
  faSpinnerThird,
  faCopy,
  faSave
)

const props = defineProps<{
  form: any
  fieldName: string
  fieldData?: {
    verification?: boolean
    save_route: {
      name: string
      parameters?: Record<string, any>
    }
  }
}>()

watch(
  () => props.form.errors,
  (errorBag) => {
    if (!errorBag || !Object.keys(errorBag).length) return

    notify({
      title: "Something went wrong",
      data: { html: [...new Set(Object.values(errorBag).flat())].join("<br>") },
      type: "error",
      duration: 5000
    })
  }
)


const sanitizeVariants = (raw: any) => {
  if (!raw) return raw

  const variants = (raw.variants ?? [])
    .filter(v => v.label?.trim())
    .map(v => ({
      label: v.label.trim(),
      options: (v.options ?? []).filter(o => o?.trim())
    }))
    .filter(v => v.options.length)

  const groupBy = variants.some(v => v.label === raw.groupBy)
    ? raw.groupBy
    : null

  const validKeys = new Set(
    variants.flatMap(v => v.options.map(o => `${v.label}=${o}`))
  )

  const products: Record<string, any> = {}

  Object.entries(raw.products ?? {}).forEach(([id, p]: any) => {
    const keyOnly = Object.entries(p)
      .filter(([k]) => !["product", "is_leader", "all_child_has_webpage"].includes(k))
      .reduce((a, [k, v]) => ({ ...a, [k]: v }), {})

    if (
      !Object.entries(keyOnly).every(([k, v]) =>
        validKeys.has(`${k}=${v}`)
      )
    ) return

    products[id] = {
      ...keyOnly,
      product: p.product,
      is_leader: !!p.is_leader,
      all_child_has_webpage: p.all_child_has_webpage,
    }
  })

  return { variants, groupBy, products }
}

const isVariantValid = computed(() => {
  const data = props.form[props.fieldName]
  if (!data?.groupBy) return false
  if (!Object.values(data.products ?? {}).length) return false

  return Object.values(data.products).some(
    (p: any) => p.is_leader === true
  )
})

const isButtonDisabled = computed(() =>
  props.form.processing ||
  !props.form.isDirty ||
  !isVariantValid.value
)


const handleSubmit = () => {
  if (isButtonDisabled.value) return

  if (!props.fieldData?.save_route?.name) {
    notify({
      title: "Route missing",
      text: "Save route is not defined",
      type: "error"
    })
    return
  }

  props.form.transform((data: any) => ({
    ...data,
    [props.fieldName]: sanitizeVariants(data[props.fieldName])
  }))

  props.form.post(
    route(
      props.fieldData.save_route.name,
      props.fieldData.save_route.parameters
    ),
    {
      preserveScroll: true,
      onSuccess: () => {
        notify({
          title: "Saved",
          type: "success",
          duration: 3000
        })
      },
      onError: () => {
        notify({
          title: "Something went wrong",
          text: trans("Please try again"),
          type: "error"
        })
      }
    }
  )
}
</script>

<template>
  <div class="flex items-start gap-2">
    <div class="flex-1">
      <PureVariantField v-model="form[fieldName]" v-bind="fieldData" />
    </div>

    <span class="flex-shrink-0">
      <div
        v-if="!fieldData?.verification"
        type="button"
        class="h-9 align-bottom text-center"
        :disabled="isButtonDisabled"
        @click="handleSubmit"
      >
        <FontAwesomeIcon
          v-if="form.processing"
          icon="fad fa-spinner-third"
          class="text-2xl animate-spin"
        />
         <FontAwesomeIcon v-else icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
    </div>

      <FontAwesomeIcon
        v-else
        icon="fas fa-question"
        class="h-8 text-gray-300"
      />
    </span>
  </div>
</template>
