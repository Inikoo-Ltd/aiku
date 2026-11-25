{{--
  Google Consent Mode v2 defaults for websites with the Own cookie banner setting on (INI-513).
  Include it before GTM in every layout that loads GTM (app-iris, app-retina), so tags see the
  visitor's choice on every page, /app (login, register, account) included. Before, CookieScript
  set consent from inside GTM on every page; without these defaults /app had no consent state and
  tags requiring analytics_storage / ad_storage never fired there.

  The choice comes from the iris_cookie_consent cookie written by the banner (useCookieConsent.ts):
  granted categories joined by dots, "none" for a rejection, absent until the visitor chooses.
--}}
@if(Arr::get(request()->input('website')?->settings, 'own_cookie_banner'))
    <script>
        (function () {
            var gtmId = '{{ Arr::get(request()->input('website')?->settings, 'google_tag_id', '') }}';
            /* When the Google tag gateway already loaded GTM, it reads window.dataLayer: point
               gtmDataLayer at it first, so these defaults land in the array GTM reads. */
            if (gtmId !== "" && (window.google_tags_first_party || []).indexOf(gtmId) > -1) {
                window.dataLayer = window.gtmDataLayer = window.dataLayer || [];
            }
            window.gtmDataLayer = window.gtmDataLayer || [];
            window.gtag = window.gtag || function () { window.gtmDataLayer.push(arguments); };

            var consentCookie = document.cookie.match(/(?:^|; )iris_cookie_consent=([^;]*)/);
            var granted = consentCookie ? decodeURIComponent(consentCookie[1]).split(".") : [];
            var consentState = function (category) { return granted.indexOf(category) > -1 ? "granted" : "denied"; };
            window.gtag("consent", "default", {
                analytics_storage: consentState("analytics"),
                ad_storage: consentState("marketing"),
                ad_user_data: consentState("marketing"),
                ad_personalization: consentState("marketing"),
                personalization_storage: consentState("preferences"),
                functionality_storage: "granted",
                security_storage: "granted",
                wait_for_update: 500
            });
        })();
    </script>
@endif
