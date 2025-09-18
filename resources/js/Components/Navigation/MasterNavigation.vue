<script setup lang="ts">
import { computed } from 'vue';
import { trans } from "laravel-vue-i18n"
import ButtonWithLink from '../Elements/Buttons/ButtonWithLink.vue';
import Button from '../Elements/Buttons/Button.vue';
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus } from '@fal';

library.add(faPlus)

// Props definition
interface Props {
    isAddSubDepartment?: boolean;
    isAddFamilies?: boolean;
    isAddBoth?: boolean;
    subDepartmentRoute?: string;
    familiesRoute?: string;
    familiesEvent?: () => void;
}

const props = withDefaults(defineProps<Props>(), {
    isAddSubDepartment: false,
    isAddFamilies: false,
    isAddBoth: false,
    subDepartmentRoute: '',
    familiesRoute: '',
});

const buttonsToShow = computed(() => {
    const buttons = [];

    if ((props.isAddBoth || props.isAddSubDepartment) && props.subDepartmentRoute) {
        buttons.push({
            label: trans("Create Sub Department"),
            route_target: route(props.subDepartmentRoute, route().params),
            icon: faPlus,
            key: 'subDepartment'
        });
    }

    if (props.isAddBoth || props.isAddFamilies) {
        if (props.familiesRoute) {
            buttons.push({
                label: trans("Create Master Family"),
                route_target: route(props.familiesRoute, route().params),
                icon: faPlus,
                key: 'families',
                type: 'route'
            });
        } else if (props.familiesEvent) {
            buttons.push({
                label: trans("Create Master Family"),
                event: props.familiesEvent,
                icon: faPlus,
                key: 'families',
                type: 'event'
            });
        }
    }

    return buttons;
});
</script>

<template>
    <div class="border rounded-lg p-4">
        <div class="flex flex-col gap-4">
            <template v-for="button in buttonsToShow" :key="button.key">
                <ButtonWithLink 
                    v-if="button.type === 'route' || !button.type"
                    :label="button.label"
                    :url="button.route_target"
                    :icon="button.icon" 
                    type="secondary" 
                    full />
                <Button 
                    v-else-if="button.type === 'event'"
                    :label="button.label"
                    @click="button.event()"
                    :icon="button.icon" 
                    type="secondary" 
                    full />
            </template>
        </div>
    </div>
</template>