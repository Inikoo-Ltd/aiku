/**
 * Aiku — daily Google Ads cost upload (MCC / manager account).
 *
 * Same job as google-ads-script.js, but run once from a manager account: it loops over the
 * child accounts listed in ACCOUNTS and sends each one's spend with that account's own token.
 *
 * Note there is still ONE TOKEN PER SHOP. The manager account holds several tokens, it does not
 * get a master one: a token is only ever valid for the single shop it was issued for, so a shop
 * can be added, moved to a different agency, or cut off without touching the others.
 *
 * Paste into the manager account: Tools & Settings > Bulk Actions > Scripts, then schedule Daily.
 */

var ENDPOINT = 'https://aiku.io/webhooks/traffic-source-costs';

/** One entry per Google Ads account you want uploaded. customerId is the 123-456-7890 number. */
var ACCOUNTS = [
  { customerId: '123-456-7890', shop: 'SHOP-CODE', token: 'TOKEN-FOR-THAT-SHOP' }
  // , { customerId: '098-765-4321', shop: 'OTHER-SHOP', token: 'TOKEN-FOR-OTHER-SHOP' }
];

function main() {
  var failures = [];

  for (var i = 0; i < ACCOUNTS.length; i++) {
    var entry = ACCOUNTS[i];

    try {
      uploadAccount(entry);
    } catch (e) {
      /* Keep going: one account's bad token must not stop the others from being uploaded. */
      Logger.log('FAILED ' + entry.customerId + ' (' + entry.shop + '): ' + e.message);
      failures.push(entry.customerId);
    }
  }

  if (failures.length > 0) {
    throw new Error('Upload failed for: ' + failures.join(', ') + '. See the log above.');
  }
}

function uploadAccount(entry) {
  var accounts = AdsManagerApp.accounts().withIds([entry.customerId]).get();

  if (!accounts.hasNext()) {
    throw new Error('Account not found under this manager account.');
  }

  var account = accounts.next();
  AdsManagerApp.select(account);

  var day = yesterday(account);
  var payload = buildPayload(account, entry.shop, day);

  if (payload.costs.length === 0) {
    Logger.log(entry.customerId + ' (' + entry.shop + '): no spend on ' + day.label + '.');
    return;
  }

  send(payload, entry.token, day.label, entry.customerId);
}

function yesterday(account) {
  var zone = account.getTimeZone();
  var date = new Date(new Date().getTime() - 24 * 60 * 60 * 1000);

  return {
    label: Utilities.formatDate(date, zone, 'yyyy-MM-dd'),
    report: Utilities.formatDate(date, zone, 'yyyyMMdd')
  };
}

function buildPayload(account, shop, day) {
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
    shop: shop,
    source: 'google-ads',
    currency: account.getCurrencyCode(),
    costs: costs
  };
}

function send(payload, token, dayLabel, customerId) {
  var response = UrlFetchApp.fetch(ENDPOINT, {
    method: 'post',
    contentType: 'application/json',
    headers: {
      Authorization: 'Bearer ' + token,
      Accept: 'application/json'
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });

  var code = response.getResponseCode();
  var body = response.getContentText();

  if (code !== 200) {
    throw new Error('Aiku returned ' + code + ': ' + body);
  }

  Logger.log(customerId + ' (' + payload.shop + '): ' + dayLabel + ' — ' + body);
}
