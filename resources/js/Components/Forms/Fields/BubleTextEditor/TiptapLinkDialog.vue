<script setup lang="ts">
import { computed, ref, watch } from "vue"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faCheck } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { type LinkDialogValue, type LinkHrefFields, type LinkType, type ReadyMadeLink, buildLinkHref, findReadyMadeLink, parseLinkHref } from "@/Components/Forms/Fields/BubleTextEditor/linkHref"

library.add(faTimes, faCheck)

const props = defineProps<{
  show: boolean
  currentUrl?: string
  currentText?: string
  currentTitle?: string | null
  currentTarget?: string | null
  underline?: boolean
  showUnderlineOption?: boolean
  readyMadeLinks?: ReadyMadeLink[]
}>()

const READY_MADE = "ready_made"
type DialogLinkType = LinkType | typeof READY_MADE
const emit = defineEmits<{
  (e: "close"): void
  (e: "update", value: LinkDialogValue): void
}>()

const visible = ref(props.show)
const isEditing = !!props.currentUrl
const fields = ref<LinkHrefFields>(parseLinkHref(props.currentUrl))
const text = ref(props.currentText ?? "")
const title = ref(props.currentTitle ?? "")
const target = ref<"_blank" | "_self">(props.currentTarget === "_self" ? "_self" : "_blank")
const isUnderlined = ref<boolean>(props.underline ?? true)

watch(
  () => props.show,
  (val) => (visible.value = val)
)

const linkTypeOptions = computed<Array<{ label: string, value: DialogLinkType }>>(() => [
  ...(props.readyMadeLinks?.length ? [{ label: ctrans("Ready-made link"), value: READY_MADE as DialogLinkType }] : []),
  { label: ctrans("Open web page"), value: "web" },
  { label: ctrans("Send email"), value: "email" },
  { label: ctrans("Make a call"), value: "phone" },
  { label: ctrans("Send SMS"), value: "sms" },
])

const currentReadyMadeLink = findReadyMadeLink(props.readyMadeLinks, props.currentUrl)
const linkType = ref<DialogLinkType>(currentReadyMadeLink ? READY_MADE : fields.value.type)
const readyMadeKey = ref<string | null>(currentReadyMadeLink?.key ?? props.readyMadeLinks?.[0]?.key ?? null)
const isReadyMade = computed(() => linkType.value === READY_MADE)
const selectedReadyMadeLink = computed(() => props.readyMadeLinks?.find((link) => link.key === readyMadeKey.value) ?? null)

watch(linkType, (type) => {
  if (type !== READY_MADE) {
    fields.value.type = type
  }
})

const targetOptions = [
  { label: ctrans("New page"), value: "_blank" },
  { label: ctrans("Same page"), value: "_self" },
]

const href = computed(() => isReadyMade.value ? selectedReadyMadeLink.value?.url ?? "" : buildLinkHref(fields.value))
const isWebLink = computed(() => isReadyMade.value || fields.value.type === "web")

function closeDialog() {
  visible.value = false
  emit("close")
}

function update() {
  if (!href.value) {
    return
  }
  emit("update", {
    href: href.value,
    text: text.value,
    title: title.value,
    target: isWebLink.value ? target.value : "_self",
    underline: props.showUnderlineOption ? isUnderlined.value : undefined,
  })
  closeDialog()
}
</script>

