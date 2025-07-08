import { createContext, useContext, useReducer } from 'react';

const ReturnContext = createContext();

const ReturnReducer = (state, action) => {
    switch (action.type) {
        case 'SET_DATA':
            return {
                ...state,
                data: state.data
                    ? {...state.data, ...action.payload}
                    : action.payload,
            };
        default:
            return state;
    }
};

export const ReturnProvider = ({children}) => {
    const [state, dispatch] = useReducer(ReturnReducer, { data: null });

    return (
        <ReturnContext.Provider
            value={{
                data: state.data,
                setData: (updater) => {
                    // Check if updater is a function (functional update)
                    if (typeof updater === 'function') {
                        dispatch({
                            type: 'SET_DATA',
                            payload: updater(state.data || {}), // Ensure state.data is an object
                        });
                    } else {
                        dispatch({ type: 'SET_DATA', payload: updater });
                    }
                },
            }}>
            {children}
        </ReturnContext.Provider>
    );
};

export const useReturn = () => useContext(ReturnContext);
