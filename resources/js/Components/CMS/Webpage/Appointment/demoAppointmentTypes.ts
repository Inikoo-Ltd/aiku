import type { AppointmentBookingResult, AppointmentTypeOption } from "./AppointmentBooking.vue"
import { ctrans } from "@/Composables/useTrans"

const toIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const weekdaySlots = (times: string[], days: number): Record<string, string[]> => {
    const slots: Record<string, string[]> = {}
    for (let offset = 1; offset <= days; offset++) {
        const day = new Date()
        day.setDate(day.getDate() + offset)
        if (day.getDay() !== 0 && day.getDay() !== 6) {
            slots[toIsoDate(day)] = times
        }
    }
    return slots
}

export const demoAppointmentTypes = (): AppointmentTypeOption[] => [
    {
        id: 1,
        name: ctrans("Showroom visit"),
        description: ctrans("See the full range in person and get advice from our team."),
        meeting_mode: "store_visit",
        meeting_mode_label: ctrans("Store visit"),
        location: ctrans("Our showroom"),
        duration_minutes: 45,
        slots: weekdaySlots(["10:00", "10:45", "11:30", "14:00", "14:45", "15:30"], 30),
    },
    {
        id: 2,
        name: ctrans("Video call"),
        description: ctrans("A guided tour of our products from wherever you are."),
        meeting_mode: "video_call",
        meeting_mode_label: ctrans("Video call"),
        location: null,
        duration_minutes: 30,
        slots: weekdaySlots(["09:00", "09:30", "13:00", "13:30"], 30),
    },
]

export const demoBook = (appointmentType: AppointmentTypeOption | undefined, date: string, time: string): Promise<AppointmentBookingResult> =>
    new Promise(resolve => setTimeout(() => resolve({
        appointment_type: appointmentType?.name ?? "",
        starts_at: `${date} ${time}`,
        ends_at: time,
        location: appointmentType?.location,
    }), 500))
