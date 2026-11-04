export type OrderableAppointmentType = {
    id: number
    name: string
}

export const orderAppointmentTypes = <T extends OrderableAppointmentType>(appointmentTypes: T[], order?: number[] | null): T[] => {
    const positions = new Map((order ?? []).map((id, position) => [id, position]))

    return [...appointmentTypes].sort((first, second) => {
        const firstPosition = positions.get(first.id) ?? Number.POSITIVE_INFINITY
        const secondPosition = positions.get(second.id) ?? Number.POSITIVE_INFINITY
        if (firstPosition !== secondPosition) {
            return firstPosition - secondPosition
        }
        return first.name.localeCompare(second.name)
    })
}
