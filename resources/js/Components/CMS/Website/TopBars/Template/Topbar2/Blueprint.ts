export default {
	blueprint: [
		{
			name: "Container",
			icon: {
				icon: "fal fa-rectangle-wide",
				tooltip: "Container",
			},
			key: ["container", "properties"],
			replaceForm: [
                {
                    key: ["background"],
                    label: "Background",
                    type: "background"
                },
                {
                    key: ["text"],
                    label: "Text",
                    type: "textProperty"
                }
            ]
		},
		{
			name: "Title",
            key : ["main_title"],
			icon: {
				icon: "fal fa-text",
				tooltip: "Text",
			},
			replaceForm: [
			/* 	{
					key: ["visible"],
					label :'Visibility',
					type: "VisibleLoggedIn",
				}, */
				{
					key: ["text"],
					label :'Text',
					type: "editorhtml",
				},
			],
		},

		{
			name: "Information",
            key : ["information"],
			icon: {
				icon: "fal fa-info",
				tooltip: "Text",
			},
			replaceForm: [
				{
					key: ["text1"],
					label :'Text 1',
					type: "text",
				},
				{
					key: ["text2"],
					label :'Text 2',
					type: "text",
				},
				{
					key: ["text3"],
					label :'Text 3',
					type: "text",
				},
				{
					key: ["text4"],
					label :'Text 4',
					type: "text",
				},
			],
		},
		
	],
}
