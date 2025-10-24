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
