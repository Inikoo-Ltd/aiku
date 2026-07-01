<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Table from '@/Components/Table/Table.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import { capitalize } from '@/Composables/capitalize'
import { trans } from 'laravel-vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faRobot, faHandHoldingHeart, faMoon, faHourglassHalf, faClock } from '@fal'
import { faTrash, faPencil } from '@fortawesome/free-solid-svg-icons'
import { PageHeadingTypes } from '@/types/PageHeading'

library.add(faRobot, faHandHoldingHeart, faMoon, faHourglassHalf, faClock, faTrash, faPencil)

const props = defineProps<{
    pageHead: PageHeadingTypes
    organisationId: number
    title: string
    data: any
}>()

const orgSlug = computed(() => (route().params as any)?.organisation)

const triggerIcon: Record<string, { icon: string; color: string }> = {
    welcome:  { icon: 'fal fa-hand-holding-heart', color: 'text-green-500' },
    offline:  { icon: 'fal fa-moon',               color: 'text-indigo-500' },
    waiting:  { icon: 'fal fa-hourglass-half',     color: 'text-yellow-500' },
    no_reply: { icon: 'fal fa-clock',              color: 'text-orange-500' },
}

function editRoute(id: number): string {
    return route('grp.org.chat.automations.edit', { organisation: orgSlug.value, chatAutomation: id })
}

function toggle(id: number): void {
    router.patch(
        route('grp.models.org.chat.automation.toggle', { organisation: props.organisationId, chatAutomation: id }),
        {},
        { preserveScroll: true, only: ['data', 'flash'] }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Table :resource="data" :name="'automations'" class="mt-5">
        <template #cell(is_enabled)="{ item }">
            <button
                type="button"
                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                :class="item.is_enabled ? 'bg-green-500' : 'bg-gray-300'"
                v-tooltip="item.is_enabled ? trans('Enabled') : trans('Disabled')"
                @click="toggle(item.id)"
            >
                <span
                    class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                    :class="item.is_enabled ? 'translate-x-5' : 'translate-x-1'"
                />
            </button>
        </template>

        <template #cell(name)="{ item }">
            <Link :href="editRoute(item.id)" class="primaryLink font-medium">{{ item.name }}</Link>
        </template>

        <template #cell(trigger_label)="{ item }">
            <div class="flex items-center gap-x-1.5 whitespace-nowrap text-sm">
                <FontAwesomeIcon
                    :icon="triggerIcon[item.trigger_type]?.icon ?? 'fal fa-robot'"
                    :class="triggerIcon[item.trigger_type]?.color ?? 'text-gray-400'"
                    fixed-width
                />
                {{ item.trigger_label }}
            </div>
        </template>

        <template #cell(shop_name)="{ item }">
            <span class="text-sm text-gray-600">{{ item.shop_name ?? '—' }}</span>
        </template>

        <template #cell(sent_count)="{ item }">
            <span class="text-sm text-gray-600">{{ (item.sent_count ?? 0).toLocaleString() }}</span>
        </template>

        <template #cell(action)="{ item }">
            <div class="flex items-center gap-2">
                <Link :href="editRoute(item.id)">
                    <Button v-tooltip="trans('Edit')" type="secondary" icon="fa-pencil" size="s" />
                </Link>
                <ModalConfirmationDelete
                    :routeDelete="{
                        name: 'grp.models.org.chat.automation.delete',
                        parameters: { organisation: props.organisationId, chatAutomation: item.id },
                    }"
                    :title="trans('Are you sure you want to delete this automated message?')"
                    :noLabel="trans('Delete')"
                    noIcon="fal fa-trash"
                >
                    <template #default="{ changeModel }">
                        <Button v-tooltip="trans('Delete')" @click="() => changeModel()" type="negative" icon="fa-trash" size="s" />
                    </template>
                </ModalConfirmationDelete>
            </div>
        </template>
    </Table>
</template>
