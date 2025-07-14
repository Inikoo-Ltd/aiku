import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import { useReturns } from '@/components/context/return';
import PalletInReturnItem from '@/components/PalletInReturnItem';
import { createGlobalStyles } from '@/globalStyles';
import { useLocalSearchParams } from 'expo-router';
import { useContext, useEffect, useState } from 'react';
import { View, useColorScheme } from 'react-native';

const PalletInReturns = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const { data } = useReturns();
  const { id } = useLocalSearchParams();
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const styles = createGlobalStyles(isDark);

  const [totalPallets, setTotalPallets] = useState(0);

  useEffect(() => {
    if (data) {
      const total =
        (data.number_pallets_state_not_received ?? 0) +
        (data.number_pallets_state_booked_in ?? 0) +
        (data.number_pallet_storing ?? 0);
      setTotalPallets(total);
    }
  }, [data]);


  return (
    <View style={styles.container}>
      <BaseList
        urlKey="get-return-pallets"
        args={[organisation.id, warehouse.id, data.id]}
        height={100}
        showTotalResults={() => null}
        listItem={({ item }) => <PalletInReturnItem item={item} />}
      />
    </View>
  );
};

export default PalletInReturns;
