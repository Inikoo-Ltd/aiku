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
				{
					key: ["visible"],
					label :'Visibility',
					type: "VisibleLoggedIn",
				},
				{
					key: ["text"],
					label :'Text',
					type: "editorhtml",
				},
			],
		},
		{
			name: "Login",
            key:['login'],
			icon: {
				icon: "fal fa-sign-in-alt",
				tooltip: "Action",
			},
			replaceForm: [
				{
					key: ["visible"],
					label :'Visibility',
					type: "VisibleLoggedIn",
				},
				{
					key: ["link"],
					label :'Link',
					type: "link",
					props_data : {
						defaultValue : {
							type : "external",
							href: "/app",
							target : '_self'
						},
						props_radio_type : {
							disabled : true
						},
						props_radio_target : {
							disabled : true
						},
						props_input: {
							disabled : true
						},
						props_selectquery:{
							disabled : true
						}
					}
				},
				{
					key: ["container",'properties','background'],
					label :'Background',
					type: "background",
				},
				{
					key: ["container",'properties','text'],
					label :'Text',
					type: "textProperty",
				},
				{
					key: ['text'],
					label :'Button Text',
					type: "text",
				},
				{
					key: ["container",'properties','border'],
					label :'Border',
					type: "border",
				},
				{
					key: ["container",'properties','margin'],
					label :'Margin',
					type: "margin",
				},
				{
					key: ["container",'properties','padding'],
					label :'Padding',
					type: "padding",
				},
			],
		},
		{
			name: "Register",
            key:["register"],
			icon: {
				icon: "fal fa-dot-circle",
				tooltip: "Action",
			},
			replaceForm: [
				{
					key: ["visible"],
					label :'Visibility',
					type: "VisibleLoggedIn",
				},
				{
					key: ["link"],
					label :'Link',
					type: "link",
					props_data : {
						defaultValue : {
							href: "/aw-fulfilment.co.uk/register-fulfilment",
							id: 15594,
							target: "_self",
							type: "internal",
							workshop: "http://app.aiku.test/org/aw/shops/awf/web/awf/webpages/register-fulfilment-awf/workshop"
						},
						props_radio_type : {
							disabled : true
						},
						props_radio_target : {
							disabled : true
						},
						props_input: {
							disabled : true
						},
						props_selectquery:{
							disabled : true
						}
					}
				},
				{
					key: ["container",'properties','background'],
					label :'Background',
					type: "background",
				},
				{
					key: ["container",'properties','text'],
					label :'Text',
					type: "textProperty",
				},
				{
					key: ['text'],
					label :'Button Text',
					type: "text",
				},
				{
					key: ["container",'properties','border'],
					label :'Border',
					type: "border",
				},
				{
					key: ["container",'properties','margin'],
					label :'Margin',
					type: "margin",
				},
				{
					key: ["container",'properties','padding'],
					label :'Padding',
					type: "padding",
				},
			],
		},
		{
			name: "Cart",
            key: ["cart"],
			icon: {
				icon: "fal fa-shopping-cart",
				tooltip: "Cart",
			},
			replaceForm: [
				{
					key: ["visible"],
					type: "VisibleLoggedIn",
					label :'Visibility',
					props_data: {
						defaultValue: 'login',
					},
				},
				{
					key: ["link"],
					type: "link",
					label :'Link',
					props_data: {
						defaultValue: {
							type : "external",
							url: "",
							id: null,
							workshop_route : ""
						},
					},
				},
				{
					key: ['container', 'properties'],
					type: "button",
					label :'Button',
				},
				{
					key: ['text'],
					type: "editorhtml",
					label :'Text',
					props_data: {
						defaultValue: '{{ cart_count }}',
					},
				},
			],
		},
		{
			name: "Favourite",
            key: ["favourite"],
			icon: {
				icon: "fal fa-heart",
				tooltip: "Favourite",
			},
			replaceForm: [
				{
					key: ["visible"],
					type: "VisibleLoggedIn",
					label :'Visibility',
					props_data: {
						defaultValue: 'login',
					},
				},
				{
					key: ["link"],
					type: "link",
					label :'Link',
					props_data: {
						defaultValue: {
							"type" : "external",
							"url": "",
							"id": null,
							"workshop_route" : ""
						},
					},
				},
				{
					key: ['container', 'properties'],
					type: "button",
					label :'Button',
				},
				{
					key: ['text'],
					type: "editorhtml",
					label :'text',
					props_data: {
						defaultValue: '{{ favourites_count }}',
					},
				},
			],
		},
		{
			name: "Profile",
            key: ["profile"],
			icon: {
				icon: "fal fa-user",
				tooltip: "Profile",
			},
			replaceForm: [
				{
					key: ["visible"],
					type: "VisibleLoggedIn",
					label :'Visibility',
					props_data: {
						defaultValue: 'login',
					},
				},
				{
					key: ['container', 'properties'],
					type: "button",
					label :'Button',
					props_data: {
						defaultValue: {
							text: {
								color: "rgba(255, 255, 255, 1)"
							},
							padding: {
								top: {
									value: 5
								},
								left: {
									value: 10
								},
								unit: "px",
								right: {
									value: 10
								},
								bottom: {
									value: 5
								}
							}
						},
					},
				},
				{
					key: ['text'],
					type: "editorhtml",
					label :'Text',
					props_data: {
						defaultValue: '{{ name }}',
					},
				},
			],
		},
		{
			name: "Logout",
            key:['logout'],
			icon: {
				icon: "fal fa-sign-out-alt",
				tooltip: "Action",
			},
			replaceForm: [
				{
					key: ["container",'properties','text'],
					label :'Text',
					type: "textProperty",
				},
				{
					key: ['text'],
					label :'Button Text',
					type: "text",
				},
				{
					key: ["container",'properties','border'],
					label :'Border',
					type: "border",
				},
				{
					key: ["container",'properties','margin'],
					label :'Margin',
					type: "margin",
				},
				{
					key: ["container",'properties','padding'],
					label :'Padding',
					type: "padding",
				},
			],
		},
	],
}
