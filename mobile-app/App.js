import React, { useContext } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { AuthProvider, AuthContext } from './src/context/AuthContext';
import { StatusBar } from 'expo-status-bar';
import { View, ActivityIndicator, Platform, StyleSheet, TouchableOpacity, Text } from 'react-native';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import GlobalSplashScreen from './src/components/GlobalSplashScreen';
import * as Font from 'expo-font';
// PermissionsScreen removed — Apple 5.1.1(iv) compliance: no custom pre-permission screens
import { TextInput } from 'react-native';

// PRO ÖZELLİK: Telefonun erişilebilirlik (büyük yazı) ayarları açık olsa bile
// tasarımın bozulmamasını ve iç içe girmemesini sağlamak için maksimum büyüme oranı (1.15x) koyuyoruz.
// Böylece yazı hem biraz büyüyüp okunabilir olur, hem de Premium UI kırılmaz.
if (Text.defaultProps == null) Text.defaultProps = {};
Text.defaultProps.maxFontSizeMultiplier = 1.15;

if (TextInput.defaultProps == null) TextInput.defaultProps = {};
TextInput.defaultProps.maxFontSizeMultiplier = 1.15;

import LoginScreen from './src/screens/LoginScreen';
import ForgotPasswordScreen from './src/screens/ForgotPasswordScreen';
import LoginTransitionScreen from './src/screens/LoginTransitionScreen';
import ProfileScreen from './src/screens/ProfileScreen';
import HomeScreen from './src/screens/HomeScreen';
import MenuScreen from './src/screens/MenuScreen';
import VehiclesScreen from './src/screens/VehiclesScreen';
import PersonnelScreen from './src/screens/PersonnelScreen';
import PersonnelDetailScreen from './src/screens/PersonnelDetailScreen';
import CustomersScreen from './src/screens/CustomersScreen';
import CustomerDetailScreen from './src/screens/CustomerDetailScreen';
import TripsScreen from './src/screens/TripsScreen';
import TripDetailScreen from './src/screens/TripDetailScreen';
import PilotChatScreen from './src/screens/PilotChatScreen';
import SupportScreen from './src/screens/SupportScreen';
import SecurityScreen from './src/screens/SecurityScreen';
import NotificationSettingsScreen from './src/screens/NotificationSettingsScreen';
import AccountInfoScreen from './src/screens/AccountInfoScreen';
import ActivityScreen from './src/screens/ActivityScreen';
import TrackingScreen from './src/screens/TrackingScreen';
import CompanyUsersScreen from './src/screens/CompanyUsersScreen';
import CompanyUserFormScreen from './src/screens/CompanyUserFormScreen';
import ReportsScreen from './src/screens/ReportsScreen';
import CompanyDocumentsScreen from './src/screens/CompanyDocumentsScreen';
import TendersScreen from './src/screens/TendersScreen';
import TenderFormScreen from './src/screens/TenderFormScreen';
import PayrollScreen from './src/screens/PayrollScreen';
import PayrollDetailScreen from './src/screens/PayrollDetailScreen';
import FinanceScreen from './src/screens/FinanceScreen';
import ExpensesScreen from './src/screens/ExpensesScreen';
import VehicleDetailScreen from './src/screens/VehicleDetailScreen';
import VehicleDocumentsScreen from './src/screens/VehicleDocumentsScreen';
import VehicleFuelsScreen from './src/screens/VehicleFuelsScreen';
import VehicleMaintenancesScreen from './src/screens/VehicleMaintenancesScreen';
import VehiclePenaltiesScreen from './src/screens/VehiclePenaltiesScreen';
import VehicleGalleryScreen from './src/screens/VehicleGalleryScreen';
import VehicleReportsScreen from './src/screens/VehicleReportsScreen';
import UpcomingInspectionsScreen from './src/screens/UpcomingInspectionsScreen';
import UpcomingInsurancesScreen from './src/screens/UpcomingInsurancesScreen';
import FuelsScreen from './src/screens/FuelsScreen';
import FuelFormScreen from './src/screens/FuelFormScreen';
import FuelStationsScreen from './src/screens/FuelStationsScreen';
import StationStatementScreen from './src/screens/StationStatementScreen';
import MaintenancesScreen from './src/screens/MaintenancesScreen';
import MaintenanceSettingsScreen from './src/screens/MaintenanceSettingsScreen';
import PenaltiesScreen from './src/screens/PenaltiesScreen';
import PenaltyFormScreen from './src/screens/PenaltyFormScreen';
import PilotCellDriverScreen from './src/screens/PilotCellDriverScreen';
import PilotCellPointsScreen from './src/screens/PilotCellPointsScreen';
import PilotCellMapScreen from './src/screens/PilotCellMapScreen';
import ParentHomeScreen from './src/screens/ParentHomeScreen';
import ParentPaymentScreen from './src/screens/ParentPaymentScreen';
import ParentNotificationsScreen from './src/screens/ParentNotificationsScreen';
import ParentAbsenceScreen from './src/screens/ParentAbsenceScreen';
import ParentSettingsScreen from './src/screens/ParentSettingsScreen';
import PilotCellRadiusScreen from './src/screens/PilotCellRadiusScreen';
import PilotCellRadiusMapScreen from './src/screens/PilotCellRadiusMapScreen';
import PilotCellTripScreen from './src/screens/PilotCellTripScreen';
const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

