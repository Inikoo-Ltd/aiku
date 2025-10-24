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
					label: "Border",
					type: "color",
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
