/**
 * Aiku — daily Microsoft Advertising (Bing Ads) cost upload (single account).
 *
 * Same job as google-ads-script.js, against the same endpoint and the same kind of token, written
 * for Microsoft Advertising Scripts. Paste it into Tools > Scripts in the Microsoft Advertising
 * account, fill in the three CONFIG values, authorise it, and schedule it Daily.
 *
 * Microsoft Advertising Scripts has no report() call, so spend comes from the campaign selectors
 * and their stats. Shopping and Performance Max campaigns have selectors of their own, so all three
 * are read and de-duplicated by campaign id: a campaign missing from the plain campaigns() list
 * would otherwise take its whole day's spend with it.
 *
 * Safe to run as many times as you like: the same day posted twice updates the figure, it never
 * adds to it.
 */

var CONFIG = {
  ENDPOINT: 'https://aiku.io/webhooks/traffic-source-costs',
  TOKEN: 'PASTE-YOUR-TOKEN-HERE',
  SHOP: 'PASTE-YOUR-SHOP-SLUG-HERE'
};

function main() {
  var account = AdsApp.currentAccount();
  var day = yesterday(account);
  var payload = buildPayload(CONFIG.SHOP, account, day);

  if (payload.costs.length === 0) {
    Logger.log('No campaign spend on ' + day.label + ' — nothing to send.');
    return;
  }

  send(payload, CONFIG.TOKEN, day);
}

/** Yesterday in the account's own time zone, which is the day Microsoft Advertising reports on. */
function yesterday(account) {
  var zone = account.getTimeZone();
  var date = new Date(new Date().getTime() - 24 * 60 * 60 * 1000);
  var parts = accountDateParts(date, zone);

  return {
    range: parts,
    label: parts.year + '-' + pad(parts.month) + '-' + pad(parts.day),
    zone: zone
  };
}

/**
 * Scripts has no Utilities.formatDate and does not document the time zone its runtime uses, so the
 * account's own zone is applied through the locale formatter where the sandbox offers one. Where it
 * does not — or where getTimeZone() hands back a name the formatter refuses — the runtime's own date
 * is used instead. The date and the zone both go in the log line so the first Preview run shows
 * which day was actually read.
 */
function accountDateParts(date, zone) {
  try {
    var parts = date.toLocaleDateString('en-CA', { timeZone: zone }).split('-');

    if (parts.length === 3) {
      return { year: Number(parts[0]), month: Number(parts[1]), day: Number(parts[2]) };
    }
  } catch (e) {
    /* Fall through to the runtime's date. */
  }

  return { year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate() };
}

function pad(number) {
  return number < 10 ? '0' + number : '' + number;
}

function buildPayload(shop, account, day) {
  var costs = [];
  var seen = {};

  /* Shopping and Performance Max are read first so that a campaign returned by more than one
     selector keeps the type of the selector that names it; campaigns() is the mixed bucket that
     names nothing, and calling all of it SEARCH would invent a label Microsoft never gave. */
  var buckets = [
    { channelType: 'SHOPPING', selector: AdsApp.shoppingCampaigns() },
    { channelType: 'PERFORMANCE_MAX', selector: AdsApp.performanceMaxCampaigns() },
    { channelType: null, selector: AdsApp.campaigns() }
  ];

  for (var i = 0; i < buckets.length; i++) {
    collect(costs, seen, buckets[i], day);
  }

  return {
    shop: shop,
    source: 'bing-ads',
    currency: account.getCurrencyCode(),
    costs: costs
  };
}

function collect(costs, seen, bucket, day) {
  var campaigns = bucket.selector.forDateRange(day.range, day.range).get();

  while (campaigns.hasNext()) {
    var campaign = campaigns.next();
    var id = String(campaign.getId());

    if (seen[id]) {
      continue;
    }

    seen[id] = true;

    var cost = Number(campaign.getStats().getCost());

    if (!(cost > 0)) {
      continue;
    }

    costs.push({
      date: day.label,
      campaign: id,
      campaign_name: String(campaign.getName()),
      channel_type: bucket.channelType,
      amount: cost
    });
  }
}

function send(payload, token, day) {
  var total = 0;
  for (var i = 0; i < payload.costs.length; i++) {
    total += payload.costs[i].amount;
  }

  Logger.log('Sending ' + payload.costs.length + ' campaign(s) for ' + day.label +
             ' (account time zone ' + day.zone + '), total ' + total.toFixed(2) + ' ' +
             payload.currency + ' for shop ' + payload.shop + '.');

  var response = UrlFetchApp.fetch(CONFIG.ENDPOINT, {
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
