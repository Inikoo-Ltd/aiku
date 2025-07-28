export default {
	blueprint: [
		{
			name: "Carousel Settings",
			key: ["carousel_data"],
			replaceForm: [
				{
					label: "Slide per View",
					key: ["carousel_setting", "slidesPerView"],
					useIn: ["desktop", "tablet", "mobile"],
					type: "number",
				},
			],
		},
	],
}
