export default {
	"login": { url: "maya/connect/credentials" },
	"login-scanner": { url: "maya/connect/qr-code" },
	"get-profile": { url: "maya/profile" },
	"update-profile": { url: "maya/action/profile" },
	"logout" : {url : "maya/logout"},

	//Goods In stock
	"get-stock-deliveries" : { url: "maya/org/{}/warehouses/{}/incoming/stock-deliveries" },
	"get-stock-delivery" : { url: "maya/org/{}/procurement/stock-deliveries/all/{}" },

    //Delivery Note
	"get-delivery-notes" : { url: "maya/org/{}/warehouses/{}/dispatching/delivery-notes" },
	"get-delivery-note" : { url: 'maya/org/{}/warehouses/{}/dispatching/delivery-notes/{}'},
	

	//locations
	"get-locations" : { url: "maya/org/{}/warehouses/{}/locations" },
	"get-areas" : { url: "maya/org/{}/warehouses/{}/areas" },
	"get-location" : { url: "maya/org/{}/warehouses/{}/locations/{}" },
	"get-area" : { url: "maya/org/{}/warehouses/{}/areas/{}" },
	"get-location-pallet" : { url: "maya/org/{}/warehouses/{}/inventory/locations/{}/pallets" },

	//stored items
	'get-stored-items' :  { url: "maya/org/{}/warehouses/{}/inventory/stored-items" },
	'get-stored-item' :  { url: "maya/org/{}/warehouses/{}/inventory/stored-items/{}" },
	"set-stored-item-pick" : { url: "maya/action/pallet-return-item/stored-items/{}/pick" },
	"set-stored-item-undo-pick" : { url: "maya/action/pallet-return-item/stored-items/{}/undo-pick" },

	//scanner
	'get-scanner' : { url: "maya/org/{}/warehouses/{}/scanners/{}" },

	//deliveries
	"get-deliveries" : { url: "maya/org/{}/warehouses/{}/incoming/handling-fulfilment-deliveries" },
	"get-delivery" : { url: "maya/org/{}/warehouses/{}/incoming/fulfilment-deliveries/{}" },
	"set-delivery-received" : { url: "maya/action/pallet-delivery/{}/received" },
	"set-delivery-booking-in" : { url: "maya/action/pallet-delivery/{}/start-booking" },
	"set-delivery-booked-in" : { url: "maya/action/pallet-delivery/{}/booked-in" },
	"set-delivery-pallet-location" : { url: "maya/action/pallet/{}/location/{}/book-in" },
	"get-pallets-delivery" : { url: "maya/org/{}/warehouses/{}/incoming/fulfilment-deliveries/{}/pallets" },


	//pallet
	'get-pallets' :  { url: "maya/org/{}/warehouses/{}/inventory/pallets" },
	'get-pallet' :  { url: "maya/org/{}/warehouses/{}/inventory/pallets/{}" },
	'set-pallet-location' : { url: "maya/action/pallet/{}/location/{}/move" },
	'set-pallet-not-received' : { url: "maya/action/pallet/{}/not-received" },
	'undo-pallet-not-received' : { url: "maya/action/pallet/{}/undo-not-received" },
	'set-pallet-picked' : { url : "maya/action/pallet-return-item/pallet/{}/pick"},
	'set-pallet-not-picked' : { url : "maya/action/pallet-return-item/pallet/{}/not-picked"},
	'undo-pallet-picked' :  { url : "maya/action/pallet-return-item/pallet/{}/undo-pick"},
	//audit
	'create-pallet-audit' :  { url : "maya/org/{}/warehouses/{}/inventory/pallets/{}/stored-item-audits/create"},
	'get-pallet-audit' :  { url : "maya/org/{}/warehouses/{}/inventory/pallets/{}/stored-item-audit/{}"},
	'edit-stored-item-audit' :  { url : "maya/action/stored-item-audit-delta/{}"},
	'add-stored-item-audit' :  { url : "maya/action/stored-item-audit/{}/stored-item-audit-delta"},
	'delete-stored-item-audit' :  { url : "maya/action/stored-item-audit-delta/{}"},
	'complete-stored-item-audit' :  { url : "maya/action/stored-item-audit/{}/complete"},

	//return
	"get-returns" : { url: "maya/org/{}/warehouses/{}/dispatching/handling-fulfilment-returns" },
	"get-return" : { url: 'maya/org/{}/warehouses/{}/dispatching/fulfilment-returns/{}'},
	"get-return-pallets" : { url: 'maya/org/{}/warehouses/{}/dispatching/fulfilment-returns/{}/pallets'},
	"get-return-stored-items" : { url: 'maya/org/{}/warehouses/{}/dispatching/fulfilment-returns/{}/stored-items'},
	"set-return-confirm" : { url: "maya/action/pallet-return/{}/confirm" },
	"set-return-picking" : { url: "maya/action/pallet-return/{}/start-picking" },
	"set-return-picked" : { url: "maya/action/pallet-return/{}/picked" },
	"set-return-dispatch" : { url: "maya/action/pallet-return/{}/dispatch" },
	


	//goods in
	'get-org-stocks' :  { url: "maya/org/{}/warehouses/{}/inventory/stocks" },
	'get-org-stock' :  { url: "maya/org/{}/warehouses/{}/inventory/stocks/{}" },


	//agent-sku
	'get-agent-skus' :  { url: "maya/org/{}/warehouses/{}/agent-inventory/skus" },
	'get-agent-sku' :  { url: "maya/org/{}/warehouses/{}/agent-inventory/skus/{}" },
}