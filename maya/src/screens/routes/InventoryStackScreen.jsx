
import React, {} from 'react';
import { SafeAreaView } from 'react-native';
import BottomTabs from '@/src/components/BottomTabs'

import OrgStocks from '@/src/screens/Fulfilment/OrgStock/OrgStocks';
import Pallet from '@/src/screens/Fulfilment/Pallet/Pallets'
import StoredItem from '@/src/screens/Fulfilment/StoredItem/StoredItems'

import { faBoxesAlt, faPallet, faNarwhal } from '@/private/fa/pro-regular-svg-icons';

const TabArr = [
  {route: 'families', label: 'Families', icon: faBoxesAlt, component: OrgStocks},
  {route: 'pallet', label: 'Pallet', icon: faPallet, component: Pallet},
  {route: 'stored-items', label: 'SKU', icon: faNarwhal, component: StoredItem},
];

export default function InventoryStackScreen() {
  return (
    <SafeAreaView style={{flex: 1}}>
      <BottomTabs tabArr={TabArr} />
    </SafeAreaView>
  );
}