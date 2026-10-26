type TabOpener = Pick<Window, "open"> & { location: Pick<Location, "assign"> }

export const openTabForRequest = async (fetchUrl: () => Promise<string | null | undefined>, opener: TabOpener = window): Promise<void> => {
	const tab = opener.open("", "_blank")

	try {
		const url = await fetchUrl()
		if (!url) {
			tab?.close()
		} else if (tab) {
			tab.location.href = url
		} else {
			opener.location.assign(url)
		}
	} catch (error) {
		tab?.close()
		throw error
	}
}
