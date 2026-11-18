/*
 * Author: Vika Aqordi
 * Created on: 2026-11-18 10:00
 * Github: https://github.com/aqordeon
 * Copyright: 2026
 */

export type SupplierProductState = { value: string, label: string, icon: object }

export type SupplierProductStateOption = { value: string, label: string }

export type SupplierProductStateRow = { id: number, code: string, name: string, state: SupplierProductState | null }

const DISCONTINUED_STATES = ["discontinuing", "discontinued"]

export const isDiscontinuedState = (state: string | null | undefined) => DISCONTINUED_STATES.includes(state ?? "")

export const stateSelectPt = {
    label: { class: "!text-xs" },
    option: ({ context }: { context: { disabled: boolean } }) => ({
        class: context.disabled ? "!cursor-not-allowed !text-gray-400 !opacity-100" : "",
    }),
}
