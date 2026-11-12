<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 16:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $body = <<<'MARKDOWN'
We want to build lasting relationships with suppliers who take pride in their products and keep their promises. Our approach is straightforward: honest communication, fair dealing, respect for people and taking responsibility when something goes wrong.

By accepting this agreement, you agree to the following conditions when supplying an Ancient Wisdom Group company (“AW”). The buying company is the company named on each Purchase Order.

## 1. Confirming your orders

Check each Purchase Order carefully and confirm the products, specifications, quantities, prices, payment terms and agreed completion date. Raise any questions or differences before accepting. Changes must be agreed with AW in writing.

## 2. Export-quality products

All products must be new, unused, safe, fit for their intended purpose and of consistent export quality. They must match the approved samples, descriptions, materials, dimensions and specifications.

Products must be clean, dry, suitably finished and free from damage, mould, infestation and unacceptable defects. Natural and handmade variations are welcome where they are part of the agreed character of the product; they must not compromise safety, function or the agreed quality.

## 3. Product safety and compliance

Products must comply with the applicable laws and safety requirements of the destination markets identified by AW, including any agreed onward sales markets.

Before accepting an order, tell AW if you cannot meet a requirement or need clarification. Do not change materials, ingredients, components, finishes, manufacturing locations or subcontractors without AW’s written approval.

## 4. Relevant documents

Supply complete, accurate and genuine documents required for the goods, their export, transport, import and sale.

Depending on the product and destination, these may include:

- Commercial invoices and packing lists.
- Test reports, risk assessments, technical information and declarations of conformity.
- Safety data sheets, ingredient or material specifications and batch information.
- Certificates of origin, export permits and treatment certificates.
- Timber species, legal sourcing and traceability records.
- Required labels, warnings and instructions.

Documents must relate to the actual products and materials supplied. Unrelated certificates or reports are not acceptable.

Required compliance documents must be provided for AW’s review before shipment release. Shipment-specific documents must be supplied by the agreed deadline. Any testing or certification charges must be agreed before the order is accepted.

## 5. Packaging and identification

Use packaging suitable for the agreed journey and handling conditions, protecting products against breakage, moisture, contamination and damage.

Follow AW’s instructions for product codes, barcodes, quantities, labels and carton markings. Packing lists must accurately match the shipment. Include batch or production identification where required.

## 6. Delivery and communication

Meet the agreed production and handover dates. Tell AW or its appointed agent promptly about any delay, shortage, quality concern or documentation problem, explaining the cause and proposed solution.

Do not conceal problems or wait until the delivery deadline to report them. Partial shipments and substitutions require written approval.

## 7. Inspection and shipment release

Check the goods before packing and allow reasonable inspection by AW or its appointed representative. Supply photographs, samples and inspection information when requested.

Do not dispatch until any agreed inspection and shipment-release requirements have been completed. Inspection, payment or shipment approval does not remove responsibility for hidden defects or non-compliance.

## 8. Putting problems right

If goods are defective, incorrect, short, inadequately packed or non-compliant because of a supplier failure, work promptly with AW to resolve the problem.

AW may require repair, replacement or a credit/refund for the affected goods, as appropriate. The supplier is responsible for reasonable, evidenced direct costs caused by that failure, including necessary reworking, testing or replacement transport.

Notify AW immediately of any safety concern discovered after shipment and cooperate fully with investigation, withdrawal or recall.

## 9. Responsible business

Use lawful materials and production methods. Do not use forced labour, illegal child labour or unsafe working practices. Treat workers fairly and with respect.

Do not offer bribes or improper personal payments to AW employees or agents. Product claims about origin, materials, craftsmanship or sustainability must be truthful and supported.

## 10. Confidentiality and AW designs

Protect confidential AW information, artwork, branding and designs. Use them only for authorised AW work. Do not sell or share AW-owned designs or branded goods with others without written permission.

## 11. Working together

AW will provide clear order instructions, communicate fairly and pay according to the agreed terms. Both parties will raise concerns promptly and work in good faith to resolve them.

This agreement applies to orders accepted after its acceptance. Any exception must be expressly agreed in writing by authorised representatives of both parties. Product-specific requirements and agreed exceptions must be recorded with the relevant PO.

Together we can sell more of your products.
MARKDOWN;

        foreach (DB::table('groups')->pluck('id') as $groupId) {
            if (DB::table('supplier_agreement_versions')->where('group_id', $groupId)->exists()) {
                continue;
            }

            DB::table('supplier_agreement_versions')->insert([
                'group_id'              => $groupId,
                'version'               => 1,
                'title'                 => 'Ancient Wisdom Group — Supplier Agreement',
                'body'                  => $body,
                'requires_reacceptance' => true,
                'state'                 => 'draft',
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('supplier_agreement_versions')->where('version', 1)->where('state', 'draft')->delete();
    }
};
