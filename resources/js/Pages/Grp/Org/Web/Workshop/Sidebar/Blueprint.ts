import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["text"],
					label: "Text",
					type: "textProperty",
				},
				{
					key: ["border", "color"],
					label: "Lines",
					information: trans('Lines that used to separates the menu'),
					type: "color",
				},
				{
					key: ["border", "width"],
					label: "Lines thickness",
					information: trans('Reasonal number is 0px to 12px'),
					props_data: {
						unit_option: [
							{ label: 'px', value: 'px' },
						],
						defaultValue: {
							value: 1,
							unit: 'px',
						}
					},
					type: "numberCss",
				},
			],
		},
		{
			name: "Logo size",
			key: ["logo_dimension"],
			// icon: {
			// 	icon: 'fal fa-image',
			// },
			replaceForm: [
				{
					key: ["dimension"],
					label: "Dimension",
					type: "dimension",
				},
			],
		},
        // {
		// 	name: "Navigation",
		// 	key: ["navigation_container", "properties"],
		// 	replaceForm: [
		// 		{
		// 			key: ["text"],
		// 			type: "textProperty",
		// 		},
		// 	],
		// },
        //  {
		// 	name: "Subnavigation",
		// 	key: ["sub_navigation", "properties"],
		// 	replaceForm: [
		// 		{
		// 			key: ["text"],
		// 			type: "textProperty",
		// 		},
		// 	],
		// },
        // {
		// 	name: "Link Subnavigation",
		// 	key: ["sub_navigation_link", "properties"],
		// 	replaceForm: [
		// 		{
		// 			key: ["text"],
		// 			type: "textProperty",
		// 		},
		// 	],
		// },
	],
}