// Tema Renkleri (Uzay Derinliği Mavisi)
const theme = {
    primary: '#2563EB', // Blue 600
    bg: '#F8FAFC', // Slate 50
    inactive: '#94A3B8', // Slate 400
    shadow: '#2563EB'
};

function CustomTabBar({ state, descriptors, navigation }) {
    const { unreadChatCount } = useContext(AuthContext);
    const insets = useSafeAreaInsets();

    // Check if any active screen wants to hide the tab bar
    const focusedOptions = descriptors[state.routes[state.index].key].options;
    if (focusedOptions?.tabBarStyle?.display === 'none') return null;

    const bottomPadding = Math.max(insets.bottom, 8);

    const tabs = [
        { icon: 'menu', iconActive: 'menu-open', label: 'Menü' },
        { icon: 'car-outline', iconActive: 'car', label: 'Araçlar' },
        { icon: 'plus', label: '' },
        { icon: 'chat-outline', iconActive: 'chat', label: 'Mesaj' },
        { icon: 'account-outline', iconActive: 'account', label: 'Profil' },
    ];
    return (
        <View style={[tabS.wrap, { paddingBottom: bottomPadding }]}>
            <View style={tabS.bar}>
                {state.routes.map((route, i) => {
                    const focused = state.index === i;
                    const isCenter = i === 2;
                    const t = tabs[i];
                    const onPress = () => {
                        const event = navigation.emit({
                            type: 'tabPress',
                            target: route.key,
                            canPreventDefault: true,
                        });

                        if (!focused && !event.defaultPrevented) {
                            navigation.navigate(route.name);
                        } else if (focused && !event.defaultPrevented) {
                            // Reset to initial screen of the stack if pressing already focused tab
                            if (route.name === 'MenuTab') navigation.navigate('MenuTab', { screen: 'Menu' });
                            if (route.name === 'VehiclesTab') navigation.navigate('VehiclesTab', { screen: 'Vehicles' });
                        }
                    };
                    if (isCenter) return (
                        <TouchableOpacity key={i} style={tabS.centerBtn} onPress={onPress} activeOpacity={0.85}>
                            <View style={tabS.centerInner}><Icon name="plus" size={30} color="#fff" /></View>
                        </TouchableOpacity>
                    );
                    return (
                        <TouchableOpacity key={i} style={tabS.tab} onPress={onPress} activeOpacity={0.7}>
                            <View style={{ position: 'relative' }}>
                                <Icon name={focused ? t.iconActive : t.icon} size={26} color={focused ? theme.primary : theme.inactive} />
                                {i === 3 && unreadChatCount > 0 && (
                                    <View style={tabS.badge}>
                                        <Text style={tabS.badgeText}>{unreadChatCount > 9 ? '9+' : unreadChatCount}</Text>
                                    </View>
                                )}
                            </View>
                            <Text style={[tabS.label, focused && tabS.labelActive]}>{t.label}</Text>
                        </TouchableOpacity>
                    );
                })}
            </View>
        </View>
    );
}

