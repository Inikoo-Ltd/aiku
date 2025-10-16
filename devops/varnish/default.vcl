vcl 4.1;

import std;

# Base Varnish 8 configuration for Iris/Laravel
# - Separates cache by login state using X-Varnish-Logged-In (derived from iris_vua cookie)
# - Caches only GET/HEAD and bypasses admin/API and authenticated traffic
# - Long TTL for static assets
# - Safe PURGE (BAN) from localhost/private networks

backend default {
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
    # Place for VMOD initialisation if needed
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
    # Derive login flag from iris_vua cookie
    if (req.http.Cookie ~ "iris_vua=true") {
        set req.http.X-Varnish-Logged-In = "1";
    } else {
        set req.http.X-Varnish-Logged-In = "0";
    }
}

sub vcl_recv {




    # Allow BAN/PURGE from trusted IPs
    if (req.method == "PURGE" || req.method == "BAN") {
        if (client.ip !~ purge) {
            return (synth(405, "Not allowed"));
        }
        # Ban by host + URL
        ban("req.http.host == '" + req.http.host + "' && req.url == '" + req.url + "'");
        return (synth(200, "Purged"));
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


    # Normalize
    set req.http.host = std.tolower(req.http.host);
    call normalize_accept_encoding;

    # Sort query string for better cache hit ratio; keeps semantics
    set req.url = std.querysort(req.url);

    # Derive login header from cookie (used by app and cache key)
    call set_login_flag_from_cookie;




    # Do not cache static files: always pass through Varnish
    if (req.url ~ "\.(pdf|csv|css|js|mjs|map|jpg|jpeg|png|gif|svg|webp|avif|ico|woff|woff2|ttf|eot|otf)(\?.*)?$") {
        return (pass);
    }
    #unset req.http.Cookie;
    return (hash);
}

sub vcl_hash {
    hash_data(req.http.host);
    hash_data(req.url);

    # Inertia-specific headers to prevent mixing HTML vs JSON/partials
    if (req.http.X-Inertia) { hash_data(req.http.X-Inertia); }
    if (req.http.X-Inertia-Version) { hash_data(req.http.X-Inertia-Version); }
    if (req.http.X-Inertia-Partial-Component) { hash_data(req.http.X-Inertia-Partial-Component); }
    if (req.http.X-Inertia-Partial-Data) { hash_data(req.http.X-Inertia-Partial-Data); }

    # Separate cache buckets by login status
    if (req.http.X-Varnish-Logged-In) {
        hash_data(req.http.X-Varnish-Logged-In);
    }
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
    if (req.http.X-Varnish-Logged-In) {
        set resp.http.X-Varnish-Logged-In = req.http.X-Varnish-Logged-In;
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
