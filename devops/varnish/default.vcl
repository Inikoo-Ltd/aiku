vcl 4.1;

import std;
import querystring;


# Base Varnish 8 configuration for Iris/Laravel
# - Separates cache by login state using X-Varnish-Logged-In (derived from iris_vua cookie)
# - Caches only GET/HEAD and bypasses admin/API and authenticated traffic
# - Long TTL for static assets
# - Safe PURGE (BAN) from localhost/private networks

backend defaultx {
    .host = "10.0.0.3";
    .port = "8080";
    .connect_timeout = 1s;
    .first_byte_timeout = 30s;
    .between_bytes_timeout = 30s;

}

acl purge {
    "127.0.0.1";
    "::1";
    # RFC1918 private networks
    "10.0.0.0"/8;
    "172.16.0.0"/12;
    "192.168.0.0"/16;
}

sub vcl_init {
        # Create a new filter for tracking parameters
        new tracking_params_filter = querystring.filter();

        # Add specific query string parameters to strip

        # google adds
        tracking_params_filter.add_string("gad_source");
        tracking_params_filter.add_string("gad_campaignid");

        #meta
        tracking_params_filter.add_string("fbclid");
        tracking_params_filter.add_glob("utm_*");

        #bing
        tracking_params_filter.add_string("msclkid");

        #debug

        tracking_params_filter.add_string("testa");
        tracking_params_filter.add_string("testb");


}


sub normalize_accept_encoding {
    if (req.http.Accept-Encoding) {
        if (req.url ~ "\.(jpg|jpeg|png|gif|svg|webp|ico)(\?.*)?$") {
            # Images are usually already compressed
            unset req.http.Accept-Encoding;
        } else {
            # Only keep gzip, brotli
            if (req.http.Accept-Encoding ~ "br") {
                set req.http.Accept-Encoding = "br";
            } elseif (req.http.Accept-Encoding ~ "gzip") {
                set req.http.Accept-Encoding = "gzip";
            } else {
                unset req.http.Accept-Encoding;
            }
        }
    }
}

sub set_login_flag_from_cookie {

    if (req.http.Cookie ~ "(?i)(^|; )iris_vua") {
        set req.http.X-Logged-Status = "In";
    } else {
        set req.http.X-Logged-Status = "Out";
    }
}

