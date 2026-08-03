<script setup lang="ts">
import { inject } from "vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { faDebug, faHourglassHalf } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faDebug, faHourglassHalf)

const props = defineProps<{
    item: any
    allowWaiting?: boolean
    allowPickerSetNotPicked?: boolean
    size?: string
    fractionData?: number[] | null
}>()

const emits = defineEmits<{
    (e: "setAsWaiting", item: any): void
}>()

const locale = inject("locale", aikuLocaleStructure)
</script>

<template>
    <div v-if="!item.is_handled" class="flex items-center gap-x-2">
        <!-- Button: Not picked -->
        <ButtonWithLink
            v-if="!allowWaiting || allowPickerSetNotPicked"
            type="negative"
            icon="fal fa-debug"
            :size="size"
            :routeTarget="item.not_picking_route"
            :bindToLink="{ preserveScroll: true }"
            v-tooltip="ctrans('Set :numberNotPicked as not picked', { numberNotPicked: locale.number(item.quantity_to_pick ?? 0) })"
        >
            <template #label>
                <FractionDisplay v-if="fractionData" :fractionData="fractionData" />
                <span v-else>{{ locale.number(item.quantity_to_pick ?? 0) }}</span>
            </template>
        </ButtonWithLink>

        <!-- Button: Set as waiting -->
        <Button
            v-if="allowWaiting"
            type="tertiary"
            iconRight="fal fa-hourglass-half"
            :size="size"
            @click="emits('setAsWaiting', item)"
            v-tooltip="ctrans('Set :numberWaiting as waiting', { numberWaiting: locale.number(item.quantity_to_pick ?? 0) })"
        >
            <template #label>
                <FractionDisplay v-if="fractionData" :fractionData="fractionData" />
                <span v-else>{{ locale.number(item.quantity_to_pick ?? 0) }}</span>
            </template>
        </Button>
    </div>
</template>
