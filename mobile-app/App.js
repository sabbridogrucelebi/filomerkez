import React, { useContext } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { AuthProvider, AuthContext } from './src/context/AuthContext';
import { StatusBar } from 'expo-status-bar';
import { View, ActivityIndicator, Platform, StyleSheet, TouchableOpacity, Text, Animated, Easing } from 'react-native';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { BlurView } from 'expo-blur';
import { LinearGradient } from 'expo-linear-gradient';
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
import PilotChatScreen from './src/screens/PilotChatScreen';
import SupportScreen from './src/screens/SupportScreen';
import SecurityScreen from './src/screens/SecurityScreen';
import NotificationSettingsScreen from './src/screens/NotificationSettingsScreen';
import AccountInfoScreen from './src/screens/AccountInfoScreen';
import ActivityScreen from './src/screens/ActivityScreen';
import TrackingScreen from './src/screens/TrackingScreen';
import TrackingReportsScreen from './src/screens/TrackingReportsScreen';
import CompanyUsersScreen from './src/screens/CompanyUsersScreen';
import CompanyUserFormScreen from './src/screens/CompanyUserFormScreen';
import ReportsScreen from './src/screens/ReportsScreen';
import SettingsScreen from './src/screens/SettingsScreen';
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
import ParentAbsenceScreen from './src/screens/ParentAbsenceScreen';
import ParentSettingsScreen from './src/screens/ParentSettingsScreen';
import PilotCellRadiusScreen from './src/screens/PilotCellRadiusScreen';
import PilotCellRadiusMapScreen from './src/screens/PilotCellRadiusMapScreen';
import PilotCellTripScreen from './src/screens/PilotCellTripScreen';
const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

// Tema Renkleri (Uzay Derinliği Mavisi)
const theme = {
    primary: '#60A5FA', // Daha parlak mavi (Koyu tema için)
    bg: '#020617',
    inactive: '#64748B',
    shadow: '#3B82F6'
};

const AnimatedTabItem = ({ t, focused, onPress, unreadChatCount, isMessageTab, isParent }) => {
    const scaleAnim = React.useRef(new Animated.Value(focused ? 1.15 : 1)).current;
    
    React.useEffect(() => {
        Animated.spring(scaleAnim, {
            toValue: focused ? 1.15 : 1,
            friction: 5,
            tension: 80,
            useNativeDriver: true
        }).start();
    }, [focused]);

    const activeColor = isParent ? '#A78BFA' : theme.primary;

    return (
        <TouchableOpacity style={tabS.tab} onPress={onPress} activeOpacity={0.7}>
            <Animated.View style={{ alignItems: 'center', transform: [{ scale: scaleAnim }] }}>
                <View style={{ position: 'relative' }}>
                    <Icon name={focused ? t.iconActive : t.icon} size={26} color={focused ? activeColor : theme.inactive} style={focused && { textShadowColor: activeColor, textShadowRadius: 10 }} />
                    {isMessageTab && unreadChatCount > 0 && (
                        <View style={tabS.badge}>
                            <Text style={tabS.badgeText}>{unreadChatCount > 9 ? '9+' : unreadChatCount}</Text>
                        </View>
                    )}
                </View>
                <Text style={[tabS.label, focused && { color: activeColor, fontWeight: '800' }]}>{t.label}</Text>
                {focused && <View style={[tabS.activeDot, { backgroundColor: activeColor, shadowColor: activeColor }]} />}
            </Animated.View>
        </TouchableOpacity>
    );
};

const AnimatedCenterButton = ({ onPress, isParent }) => {
    const breatheAnim = React.useRef(new Animated.Value(1)).current;

    React.useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(breatheAnim, { toValue: 1.1, duration: 1500, easing: Easing.inOut(Easing.ease), useNativeDriver: true }),
                Animated.timing(breatheAnim, { toValue: 1, duration: 1500, easing: Easing.inOut(Easing.ease), useNativeDriver: true })
            ])
        ).start();
    }, []);

    const colors = isParent ? ['#8B5CF6', '#6D28D9'] : ['#3B82F6', '#1D4ED8'];
    const shadowCol = isParent ? '#8B5CF6' : '#3B82F6';
    const iconName = isParent ? 'bell' : 'plus';

    return (
        <TouchableOpacity style={tabS.centerBtn} onPress={onPress} activeOpacity={0.9}>
            <Animated.View style={[tabS.centerInnerWrap, { shadowColor: shadowCol, transform: [{ scale: breatheAnim }] }]}>
                <LinearGradient colors={colors} style={tabS.centerInner}>
                    <Icon name={iconName} size={32} color="#fff" style={{ textShadowColor: 'rgba(255,255,255,0.5)', textShadowRadius: 8 }} />
                </LinearGradient>
            </Animated.View>
        </TouchableOpacity>
    );
};

