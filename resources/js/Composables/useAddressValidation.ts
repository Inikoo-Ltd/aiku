export interface AddressFieldFormat {
	label?: string
	required?: boolean
}

export interface MissingAddressField {
	name: string
	label: string
}

const isBlankAddressLine = (line: unknown): boolean => ["", "0"].includes(String(line ?? "").trim())

export const missingRequiredAddressFields = (
	countryFields: Record<string, AddressFieldFormat> = {},
	address: Record<string, any> = {},
): MissingAddressField[] =>
	Object.entries(countryFields ?? {})
		.filter(([name, fieldFormat]) => fieldFormat?.required && isBlankAddressLine(address?.[name]))
		.map(([name, fieldFormat]) => ({ name, label: fieldFormat.label || name }))

export interface AdministrativeArea {
	name: string
}

export const isKnownAdministrativeArea = (
	administrativeAreas: AdministrativeArea[] = [],
	administrativeArea?: string | null,
): boolean => (administrativeAreas ?? []).some((area) => area?.name === administrativeArea)

export const picksAdministrativeAreaFromList = (
	administrativeAreas: AdministrativeArea[] = [],
	administrativeArea?: string | null,
): boolean =>
	(administrativeAreas ?? []).length > 0 &&
	(!administrativeArea || isKnownAdministrativeArea(administrativeAreas, administrativeArea))
