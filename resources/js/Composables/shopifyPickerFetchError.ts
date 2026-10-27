export const shopifyPickerFetchErrorText = (error: any, fallback: string): string => {
    const message = error?.response?.data?.message

    return typeof message === "string" && message !== "" ? message : fallback
}
