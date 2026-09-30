
export const setFormValue = (data: Object, fieldName: String|Array) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return data[fieldName]
    }
}

export const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
       /*  console.log(acc, key) */
        if (acc && typeof acc === "object" && key in acc){
            return acc[key]
        } 
        return null
    }, obj)
};