import { get, set, isEqual, cloneDeep } from "lodash-es"

/**
 * Seeds a form field with the rows a field component builds from its options.
 *
 * The defaults get their own copy: Inertia stores the value handed to `defaults()`
 * by reference, so sharing the array would make `isDirty` compare the rows against
 * themselves and the save button would never wake up.
 */
export const seedFormRows = <T>(form: any, fieldName: string, rows: T[]): void => {
	if (isEqual(get(form, fieldName), rows)) {
		return
	}

	form.defaults?.(fieldName, cloneDeep(rows))
	set(form, fieldName, rows)
}
