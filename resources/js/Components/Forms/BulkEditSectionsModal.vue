<!--
  BulkEditSectionsModal: apply the same form values to many selected records at once.

  Reference implementation: Trade Units bulk edit
    - Frontend: resources/js/Pages/Grp/Goods/TradeUnits.vue and TradeUnitFamily.vue
    - Blueprint: app/Actions/Goods/TradeUnit/UI/Traits/WithTradeUnitEditSections.php -> getTradeUnitsBulkEdit()
    - Endpoints: app/Actions/Goods/TradeUnit/UpdateBulkTradeUnitGpsr.php, UpdateBulkTradeUnitLabelInfo.php
    - Routes: routes/grp/web/models/stock/stock.php (grp.models.trade_units.bulk_update_*)
    - Tests: tests/Feature/TradeUnitLabelInfoCascadeTest.php

  How it works
    - Left: one nav entry per section. Middle: that section's form (scrolls). Right: the selected items (removable).
    - Every section has its own Inertia form, built fresh from the blueprint each time the modal opens.
      Switching sections keeps what was typed in the others.
    - "Replace" submits only the section on screen. The modal stays open so another section can be pushed after.
    - Nav icons: amber triangle = unsaved changes in that section, green check = pushed and untouched since.
      Nothing is shown for a section that was not touched.
    - There is no close (X) button: it closes with the Close button in the footer or a click outside the modal.
      Closing keeps nothing, the next open starts from fresh forms again.

  Blueprint (props.sections), built in PHP. Fields use the same blueprint as EditModel (getComponent(field.type)):
    sections: {
      [sectionKey]: {
        label: string,
        icon?: string,             // e.g. 'fa-light fa-stamp', register the icon in library.add below if new
        fields: {                  // same shape as an EditModel blueprint section's fields, values are the fresh defaults
          [fieldName]: { type, label, value, information?, hasOther?: { name, value }, ...component props }
        },
        updateRoute: { name, parameters, method? }   // method defaults to patch
      }
    }
    Build the fields with a PHP method that takes the model (or null for a fresh form), so the Edit page and the
    bulk edit share one definition. Example: getTradeUnitLabelInfoFields(?array) / getTradeUnitGpsrFields(?TradeUnit).

  Payload sent to updateRoute
    - Every field of the section, as the form holds it, plus each field's hasOther.name value.
    - 'checkbox' fields (array of { key, label, value }) are sent as the list of the checked keys.
    - props.itemsKey => array of props.items ids, e.g. trade_units: [1, 2, 3].

  Backend endpoint checklist
    - Validate itemsKey as required|array|min:1 with each id existing in the group.
    - Require every field of the section (required / present), so a partial payload can never half override records.
    - Loop over the records and call the model's normal Update action, so its hydrators and cascades still run.
    - Return nothing (void) like other Inertia model actions, the page reloads its props with preserveState.
    - Gate the blueprint prop on canEdit and use the edit authorisation trait on the endpoint.

  Props
    - sections: blueprint above.
    - items: selected records, needs id and optionally code, name, image_thumbnail (image sources for <Image>).
    - itemsKey: payload key for the ids.
    - itemsLabel: plural noun used in the texts, e.g. ctrans('trade units').
    - itemsIcon: icon of the model, shown next to itemsLabel at the top left under "Bulk edit".
      Pass the imported icon object (e.g. faAtom from @fal) so it does not need a library.add.
    - v-model:visible.

  Header: top left shows "Bulk edit" + itemsIcon + itemsLabel, the middle heading is the current section label.

  Events
    - removeItem(id): the parent must drop the id from its selection (e.g. untick the table row).
      The modal closes itself when the last item is removed.

  Keeping items in sync with a Table: see TableTradeUnits.vue (onSelectRow keeps the row objects across pages,
  exposes deselectTradeUnit and clearSelection).
-->

<script setup lang="ts">
import { computed, inject, ref, shallowRef } from "vue"
import { InertiaForm, useForm } from "@inertiajs/vue3"
import { cloneDeep } from "lodash-es"
import Dialog from "primevue/dialog"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle, faStamp, faBiohazard, faTimes, faInfoCircle } from "@fal"
import { faCheckCircle, faExclamationTriangle as fasExclamationTriangle } from "@fas"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { getComponent } from "@/Composables/Listing/FieldFormList"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faStamp, faBiohazard, faInfoCircle)

export interface BulkEditSection {
    label: string
    icon?: string
    fields: Record<string, any>
    updateRoute: routeType
}

export interface BulkEditItem {
    id: number
    code?: string
    name?: string
    image_thumbnail?: Record<string, any> | null
}

const props = defineProps<{
    sections: Record<string, BulkEditSection>
    items: BulkEditItem[]
    itemsKey: string
    itemsLabel: string
    itemsIcon?: string | object
}>()

const emits = defineEmits<{
    (e: "removeItem", itemId: number): void
}>()

