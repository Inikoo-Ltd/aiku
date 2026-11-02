<script setup lang="ts">
import { computed } from "vue"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faStoreAlt } from "@fal"

library.add(faStoreAlt)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        default_location?: string | null
    }
}>()

const defaultLocation = computed(() => props.fieldData.default_location ?? "")
const canUseShopAddress = computed(() => !!defaultLocation.value && props.form[props.fieldName] !== defaultLocation.value)

const useShopAddress = () => {
    props.form[props.fieldName] = defaultLocation.value
}

const fieldFocusClass = "focus:!border-[--app-accent]"
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <InputText
                v-model="form[fieldName]"
                :placeholder="ctrans('Address the customer comes to')"
                :invalid="!!form.errors?.[fieldName]"
                maxlength="255"
                :class="fieldFocusClass"
                class="min-w-0 flex-1 !py-1.5 !text-sm"
                :aria-label="ctrans('Appointment location')" />
            <Button v-if="canUseShopAddress" type="tertiary" size="s" :label="ctrans('Use shop address')" @click="useShopAddress">
                <template #icon>
                    <FontAwesomeIcon :icon="faStoreAlt" class="text-gray-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
        <p v-if="form.errors?.[fieldName]" class="mt-2 text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