function CustomParentTabBar({ state, descriptors, navigation }) {
    const insets = useSafeAreaInsets();
    const focusedOptions = descriptors[state.routes[state.index].key].options;
    if (focusedOptions?.tabBarStyle?.display === 'none') return null;

    const bottomPadding = Math.max(insets.bottom, 8);

    const tabs = [
        { icon: 'credit-card-outline', iconActive: 'credit-card', label: 'Ödeme' },
        { icon: 'map-marker-outline', iconActive: 'map-marker', label: 'Takip Et' },
        { icon: 'bell', label: '' },
        { icon: 'calendar-remove-outline', iconActive: 'calendar-remove', label: 'Gelmeyecek' },
        { icon: 'cog-outline', iconActive: 'cog', label: 'Ayarlar' },
    ];

    return (
        <View style={[tabS.wrap, { paddingBottom: bottomPadding }]}>
            <View style={tabS.bar}>
                {state.routes.map((route, i) => {
                    const focused = state.index === i;
                    const isCenter = i === 2;
                    const t = tabs[i];
                    const onPress = () => {
                        const event = navigation.emit({
                            type: 'tabPress',
                            target: route.key,
                            canPreventDefault: true,
                        });

                        if (!focused && !event.defaultPrevented) {
                            navigation.navigate(route.name);
                        }
                    };
                    if (isCenter) return (
                        <TouchableOpacity key={i} style={tabS.centerBtn} onPress={onPress} activeOpacity={0.85}>
                            <View style={[tabS.centerInner, { backgroundColor: '#8B5CF6', shadowColor: '#8B5CF6' }]}>
                                <Icon name="bell" size={26} color="#fff" />
                            </View>
                        </TouchableOpacity>
                    );
                    return (
                        <TouchableOpacity key={i} style={tabS.tab} onPress={onPress} activeOpacity={0.7}>
                            <Icon name={focused ? t.iconActive : t.icon} size={26} color={focused ? '#8B5CF6' : theme.inactive} />
                            <Text style={[tabS.label, focused && { color: '#8B5CF6', fontWeight: '700' }]}>{t.label}</Text>
                        </TouchableOpacity>
                    );
                })}
            </View>
        </View>
    );
}

const tabS = StyleSheet.create({
    wrap: { position: 'absolute', bottom: 0, left: 0, right: 0, paddingHorizontal: 16, backgroundColor: 'transparent' },
    bar: { flexDirection: 'row', backgroundColor: '#ffffff', borderRadius: 28, paddingVertical: 12, paddingHorizontal: 8, alignItems: 'center', justifyContent: 'space-around', shadowColor: '#000', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.1, shadowRadius: 20, elevation: 15 },
    tab: { alignItems: 'center', justifyContent: 'center', paddingVertical: 4, flex: 1 },
    label: { fontSize: 10, fontWeight: '600', color: theme.inactive, marginTop: 4 },
    labelActive: { color: theme.primary, fontWeight: '700' },
    centerBtn: { alignItems: 'center', justifyContent: 'center', marginTop: -30 },
    centerInner: { width: 56, height: 56, borderRadius: 28, backgroundColor: theme.primary, alignItems: 'center', justifyContent: 'center', shadowColor: theme.primary, shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.4, shadowRadius: 16, elevation: 10 },
    badge: { position: 'absolute', top: -4, right: -6, backgroundColor: '#EF4444', minWidth: 16, height: 16, borderRadius: 8, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4, borderWidth: 1.5, borderColor: '#FFF' },
    badgeText: { color: '#FFF', fontSize: 9, fontWeight: '900' }
});

