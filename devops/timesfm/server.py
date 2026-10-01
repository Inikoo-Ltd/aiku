"""TimesFM over HTTP for aiku's nightly forecasts.

POST /forecast  {"horizon": 6, "series": [[...], ...], "past_future_covariates": [[[...]], ...] | null}
             -> {"version": "3", "deciles": [[[p10..p90] per step] per series]}
GET  /health    -> {"version": "3"}

Requests carry "Authorization: Bearer $TIMESFM_TOKEN" and are served one at a time: the model
already uses every thread it is given.
"""

import hmac
import json
import os
from http.server import BaseHTTPRequestHandler, HTTPServer

import numpy as np

from forecaster import VERSION, Forecaster

HOST = os.environ.get("TIMESFM_HOST", "127.0.0.1")
PORT = int(os.environ.get("TIMESFM_PORT", "8765"))
TOKEN = os.environ.get("TIMESFM_TOKEN", "")
MAX_BODY_BYTES = 256 * 1024 * 1024


class Handler(BaseHTTPRequestHandler):
    forecaster = None

    def reply(self, status, payload):
        body = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def authorised(self):
        return hmac.compare_digest(self.headers.get("Authorization", ""), f"Bearer {TOKEN}")

    def do_GET(self):
        if not self.authorised():
            return self.reply(401, {"error": "unauthorised"})
        if self.path != "/health":
            return self.reply(404, {"error": "not found"})
        return self.reply(200, {"version": VERSION})

    def do_POST(self):
        if not self.authorised():
            return self.reply(401, {"error": "unauthorised"})
        if self.path != "/forecast":
            return self.reply(404, {"error": "not found"})
        length = int(self.headers.get("Content-Length", 0))
        if length <= 0 or length > MAX_BODY_BYTES:
            return self.reply(413, {"error": "body must be between 1 byte and 256 MB"})
        try:
            request = json.loads(self.rfile.read(length))
            horizon = int(request["horizon"])
            series = [np.asarray(values, dtype=np.float32) for values in request["series"]]
            covariates = request.get("past_future_covariates")
            if horizon < 1 or not series or any(len(values) < 1 for values in series):
                raise ValueError("horizon and every series need at least one value")
            if covariates is not None:
                covariates = [np.asarray(values, dtype=np.float32) for values in covariates]
        except (KeyError, TypeError, ValueError) as exception:
            return self.reply(422, {"error": str(exception)})

        try:
            deciles = Handler.forecaster.deciles(series, horizon, past_future_covariates=covariates)
        except Exception as exception:
            return self.reply(500, {"error": f"{type(exception).__name__}: {exception}"})
        return self.reply(200, {"version": VERSION, "deciles": np.round(deciles, 4).tolist()})

    def log_message(self, format, *args):
        print(f"{self.address_string()} {format % args}", flush=True)


if __name__ == "__main__":
    if len(TOKEN) < 32:
        raise SystemExit("TIMESFM_TOKEN must be set to at least 32 characters")
    Handler.forecaster = Forecaster()
    print(f"TimesFM {VERSION} listening on {HOST}:{PORT}", flush=True)
    HTTPServer((HOST, PORT), Handler).serve_forever()
