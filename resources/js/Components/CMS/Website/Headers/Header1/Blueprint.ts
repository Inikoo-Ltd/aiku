import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Logo",
			key: ["logo"],
			icon: {
				icon: "fal fa-image",
				tooltip: "Logo",
			},
			replaceForm: [
				{
					key: ["image", "source"],
					label: "Upload image",
					type: "image-cropped",
					props_data: {
						stencilProps: {
							aspectRatio: 4 / 2,
							movable: true,
							scalable: true,
							resizable: true,
						},
					},
				},
				{
					key: ["link"],
					label : "Link",
					type: "link",
				},
				{
					key: ["alt"],
					label: "Alternate Text",
					type: "text",
				},
			],
		},
		{
			name: "Search",
			key: ["search"],
			icon: {
				icon: "fal fa-search",
				tooltip: "Search",
			},
			replaceForm: [
				{
					key: ["placeholder"],
					label: "Placeholder text",
					information: "This text will be displayed inside the search box (when the user didn't type anything yet).",
					props_data: {
						placeholder: 'Enter text to search'
					},
					type: "text",
				},
				{
					key: ["is_box_full_width"],
					label: "Full width search box?",
					information: "If enabled, the search box will take the full width available.",
					type: "switch",
				},
			],
		},
		{
			name: "Mobile",
			key: ["mobile"],
			icon: {
				icon: "fal fa-mobile",
				tooltip: "Action",
			},
			replaceForm: [
				{
					key: ["profile"],
					name: "Profile Icon",
					replaceForm: [
						{
							key: ["icon"],
							label: "Icon",
							type: "icon-picker",
						},
						{
							key: ["container", "properties", "text"],
							label: "Icon Setting",
							type: "textProperty",
						},
					],
				},
				{
					key: ["menu"],
					name: "Menu Icon",
					replaceForm: [
						{
							key: ["icon"],
							label: "Icon",
							type: "icon-picker",
						},
						{
							key: ["container", "properties", "text"],
							label: "Icon Setting",
							type: "textProperty",
						},
					],
				},
			],
		},
	],
}
