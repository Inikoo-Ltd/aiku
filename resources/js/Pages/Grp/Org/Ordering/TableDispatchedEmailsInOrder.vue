<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { DispatchedEmail } from "@/types/dispatched-email";
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
    faEyeEvil
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from "@/Components/Icon.vue";
import { inject, ref} from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { useFormatTime } from "@/Composables/useFormatTime";
import Modal from "@/Components/Utils/Modal.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

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
    faEyeEvil
);
defineProps<{
    data: object,
    tab?: string
}>();






const showEmailPreview = ref(false);
const selectedEmail = ref<DispatchedEmail | null>(null);

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleString();
}

function dispatchedEmailRoute(dispatchedEmail: DispatchedEmail) {
  selectedEmail.value = dispatchedEmail;
  showEmailPreview.value = true;
}


const locale = inject("locale", aikuLocaleStructure);


</script>

<template>
    <div>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: dispatchedEmail }">
            <Icon v-if="dispatchedEmail.state" :data="dispatchedEmail.state" />
            <Icon v-if="dispatchedEmail.state_icon" :data="dispatchedEmail.state_icon" />
        </template>
        <template #cell(subject)="{ item: dispatchedEmail }">
            <div class="flex items-center gap-2">
                <span>{{ dispatchedEmail["subject"] }}</span>
                <span class="cursor-pointer" @click="() => { dispatchedEmailRoute(dispatchedEmail); }">
                    <FontAwesomeIcon :icon="faEyeEvil" />
                </span>
            </div>
        </template>
        <template #cell(sent_at)="{ item: dispatchedEmail }">
            {{ useFormatTime(dispatchedEmail.sent_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}
        </template>

    </Table>

      <!-- Email Preview Modal -->
    <Modal :show="showEmailPreview" @close="showEmailPreview = false" width="w-full max-w-lg">
      <div class="p-4">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Email Preview</h3>
        <div v-if="selectedEmail" class="space-y-4">
          <div>
            <p class="text-sm text-gray-500">To: {{ selectedEmail?.email_address }}</p>
            <p class="text-sm text-gray-500">Sent: {{ formatDate(selectedEmail?.sent_at) }}</p>
          </div>
          <div class="border-t border-gray-200 pt-4">
            <div class="bg-gray-50 p-4 rounded" v-html="selectedEmail?.body_preview"></div>
          </div>
        </div>
      </div>
    </Modal>
    </div>
</template>


