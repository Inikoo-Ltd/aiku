export const getFilteredActionsReturn = state => {
  if (state === 'confirmed') return {id: 'picking', title: 'Picking'};
  if (state === 'picking') return {id: 'picked', title: 'Picked'};
  if (state === 'picked') return {id: 'dispatch', title: 'Dispatched'};
  return [];
};

export const getFilteredActionsDelivery = state => {
  if (state === 'confirmed') {
    return {id: 'received', title: 'Received'};
  }
  if (state === 'received') {
    return {id: 'booking-in', title: 'Booking in'};
  }
  if (state === 'booking_in') {
    return {id: 'booked-in', title: 'Booked in'}
  }
  return [];
};