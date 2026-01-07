<script setup lang="ts">
import { DatePicker } from 'v-calendar'
import 'v-calendar/style.css'

const props = defineProps<{
    data: Object
}>()


// Attribute for DatePicker
const attrs = [
    {
        key: "today",
        highlight: {
            color: "gray",
            fillMode: "outline",
        },
        dates: new Date(),
    },
    {
        dates: { repeat: { weekdays: 5 } },
        dot: {
            color: 'red',
            class: 'qwezxc',
            isComplete: true,
        },
        popover: {
            label: 'Take Michelle to festivals.',
            visibility: 'hover',
            hideIndicator: false,
        },
    },
    {
        // Object
        content: {
            color: 'purple',
            style: {
                fontStyle: 'italic',
            },
        },
        dates: [
            new Date(2024, 0, 12),
            new Date(2024, 0, 26),
            new Date(2024, 0, 15),
        ],
    },
]

const isDateSameDay = (date1: Date, date2: Date) => {
    return date1.getFullYear() === date2.getFullYear() && date1.getMonth() === date2.getMonth() && date1.getDate() === date2.getDate()
}
</script>


<template  layout="Grp">
    <div class="w-full mx-auto mt-5">
        <DatePicker expanded :attributes="attrs">
            <template #day-content="{ day, attributes }">
                <!-- {{ console.log() }} -->

                <div class="rounded-sm h-20 px-1 py-0.5"
                    :class="[
                        isDateSameDay(day.date, new Date()) ? 'bg-lime-100' : ' ',
                    ]"
                >
                    <div class="text-sm text-slate-600">
                        {{ day.day }}
                    </div>
                    <div class="">{{ attributes[0]?.popover?.label }}</div>
                </div>
            </template>
            <!-- <template #day-popover>
                aaaaaaaaaaaaaaaa
            </template> -->
        </DatePicker>
    </div>
</template>

<style lang="scss" scoped>
.vc-container {
    position: relative;
    display: inline-flex;
    width: max-content;
    height: max-content;
    font-family: var(--vc-font-family);
    color: var(--vc-color);
    background-color: var(--vc-bg);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    -webkit-tap-highlight-color: transparent;

    &,
    & * {
        box-sizing: border-box;

        &:focus {
            outline: none;
        }
    }

    /* Hides double border within popovers */
    & .vc-container {
        @apply border border-red-500
    }
}

.vc-bordered {
    @apply border border-blue-600
}

.vc-expanded {
    @apply min-w-full
}

.vc-transparent {
    @apply bg-transparent
}

.vc-date-picker-content {
    @apply bg-lime-300 p-2;

    .vc-container {
        @apply bg-lime-300 p-2;
    }
}

.vc-weeks {
    @apply divide-x-2 divide-black/10 border border-black/10 p-0
}

.vc-week {
    @apply divide-x-2 divide-y-2 divide-black/10
}

.vc-weekday-1,
.vc-weekday-7 {
    // For title Sunday and Saturday
    @apply text-red-500 bg-red-100
}

.vc-week {
    .on-left,
    .on-right {
        // For column in Sunday and Saturday
        @apply bg-red-300/10;
    }
}

.is-not-in-month {
    @apply bg-gray-500/10 text-red-500 #{!important};
}

</style>

