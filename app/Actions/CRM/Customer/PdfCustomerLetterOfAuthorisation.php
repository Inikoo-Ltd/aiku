<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfCustomerLetterOfAuthorisation extends OrgAction
{
    use WithCRMAuthorisation;

    public const array PLACEHOLDERS = ['{supplier}', '{company}', '{email}', '{address}', '{reference}', '{date}'];

    public static function isOffered(Shop $shop): bool
    {
        return $shop->type === ShopTypeEnum::DROPSHIPPING;
    }

    public static function isEnabled(Shop $shop): bool
    {
        return (bool) Arr::get($shop->settings, 'letter_of_authorisation.enabled', false);
    }

    public static function isSigned(Shop $shop): bool
    {
        return self::media($shop, 'signature') !== null;
    }

    public static function isAvailable(Shop $shop): bool
    {
        return self::isOffered($shop) && self::isEnabled($shop) && self::isSigned($shop);
    }

    public static function canSign(User $user, Shop $shop): bool
    {
        return $user->hasRole(RolesEnum::GROUP_ADMIN->value) || $user->authTo('org-admin.'.$shop->organisation_id);
    }

    public static function media(Shop $shop, string $image): ?Media
    {
        $mediaId = Arr::get($shop->settings, "letter_of_authorisation.{$image}_media_id");

        return $mediaId ? Media::find($mediaId) : null;
    }

    public static function supplierName(Shop $shop): string
    {
        return Arr::get($shop->settings, 'letter_of_authorisation.company_name') ?: $shop->organisation->name;
    }

    public static function defaultBody(Shop $shop): string
    {
        $place = e($shop->address?->locality);

        return '<p>'.($place ? $place.', ' : '').'{date}</p>'
            .'<p>To whom it may concern;</p>'
            .'<p>This is to confirm that {supplier} is a supplier to:</p>'
            .'<p>Company Name: <strong>{company}</strong><br>Trade Account Email: {email}<br>Address: {address}</p>'
            .'<p>Our customer <strong>{company}</strong> sells on online marketplaces.</p>'
            .'<p>Articles supplied by {supplier} to <strong>{company}</strong> are not limited to, the following:</p>'
            .'<p>- Homeware products<br>- Bath Products<br>- Cosmetic Products<br>- Aromatherapy and Fragrance Products<br>- Various giftware products</p>'
            .'<p>{supplier} owns the brand and barcodes created for the above-mentioned articles.</p>'
            .'<p>We also wanted to confirm that orders placed by <strong>{company}</strong> will be fulfilled and dispatched from our warehouse on their behalf.</p>'
            .'<p>Should you wish to contact {supplier} with regards to the information included in this letter, please do so using the following details:</p>'
            .'<p>{supplier}<br>'.self::addressLines($shop->address, '<br>').'</p>';
    }

    public static function defaultFooter(Shop $shop): string
    {
        $registration = data_get($shop->data, 'registration_number');
        $vat          = data_get($shop->data, 'vat_number');

        $lines = array_filter([
            e(self::supplierName($shop)),
            self::addressLines($shop->address, ', '),
            $registration ? __('Company Reg. No.').' '.e($registration) : null,
            $vat ? __('VAT No.').' '.e($vat) : null,
            $shop->email ? 'E. '.e($shop->email) : null,
            $shop->phone ? 'T. '.e($shop->phone) : null,
        ]);

        return '<p>'.implode('<br>', $lines).'</p>';
    }

    public function body(Customer $customer): string
    {
        $shop = $customer->shop;

        return strtr(
            Arr::get($shop->settings, 'letter_of_authorisation.body') ?: self::defaultBody($shop),
            [
                '{supplier}'  => e(self::supplierName($shop)),
                '{company}'   => e($customer->company_name ?: $customer->name),
                '{email}'     => e($customer->email),
                '{address}'   => self::addressLines($customer->address, ', '),
                '{reference}' => e($customer->reference),
                '{date}'      => now()->format('d/m/Y'),
            ]
        );
    }

    public function handle(Customer $customer): Response
    {
        $shop    = $customer->shop;
        $company = $customer->company_name ?: $customer->name;

        $pdf = PDF::loadView('crm.letter-of-authorisation', [
            'body'          => $this->body($customer),
            'footer'        => Arr::get($shop->settings, 'letter_of_authorisation.footer') ?: self::defaultFooter($shop),
            'logoPath'      => $this->existingPath(self::media($shop, 'logo') ?? $shop->image),
            'signaturePath' => $this->existingPath(self::media($shop, 'signature')),
            'signatory'     => Arr::get($shop->settings, 'letter_of_authorisation.signatory'),
        ], [], [
            'title' => __('Letter of authorisation').' '.$company,
        ]);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="letter-of-authorisation-'.$customer->reference.'.pdf"');
    }

    private function existingPath(?Media $media): ?string
    {
        $path = $media?->getPath();

        return $path && file_exists($path) ? $path : null;
    }

    private static function addressLines(?Address $address, string $separator): string
    {
        if (!$address) {
            return '';
        }

        $lines = array_filter(array_map('trim', explode("\n", $address->formatted_address)));

        return implode($separator, array_map('e', $lines));
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        if (!self::isAvailable($shop)) {
            abort(404);
        }

        return $this->handle($customer);
    }
}