const VehiclesStack = createNativeStackNavigator();
function VehiclesStackScreen() {
    return (
        <VehiclesStack.Navigator screenOptions={{ headerShown: false, gestureEnabled: true, fullScreenGestureEnabled: true, animation: 'slide_from_right', gestureDirection: 'horizontal', customAnimationOnSwipe: true }}>
            <VehiclesStack.Screen name="Vehicles" component={VehiclesScreen} />
            <VehiclesStack.Screen name="VehicleDetail" component={VehicleDetailScreen} />
            <VehiclesStack.Screen name="Fuels" component={FuelsScreen} />
            <VehiclesStack.Screen name="FuelForm" component={FuelFormScreen} />
            <VehiclesStack.Screen name="FuelStations" component={FuelStationsScreen} />
            <VehiclesStack.Screen name="StationStatement" component={StationStatementScreen} />
            <VehiclesStack.Screen name="VehicleDocuments" component={VehicleDocumentsScreen} />
            <VehiclesStack.Screen name="VehicleFuels" component={VehicleFuelsScreen} />
            <VehiclesStack.Screen name="VehicleMaintenances" component={VehicleMaintenancesScreen} />
            <VehiclesStack.Screen name="VehiclePenalties" component={VehiclePenaltiesScreen} />
            <VehiclesStack.Screen name="VehicleGallery" component={VehicleGalleryScreen} />
            <VehiclesStack.Screen name="VehicleReports" component={VehicleReportsScreen} />
        </VehiclesStack.Navigator>
    );
}

const MenuStack = createNativeStackNavigator();
function MenuStackScreen() {
    return (
        <MenuStack.Navigator screenOptions={{ headerShown: false, gestureEnabled: true, fullScreenGestureEnabled: true, animation: 'slide_from_right', gestureDirection: 'horizontal', customAnimationOnSwipe: true }}>
            <MenuStack.Screen name="Menu" component={MenuScreen} />
            <MenuStack.Screen name="Personnel" component={PersonnelScreen} />
            <MenuStack.Screen name="PersonnelDetail" component={PersonnelDetailScreen} />
            <MenuStack.Screen name="Maintenances" component={MaintenancesScreen} />
            <MenuStack.Screen name="Penalties" component={PenaltiesScreen} />
            <MenuStack.Screen name="PenaltyForm" component={PenaltyFormScreen} />
            <MenuStack.Screen name="Customers" component={CustomersScreen} />
            <MenuStack.Screen name="CustomerDetail" component={CustomerDetailScreen} />
            <MenuStack.Screen name="Trips" component={TripsScreen} />
            <MenuStack.Screen name="TripDetail" component={TripDetailScreen} />
            <MenuStack.Screen name="Reports" component={ReportsScreen} />
            <MenuStack.Screen name="Tracking" component={TrackingScreen} />
            <MenuStack.Screen name="CompanyDocuments" component={CompanyDocumentsScreen} />
            <MenuStack.Screen name="Tenders" component={TendersScreen} />
            <MenuStack.Screen name="TenderForm" component={TenderFormScreen} />
            <MenuStack.Screen name="Payrolls" component={PayrollScreen} />
            <MenuStack.Screen name="PayrollDetail" component={PayrollDetailScreen} />
            <MenuStack.Screen name="Finance" component={FinanceScreen} />
            <MenuStack.Screen name="Expenses" component={ExpensesScreen} />
            <MenuStack.Screen name="Activity" component={ActivityScreen} />
            <MenuStack.Screen name="Support" component={SupportScreen} />
            <MenuStack.Screen name="Security" component={SecurityScreen} />
            <MenuStack.Screen name="NotificationSettings" component={NotificationSettingsScreen} />
            <MenuStack.Screen name="AccountInfo" component={AccountInfoScreen} />
            <MenuStack.Screen name="MaintenanceSettings" component={MaintenanceSettingsScreen} />
            <MenuStack.Screen name="UpcomingInspections" component={UpcomingInspectionsScreen} />
            <MenuStack.Screen name="UpcomingInsurances" component={UpcomingInsurancesScreen} />
            <MenuStack.Screen name="CompanyUsers" component={CompanyUsersScreen} />
            <MenuStack.Screen name="CompanyUserForm" component={CompanyUserFormScreen} />
            <MenuStack.Screen name="PilotCellDriver" component={PilotCellDriverScreen} />
            <MenuStack.Screen name="PilotCellPoints" component={PilotCellPointsScreen} />
            <MenuStack.Screen name="PilotCellRadius" component={PilotCellRadiusScreen} />
            <MenuStack.Screen name="PilotCellRadiusMap" component={PilotCellRadiusMapScreen} />
            <MenuStack.Screen name="PilotCellTrip" component={PilotCellTripScreen} />
        </MenuStack.Navigator>
    );
}

