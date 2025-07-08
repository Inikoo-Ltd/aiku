import urlConfig from '@/config/url';
import { getData } from '@/utils/AsyncStorage';
import { logout } from '@/utils/user';
import axios from 'axios';
import Constants from 'expo-constants';

const API_URL = Constants.expoConfig?.extra?.API_URL;

const ERROR_BY_STATUSES = {
  400: 'Data sent is invalid',
  401: 'Session expired',
  403: 'You do not have permission to perform this action.',
  404: 'Request Data Not Found',
  405: 'Method Not Allowed',
  408: 'Request Timeout',
  422: 'Unprocessable Content',
  500: 'Internal Server Error, please try again later.',
  offline: 'Fail connecting to server, please try again',
  default: 'Failed to fetch, please contact your admin!',
};

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Maya-Version': '1',
  },
});

// ===== TOKEN INJECTION =====
api.interceptors.request.use(
  async (config) => {
    const state = await getData('persist:user');
    const accessToken = state?.token;
    if (accessToken) {
      config.headers.Authorization = `Bearer ${accessToken}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// ===== RESPONSE HANDLING =====
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error.response?.status;

    // Log full error
    if (error.response) {
      console.log('❌ [API ERROR]', status, error.response.data);
    } else if (error.request) {
      console.log('❌ [API NO RESPONSE]', error.request);
    } else {
      console.log('❌ [AXIOS ERROR]', error.message);
    }

    // Handle token expiration
    if (status === 401) {
      logout(); // optionally navigate to login screen
    }

    return Promise.reject(error);
  }
);

// ===== MAIN REQUEST FUNCTION =====
const request = async ({
  method = 'GET',
  urlKey,
  url,
  args = [],
  params,
  data,
  headers = {},
  responseType = 'json',
  useCustomErrorMessage = false,
  default: defaultValue = null,
  onSuccess = () => {},
  onFailed = () => {},
  onBoth = () => {},
  extra = {},
  returnResultObject = false,
  key,
}) => {
  try {
    let finalUrl = url || urlConfig[urlKey]?.url;
    if (!finalUrl) throw new Error(`Invalid URL key: ${urlKey}`);

    // Inject args
    args.forEach((arg) => {
      finalUrl = finalUrl.replace('{}', arg);
    });

    const response = await api.request({
      method,
      url: finalUrl,
      params,
      data,
      headers,
      responseType,
    });

    onSuccess?.(response.data, extra);
    onBoth?.(true, response.data, extra);

    if (returnResultObject) {
      return { success: true, result: response.data, key, default: defaultValue };
    }

    return response.data;
  } catch (error) {
    console.log('error',error)
    const status = error.response?.status || 500;
    let errorMessage = ERROR_BY_STATUSES[status] || ERROR_BY_STATUSES.default;

    if (!useCustomErrorMessage && error.response?.data?.message) {
      errorMessage = error.response.data.message;
    }

    const errorObj = { status, data: errorMessage };

    onFailed?.(errorObj, extra);
    onBoth?.(false, errorObj, extra);

    if (returnResultObject) {
      return { success: false, result: errorObj, key, default: defaultValue };
    }

    return defaultValue;
  }
};

// ===== BULK REQUEST SUPPORT =====
const bulk = async (
  requests,
  options = {}
) => {
  const {
    returnResultObject = false,
    resultType = 'object',
    onSuccess,
    onFailed,
    onBoth,
  } = options;

  const results = await Promise.all(
    requests.map((req) => request({ ...req, returnResultObject: true }))
  );

  const success = !results.some((res) => res.success === false);
  const finalResults = resultType === 'object' ? {} : [];

  results.forEach((result) => {
    const resData = returnResultObject
      ? result
      : result.success
        ? result.result
        : result.default;

    if (resultType === 'object' && result.key) {
      finalResults[result.key] = resData;
    } else if (Array.isArray(finalResults)) {
      finalResults.push(resData);
    }
  });

  if (success) onSuccess?.(finalResults);
  else onFailed?.(finalResults);

  onBoth?.(success, finalResults);

  return finalResults;
};

request.bulk = bulk;

export default request;
