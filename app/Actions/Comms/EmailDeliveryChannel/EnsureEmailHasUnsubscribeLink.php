<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Mar 2026 19:39:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailDeliveryChannel;

use Lorisleiva\Actions\Concerns\AsObject;

class EnsureEmailHasUnsubscribeLink
{
    use AsObject;

    public function handle(string $htmlEmail): string
    {
        if (preg_match('/\{\{unsubscribe}}|\[unsubscribe]/i', $htmlEmail)) {
            return $htmlEmail;
        }

        $unsubscribeLink = <<<'HTML'
<table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#666666">
    <tr>
        <td align="center" style="padding: 20px 0;">
            <p style="font-family: Arial, sans-serif; font-size: 12px; color: #ffffff; line-height: 140%;">
                {{unsubscribe_fallback}}
            </p>
        </td>
    </tr>
</table>
HTML;

        if (preg_match('/<\/body>/i', $htmlEmail)) {
            return preg_replace('/<\/body>/i', $unsubscribeLink."\n</body>", $htmlEmail);
        }

        if (preg_match('/<\/html>/i', $htmlEmail)) {
            return preg_replace('/<\/html>/i', $unsubscribeLink."\n</html>", $htmlEmail);
        }

        return $htmlEmail."\n".$unsubscribeLink;
    }



}