function MainTabs() {
    return (
        <Tab.Navigator tabBar={p => <CustomTabBar {...p} />} screenOptions={{ headerShown: false }}>
            <Tab.Screen name="MenuTab" component={MenuStackScreen} />
            <Tab.Screen name="VehiclesTab" component={VehiclesStackScreen} />
            <Tab.Screen name="HomeTab" component={HomeScreen} />
            <Tab.Screen name="ChatTab" component={PilotChatScreen} />
            <Tab.Screen name="Profile" component={ProfileScreen} />
        </Tab.Navigator>
    );
}

function ParentTabs() {
    return (
        <Tab.Navigator tabBar={p => <CustomParentTabBar {...p} />} screenOptions={{ headerShown: false }} initialRouteName="ParentTrack">
            <Tab.Screen name="ParentPayment" component={ParentPaymentScreen} />
            <Tab.Screen name="ParentTrack" component={ParentHomeScreen} />
            <Tab.Screen name="ParentNotifications" component={ParentNotificationsScreen} />
            <Tab.Screen name="ParentAbsence" component={ParentAbsenceScreen} />
            <Tab.Screen name="ParentSettings" component={ParentSettingsScreen} />
        </Tab.Navigator>
    );
}

const DriverStack = createNativeStackNavigator();
function DriverStackScreen() {
    const { userInfo } = useContext(AuthContext);
    const initialRoute = 'PilotCellDriverMain';

    return (
        <DriverStack.Navigator screenOptions={{ headerShown: false, gestureEnabled: false }} initialRouteName={initialRoute}>
            <DriverStack.Screen name="PilotCellDriverMain" component={PilotCellDriverScreen} />
            <DriverStack.Screen name="PilotCellPoints" component={PilotCellPointsScreen} />
            <DriverStack.Screen name="PilotCellMap" component={PilotCellMapScreen} />
            <DriverStack.Screen name="PilotCellRadius" component={PilotCellRadiusScreen} />
            <DriverStack.Screen name="PilotCellRadiusMap" component={PilotCellRadiusMapScreen} />
            <DriverStack.Screen name="PilotCellTrip" component={PilotCellTripScreen} />
        </DriverStack.Navigator>
    );
}

function AppNavigation() {
    const { userToken, userInfo, isInitializing, showTransition, setShowTransition } = useContext(AuthContext);
    if (isInitializing) return <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: theme.bg }}><ActivityIndicator size="large" color={theme.primary} /></View>;
    return (
        <NavigationContainer>
            <Stack.Navigator screenOptions={{ headerShown: false, gestureEnabled: false }}>
                {userToken === null ? (
                    <Stack.Group>
                        <Stack.Screen name="Login" component={LoginScreen} />
                        <Stack.Screen name="ForgotPassword" component={ForgotPasswordScreen} options={{ gestureEnabled: true, animation: 'slide_from_right', fullScreenGestureEnabled: true }} />
                    </Stack.Group>
                ) : (
                    <Stack.Group>
                        {showTransition ? (
                            <Stack.Screen name="LoginTransition">
                                {() => <LoginTransitionScreen onFinish={() => setShowTransition(false)} />}
                            </Stack.Screen>
                        ) : (
                            <Stack.Group>
                                {userInfo?.user_type === 'customer_portal' ? (
                                    <Stack.Screen name="ParentTabs" component={ParentTabs} />
                                ) : userInfo?.user_type === 'personnel' ? (
                                    <Stack.Screen name="DriverStack" component={DriverStackScreen} />
                                ) : (
                                    <Stack.Screen name="MainTabs" component={MainTabs} />
                                )}
                            </Stack.Group>
                        )}
                    </Stack.Group>
                )}
            </Stack.Navigator>
        </NavigationContainer>
    );
}

