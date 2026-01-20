<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Created: Thursday, 8 Jan 2026 11:33:00 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import {
    faCheck,
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
    faVirus,
    faEnvelope,
    faBan
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from "../Icon.vue";
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { useFormatTime } from "@/Composables/useFormatTime";
import { RouteParams } from "@/types/route-params";
import Button from "@/Components/Elements/Buttons/Button.vue";

library.add(
    faSpellCheck,
    faPaperPlane,
    faExclamationCircle,
    faVirus,
    faInboxIn,
    faMousePointer,
    faExclamationTriangle,
    faSquare,
    faEnvelopeOpen,
    faMousePointer,
    faDumpster,
    faHandPaper,
    faCheck,
    faTimesCircle,
    faEnvelope,
    faBan
);

const props = defineProps<{
    data: any[]
    tab?: string
}>()

const emits = defineEmits<{
    (e: 'select-snapshot', snapshot: any): void
}>()

const locale = inject("locale", aikuLocaleStructure);

const useTemplate = async (id: number) => {
    try {
        const { data } = await axios.get(
            route('grp.json.mailshot.template', {
                mailshot: id,
            })
        )

        const html = data.page

        if (!html) {
            console.warn("HTML kosong")
            return
        }

        emits("select-snapshot", html)

    } catch (error) {
        console.error("Gagal ambil template", error)
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item }">
            <Icon v-if="item.state" :data="item.state" />
        </template>
        <template #cell(actions)="{ item }">
            <div class="flex items-center justify-between gap-2">
                <Button :type="'create'" label="Clone Me" :size="'xxs'" @click="useTemplate(item.id)" />
            </div>
        </template>
        <template #cell(created_at)="{ item }">
            <div class="text-sm">
                {{ useFormatTime(item.created_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>
    </Table>
</template>
