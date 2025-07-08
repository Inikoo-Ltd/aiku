export const loginReducer = (prevState, action) => {
  switch (action.type) {
    case 'RETRIEVE_TOKEN':
    case 'LOGIN':
      return {
        ...prevState,
        userToken: action.token,
        userData: action.userData,
        organisation: action.organisation,
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
        fulfilment: action.fulfilment,
        warehouse: action.warehouse,
      };
    case 'LOGOUT':
      return {
        ...prevState,
        userToken: null,
        userData: null,
        organisation: null,
        isLoading: false,
      };
    default:
      return prevState;
  }
};
