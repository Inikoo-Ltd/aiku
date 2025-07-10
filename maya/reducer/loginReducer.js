export const loginReducer = (prevState, action) => {
  switch (action.type) {
    case 'RETRIEVE_TOKEN':
      return {
        ...prevState,
        userToken: action.token,
        userData: action.userData,
        organisation: action.organisation,
        warehouse: action.warehouse ?? prevState.warehouse,
        fulfilment: action.fulfilment ?? prevState.fulfilment,
        isLoading: false,
      };
    case 'LOGIN':
      return {
        ...prevState,
        userToken: action.token,
        userData: action.userData,
        organisation: action.organisation,
        warehouse: action.warehouse,
        fulfilment: action.fulfilment,
        isLoading: false,
      };
     case 'LOGOUT':
      return {
        ...prevState,
        userToken: null,
        userData: null,
        organisation: null,
        isLoading: false,
      };
    case 'SET_ORGANISATION':
      return {
        ...prevState,
        organisation: action.organisation,
      };
    case 'SET_FULFILMENT_WAREHOUSE':
      return {
        ...prevState,
        warehouse: action.warehouse,
        fulfilment: action.fulfilment,
      };
    default:
      return prevState;
  }
};
