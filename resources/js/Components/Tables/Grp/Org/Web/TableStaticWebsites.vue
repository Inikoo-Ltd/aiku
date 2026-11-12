<!--
  - Author: Rifqi Taufiqurrohman
  - Created: Thu, 12 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBroadcastTower, faExternalLink, faEye, faSeedling, faSkull } from "@fal"

library.add(faSeedling, faBroadcastTower, faSkull, faExternalLink, faEye)

defineProps<{
    data: object
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(domain)="{ item }">
            <a :href="item.url" target="_blank" rel="noopener" v-tooltip="ctrans('Opens the live domain, which still shows the old host until its DNS points to Aiku')"
               class="whitespace-nowrap text-gray-700 hover:text-[var(--app-accent)] hover:underline">
                {{ item.domain }}
                <FontAwesomeIcon icon="fal fa-external-link" class="ml-1 text-[10px] text-gray-400" fixed-width aria-hidden="true" />
            </a>
        </template>
        <template #cell(preview)="{ item }">
            <a :href="item.preview_url" target="_blank" rel="noopener">
                <Button type="tertiary" size="xs" icon="fal fa-eye" :label="ctrans('Preview in Aiku')" />
            </a>
        </template>
    </Table>
</template>