sub vcl_recv {

    # Allow BAN/PURGE from trusted IPs
    if (req.method == "PURGE" || req.method == "BAN") {
        if (client.ip !~ purge) {
            return (synth(405, "Not allowed"));
        }
        if(req.http.x-ban-webpage){
            ban("obj.http.x-aiku-webpage == "+req.http.x-ban-webpage);
            return(synth(200, "Ban webpage "+req.http.x-ban-webpage));
        }

        if(req.http.x-ban-website){
            ban("obj.http.x-aiku-website == "+req.http.x-ban-website);
            return(synth(200, "Ban website "+req.http.x-ban-website));
        }

         if(req.http.x-ban-all){
            ban("obj.http.x-aiku-website ~ .");
            return(synth(200, "Ban all websites"));
         }

        return (synth(200, "Purged"));
    }

    # If X-Original-Referer is missing but Referer is present, copy it
    if (!req.http.X-Original-Referer && req.http.Referer) {
        set req.http.X-Original-Referer = req.http.Referer;
    }

    # Only cache GET/HEAD
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }



    # Do not cache unsubscribe.php
    if (req.url ~ "^/unsubscribe\.php(\?.*)?$") {
        return (pass);
    }

    # Aiku no cachable iris paths
    if (req.url ~ "^/(app|json|disclosure|unsubscribe|locale|models|catalogue|invoice|attachment)(/|$)") {
        return (pass);
    }

    # Common no cachable paths
    if (req.url ~ "^/(admin|json|nova|api|horizon|telescope)(/|$)") {
        return (pass);
    }

    # If Authorization present, don't cache
    if (req.http.Authorization) {
        return (pass);
    }

    # Log the stripped parameters to a new header, e.g., X-Stripped-Query
    # Use the 'keep' mode in extract() to preserve original values (correct vmod call syntax)
    set req.http.X-Stripped-Query = tracking_params_filter.extract(req.url, keep);

    # Apply the filtering, which modifies req.url by removing the specified parameters
    set req.url = tracking_params_filter.apply(req.url);

    # Optional: Remove the trailing question mark if the query string is now empty
    if (req.url ~ "\?$") {
        set req.url = regsub(req.url, "\?$", "");
    }

    # Remove tracking query string parameters used by analytics tools
    if (req.url ~ "(\?|&)(_branch_match_id|_bta_[a-z]+|_bta_c|_bta_tid|_ga|_gl|_ke|_kx|campid|cof|customid|cx|dclid|dm_i|ef_id|epik|fbclid|gad_source|gbraid|gclid|gclsrc|gdffi|gdfms|gdftrk|hsa_acc|hsa_ad|hsa_cam|hsa_grp|hsa_kw|hsa_mt|hsa_net|hsa_src|hsa_tgt|hsa_ver|ie|igshid|irclickid|matomo_campaign|matomo_cid|matomo_content|matomo_group|matomo_keyword|matomo_medium|matomo_placement|matomo_source|mc_[a-z]+|mc_cid|mc_eid|mkcid|mkevt|mkrid|mkwid|msclkid|mtm_campaign|mtm_cid|mtm_content|mtm_group|mtm_keyword|mtm_medium|mtm_placement|mtm_source|nb_klid|ndclid|origin|pcrid|piwik_campaign|piwik_keyword|piwik_kwd|pk_campaign|pk_keyword|pk_kwd|redirect_log_mongo_id|redirect_mongo_id|rtid|s_kwcid|sb_referer_host|sccid|si|siteurl|sms_click|sms_source|sms_uph|srsltid|toolid|trk_contact|trk_module|trk_msg|trk_sid|ttclid|twclid|utm_[a-z]+|utm_campaign|utm_content|utm_creative_format|utm_id|utm_marketing_tactic|utm_medium|utm_source|utm_source_platform|utm_term|vmcid|wbraid|yclid|zanpid)=") {
        set req.url = regsuball(req.url, "(_branch_match_id|_bta_[a-z]+|_bta_c|_bta_tid|_ga|_gl|_ke|_kx|campid|cof|customid|cx|dclid|dm_i|ef_id|epik|fbclid|gad_source|gbraid|gclid|gclsrc|gdffi|gdfms|gdftrk|hsa_acc|hsa_ad|hsa_cam|hsa_grp|hsa_kw|hsa_mt|hsa_net|hsa_src|hsa_tgt|hsa_ver|ie|igshid|irclickid|matomo_campaign|matomo_cid|matomo_content|matomo_group|matomo_keyword|matomo_medium|matomo_placement|matomo_source|mc_[a-z]+|mc_cid|mc_eid|mkcid|mkevt|mkrid|mkwid|msclkid|mtm_campaign|mtm_cid|mtm_content|mtm_group|mtm_keyword|mtm_medium|mtm_placement|mtm_source|nb_klid|ndclid|origin|pcrid|piwik_campaign|piwik_keyword|piwik_kwd|pk_campaign|pk_keyword|pk_kwd|redirect_log_mongo_id|redirect_mongo_id|rtid|s_kwcid|sb_referer_host|sccid|si|siteurl|sms_click|sms_source|sms_uph|srsltid|toolid|trk_contact|trk_module|trk_msg|trk_sid|ttclid|twclid|utm_[a-z]+|utm_campaign|utm_content|utm_creative_format|utm_id|utm_marketing_tactic|utm_medium|utm_source|utm_source_platform|utm_term|vmcid|wbraid|yclid|zanpid)=[-_A-z0-9+(){}%.*]+&?", "");
        set req.url = regsub(req.url, "[?|&]+$", "");
    }

    # Normalize
    set req.http.host = std.tolower(req.http.host);
    call normalize_accept_encoding;

    # Sort query string for better cache hit ratio; keeps semantics
    set req.url = std.querysort(req.url);

    # Determine login header (used by app and cache key)
    # If the warm-up header is present, trust it and bypass cookie derivation
    if (req.http.X-Warm-Logged-Status) {
        set req.http.X-Logged-Status = req.http.X-Warm-Logged-Status;
    } else {
        # Otherwise derive from cookie
        call set_login_flag_from_cookie;
    }



    # Do not cache static files: always pass through Varnish
    if (req.url ~ "\.(pdf|csv|css|js|mjs|map|jpg|jpeg|png|gif|svg|webp|avif|ico|woff|woff2|ttf|eot|otf)(\?.*)?$") {
        return (pass);
    }
    
    return (hash);
}