const isVisible = defineModel<boolean>("visible", { default: false })

const layout: any = inject("layout")

const sectionKeys = computed(() => Object.keys(props.sections))
const currentSectionKey = ref<string>(sectionKeys.value[0])
const currentSection = computed(() => props.sections[currentSectionKey.value])

const buildFormData = (section: BulkEditSection) => {
    const formData: Record<string, any> = {}

    for (const [fieldName, fieldData] of Object.entries(section.fields)) {
        formData[fieldName] = cloneDeep(fieldData.value)

        if (fieldData.hasOther) {
            formData[fieldData.hasOther.name] = fieldData.hasOther.value
        }
    }

    return formData
}

const buildForms = () => {
    const forms: Record<string, InertiaForm<Record<string, any>>> = {}

    for (const [sectionKey, section] of Object.entries(props.sections)) {
        forms[sectionKey] = useForm(buildFormData(section))
    }

    return forms
}

const forms = shallowRef(buildForms())
const formsVersion = ref(0)
const savedSectionKeys = ref<string[]>([])

const currentForm = computed(() => forms.value[currentSectionKey.value])

const isSectionUnsaved = (sectionKey: string) => !!forms.value[sectionKey]?.isDirty
const isSectionSaved = (sectionKey: string) => savedSectionKeys.value.includes(sectionKey) && !isSectionUnsaved(sectionKey)

const resetForms = () => {
    forms.value = buildForms()
    formsVersion.value++
    currentSectionKey.value = sectionKeys.value[0]
    savedSectionKeys.value = []
}

const removeItem = (itemId: number) => {
    emits("removeItem", itemId)

    if (props.items.length <= 1) {
        isVisible.value = false
    }
}

const transformSectionData = (section: BulkEditSection, data: Record<string, any>) => {
    const transformedData = { ...data }

    for (const [fieldName, fieldData] of Object.entries(section.fields)) {
        if (fieldData.type === "checkbox" && Array.isArray(transformedData[fieldName])) {
            transformedData[fieldName] = transformedData[fieldName]
                .filter((option: { value: boolean }) => option.value)
                .map((option: { key: string }) => option.key)
        }
    }

    return transformedData
}

const submit = () => {
    const sectionKey = currentSectionKey.value
    const section = currentSection.value
    const form = currentForm.value

    form
        .transform((data) => ({
            ...transformSectionData(section, data),
            [props.itemsKey]: props.items.map((item) => item.id),
        }))
        .submit(section.updateRoute.method ?? "patch", route(section.updateRoute.name, section.updateRoute.parameters), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                form.defaults()
                if (!savedSectionKeys.value.includes(sectionKey)) {
                    savedSectionKeys.value.push(sectionKey)
                }
                notify({
                    title: ctrans("Success"),
                    text: ctrans(":section applied to :count :items", { section: section.label, count: String(props.items.length), items: props.itemsLabel }),
                    type: "success",
                })
            },
            onError: () => {
                notify({
                    title: ctrans("Something went wrong"),
                    text: ctrans("Please check the form and try again"),
                    type: "error",
                })
            },
        })
}
</script>

