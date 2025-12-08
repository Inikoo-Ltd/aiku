import {
  Login,
  LoginScanner,
  FormProfile,
  Home,
  Profile,
  HomeHr,
  WorkingPlaces,
  WokingPlacesClockingMachines,
  FormWorkingPlaces,
  WorkingPlacesDetail,
  WokingPlacesCreateClockingMachine,
  CreateClockingMachines,
  ClockingMachines,
} from '~/screens';
import {ROUTES} from '~/constants';
import {Icon} from 'react-native-paper';


export default {
     routes : [
        {
          name: ROUTES.LOGIN,
          component: Login,
          option: {headerShown: false},
        },
        {
          name: ROUTES.LOGIN_SCANNER,
          component: LoginScanner,
          option: {headerShown: false},
        },
        //user
        {
          name: ROUTES.HOME,
          component: Home,
          option: {headerShown: false},
        },
        {
          name: ROUTES.PROFILE,
          component: Profile,
          option: {headerShown: false},
        },
        {
          name: ROUTES.PROFILE + 'edit',
          component: FormProfile,
          option: {headerShown: false},
        },
        //hr
        {
          name: ROUTES.HR,
          component: HomeHr,
          option: {headerShown: false},
          permissions: ['hr', 'hr.view'],
        },
        //working places
        {
          name: ROUTES.WORKING_PLACES,
          component: WorkingPlaces,
          option: {headerShown: true},
          permissions: ['hr', 'hr.view'],
        },
        {
          name: ROUTES.WORKING_PLACES + 'detail',
          component: WorkingPlacesDetail,
          option: {headerShown: true},
          permissions: ['hr', 'hr.view'],
        },
        {
          name: ROUTES.WORKING_PLACES + 'add',
          component: FormWorkingPlaces,
          option: {headerShown: true},
          permissions: ['hr', 'hr.edit'],
        },
        {
          name: ROUTES.WORKING_PLACES + 'edit',
          component: FormWorkingPlaces,
          option: {headerShown: true},
          permissions: ['hr', 'hr.edit'],
        },
        //WORKING_PLACES_CLOCKING_MACHINE
        {
          name: ROUTES.WORKING_PLACES_CLOCKING_MACHINE,
          component: WokingPlacesClockingMachines,
          option: {headerShown: true},
          permissions: ['hr', 'hr.view'],
        },
        {
          name: ROUTES.WORKING_PLACES_CLOCKING_MACHINE + 'add',
          component: WokingPlacesCreateClockingMachine,
          option: {headerShown: true},
          permissions: ['hr', 'hr.edit'],
        },
        //CLOCKING_MACHINE
        {
          name: ROUTES.CLOCKING_MACHINE,
          component: ClockingMachines,
          option: {headerShown: true},
          permissions: ['hr', 'hr.view'],
        },
        {
          name: ROUTES.CLOCKING_MACHINE + 'add',
          component: CreateClockingMachines,
          option: {headerShown: true},
          permissions: ['hr', 'hr.edit'],
        },
      ],



      BottomNavigatorRoutes : [
        {
          name: ROUTES.BOTTOMHOME,
          option: {headerShown: false},
          components: [
            {
              name: ROUTES.HOME,
              component: Home,
              option: {
                headerShown: false,
                tabBarIcon: ({color}) => (
                  <Icon source="home" color={color} size={26} />
                ),
              },
            },
            {
              name: ROUTES.HR,
              component: HomeHr,
              permissions: ['hr', 'hr.view'],
              option: {
                headerShown: true,
                tabBarIcon: ({color}) => (
                  <Icon source="human-male-board-poll" color={color} size={26} />
                ),
              },
            },
            {
              name: ROUTES.PROFILE,
              component: Profile,
              option: {
                headerShown: true,
                tabBarIcon: ({color}) => (
                  <Icon source="account" color={color} size={26} />
                ),
              },
            },
          ],
        },
        {
          name: ROUTES.WORKING_PLACES + ' Detail',
          option: {headerShown: false},
          components: [
            {
              name: ROUTES.WORKING_PLACES + 'detail',
              permissions: ['hr', 'hr.view'],
              component: WorkingPlacesDetail,
              option: {
                headerShown: false,
                tabBarIcon: ({color}) => (
                  <Icon source="google-maps" color={color} size={26} />
                ),
              },
            },
            {
              name: ROUTES.HR,
              component: WokingPlacesClockingMachines,
              permissions: ['hr', 'hr.view'],
              option: {
                headerShown: true,
                tabBarIcon: ({color}) => (
                  <Icon source="timer" color={color} size={26} />
                ),
              },
            },
          ],
        },
      ]
  }