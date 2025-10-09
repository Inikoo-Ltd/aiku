<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useFormatTime } from "@/Composables/useFormatTime"
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Icon from '@/Components/Icon.vue'
import { trans } from 'laravel-vue-i18n'


library.add(faCheck, faTimes)

defineProps<{
    data: {}
    tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: storedItemAudit }">
            <Icon :data="storedItemAudit['state_icon']" class="px-1" />
        </template>

        <template #cell(pallet_customer_reference)="{ item: storedItemAudit }">
            {{ storedItemAudit.pallet_customer_reference }}
            <div v-if="storedItemAudit.pallet_customer_reference">
                {{ storedItemAudit.pallet_customer_reference }}
            </div>
            <div v-else class="text-gray-400 italic text-xs">
                {{ trans('No pallet customer\'s reference') }}
            </div>
        </template>

        <template #cell(description)="{ item }">
            <!-- edit type : {{ item.edit_type }} -->
            <div v-if="item.description?.model || item.description?.title || item.description?.after_title">
            <FontAwesomeIcon :icon="item.description.icon" fixed-width aria-hidden="true" class="pr-2" />
                <span v-if="item.description?.model">{{ item.description.model }}:</span>
                <Link v-if="item.description?.title && item.description.route?.name"
                      :href="route(item.description.route?.name, item.description.route?.parameters)" class="primaryLink">
                    {{ item.description.title }}
                </Link>
                <span v-else>&nbsp;{{ item.description.title }}</span>
                
                <div v-if="item.description.after_title" class="text-gray-400 italic text-xs">({{ item.description.after_title }})</div>
            </div>

            <div v-else>

            </div>
        </template>

        
        <template #cell(audited_at)="{ item: item }">
            <div class="text-gray-500 text-right">{{ useFormatTime(item.audited_at, { localeCode: locale.language.code, formatTime: "hms" }) }}</div>
        </template>
    </Table>
</template>
