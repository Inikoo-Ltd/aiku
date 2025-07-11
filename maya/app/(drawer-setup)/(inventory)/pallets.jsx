import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import PalletItem from '@/components/PalletItem';
import { createGlobalStyles } from '@/globalStyles';
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';

const Pallet = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-pallets"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <PalletItem
            item={item}
          />
        )}
      />
    </View>
  );
};

export default Pallet;
