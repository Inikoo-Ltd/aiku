import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import PalletReturnItem from '@/components/PalletReturnItem';
import { createGlobalStyles } from '@/globalStyles';
import { useRouter } from 'expo-router';
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';

const FulfilmentReturns = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);
  const router = useRouter();

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-returns"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <PalletReturnItem
            item={item}
            onPress={() => router.push(`/show-fulfilment-return?id=${item.id}`)}
          />
        )}
      />
    </View>
  );
};

export default FulfilmentReturns;
