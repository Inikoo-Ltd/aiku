/**
 * Product webpages download their own marketing pack, while the surrounding tab data
 * only carries the route of the family the product belongs to.
 */
export const productMarketingMaterialRoute = (tabs: any, product: any): any =>
	product?.marketing_material_route ?? tabs?.marketing_material_route ?? null