export default function App() {
    const [splashFinished, setSplashFinished] = React.useState(false);
    const [fontsLoaded, setFontsLoaded] = React.useState(false);

    React.useEffect(() => {
        async function loadFonts() {
            try {
                // Inter fontlarını yükle — Türkçe (Latin Extended) karakter seti tam destekli
                const InterFonts = require('@expo-google-fonts/inter');
                await Font.loadAsync({
                    Inter_400Regular: InterFonts.Inter_400Regular,
                    Inter_500Medium: InterFonts.Inter_500Medium,
                    Inter_600SemiBold: InterFonts.Inter_600SemiBold,
                    Inter_700Bold: InterFonts.Inter_700Bold,
                    Inter_800ExtraBold: InterFonts.Inter_800ExtraBold,
                    Inter_900Black: InterFonts.Inter_900Black,
                });
                console.log('✅ Inter fontları başarıyla yüklendi');
            } catch (e) {
                // Font yüklenemezse sistem fontlarıyla devam et — uygulama çökmez
                console.warn('⚠️ Font yükleme hatası, sistem fontlarıyla devam ediliyor:', e.message);
            } finally {
                setFontsLoaded(true);
            }
        }
        loadFonts();
    }, []);

    if (!fontsLoaded) {
        return (
            <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#040B16' }}>
                <ActivityIndicator size="large" color={theme.primary} />
            </View>
        );
    }

    if (Platform.OS === 'web') {
        return (
            <SafeAreaProvider>
                <View style={s.webWrap}>
                    <View style={s.phone}>
                        <View style={s.notch} />
                        {!splashFinished && <GlobalSplashScreen onFinish={() => setSplashFinished(true)} />}
                        {splashFinished && (
                            <AuthProvider><StatusBar style="light" /><AppNavigation /></AuthProvider>
                        )}
                    </View>
                    <View style={{ marginTop: 24, alignItems: 'center' }}>
                        <div style={{ color: theme.primary, fontFamily: "'Inter', system-ui, sans-serif", fontWeight: '800', fontSize: '16px' }}>📱 ServisPilot Mobile</div>
                        <div style={{ color: '#94a3b8', fontSize: '13px', marginTop: '6px', fontFamily: "'Inter', system-ui, sans-serif" }}>iPhone 14 Pro Simülasyonu</div>
                    </View>
                </View>
            </SafeAreaProvider>
        );
    }

    return (
        <SafeAreaProvider>
            {!splashFinished && <GlobalSplashScreen onFinish={() => setSplashFinished(true)} />}
            {splashFinished && (
                <AuthProvider><StatusBar style="light" /><AppNavigation /></AuthProvider>
            )}
        </SafeAreaProvider>
    );
}

const s = StyleSheet.create({
    webWrap: { flex: 1, backgroundColor: '#E2E8F0', alignItems: 'center', justifyContent: 'center' },
    phone: { width: 393, height: 852, backgroundColor: theme.bg, borderRadius: 55, overflow: 'hidden', borderWidth: 10, borderColor: '#0F172A', position: 'relative', boxShadow: '0 25px 60px -12px rgba(15, 23, 42, 0.4)' },
    notch: { position: 'absolute', top: 0, left: '50%', marginLeft: -60, width: 120, height: 28, backgroundColor: '#0F172A', borderBottomLeftRadius: 20, borderBottomRightRadius: 20, zIndex: 100 },
});
