/**
 * Aiku — daily Google Ads cost upload (single account).
 *
 * Paste this into Tools & Settings > Bulk Actions > Scripts in the Google Ads account,
 * fill in the three CONFIG values, authorise it, and schedule it Daily.
 *
 * Sends yesterday's cost per campaign. Safe to run as many times as you like: the same
 * day posted twice updates the figure, it never adds to it.
 */

var CONFIG = {
  ENDPOINT: 'https://aiku.io/webhooks/traffic-source-costs',
  TOKEN: 'PASTE-YOUR-TOKEN-HERE',
  SHOP: 'PASTE-YOUR-SHOP-SLUG-HERE'
};

function main() {
  var day = yesterday();
  var payload = buildPayload(AdsApp.currentAccount(), day);

  if (payload.costs.length === 0) {
    Logger.log('No campaign spend on ' + day.label + ' — nothing to send.');
    return;
  }

  send(payload, day.label);
}

/** Yesterday in the account's own time zone, which is the day Google Ads reports on. */
function yesterday() {
  var zone = AdsApp.currentAccount().getTimeZone();
  var date = new Date(new Date().getTime() - 24 * 60 * 60 * 1000);

  return {
    label: Utilities.formatDate(date, zone, 'yyyy-MM-dd'),
    report: Utilities.formatDate(date, zone, 'yyyyMMdd')
  };
}

function buildPayload(account, day) {
  var currency = account.getCurrencyCode();
  var costs = [];

  var rows = AdsApp.report(
    'SELECT campaign.id, campaign.name, campaign.advertising_channel_type, metrics.cost_micros ' +
    'FROM campaign ' +
    'WHERE segments.date BETWEEN ' + day.report + ' AND ' + day.report
  ).rows();

  while (rows.hasNext()) {
    var row = rows.next();
    var cost = Number(row['metrics.cost_micros']) / 1000000;

    if (cost <= 0) {
      continue;
    }

    costs.push({
      date: day.label,
      campaign: String(row['campaign.id']),
      campaign_name: String(row['campaign.name']),
      channel_type: String(row['campaign.advertising_channel_type']),
      amount: cost
    });
  }

  return {
    shop: CONFIG.SHOP,
    source: 'google-ads',
    currency: currency,
    costs: costs
  };
}

function send(payload, dayLabel) {
  var total = 0;
  for (var i = 0; i < payload.costs.length; i++) {
    total += payload.costs[i].amount;
  }

  Logger.log('Sending ' + payload.costs.length + ' campaign(s) for ' + dayLabel +
             ', total ' + total.toFixed(2) + ' ' + payload.currency +
             ' for shop ' + payload.shop + '.');

  var response = UrlFetchApp.fetch(CONFIG.ENDPOINT, {
    method: 'post',
    contentType: 'application/json',
    headers: {
      Authorization: 'Bearer ' + CONFIG.TOKEN,
      Accept: 'application/json'
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });

  var code = response.getResponseCode();
  var body = response.getContentText();

  if (code === 200) {
    Logger.log('OK — ' + body);
    return;
  }

  if (code === 403) {
    throw new Error('Aiku rejected the token (403). Either the token is wrong or revoked, or ' +
                    'SHOP does not match the shop the token was issued for. Body: ' + body);
  }

  if (code === 422) {
    throw new Error('Aiku could not read the data (422). Body: ' + body);
  }

  throw new Error('Aiku returned ' + code + '. Body: ' + body);
}
