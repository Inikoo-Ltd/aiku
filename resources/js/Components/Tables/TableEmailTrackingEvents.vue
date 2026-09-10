<script setup lang="ts">
import Table from '@/Components/Table/Table.vue';
import {
    faBan,
    faClock,
    faDumpster,
    faEnvelopeOpen,
    faExclamationCircle,
    faExclamationTriangle,
    faHandPaper,
    faInboxIn,
    faMousePointer,
    faPaperPlane,
    faSpellCheck,
    faSquare,
    faTimesCircle,
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from '../Icon.vue'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { useFormatTime } from '@/Composables/useFormatTime'
import { faDesktopAlt } from '@far'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExternalLink } from '@fal'
import { faMobileAlt, faRobot } from '@fas'

library.add(
    faSpellCheck,
    faPaperPlane,
    faBan,
    faExclamationCircle,
    faInboxIn,
    faMousePointer,
    faExclamationTriangle,
    faSquare,
    faEnvelopeOpen,
    faDumpster,
    faHandPaper,
    faClock,
    faTimesCircle,
    faDesktopAlt,
    faMobileAlt,
    faRobot
);
const props = defineProps<{
    data: object,
    tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)


</script>

<template>
    <Table :resource="data" :name="tab"  class="mt-5">
        <template #cell(type)="{ item: emailTrackingEvent }">
            <Icon :data="emailTrackingEvent.type" />
        </template>
        <template #cell(label)="{ item: emailTrackingEvent }">
            <a v-if="emailTrackingEvent.url" :href="emailTrackingEvent.url" target="_blank" rel="noopener noreferrer"
                :title="emailTrackingEvent.url"
                class="text-gray-700 hover:text-gray-900 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
                {{ emailTrackingEvent.label }}
                <FontAwesomeIcon :icon="faExternalLink" class="text-gray-400 text-xs" fixed-width aria-hidden="true" />
            </a>
            <span v-if="emailTrackingEvent.element"
                class="ml-1 font-mono text-xs bg-gray-100 text-gray-700 rounded px-1.5 py-0.5">{{ emailTrackingEvent.element }}</span>
        </template>
        <template #cell(device)="{ item: emailTrackingEvent }">
            <Icon :data="emailTrackingEvent.device" /> <span>{{ emailTrackingEvent.device['tooltip'] }}</span>
        </template>
        <template #cell(date)="{ item: emailTrackingEvent }">
            {{ useFormatTime(emailTrackingEvent.date, { localeCode: locale.language.code, formatTime: "aiku" }) }}
        </template>
    </Table>
</template>
