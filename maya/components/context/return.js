import { AuthContext } from '@/components/context/AuthContext';
import request from '@/utils/Request';
import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

const ReturnsContext = createContext({
  data: null,
  loading: true,
  params: {},
  setParams: () => {},
  refetch: async () => {},
});

export const useReturns = () => useContext(ReturnsContext);

export const ReturnsProvider = ({ children }) => {
  const { organisation, warehouse } = useContext(AuthContext);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [params, setParams] = useState({});

  const fetchReturns = useCallback(async (currentParams) => {
    if (!organisation?.id || !warehouse?.id || !currentParams?.id) return;

    setLoading(true);
    try {
      await request({
        urlKey: 'get-return',
        args: [organisation.id, warehouse.id, currentParams.id],
        onSuccess: response => {
          setData(response.data);
          setLoading(false);
        },
        onFailed: error => {
          setLoading(false);
          Toast.show({
            type: ALERT_TYPE.DANGER,
            title: 'Error',
            textBody: error.detail?.message || 'Failed to fetch return data',
          });
        }
      });
    } finally {
      setLoading(false);
    }
  }, [organisation?.id, warehouse?.id]);

  useEffect(() => {
    fetchReturns(params);
  }, [params, fetchReturns]);

  const refetch = async () => fetchReturns(params);

  return (
    <ReturnsContext.Provider value={{ data, loading, params, setParams, refetch }}>
      {children}
    </ReturnsContext.Provider>
  );
};