<template>
  <Dialog
    v-model:visible="visible"
    :header="isEditing ? ctrans('Edit link') : ctrans('Insert link')"
    modal
    :draggable="false"
    :style="{ width: '32rem' }"
    :breakpoints="{ '640px': '95vw' }"
    @hide="closeDialog"
  >
    <form id="tiptap-link-dialog-form" class="flex flex-col gap-y-4" @submit.prevent="update">
      <div class="flex flex-col gap-y-1.5">
        <label for="link-dialog-type" class="text-sm text-gray-700">{{ ctrans("Link type") }}</label>
        <Select v-model="linkType" inputId="link-dialog-type" :options="linkTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
      </div>

      <div v-if="isReadyMade" class="flex flex-col gap-y-1.5">
        <label for="link-dialog-ready-made" class="text-sm text-gray-700">{{ ctrans("Link") }}</label>
        <Select v-model="readyMadeKey" inputId="link-dialog-ready-made" :options="readyMadeLinks" optionLabel="label" optionValue="key" class="w-full" />
        <p v-if="selectedReadyMadeLink" class="break-all text-xs text-gray-500">{{ selectedReadyMadeLink.url }}</p>
        <p class="text-xs text-gray-500">{{ ctrans("Tracking for this link is set automatically.") }}</p>
      </div>

      <div v-else-if="isWebLink" class="flex flex-col gap-y-1.5">
        <label for="link-dialog-url" class="text-sm text-gray-700">{{ ctrans("Url") }}</label>
        <InputText id="link-dialog-url" v-model="fields.url" class="w-full" placeholder="https://" autofocus />
      </div>

      <template v-else-if="fields.type === 'email'">
        <div class="flex flex-col gap-y-1.5">
          <label for="link-dialog-email" class="text-sm text-gray-700">{{ ctrans("Email address") }}</label>
          <InputText id="link-dialog-email" v-model="fields.email" type="email" class="w-full" placeholder="name@example.com" autofocus />
        </div>
        <div class="flex flex-col gap-y-1.5">
          <label for="link-dialog-subject" class="text-sm text-gray-700">{{ ctrans("Subject") }}</label>
          <InputText id="link-dialog-subject" v-model="fields.subject" class="w-full" />
        </div>
        <div class="flex flex-col gap-y-1.5">
          <label for="link-dialog-body" class="text-sm text-gray-700">{{ ctrans("Message") }}</label>
          <InputText id="link-dialog-body" v-model="fields.body" class="w-full" />
        </div>
      </template>

      <template v-else>
        <div class="flex flex-col gap-y-1.5">
          <label for="link-dialog-phone" class="text-sm text-gray-700">{{ ctrans("Phone number") }}</label>
          <InputText id="link-dialog-phone" v-model="fields.phone" type="tel" class="w-full" placeholder="+44 20 1234 5678" autofocus />
        </div>
        <div v-if="fields.type === 'sms'" class="flex flex-col gap-y-1.5">
          <label for="link-dialog-sms-body" class="text-sm text-gray-700">{{ ctrans("Message") }}</label>
          <InputText id="link-dialog-sms-body" v-model="fields.body" class="w-full" />
        </div>
      </template>

      <div v-if="showUnderlineOption" class="flex items-center gap-x-2">
        <Checkbox v-model="isUnderlined" inputId="link-dialog-underline" binary />
        <label for="link-dialog-underline" class="select-none text-sm text-gray-700">{{ ctrans("Underline link") }}</label>
      </div>

      <div class="flex flex-col gap-y-1.5">
        <label for="link-dialog-text" class="text-sm text-gray-700">{{ ctrans("Text to display") }}</label>
        <InputText id="link-dialog-text" v-model="text" class="w-full" />
      </div>

      <div class="flex flex-col gap-y-1.5">
        <label for="link-dialog-title" class="text-sm text-gray-700">{{ ctrans("Title") }}</label>
        <InputText id="link-dialog-title" v-model="title" class="w-full" />
      </div>

      <div v-if="isWebLink" class="flex flex-col gap-y-1.5">
        <label for="link-dialog-target" class="text-sm text-gray-700">{{ ctrans("Target") }}</label>
        <Select v-model="target" inputId="link-dialog-target" :options="targetOptions" optionLabel="label" optionValue="value" class="w-full" />
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end gap-x-3">
        <Button type="tertiary" :label="ctrans('Cancel')" @click="closeDialog">
          <template #icon>
            <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
          </template>
        </Button>
        <Button type="tertiary" :label="ctrans('Save')" :disabled="!href" @click="update">
          <template #icon>
            <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
          </template>
        </Button>
      </div>
    </template>
  </Dialog>
</template>
