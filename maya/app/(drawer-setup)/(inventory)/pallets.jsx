import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import PalletItem from '@/components/PalletItem';
import { createGlobalStyles } from '@/globalStyles';
import { useRouter } from 'expo-router';
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';

const Pallet = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);
  const router = useRouter();

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-pallets"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <PalletItem
            item={item}
            onPress={() => router.push(`/show-pallet?id=${item.id}`)}
          />
        )}
      />
    </View>
  );
};

export default Pallet;