function CustomTabBar({ state, descriptors, navigation }) {
    const { unreadChatCount } = useContext(AuthContext);
    const insets = useSafeAreaInsets();

    const focusedOptions = descriptors[state.routes[state.index].key].options;
    if (focusedOptions?.tabBarStyle?.display === 'none') return null;

    const bottomPadding = Math.max(insets.bottom, 12);

    const tabs = [
        { icon: 'menu', iconActive: 'menu', label: 'Menü' },
        { icon: 'car-outline', iconActive: 'car', label: 'Araçlar' },
        { icon: 'plus', label: '' },
        { icon: 'chat-outline', iconActive: 'chat', label: 'Mesaj' },
        { icon: 'account-outline', iconActive: 'account', label: 'Profil' },
    ];
    return (
        <View style={[tabS.wrap, { paddingBottom: bottomPadding }]}>
            <View style={tabS.barContainer}>
                <BlurView intensity={50} tint="dark" style={tabS.bar}>
                    {state.routes.map((route, i) => {
                        const focused = state.index === i;
                        const isCenter = i === 2;
                        const t = tabs[i];
                        const onPress = () => {
                            const event = navigation.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });
                            if (!focused && !event.defaultPrevented) {
                                navigation.navigate(route.name);
                            } else if (focused && !event.defaultPrevented) {
                                if (route.name === 'MenuTab') navigation.navigate('MenuTab', { screen: 'Menu' });
                                if (route.name === 'VehiclesTab') navigation.navigate('VehiclesTab', { screen: 'Vehicles' });
                            }
                        };
                        if (isCenter) return <AnimatedCenterButton key={i} onPress={onPress} isParent={false} />;
                        return <AnimatedTabItem key={i} t={t} focused={focused} onPress={onPress} unreadChatCount={unreadChatCount} isMessageTab={i === 3} isParent={false} />;
                    })}
                </BlurView>
            </View>
        </View>
    );
}

function CustomParentTabBar({ state, descriptors, navigation }) {
    const insets = useSafeAreaInsets();
    const focusedOptions = descriptors[state.routes[state.index].key].options;
    if (focusedOptions?.tabBarStyle?.display === 'none') return null;

    const bottomPadding = Math.max(insets.bottom, 12);

    const tabs = [
        { icon: 'map-marker-outline', iconActive: 'map-marker', label: 'Takip Et' },
        { icon: 'calendar-remove-outline', iconActive: 'calendar-remove', label: 'Gelmeyecek' },
        { icon: 'credit-card-outline', iconActive: 'credit-card', label: 'Ödeme' },
        { icon: 'cog-outline', iconActive: 'cog', label: 'Ayarlar' },
    ];

    return (
        <View style={[tabS.wrap, { paddingBottom: bottomPadding }]}>
            <View style={tabS.barContainer}>
                <BlurView intensity={50} tint="dark" style={tabS.bar}>
                    {state.routes.map((route, i) => {
                        const focused = state.index === i;
                        const t = tabs[i];
                        const onPress = () => {
                            const event = navigation.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });
                            if (!focused && !event.defaultPrevented) navigation.navigate(route.name);
                        };
                        return <AnimatedTabItem key={i} t={t} focused={focused} onPress={onPress} isMessageTab={false} isParent={true} />;
                    })}
                </BlurView>
            </View>
        </View>
    );
}

