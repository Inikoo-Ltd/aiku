import { AuthContext } from '@/components/context/AuthContext';
import request from '@/utils/Request';
import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

const DeliveriesContext = createContext({
  data: null,
  loading: true,
  params: {},
  setParams: () => {},
  refetch: async () => {},
});

export const useDeliveries = () => useContext(DeliveriesContext);

export const DeliveriesProvider = ({ children }) => {
  const { organisation, warehouse } = useContext(AuthContext);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [params, setParams] = useState({});

  /** Main request function */
  const fetchDeliveries = useCallback(async (currentParams) => {
    if (!organisation?.id || !warehouse?.id || !currentParams?.id) return;

    setLoading(true);
    try {
      await request({
        urlKey: 'get-delivery',
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
            textBody: error.detail?.message || 'Failed to fetch data',
          });
        }
      });
    } finally {
      setLoading(false);
    }
  }, [organisation?.id, warehouse?.id]);

  useEffect(() => {
    fetchDeliveries(params);
  }, [params, fetchDeliveries]);

  const refetch = async () => fetchDeliveries(params);

  return (
    <DeliveriesContext.Provider value={{ data, loading, params, setParams, refetch }}>
      {children}
    </DeliveriesContext.Provider>
  );
};
