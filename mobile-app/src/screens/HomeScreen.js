import React, { useCallback, useContext, useEffect, useRef, useState } from 'react';
import {
    AccessibilityInfo,
    ActivityIndicator,
    Animated,
    Easing,
    Image,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import dayjs from 'dayjs';
import 'dayjs/locale/tr';
import { AuthContext } from '../context/AuthContext';
import api from '../api/axios';

const coach = require('../../assets/fleet-dashboard-coach.webp');
const numbers = new Intl.NumberFormat('tr-TR');
const currency = new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
    maximumFractionDigits: 0,
    minimumFractionDigits: 0,
});

const quickActions = [
    { label: 'Yeni Şoför', icon: 'account-plus-outline', route: 'Personnel' },
    { label: 'Yeni Araç', icon: 'car-side', route: 'VehiclesTab' },
    { label: 'Seferler', icon: 'map-marker-path', route: 'Trips' },
    { label: 'Maaşlar', icon: 'cash-multiple', route: 'Payroll' },
];

function MaintenanceCard({ vehicle, onPress }) {
    return (
        <TouchableOpacity
            style={styles.maintenanceCard}
            activeOpacity={0.8}
            accessibilityRole="button"
            onPress={onPress}
        >
            <View style={styles.maintenanceHeader}>
                <View>
                    <Text style={styles.plate}>{vehicle.plate}</Text>
                    <Text style={styles.note}>{numbers.format(vehicle.current_km)} km</Text>
                </View>
                <Icon name="chevron-right" size={24} color="#416b9c" />
            </View>

            {vehicle.alerts.map((alert, index) => {
                const overdue = alert.remaining <= 0;
                const distance = numbers.format(Math.abs(alert.remaining));
                return (
                    <View key={index} style={styles.alertRow}>
                        <Text style={styles.alertType}>{alert.type}</Text>
                        <Text style={[styles.alertStatus, overdue && styles.overdue]}>
                            {distance} km {overdue ? 'gecikti' : 'kaldı'}
                        </Text>
                    </View>
                );
            })}
        </TouchableOpacity>
    );
}

