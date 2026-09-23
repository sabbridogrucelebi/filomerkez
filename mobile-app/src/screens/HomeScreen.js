import React, { useContext, useState, useCallback, useRef, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, RefreshControl, Animated, Dimensions, Image, Easing } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { AuthContext } from '../context/AuthContext';
import api from '../api/axios';
import { emoji } from '../emoji';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import dayjs from 'dayjs';
import 'dayjs/locale/tr';

dayjs.locale('tr');
const { width } = Dimensions.get('window');

const toTitleCase = (str) => {
    if (!str) return '';
    return str.toLocaleLowerCase('tr-TR').split(' ').map(word => word.charAt(0).toLocaleUpperCase('tr-TR') + word.slice(1)).join(' ');
};

export default function HomeScreen({ navigation }) {
    const { userInfo } = useContext(AuthContext);
    const [stats, setStats] = useState(null);
    const [refreshing, setRefreshing] = useState(false);
    
    // Animasyon Değerleri
    const scrollY = useRef(new Animated.Value(0)).current;
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const slideAnim = useRef(new Animated.Value(50)).current;
    
    // 3D Flip & Float Değerleri
    const flipAnims = useRef([new Animated.Value(0), new Animated.Value(0), new Animated.Value(0)]).current;
    const floatAnim = useRef(new Animated.Value(0)).current;

    const fetchDashboard = async () => {
        try {
            const res = await api.get('/v1/dashboard');
            setStats(res.data.data);
            
            // Giriş Animasyonları
            Animated.parallel([
                Animated.timing(fadeAnim, { toValue: 1, duration: 800, useNativeDriver: true }),
                Animated.timing(slideAnim, { toValue: 0, duration: 800, easing: Easing.out(Easing.exp), useNativeDriver: true }),
                // Kartların sırayla takla atarak gelmesi
                Animated.stagger(150, flipAnims.map(anim => 
                    Animated.spring(anim, {
                        toValue: 1,
                        friction: 6,
                        tension: 40,
                        useNativeDriver: true
                    })
                ))
            ]).start();
            
        } catch (e) {
            console.log('Dashboard fetch error:', e);
        }
    };

    useFocusEffect(
        useCallback(() => {
            fetchDashboard();
        }, [])
    );

    // Sürekli Float (Levitation) Animasyonu
    useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(floatAnim, {
                    toValue: 1,
                    duration: 2000,
                    easing: Easing.inOut(Easing.sin),
                    useNativeDriver: true
                }),
                Animated.timing(floatAnim, {
                    toValue: 0,
                    duration: 2000,
                    easing: Easing.inOut(Easing.sin),
                    useNativeDriver: true
                })
            ])
        ).start();
    }, []);

    const onRefresh = useCallback(async () => {
        setRefreshing(true);
        // Animasyonları sıfırla
        fadeAnim.setValue(0);
        slideAnim.setValue(50);
        flipAnims.forEach(a => a.setValue(0));
        
        await fetchDashboard();
        setRefreshing(false);
    }, []);

    const firstName = toTitleCase(userInfo?.name?.split(' ')[0] || 'Kullanıcı');
    const currentDate = dayjs().format('D MMMM YYYY, dddd');

    const get3DIcon = (iconName) => {
        switch (iconName) {
            case 'bus-multiple': return emoji('Travel and places/Oncoming Bus');
            case 'account-tie': return emoji('People/Construction Worker');
            case 'domain': return emoji('Travel and places/Office Building');
            default: return emoji('Travel and places/Automobile');
        }
    };

    // Parallax Background Style
    const bgTransform = {
        transform: [{
            translateY: scrollY.interpolate({
                inputRange: [-100, 0, 500],
                outputRange: [-50, 0, 150],
                extrapolate: 'clamp'
            })
        }]
    };

    const floatTransform = {
        transform: [{
            translateY: floatAnim.interpolate({
                inputRange: [0, 1],
                outputRange: [0, -8]
            })
        }]
    };

    const KpiCard = ({ index, icon, title, value, gradientColors, darkColor, isHalf }) => {
        // 3D Flip Efekti X ve Y Ekseninde
        const flipRotate = flipAnims[index].interpolate({
            inputRange: [0, 1],
            outputRange: ['-90deg', '0deg']
        });
        
        const scaleEffect = flipAnims[index].interpolate({
            inputRange: [0, 0.5, 1],
            outputRange: [0.5, 1.1, 1]
        });

        return (
            <Animated.View style={[
                styles.kpi3DBase, 
                { shadowColor: darkColor },
                { 
                    opacity: flipAnims[index],
                    transform: [
                        { perspective: 1000 },
                        { rotateX: flipRotate },
                        { scale: scaleEffect }
                    ]
                }
            ]}>
                <BlurView intensity={40} tint="dark" style={{ borderRadius: 28, overflow: 'hidden' }}>
                    <LinearGradient colors={[gradientColors[0] + '95', gradientColors[1] + '70']} style={[styles.kpiCard, isHalf && { padding: 18, flexDirection: 'column', alignItems: 'flex-start' }]} start={{x: 0, y: 0}} end={{x: 1, y: 1}}>
                        <Animated.View style={[
                            styles.kpiIconBox, 
                            { shadowColor: darkColor, backgroundColor: 'rgba(255,255,255,0.15)' }, 
                            isHalf && { width: 46, height: 46, borderRadius: 14, marginBottom: 12, marginRight: 0 },
                            floatTransform // Havada süzülme animasyonu
                        ]}>
                            <Image source={get3DIcon(icon)} style={{ width: isHalf ? 36 : 48, height: isHalf ? 36 : 48 }} resizeMode="contain" />
                        </Animated.View>
                        <View style={[styles.kpiInfo, isHalf && { width: '100%' }]}>
                            <Text style={[styles.kpiValue, isHalf && { fontSize: 28 }]} adjustsFontSizeToFit numberOfLines={1}>{value !== undefined ? value : '-'}</Text>
                            <Text style={[styles.kpiTitle, isHalf && { fontSize: 13 }]} adjustsFontSizeToFit numberOfLines={1}>{title}</Text>
                        </View>
                    </LinearGradient>
                </BlurView>
            </Animated.View>
        );
    };

    const QuickActions = () => {
        const actions = [
            { id: 1, title: 'Yeni Şoför', icon: 'account-plus', color: '#8B5CF6', route: 'Personnel' },
            { id: 2, title: 'Yeni Araç', icon: 'car-side', color: '#3B82F6', route: 'VehiclesTab' },
            { id: 3, title: 'Seferler', icon: 'map-marker-path', color: '#10B981', route: 'Trips' },
            { id: 4, title: 'Maaşlar', icon: 'cash-multiple', color: '#F59E0B', route: 'Payroll' },
        ];

        return (
            <View style={{ marginBottom: 30 }}>
                <Text style={styles.sectionTitle}>Hızlı İşlemler</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 16 }}>
                    {actions.map((action, idx) => {
                        // Basit kaydırma ile gelen gecikmeli giriş animasyonu
                        const actionSlide = slideAnim.interpolate({
                            inputRange: [0, 50],
                            outputRange: [0, 20 + (idx * 15)]
                        });

                        return (
                            <Animated.View key={action.id} style={{ transform: [{ translateX: actionSlide }] }}>
                                <TouchableOpacity 
                                    style={styles.quickActionCard}
                                    activeOpacity={0.7}
                                    onPress={() => navigation.navigate(action.route)}
                                >
                                    <BlurView intensity={30} tint="light" style={styles.quickActionBlur}>
                                        <View style={[styles.quickActionIconBox, { backgroundColor: action.color + '25', borderWidth: 1, borderColor: action.color + '40' }]}>
                                            <Icon name={action.icon} size={26} color={action.color} />
                                        </View>
                                        <Text style={styles.quickActionTitle}>{action.title}</Text>
                                    </BlurView>
                                </TouchableOpacity>
                            </Animated.View>
                        );
                    })}
                </ScrollView>
            </View>
        );
    };

    const renderMaintenanceHealth = () => {
        if (!stats?.maintenance_health || stats.maintenance_health.length === 0) {
            return (
                <View style={styles.emptyState}>
                    <Icon name="check-circle" size={48} color="#10B981" style={{ marginBottom: 16 }} />
                    <Text style={styles.emptyTitle}>Harika Haber!</Text>
                    <Text style={styles.emptyDesc}>Acil bakım veya yağlama gerektiren aracınız bulunmuyor. Filonuz tamamen güvende.</Text>
                </View>
            );
        }

        return stats.maintenance_health.map((mh, i) => (
            <View key={i} style={styles.mhCard}>
                <BlurView intensity={50} tint="dark" style={StyleSheet.absoluteFillObject} />
                <LinearGradient colors={['rgba(255,255,255,0.08)', 'transparent']} style={StyleSheet.absoluteFillObject} />
                <View style={styles.mhHeader}>
                    <View style={styles.mhHeaderLeft}>
                        <View style={styles.mhPlateBox}>
                            <Icon name="car-sports" size={18} color="#60A5FA" />
                            <Text style={styles.mhPlateText}>{mh.plate}</Text>
                        </View>
                        <View style={styles.mhKmBadge}>
                            <Icon name="speedometer" size={14} color="#94A3B8" />
                            <Text style={styles.mhCurrentKm}>{new Intl.NumberFormat('tr-TR').format(mh.current_km)} KM</Text>
                        </View>
                    </View>
                    <TouchableOpacity style={styles.mhActionBtn} onPress={() => navigation.navigate('VehiclesTab', { screen: 'VehicleMaintenances', params: { vehicleId: mh.vehicle_id, vehicle: mh } })}>
                        <Text style={styles.mhActionText}>Göz At</Text>
                        <Icon name="arrow-right-circle" size={18} color="#fff" />
                    </TouchableOpacity>
                </View>
                <View style={styles.mhAlerts}>
                    {mh.alerts.map((alert, idx) => {
                        const isOverdue = alert.remaining <= 0;
                        const alertColor = isOverdue ? '#F43F5E' : '#F59E0B';
                        const alertBg = isOverdue ? 'rgba(244, 63, 94, 0.15)' : 'rgba(245, 158, 11, 0.15)';
                        const alertIcon = isOverdue ? 'alert-decagram' : 'alert-circle-outline';
                        const statusText = isOverdue ? `Gecikti (${Math.abs(alert.remaining)} KM)` : `Yaklaştı (${alert.remaining} KM Kaldı)`;

                        return (
                            <View key={idx} style={[styles.alertRow, { backgroundColor: alertBg, borderColor: alertColor + '50' }]}>
                                <View style={[styles.alertIconWrap, { backgroundColor: alertColor + '20' }]}>
                                    <Icon name={alertIcon} size={22} color={alertColor} />
                                </View>
                                <View style={styles.alertContent}>
                                    <Text style={[styles.alertType, { color: alertColor }]}>{alert.type}</Text>
                                    <Text style={[styles.alertStatus, { color: alertColor }]}>{statusText}</Text>
                                </View>
                                {isOverdue && <View style={[styles.pulseDot, { backgroundColor: alertColor }]} />}
                            </View>
                        );
                    })}
                </View>
            </View>
        ));
    };

    return (
        <View style={styles.container}>
            {/* Parallax arka plan */}
            <Animated.View style={[StyleSheet.absoluteFill, bgTransform]}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <View style={styles.bgBlob1} />
                <View style={styles.bgBlob2} />
            </Animated.View>
            
            <SafeAreaView style={{ flex: 1 }}>
                <Animated.ScrollView 
                    contentContainerStyle={styles.scrollContent}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#8B5CF6" />}
                    showsVerticalScrollIndicator={false}
                    onScroll={Animated.event(
                        [{ nativeEvent: { contentOffset: { y: scrollY } } }],
                        { useNativeDriver: true }
                    )}
                    scrollEventThrottle={16}
                >
                    <View style={styles.header}>
                        <View>
                            <Text style={styles.dateText}>{currentDate}</Text>
                            <Text style={styles.welcomeText}>Hoş Geldin, <Text style={styles.userName}>{firstName}</Text></Text>
                        </View>
                        <View style={styles.profileAvatar}>
                            <Text style={styles.profileInitials}>{firstName.charAt(0)}</Text>
                        </View>
                    </View>

                    {stats ? (
                        <Animated.View style={{ opacity: fadeAnim, transform: [{ translateY: slideAnim }] }}>
                            
                            {/* Hızlı İşlemler */}
                            <QuickActions />

                            {/* KPI Grid */}
                            <Text style={styles.sectionTitle}>Filo Özeti</Text>
                            <View style={styles.kpiContainer}>
                                <KpiCard index={0} icon="bus-multiple" title="Toplam Araç" value={stats.vehicle_count} gradientColors={['#3B82F6', '#1D4ED8']} darkColor="#1E3A8A" />
                                <View style={styles.kpiRow}>
                                    <View style={{ flex: 1, marginRight: 6 }}>
                                        <KpiCard index={1} icon="account-tie" title="Şoförler" value={stats.driver_count} gradientColors={['#8B5CF6', '#6D28D9']} darkColor="#4C1D95" isHalf />
                                    </View>
                                    <View style={{ flex: 1, marginLeft: 6 }}>
                                        <KpiCard index={2} icon="domain" title="Müşteriler" value={stats.customer_count} gradientColors={['#10B981', '#047857']} darkColor="#064E3B" isHalf />
                                    </View>
                                </View>
                            </View>

                            {/* Bakım Sağlığı (Maintenance Health) */}
                            <View style={styles.sectionHeaderWrap}>
                                <Icon name="heart-pulse" size={24} color="#F43F5E" style={{ marginRight: 8 }} />
                                <Text style={[styles.sectionTitle, { marginBottom: 0 }]}>Bakım Sağlığı</Text>
                            </View>
                            <Text style={styles.sectionSubtitle}>500 KM altına düşen veya süresi geçen araçlar.</Text>
                            
                            <View style={styles.mhContainer}>
                                {renderMaintenanceHealth()}
                            </View>

                            <View style={{ height: 120 }} />
                        </Animated.View>
                    ) : (
                        <View style={{ marginTop: 50, alignItems: 'center' }}>
                            <Icon name="loading" size={32} color="#8B5CF6" />
                        </View>
                    )}
                </Animated.ScrollView>
            </SafeAreaView>
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    
    // Parallax efekti için arka plan blur efektleri
    bgBlob1: { position: 'absolute', top: -50, right: -50, width: 250, height: 250, borderRadius: 125, backgroundColor: 'rgba(139, 92, 246, 0.15)', transform: [{ scale: 1.5 }] },
    bgBlob2: { position: 'absolute', top: 250, left: -80, width: 200, height: 200, borderRadius: 100, backgroundColor: 'rgba(59, 130, 246, 0.12)', transform: [{ scale: 2 }] },

    scrollContent: { paddingHorizontal: 20, paddingTop: 10 },
    
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 30 },
    dateText: { fontSize: 13, color: '#94A3B8', fontWeight: '600', letterSpacing: 0.5, marginBottom: 4 },
    welcomeText: { fontSize: 28, color: '#fff', fontWeight: '500' },
    userName: { fontWeight: '900', color: '#8B5CF6' },
    profileAvatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: 'rgba(139, 92, 246, 0.2)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.4)' },
    profileInitials: { color: '#C4B5FD', fontSize: 20, fontWeight: '800' },

    sectionTitle: { fontSize: 18, fontWeight: '800', color: '#F8FAFC', marginBottom: 16, letterSpacing: 0.5 },
    sectionHeaderWrap: { flexDirection: 'row', alignItems: 'center', marginTop: 32, marginBottom: 4 },
    sectionSubtitle: { fontSize: 13, color: '#94A3B8', marginBottom: 16, fontWeight: '500' },

    kpiContainer: { gap: 12 },
    kpiRow: { flexDirection: 'row' },
    kpi3DBase: { borderRadius: 28, shadowColor: '#000', shadowOffset: { width: 0, height: 12 }, shadowOpacity: 0.9, shadowRadius: 18, elevation: 15, marginBottom: 8 },
    kpiCard: { padding: 22, flexDirection: 'row', alignItems: 'center', borderRadius: 28, borderWidth: 1, borderColor: 'rgba(255,255,255,0.15)' },
    kpiIconBox: { width: 60, height: 60, borderRadius: 20, alignItems: 'center', justifyContent: 'center', marginRight: 16, shadowOffset: { width: 0, height: 6 }, shadowOpacity: 1, shadowRadius: 8, elevation: 6 },
    kpiInfo: { flex: 1 },
    kpiValue: { fontSize: 36, fontWeight: '900', color: '#FFF', letterSpacing: -1, marginBottom: 2, textShadowColor: 'rgba(0,0,0,0.6)', textShadowOffset: { width: 0, height: 2 }, textShadowRadius: 10 },
    kpiTitle: { fontSize: 14, fontWeight: '700', color: 'rgba(255,255,255,0.85)', letterSpacing: 0.5 },

    quickActionCard: { width: 105, height: 115, borderRadius: 24, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.15)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.4, shadowRadius: 8, elevation: 5 },
    quickActionBlur: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 10, backgroundColor: 'rgba(255,255,255,0.05)' },
    quickActionIconBox: { width: 54, height: 54, borderRadius: 20, alignItems: 'center', justifyContent: 'center', marginBottom: 10 },
    quickActionTitle: { color: '#F8FAFC', fontSize: 13, fontWeight: '700' },

    mhContainer: { gap: 16 },
    mhCard: { backgroundColor: 'transparent', borderRadius: 24, padding: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.12)', shadowColor: '#000', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.7, shadowRadius: 15, elevation: 10, overflow: 'hidden' },
    mhHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 },
    mhHeaderLeft: { flex: 1 },
    mhPlateBox: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(59, 130, 246, 0.15)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 10, alignSelf: 'flex-start', marginBottom: 8, borderWidth: 1, borderColor: 'rgba(59, 130, 246, 0.4)' },
    mhPlateText: { color: '#60A5FA', fontSize: 16, fontWeight: '900', marginLeft: 8, letterSpacing: 0.5 },
    mhKmBadge: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.08)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, alignSelf: 'flex-start' },
    mhCurrentKm: { color: '#CBD5E1', fontSize: 12, fontWeight: '700', marginLeft: 4 },
    
    mhActionBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(59, 130, 246, 0.2)', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 14, borderWidth: 1, borderColor: 'rgba(59, 130, 246, 0.5)' },
    mhActionText: { color: '#60A5FA', fontSize: 13, fontWeight: '800', marginRight: 6 },

    mhAlerts: { gap: 10 },
    alertRow: { flexDirection: 'row', alignItems: 'center', padding: 14, borderRadius: 16, borderWidth: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 6, elevation: 3 },
    alertIconWrap: { width: 40, height: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
    alertContent: { marginLeft: 14, flex: 1 },
    alertType: { fontSize: 15, fontWeight: '900', marginBottom: 2 },
    alertStatus: { fontSize: 13, fontWeight: '700', opacity: 1 },
    pulseDot: { width: 8, height: 8, borderRadius: 4, shadowColor: '#F43F5E', shadowOffset: { width: 0, height: 0 }, shadowOpacity: 1, shadowRadius: 8, elevation: 5 },

    emptyState: { backgroundColor: 'rgba(16, 185, 129, 0.08)', borderRadius: 28, padding: 32, alignItems: 'center', borderWidth: 1, borderColor: 'rgba(16, 185, 129, 0.2)', shadowColor: '#10B981', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.2, shadowRadius: 15, elevation: 6 },
    emptyTitle: { fontSize: 20, fontWeight: '900', color: '#10B981', marginBottom: 8 },
    emptyDesc: { fontSize: 14, color: '#94A3B8', textAlign: 'center', lineHeight: 22, fontWeight: '500' }
});
