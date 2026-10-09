<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faStoreAlt, faVideo } from "@fal"

library.add(faCheck, faTimes, faStoreAlt, faVideo)

defineProps<{
    data: {}
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(is_active)="{ item }">
            <Icon :data="item.is_active_icon" />
        </template>

        <template #cell(name)="{ item }">
            <Link
                :href="route('grp.org.shops.show.crm.appointments.types.edit', { organisation: route().params.organisation, shop: route().params.shop, appointmentType: item.slug })"
                class="primaryLink">
                {{ item.name }}
            </Link>
        </template>

        <template #cell(meeting_mode)="{ item }">
            <span class="inline-flex items-center gap-x-1.5">
                <Icon :data="item.meeting_mode_icon" />
                {{ item.meeting_mode }}
            </span>
        </template>

        <template #cell(duration_minutes)="{ item }">
            {{ ctrans(":minutes min", { minutes: item.duration_minutes }) }}
        </template>
    </Table>
</template>
