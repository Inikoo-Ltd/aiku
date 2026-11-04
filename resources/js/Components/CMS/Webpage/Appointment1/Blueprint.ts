type WebpageWithBlocks = {
	layout?: { web_blocks?: { id: number, appointment_types?: object[] }[] }
}

export const blueprint = (data?: WebpageWithBlocks, blockId?: number) => {
	const appointmentTypes = data?.layout?.web_blocks?.find(webBlock => webBlock.id === blockId)?.appointment_types ?? []

	return {
		blueprint: [
			{
				label: "# Id ",
				key: ["id"],
				type: "text",
				information: "id selector is used to select one unique element!",
			},
			{
				label: "Responsive Visibility",
				key: ["container", "properties", "visibility"],
				type: "visibility",
				useIn: ["desktop", "tablet", "mobile"],
			},
			{
				name: "Layout",
				key: ["container", "properties"],
				replaceForm: [
					{
						key: ["background"],
						useIn: ["desktop", "tablet", "mobile"],
						label: "Background",
						type: "background",
					},
					{
						key: ["text"],
						label: "Text",
						type: "textProperty",
						useIn: ["desktop", "tablet", "mobile"],
					},
					{
						key: ["padding"],
						useIn: ["desktop", "tablet", "mobile"],
						label: "Padding",
						type: "padding",
						props_data: {},
					},
				],
			},
			{
				name: "Booking",
				key: ["settings"],
				replaceForm: [
					{
						key: ["appointment_type_order"],
						label: "Order of appointment types",
						information: "Drag to set the order visitors see. New appointment types go after these, in alphabetical order.",
						type: "appointmentTypeOrder",
						props_data: { appointmentTypes },
					},
					{
						key: ["show_marketing_opt_in"],
						label: "Ask to receive news and offers",
						information: "Shows a checkbox under the booking form. Visitors who tick it can receive marketing emails.",
						type: "switch",
					},
				],
			},
		],
	}
}
