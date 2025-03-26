import React, {} from 'react';

import Home from '@/src/screens/Home';
import AgentStockControl from '@/src/screens/Agent/StockControl/AgentStockControl'
import AgentsGoodOut from '@/src/screens/Agent/GoodsOut/AgentsGoodOut'
import AgentGoodsIn from '@/src/screens/Agent/GoodsIn/AgentGoodsIn'
import LocationsStackScreen from '@/src/screens/routes/LocationsStackScreen';
import AgentSkus from '@/src/screens/Agent/StockControl/AgentSkus';

import {FontAwesomeIcon} from '@fortawesome/react-native-fontawesome';
import {
    faHome,
    faArrowToBottom,
    faArrowFromLeft,
    faBoxUsd
} from '@/private/fa/pro-regular-svg-icons';
import {faHandHoldingBox} from '@/private/fa/pro-regular-svg-icons';

const agentMenu = (fulfilment,warehouse) => {
    return [
        {
            name: 'Home',
            component: Home,
            options: {
                drawerIcon: ({color}) => (
                    <FontAwesomeIcon icon={faHome} size={22} color={color} />
                ),
            },
        },
        {
            name: 'Location',
            component: LocationsStackScreen,
            options: {
                drawerIcon: ({color}) => (
                    <FontAwesomeIcon
                        icon={faArrowFromLeft}
                        size={22}
                        color={color}
                    />
                ),
            },
        },
        {
            name: 'Stock Control',
            component: AgentSkus,
            options: {
                drawerIcon: ({color}) => (
                    <FontAwesomeIcon
                        icon={faBoxUsd}
                        size={22}
                        color={color}
                    />
                ),
            },
        },
        {
            name: 'Goods In',
            component: AgentGoodsIn,
            options: {
                drawerIcon: ({color}) => (
                    <FontAwesomeIcon
                        icon={faArrowToBottom}
                        size={22}
                        color={color}
                    />
                ),
            },
        },
        {
            name: 'Goods Out',
            component: AgentsGoodOut,
            options: {
                drawerIcon: ({color}) => (
                    <FontAwesomeIcon
                        icon={faArrowFromLeft}
                        size={22}
                        color={color}
                    />
                ),
            },
        },
    ]
}

export default agentMenu
      