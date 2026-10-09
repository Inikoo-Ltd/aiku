<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendarCheck, faCheckCircle, faUserSlash, faTimesCircle } from "@fal"

library.add(faCalendarCheck, faCheckCircle, faUserSlash, faTimesCircle)

defineProps<{
    data: {}
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

        <template #cell(when)="{ item }">
            <Link
                :href="route('grp.org.shops.show.crm.appointments.edit', { organisation: route().params.organisation, shop: route().params.shop, appointment: item.id })"
                class="primaryLink whitespace-nowrap">
                {{ item.when }}
            </Link>
        </template>

        <template #cell(contact_name)="{ item }">
            <div>
                <Link v-if="item.visitor_type === 'Customer' && item.visitor_slug"
                    :href="route('grp.org.shops.show.crm.customers.show', { organisation: route().params.organisation, shop: route().params.shop, customer: item.visitor_slug })"
                    class="secondaryLink">
                    {{ item.contact_name }}
                </Link>
                <Link v-else-if="item.visitor_type === 'Prospect' && item.visitor_slug"
                    :href="route('grp.org.shops.show.crm.prospects.show', { organisation: route().params.organisation, shop: route().params.shop, prospect: item.visitor_slug })"
                    class="secondaryLink">
                    {{ item.contact_name }}
                </Link>
                <span v-else>{{ item.contact_name }}</span>
                <span v-if="item.visitor_type"
                    class="ml-1.5 rounded border border-[--app-accent-muted] bg-[--app-accent-soft] px-1 py-px text-[10px] text-gray-600">
                    {{ item.visitor_type === 'Customer' ? ctrans('Customer') : ctrans('Prospect') }}
                </span>
                <div class="text-xs text-gray-500">{{ [item.email, item.phone].filter(Boolean).join(" · ") }}</div>
            </div>
        </template>

        <template #cell(staff_name)="{ item }">
            <span v-if="item.staff_name">{{ item.staff_name }}</span>
            <span v-else class="text-gray-400">—</span>
        </template>
    </Table>
</template>