<template>
    <Dialog
        v-model:visible="isVisible"
        modal
        dismissableMask
        :showHeader="false"
        :closable="false"
        :style="{ width: '90vw', maxWidth: '1400px' }"
        :contentStyle="{ height: '82vh', padding: '0' }"
        @show="resetForms"
    >
        <div class="flex h-full gap-5 p-5">
            <aside class="w-60 shrink-0 flex flex-col">
                <div class="shrink-0 px-1 pb-4">
                    <div class="text-sm text-gray-500">{{ ctrans("Bulk edit") }}</div>
                    <div class="flex items-center gap-2 text-lg font-semibold text-gray-800">
                        <FontAwesomeIcon v-if="itemsIcon" :icon="itemsIcon" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                        <span class="truncate capitalize">{{ itemsLabel }}</span>
                    </div>
                </div>
                <div class="flex-1 bg-gray-50/50">
                <div
                    v-for="sectionKey in sectionKeys"
                    :key="sectionKey"
                    :class="[
                        sectionKey === currentSectionKey ? 'navigationSecondActive' : 'navigationSecond',
                        'cursor-pointer px-3 py-2 flex items-center gap-2 text-sm font-medium',
                    ]"
                    :style="sectionKey === currentSectionKey
                        ? {
                            'border-left': `4px solid ${layout?.app?.theme[2]}`,
                            'background-color': `color-mix(in srgb, ${layout?.app?.theme[2]} 20%, white)`,
                            color: `color-mix(in srgb, ${layout?.app?.theme[3]} 50%, black)`,
                        }
                        : { 'border-left': '4px solid transparent' }"
                    @click="currentSectionKey = sectionKey"
                >
                    <FontAwesomeIcon v-if="sections[sectionKey].icon" :icon="sections[sectionKey].icon" class="flex-shrink-0 -ml-1 h-4 w-4 text-gray-400" fixed-width aria-hidden="true" />
                    <span class="truncate">{{ sections[sectionKey].label }}</span>
                    <FontAwesomeIcon
                        v-if="isSectionUnsaved(sectionKey)"
                        v-tooltip="ctrans('Unsaved changes')"
                        :icon="fasExclamationTriangle"
                        class="ml-auto shrink-0 text-xs text-amber-500"
                        fixed-width
                        aria-hidden="true"
                    />
                    <FontAwesomeIcon
                        v-else-if="isSectionSaved(sectionKey)"
                        v-tooltip="ctrans('Saved to the selected :items', { items: itemsLabel })"
                        :icon="faCheckCircle"
                        class="ml-auto shrink-0 text-xs text-green-500"
                        fixed-width
                        aria-hidden="true"
                    />
                </div>
                </div>
            </aside>

            <div class="flex-1 min-w-0 flex flex-col gap-4">
                <div class="shrink-0">
                    <div class="text-lg font-semibold text-gray-800">{{ currentSection.label }}</div>
                    <div class="text-sm text-gray-500">
                        {{ ctrans("Set up :section once and give it to every selected :items.", { section: currentSection.label, items: itemsLabel }) }}
                    </div>
                </div>

                <div :key="`${currentSectionKey}-${formsVersion}`" class="flex-1 min-h-0 overflow-y-auto [scrollbar-width:thin] border border-gray-200 rounded-lg px-4 pt-5 pb-10">
                    <dl
                        v-for="(fieldData, fieldName) in currentSection.fields"
                        :key="fieldName"
                        class="sm:grid sm:grid-cols-3 sm:gap-4 pb-8 last:pb-0"
                    >
                        <dt class="text-sm font-medium text-gray-400">
                            <div class="inline-flex items-start leading-none">
                                {{ fieldData.label }}
                                <div v-if="fieldData.information" v-tooltip="fieldData.information" class="opacity-50 hover:opacity-100 cursor-pointer ml-1">
                                    <FontAwesomeIcon icon="fal fa-info-circle" class="text-gray-500" fixed-width aria-hidden="true" />
                                </div>
                            </div>
                        </dt>
                        <dd class="sm:col-span-2 text-sm text-gray-700">
                            <component
                                :is="getComponent(fieldData.type)"
                                :form="currentForm"
                                :fieldName="fieldName"
                                :options="fieldData.options"
                                :fieldData="fieldData"
                            />
                        </dd>
                    </dl>
                </div>

                <div class="shrink-0 flex gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-800">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-600" fixed-width aria-hidden="true" />
                    <div class="text-sm">
                        <div class="font-semibold">
                            {{ ctrans("The current :section setup of these :count :items will be replaced.", { section: currentSection.label, count: String(items.length), items: itemsLabel }) }}
                        </div>
                        <div class="mt-0.5">
                            {{ ctrans("Every field above is applied as shown, including the ones you leave untouched.") }}
                        </div>
                    </div>
                </div>

                <div class="shrink-0 flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                    <Button :label="ctrans('Close')" type="tertiary" @click="isVisible = false" />
                    <Button
                        :label="ctrans('Replace :section on :count :items', { section: currentSection.label, count: String(items.length), items: itemsLabel })"
                        :loading="currentForm.processing"
                        :disabled="!items.length"
                        @click="submit"
                    />
                </div>
            </div>

            <div class="w-72 shrink-0 self-start max-h-full flex flex-col border border-gray-200 rounded-lg overflow-hidden">
                <div class="shrink-0 px-3 py-2 border-b border-gray-200 bg-gray-50">
                    <div class="text-sm font-semibold text-gray-800">
                        {{ ctrans(":count :items selected", { count: String(items.length), items: itemsLabel }) }}
                    </div>
                    <div class="text-xs text-gray-500">{{ ctrans("Remove any you do not want to change.") }}</div>
                </div>
                <div class="overflow-y-auto [scrollbar-width:thin] divide-y divide-gray-100">
                    <div v-for="item in items" :key="item.id" class="flex items-center gap-3 px-3 py-2">
                        <Image :src="item.image_thumbnail" imageCover class="w-8 aspect-square rounded overflow-hidden shrink-0" />
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-gray-800 truncate">{{ item.code }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ item.name }}</div>
                        </div>
                        <button
                            v-tooltip="ctrans('Remove from selection')"
                            type="button"
                            class="shrink-0 px-1 text-gray-400 hover:text-red-500"
                            :disabled="currentForm.processing"
                            @click="removeItem(item.id)"
                        >
                            <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                </div>
                <p v-if="currentForm.errors[itemsKey]" class="shrink-0 px-3 py-2 text-sm text-red-600">{{ currentForm.errors[itemsKey] }}</p>
            </div>
        </div>
    </Dialog>
</template>