export default function HomeScreen({ navigation }) {
    const { userInfo } = useContext(AuthContext);
    const [stats, setStats] = useState(null);
    const [refreshing, setRefreshing] = useState(false);
    const [failed, setFailed] = useState(false);
    const request = useRef(null);
    const arrival = useRef(new Animated.Value(1)).current;

    const loadOverview = useCallback(async (showRefresh = false) => {
        request.current?.abort();
        const controller = new AbortController();
        request.current = controller;
        setRefreshing(showRefresh);
        setFailed(false);

        try {
            const response = await api.get('/v1/dashboard', {
                params: { view: 'home' },
                signal: controller.signal,
            });
            if (request.current === controller) setStats(response.data.data);
        } catch {
            if (request.current === controller && !controller.signal.aborted) setFailed(true);
        } finally {
            if (request.current === controller) {
                request.current = null;
                setRefreshing(false);
            }
        }
    }, []);

    useFocusEffect(useCallback(() => {
        loadOverview();
        return () => {
            request.current?.abort();
            request.current = null;
        };
    }, [loadOverview]));

    useEffect(() => {
        let active = true;
        const animation = Animated.timing(arrival, {
            toValue: 1,
            duration: 650,
            easing: Easing.out(Easing.cubic),
            useNativeDriver: true,
            isInteraction: false,
        });
        AccessibilityInfo.isReduceMotionEnabled().then((reducedMotion) => {
            if (!active || reducedMotion) return;
            arrival.setValue(0);
            animation.start();
        }).catch(() => {});
        const setting = AccessibilityInfo.addEventListener('reduceMotionChanged', (reducedMotion) => {
            if (reducedMotion) {
                animation.stop();
                arrival.setValue(1);
            }
        });
        return () => {
            active = false;
            setting?.remove();
            animation.stop();
        };
    }, [arrival]);

    const firstName = userInfo?.name?.trim().split(/\s+/)[0] || 'Kullanıcı';
    const currentDate = dayjs().locale('tr').format('D MMMM YYYY, dddd');
    const hour = dayjs().hour();
    const greeting = hour < 12 ? 'Günaydın' : hour < 18 ? 'Tünaydın' : 'İyi Akşamlar';
    const operations = [
        { title: 'Bugünkü sefer', value: stats?.today_trips },
        { title: 'Devam eden bakım', value: stats?.active_maintenances },
        { title: 'Bekleyen bakım', value: stats?.waiting_maintenances },
    ];

    return (
        <SafeAreaView style={styles.container}>
            <ScrollView
                contentContainerStyle={styles.content}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => loadOverview(true)} />}
            >
                <LinearGradient colors={['#09172e', '#183858']} style={styles.hero}>
                    <Text style={styles.date}>{currentDate}</Text>
                    <Text accessibilityRole="header" style={styles.greeting}>
                        {greeting}, <Text style={styles.name}>{firstName}</Text>
                    </Text>
                    <Animated.View
                        accessible={false}
                        importantForAccessibility="no-hide-descendants"
                        style={[styles.scene, {
                            opacity: arrival,
                            transform: [
                                { perspective: 850 },
                                { translateY: arrival.interpolate({ inputRange: [0, 1], outputRange: [12, 0] }) },
                                { rotateY: arrival.interpolate({ inputRange: [0, 1], outputRange: ['-10deg', '0deg'] }) },
                            ],
                        }]}
                    >
                        <Image source={coach} resizeMode="contain" style={styles.coach} />
                    </Animated.View>
                    <View style={styles.operations}>
                        {operations.map(({ title, value }) => (
                            <View key={title} style={styles.operation}>
                                <Text style={styles.operationValue}>{value ?? '—'}</Text>
                                <Text style={styles.operationLabel}>{title}</Text>
                            </View>
                        ))}
                    </View>
                </LinearGradient>

                {failed && (
                    <TouchableOpacity style={styles.retry} accessibilityRole="button" onPress={() => loadOverview(true)}>
                        <Text style={styles.link}>Özet yüklenemedi. Yeniden dene.</Text>
                    </TouchableOpacity>
                )}
                {!stats && !failed && <ActivityIndicator color="#275ba7" />}
                {stats && (
                    <>
                        <View>
                            <Text accessibilityRole="header" style={styles.sectionTitle}>Filo Özeti</Text>
                            <View style={styles.grid}>
                                {[
                                    { label: 'Toplam araç', value: stats.vehicle_count, color: '#275ba7', tint: '#e5eeff' },
                                    { label: 'Şoför', value: stats.driver_count, color: '#633e9a', tint: '#f0e7fe' },
                                    { label: 'Müşteri', value: stats.customer_count, color: '#176a65', tint: '#e2f5f1' },
                                    { label: 'Aylık gelir', value: currency.format(stats.monthly_income), color: '#885521', tint: '#fff2dc' },
                                ].map(({ label, value, color, tint }) => (
                                    <LinearGradient key={label} colors={['#ffffff', tint]} style={styles.metric}>
                                        <Text style={[styles.metricLabel, { color }]}>{label}</Text>
                                        <Text style={styles.metricValue}>{value}</Text>
                                    </LinearGradient>
                                ))}
                            </View>
                        </View>

                        <View>
                            <Text accessibilityRole="header" style={styles.sectionTitle}>Hızlı İşlemler</Text>
                            <View style={styles.actions}>
                                {quickActions.map(({ label, icon, route }) => (
                                    <TouchableOpacity
                                        key={route}
                                        accessibilityRole="button"
                                        activeOpacity={0.75}
                                        style={styles.action}
                                        onPress={() => navigation.navigate(route)}
                                    >
                                        <Icon name={icon} size={21} color="#275ba7" />
                                        <Text style={styles.actionLabel}>{label}</Text>
                                    </TouchableOpacity>
                                ))}
                            </View>
                        </View>

                        <View>
                            <Text accessibilityRole="header" style={styles.sectionTitle}>Filo Bakım Sağlığı</Text>
                            <Text style={styles.maintenanceNote}>500 km içinde yaklaşan ve geciken bakımlar</Text>
                            {stats.maintenance_health?.length > 0 ? (
                                <View style={styles.alerts}>
                                    {stats.maintenance_health.map((vehicle) => (
                                        <MaintenanceCard
                                            key={vehicle.vehicle_id}
                                            vehicle={vehicle}
                                            onPress={() => navigation.navigate('VehiclesTab', {
                                                screen: 'VehicleMaintenances',
                                                params: { vehicleId: vehicle.vehicle_id, vehicle },
                                            })}
                                        />
                                    ))}
                                </View>
                            ) : <Text style={styles.empty}>Bakım uyarısı yok.</Text>}
                        </View>
                    </>
                )}
            </ScrollView>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#f2f6fc' },
    content: { width: '100%', maxWidth: 680, alignSelf: 'center', padding: 16, paddingBottom: 120, gap: 22 },
    hero: { borderRadius: 24, padding: 18, borderWidth: 1, borderColor: '#29466d', elevation: 4 },
    date: { color: '#9ae6e1', fontSize: 12, fontWeight: '700' },
    greeting: { color: '#fff', fontSize: 29, fontWeight: '800', letterSpacing: -0.8, marginTop: 6 },
    name: { color: '#c5d9f0' },
    scene: { width: '100%', maxWidth: 440, aspectRatio: 1.8, alignSelf: 'center' },
    coach: { width: '100%', height: '100%' },
    operations: { flexDirection: 'row', gap: 7 },
    operation: { flex: 1, padding: 8, borderRadius: 11, borderWidth: 1, borderColor: '#456387', backgroundColor: '#233f60' },
    operationValue: { fontSize: 22, fontWeight: '800', color: 'white', fontVariant: ['tabular-nums'] },
    operationLabel: { fontSize: 11, color: '#d3e3f7', marginTop: 3 },
    sectionTitle: { color: '#14243b', fontWeight: '800', fontSize: 19, marginBottom: 9 },
    grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    metric: { width: '48%', flexGrow: 1, padding: 13, borderRadius: 15, borderWidth: 1, borderBottomWidth: 3, borderColor: '#dbe6f4' },
    metricLabel: { fontWeight: '700', fontSize: 13 },
    metricValue: { color: '#14243b', fontWeight: '800', fontSize: 25, marginTop: 4, fontVariant: ['tabular-nums'] },
    actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    action: { flexGrow: 1, minWidth: '46%', minHeight: 46, flexDirection: 'row', gap: 8, alignItems: 'center', borderWidth: 1, padding: 10, borderRadius: 12, borderColor: '#dbe6f4', backgroundColor: 'white' },
    actionLabel: { color: '#243958', fontWeight: '700', fontSize: 13 },
    note: { color: '#526780', fontSize: 12 },
    link: { color: '#275ba7', fontWeight: '700' },
    retry: { padding: 13, borderRadius: 12, backgroundColor: 'white' },
    maintenanceNote: { color: '#526780', fontSize: 13, marginBottom: 10 },
    alerts: { gap: 8 },
    maintenanceCard: { padding: 12, borderWidth: 1, borderColor: '#dbe6f4', borderRadius: 14, backgroundColor: 'white' },
    maintenanceHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
    plate: { color: '#14243b', fontWeight: '800', fontSize: 15 },
    alertRow: { flexDirection: 'row', justifyContent: 'space-between', flexWrap: 'wrap', gap: 6, marginTop: 8, paddingTop: 8, borderTopWidth: 1, borderColor: '#e9eef6' },
    alertType: { color: '#526780', fontSize: 12 },
    alertStatus: { color: '#83501d', fontWeight: '700', fontSize: 12 },
    overdue: { color: '#a12b41' },
    empty: { backgroundColor: 'white', padding: 14, borderWidth: 1, borderRadius: 12, borderColor: '#dbe6f4', color: '#526780' },
});
