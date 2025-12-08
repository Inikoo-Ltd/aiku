import React from 'react';
import {StyleSheet, View} from 'react-native';
import BaseList from '~/components/Base/BaseList';
import {ROUTES} from '~/constants';
import {Avatar, IconButton, Card, AnimatedFAB} from 'react-native-paper';
import {useNavigation} from '@react-navigation/native';


const WokingPlacesClockingMachines = p => {
  const navigation = useNavigation();

  const cardContent = (data: object) => {  
    return (
      <Card.Title
        title={data.name}
        subtitle={data.type}
        left={props => <Avatar.Icon {...props} icon="alarm-check" />}
        right={props => (
          <IconButton {...props} icon="chevron-right"/>
        )}
      />
    );
  };

  const renderAddButton = () => {
    return (
      <AnimatedFAB
        icon={'plus'}
        label={'Label'}
        onPress={() =>
          navigation.navigate(ROUTES.WORKING_PLACES_CLOCKING_MACHINE + 'add', {id: p.route.params.id})
        }
        visible={true}
        animateFrom={'right'}
        iconMode={'static'}
        style={[styles.fabStyle]}
      />
    );
  };

  return (
    <View style={styles.container}>
      <BaseList
        urlKey='hr-working-places-time-sheets'
        urlPrefix={ROUTES.WORKING_PLACES_CLOCKING_MACHINE}
        args={[p.route.params.id]}
        cardContent={cardContent}
        renderAddButton={renderAddButton}
      />
    </View>
  );
};

export default WokingPlacesClockingMachines;

const styles = StyleSheet.create({
  container: {
    flex: 1,
    paddingHorizontal: 10,
    paddingVertical: 0,
  },
  fabStyle: {
    bottom: 16,
    right: 16,
    position: 'absolute',
  },
});