const tabS = StyleSheet.create({
    wrap: { position: 'absolute', bottom: 0, left: 0, right: 0, paddingHorizontal: 20, backgroundColor: 'transparent' },
    barContainer: { borderRadius: 32, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.6, shadowRadius: 20, elevation: 15 },
    bar: { flexDirection: 'row', backgroundColor: 'rgba(2,6,23,0.6)', paddingVertical: 12, paddingHorizontal: 12, alignItems: 'center', justifyContent: 'space-around' },
    tab: { alignItems: 'center', justifyContent: 'center', paddingVertical: 4, flex: 1 },
    label: { fontSize: 10, fontWeight: '700', color: theme.inactive, marginTop: 4, letterSpacing: 0.5 },
    activeDot: { width: 4, height: 4, borderRadius: 2, marginTop: 4, shadowOffset: { width: 0, height: 0 }, shadowOpacity: 1, shadowRadius: 6, elevation: 4 },
    centerBtn: { alignItems: 'center', justifyContent: 'center', marginTop: -35 },
    centerInnerWrap: { shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.5, shadowRadius: 16, elevation: 10 },
    centerInner: { width: 60, height: 60, borderRadius: 30, alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: 'rgba(255,255,255,0.2)' },
    badge: { position: 'absolute', top: -6, right: -10, backgroundColor: '#EF4444', minWidth: 18, height: 18, borderRadius: 9, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4, borderWidth: 1.5, borderColor: '#020617', shadowColor: '#EF4444', shadowOffset: { width: 0, height: 0 }, shadowOpacity: 0.8, shadowRadius: 4, elevation: 3 },
    badgeText: { color: '#FFF', fontSize: 10, fontWeight: '900' }
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
            <MenuStack.Screen name="TrackingReports" component={TrackingReportsScreen} />
            <MenuStack.Screen name="NotificationSettings" component={NotificationSettingsScreen} />
            <MenuStack.Screen name="AccountInfo" component={AccountInfoScreen} />
            <MenuStack.Screen name="MaintenanceSettings" component={MaintenanceSettingsScreen} />
            <MenuStack.Screen name="CompanyUsers" component={CompanyUsersScreen} />
            <MenuStack.Screen name="CompanyUserForm" component={CompanyUserFormScreen} />
            <MenuStack.Screen name="Settings" component={SettingsScreen} />
            <MenuStack.Screen name="PilotCellDriver" component={PilotCellDriverScreen} />
            <MenuStack.Screen name="PilotCellPoints" component={PilotCellPointsScreen} />
            <MenuStack.Screen name="PilotCellRadius" component={PilotCellRadiusScreen} />
            <MenuStack.Screen name="PilotCellRadiusMap" component={PilotCellRadiusMapScreen} />
            <MenuStack.Screen name="PilotCellTrip" component={PilotCellTripScreen} />
        </MenuStack.Navigator>
    );
}

const ProfileStack = createNativeStackNavigator();
function ProfileStackScreen() {
    return (
        <ProfileStack.Navigator screenOptions={{ headerShown: false, gestureEnabled: true, fullScreenGestureEnabled: true, animation: 'slide_from_right', gestureDirection: 'horizontal', customAnimationOnSwipe: true }}>
            <ProfileStack.Screen name="ProfileMain" component={ProfileScreen} />
            <ProfileStack.Screen name="AccountInfo" component={AccountInfoScreen} />
            <ProfileStack.Screen name="NotificationSettings" component={NotificationSettingsScreen} />
            <ProfileStack.Screen name="Security" component={SecurityScreen} />
            <ProfileStack.Screen name="Support" component={SupportScreen} />
            <ProfileStack.Screen name="Settings" component={SettingsScreen} />
        </ProfileStack.Navigator>
    );
}

function MainTabs() {
    return (
        <Tab.Navigator tabBar={p => <CustomTabBar {...p} />} screenOptions={{ headerShown: false }}>
            <Tab.Screen name="MenuTab" component={MenuStackScreen} />
            <Tab.Screen name="VehiclesTab" component={VehiclesStackScreen} />
            <Tab.Screen name="HomeTab" component={HomeScreen} />
            <Tab.Screen name="ChatTab" component={PilotChatScreen} />
            <Tab.Screen name="Profile" component={ProfileStackScreen} />
        </Tab.Navigator>
    );
}

function ParentTabs() {
    return (
        <Tab.Navigator tabBar={p => <CustomParentTabBar {...p} />} screenOptions={{ headerShown: false }} initialRouteName="ParentTrack">
            <Tab.Screen name="ParentTrack" component={ParentHomeScreen} />
            <Tab.Screen name="ParentAbsence" component={ParentAbsenceScreen} />
            <Tab.Screen name="ParentPayment" component={ParentPaymentScreen} />
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
    const [splashFinished, setSplashFinished] = React.useState(true); // Intro videosu iptal edildi, direkt uygulamaya girecek
    const [fontsLoaded, setFontsLoaded] = React.useState(false);

    React.useEffect(() => {
        async function loadFonts() {
            try {
                // Inter fontlarını yükle — Türkçe (Latin Extended) karakter seti tam destekli.
                // Paketin tamamı yerine tek tek ağırlıklar alınıyor: aksi halde kullanılmayan
                // 12 varyant (italikler dahil) da uygulama boyutuna ekleniyor.
                await Font.loadAsync({
                    Inter_400Regular: require('@expo-google-fonts/inter/400Regular').Inter_400Regular,
                    Inter_500Medium: require('@expo-google-fonts/inter/500Medium').Inter_500Medium,
                    Inter_600SemiBold: require('@expo-google-fonts/inter/600SemiBold').Inter_600SemiBold,
                    Inter_700Bold: require('@expo-google-fonts/inter/700Bold').Inter_700Bold,
                    Inter_800ExtraBold: require('@expo-google-fonts/inter/800ExtraBold').Inter_800ExtraBold,
                    Inter_900Black: require('@expo-google-fonts/inter/900Black').Inter_900Black,
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
