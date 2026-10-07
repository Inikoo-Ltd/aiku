<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Tag from "@/Components/Tag.vue"
import Icon from "@/Components/Icon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { faStop } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faStop)

defineProps<{
    data: {}
    tab?: string
}>()

const websiteDialogRoute = (websiteDialog: { ulid: string }) => route(
    "grp.org.shops.show.web.website_dialogs.show",
    {
        ...route().params,
        websiteDialog: websiteDialog.ulid,
    }
)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item: websiteDialog }">
            <Icon :data="websiteDialog.status" />
        </template>

        <template #cell(name)="{ item: websiteDialog }">
            <Link :href="websiteDialogRoute(websiteDialog)" class="primaryLink whitespace-nowrap px-2 py-1">
                {{ websiteDialog.name }}
            </Link>
        </template>

        <template #cell(closed_at)="{ item: websiteDialog }">
            <div class="flex items-center justify-end gap-x-2">
                <span v-if="websiteDialog.closed_at" class="whitespace-nowrap">
                    {{ useFormatTime(websiteDialog.closed_at, { formatTime: 'hm' }) }}
                </span>
                <span v-else>-</span>
            </div>
            <div v-if="websiteDialog.is_expired" class="inline-flex w-fit select-none items-center rounded border border-red-200 bg-red-100 px-1 py-0.5 text-xxs font-medium text-red-500">
                {{ ctrans("Expired") }}
            </div>
        </template>

        <template #cell(show_pages)="{ item: websiteDialog }">
            <div class="flex flex-wrap gap-1">
                <Tag v-for="page in websiteDialog.show_pages" :key="page" :label="page" noHoverColor />
            </div>
        </template>
    </Table>
</template>
