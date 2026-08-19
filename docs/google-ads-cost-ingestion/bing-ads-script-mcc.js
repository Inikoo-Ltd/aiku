/**
 * Aiku — daily Microsoft Advertising (Bing Ads) cost upload (manager account).
 *
 * Same job as bing-ads-script.js, but run once from a manager account: it loops over the accounts
 * listed in ACCOUNTS and sends each one's spend with that account's own token. Multi-account scripts
 * only exist in the Scripts editor reached from Accounts Summary; the per-account editor has no
 * AccountsApp.
 *
 * Note there is still ONE TOKEN PER SHOP. The manager account holds several tokens, it does not get
 * a master one: a token is only ever valid for the single shop it was issued for, so a shop can be
 * added, moved to a different agency, or cut off without touching the others.
 */

var ENDPOINT = 'https://aiku.io/webhooks/traffic-source-costs';

/**
 * One entry per Microsoft Advertising account you want uploaded. Give it either the account number
 * shown in Accounts Summary (accountNumber) or the numeric account id (accountId).
 */
var ACCOUNTS = [
  { accountNumber: 'X0000000', shop: 'SHOP-SLUG', token: 'TOKEN-FOR-THAT-SHOP' }
  // , { accountId: '123456789', shop: 'OTHER-SHOP-SLUG', token: 'TOKEN-FOR-OTHER-SHOP' }
];

function main() {
  var failures = [];

  for (var i = 0; i < ACCOUNTS.length; i++) {
    var entry = ACCOUNTS[i];

    try {
      uploadAccount(entry);
    } catch (e) {
      /* Keep going: one account's bad token must not stop the others from being uploaded. */
      Logger.log('FAILED ' + accountLabel(entry) + ' (' + entry.shop + '): ' + e.message);
      failures.push(accountLabel(entry));
    }
  }

  if (failures.length > 0) {
    throw new Error('Upload failed for: ' + failures.join(', ') + '. See the log above.');
  }
}

function uploadAccount(entry) {
  var accounts = entry.accountNumber
    ? AccountsApp.accounts().withAccountNumbers([entry.accountNumber]).get()
    : AccountsApp.accounts().withIds([entry.accountId]).get();

  if (!accounts.hasNext()) {
    throw new Error('Account not found under this manager account.');
  }

  var account = accounts.next();
  AccountsApp.select(account);

  var day = yesterday(account);
  var payload = buildPayload(entry.shop, account, day);

  if (payload.costs.length === 0) {
    Logger.log(accountLabel(entry) + ' (' + entry.shop + '): no spend on ' + day.label + '.');
    return;
  }

  send(payload, entry.token, day, accountLabel(entry));
}

function accountLabel(entry) {
  return entry.accountNumber || entry.accountId;
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
 * does not, the runtime's own date is used instead. The date and the zone both go in the log line so
 * the first Preview run shows which day was actually read.
 */
function accountDateParts(date, zone) {
  return localeDateParts(date, zone) ||
         { year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate() };
}

/**
 * The formatter is under no obligation to honour the locale it is handed, and its output has been
 * seen carrying a trailing time and direction marks. Splitting that on '-' yielded pieces such as
 * '17T00:00:00', which reach forDateRange as NaN and are rejected there with a message that names
 * the date and not the formatter that produced it. So only a string whose digits read as a real
 * y-m-d date is trusted, and anything else hands the day back to the runtime clock.
 */
function localeDateParts(date, zone) {
  var formatted;

  try {
    formatted = String(date.toLocaleDateString('en-CA', { timeZone: zone }));
  } catch (e) {
    return null;
  }

  var match = /(\d{4})\D{1,3}(\d{1,2})\D{1,3}(\d{1,2})/.exec(formatted);

  if (!match) {
    return null;
  }

  var parts = { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]) };

  if (parts.month < 1 || parts.month > 12 || parts.day < 1 || parts.day > 31) {
    return null;
  }

  return parts;
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

function send(payload, token, day, label) {
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

  if (code === 403) {
    throw new Error('Aiku rejected the token (403): wrong, revoked, or issued for another shop. ' +
                    'Body: ' + body);
  }

  if (code !== 200) {
    throw new Error('Aiku returned ' + code + ': ' + body);
  }

  Logger.log(label + ' (' + payload.shop + '): ' + day.label + ' in ' + day.zone + ' — ' + body);
}