sub vcl_hash {
    hash_data(req.http.host);
    hash_data(req.url);

    # Separate cache buckets by login status
    if (req.http.X-Logged-Status) {
        hash_data(req.http.X-Logged-Status);
    }

    # Categorize requests into two hash buckets based on X-Inertia header
    # If X-Inertia exists and equals "true" (case-insensitive) → bucket "Inertia"
    # otherwise → bucket "Direct"
    if (req.http.X-Inertia && std.tolower(req.http.X-Inertia) == "true") {
        hash_data("Inertia");
    } else {
        hash_data("Direct");
    }

    # Inertia-specific headers to prevent mixing JSON partials/versioned payloads
    if (req.http.X-Inertia-Version) { hash_data(req.http.X-Inertia-Version); }
    if (req.http.X-Inertia-Partial-Component) { hash_data(req.http.X-Inertia-Partial-Component); }
    if (req.http.X-Inertia-Partial-Data) { hash_data(req.http.X-Inertia-Partial-Data); }


    return (lookup);
}

sub vcl_backend_response {

    # Default TTL for dynamic content
    set beresp.ttl = 10d;
    set beresp.grace = 2m;
    set beresp.keep = 10m;
    # Store original TTL as header for later use in vcl_deliver
    set beresp.http.X-Varnish-TTL = beresp.ttl;



    # Inertia.js responses: cache JSON and vary on Inertia headers
    if (bereq.http.X-Inertia || beresp.http.X-Inertia) {
        if (beresp.http.Vary) {
            set beresp.http.Vary = beresp.http.Vary + ", X-Inertia, X-Inertia-Version, X-Inertia-Partial-Component";
        } else {
            set beresp.http.Vary = "X-Inertia, X-Inertia-Version, X-Inertia-Partial-Component";
        }
        # Ensure Content-Type is JSON for Inertia payloads
        if (beresp.http.Content-Type !~ "application/json") {
            set beresp.http.Content-Type = "application/json; charset=utf-8";
        }
        # Ensure a reasonable minimum TTL for cacheable Inertia responses
        if (beresp.ttl < 60s) {
            set beresp.ttl = 60s;
        }
    }

    # Also avoid caching conflict responses often used by Inertia version mismatches
    if (beresp.status == 409) {
        set beresp.ttl = 0s;
        set beresp.uncacheable = true;
    }

    # Do not cache redirects (301, 302, 303, 307, 308)
    if (beresp.status == 301 || beresp.status == 302 || beresp.status == 303 || beresp.status == 307 || beresp.status == 308) {
        set beresp.ttl = 2d;
    }

    # Enable gzip on text-like content
    if (beresp.http.Content-Type ~ "(text|javascript|json|xml|svg|font|css)") {
        set beresp.do_gzip = true;
    }

    return (deliver);
}

sub vcl_deliver {
    # Strip Set-Cookie on cache hits only
    if (obj.hits > 0) {
        unset resp.http.Set-Cookie;
    }

    # Add debug headers (can be removed in production)
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
    set resp.http.X-Cache-Hits = obj.hits;

    # Echo login derivation for observability and for the app if needed
    if (req.http.X-Logged-Status) {
        set resp.http.X-Logged-Status = req.http.X-Logged-Status;
    }

     if (req.http.X-Original-Referer) {
        set resp.http.X-Original-Referer = req.http.X-Original-Referer;
     }

      if (req.http.X-Stripped-Query) {
        set resp.http.X-Traffic-Sources = req.http.X-Stripped-Query;
      }


    # If response is a redirect (3xx), set client cache to 1 day
    if (resp.status == 301 || resp.status == 302 || resp.status == 303 || resp.status == 307 || resp.status == 308) {
        set resp.http.Cache-Control = "public, max-age=14400";
    }

    set resp.http.Via = "varnish";
}

sub vcl_synth {
    if (resp.status == 200 && resp.reason == "Purged") {
        set resp.http.Content-Type = "text/plain; charset=utf-8";
        synthetic("Purged");
        return (deliver);
    }
}
