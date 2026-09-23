import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, ActivityIndicator, Animated, Alert, Image, Dimensions, Platform, Easing, StatusBar } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import api from '../api/axios';
import { emoji } from '../emoji';

const { width: W } = Dimensions.get('window');

// Yer tutucu araç görselleri uygulama içinden geliyor: internet yokken veya
// üçüncü taraf barındırma erişilemezken de liste bozulmuyor.
const getVehicleImage = (type) => {
    const t = (type || '').toLowerCase();
    if (t.includes('minib')) return require('../../assets/arac_profilleri/servis_pilot_minibus_profil.png');
    if (t.includes('midib')) return require('../../assets/arac_profilleri/servis_pilot_midibus_profil.png');
    if (t.includes('otob')) return require('../../assets/arac_profilleri/servis_pilot_otobus_profil.png');
    if (t.includes('panelvan')) return require('../../assets/arac_profilleri/servis_pilot_panelvan_profil.png');
    if (t.includes('kamyonet')) return require('../../assets/arac_profilleri/servis_pilot_kamyonet_profil.png');
    if (t.includes('binek') || t.includes('sedan') || t.includes('taksi')) return require('../../assets/arac_profilleri/servis_pilot_taksi_profil.png');

    return require('../../assets/arac_profilleri/servis_pilot_panelvan_profil.png');
};

const fmtKm = (v) => new Intl.NumberFormat('tr-TR').format(v || 0);
const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 0 }).format(v || 0);
import { AuthContext } from '../context/AuthContext';
export default function VehicleDetailScreen({ route, navigation }) {
    const { hasPermission } = React.useContext(AuthContext);
    const { vehicle: init } = route.params || {};
    const [v, setV] = useState(init);
    const [stats, setStats] = useState({ revenue: 0, fuel: 0, salary: 0, net: 0 });
    const [mh, setMh] = useState({});
    const [loading, setLoading] = useState(true);
    
    // Animasyon Değerleri
    const scrollY = useRef(new Animated.Value(0)).current;
    const fadeAnim = useRef(new Animated.Value(0)).current;
    const slideAnim = useRef(new Animated.Value(50)).current;
    const blinkAnim = useRef(new Animated.Value(1)).current;
    const floatAnim = useRef(new Animated.Value(0)).current;
    const imgScaleAnim = useRef(new Animated.Value(0.5)).current;
    const menuFlipAnims = useRef([...Array(10)].map(() => new Animated.Value(0))).current;

    useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(blinkAnim, { toValue: 0.2, duration: 800, useNativeDriver: true }),
                Animated.timing(blinkAnim, { toValue: 1, duration: 800, useNativeDriver: true }),
            ])
        ).start();

        Animated.loop(
            Animated.sequence([
                Animated.timing(floatAnim, { toValue: 1, duration: 2500, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(floatAnim, { toValue: 0, duration: 2500, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        ).start();

        // Animasyonları sayfa açılır açılmaz anında başlat (Sunucuyu bekleme)
        Animated.parallel([
            Animated.timing(fadeAnim, { toValue: 1, duration: 400, useNativeDriver: true }),
            Animated.spring(slideAnim, { toValue: 0, friction: 6, tension: 40, useNativeDriver: true }),
            Animated.spring(imgScaleAnim, { toValue: 1, friction: 5, tension: 50, useNativeDriver: true }),
            Animated.stagger(80, menuFlipAnims.map(anim => 
                Animated.spring(anim, {
                    toValue: 1,
                    friction: 6,
                    tension: 40,
                    useNativeDriver: true
                })
            ))
        ]).start();

        (async () => {
            if (!init?.id) return;
            try {
                const r = await api.get(`/v1/vehicles/${init.id}`);
                setV(r.data.data.vehicle);
                setStats(r.data.data.stats || {});
                setMh(r.data.data.maintenance_health || {});
            } catch (e) {
                // Sessizce hatayı yakala, sayfayı çökertme
                console.log('Araç detay yüklenemedi:', e);
            } finally {
                setLoading(false);
            }
        })();
    }, []);

    const daysUntil = (d) => {
        if (!d) return { text: 'Tanımsız', days: null, color: '#94A3B8' };
        const diff = Math.ceil((new Date(d) - new Date()) / 86400000);
        if (diff < 0) return { text: `${Math.abs(diff)} gün geçti`, days: diff, color: '#EF4444' };
        if (diff <= 30) return { text: `${diff} gün kaldı`, days: diff, color: '#F59E0B' };
        return { text: `${diff} gün kaldı`, days: diff, color: '#10B981' };
    };

    const formatDate = (d) => d ? new Date(d).toLocaleDateString('tr-TR') : '-';

    if (!v) return <View style={st.loaderWrap}><Text style={{ color: '#94A3B8', fontSize: 16 }}>Araç bulunamadı</Text></View>;

    const inspection = daysUntil(v.inspection_date);
    const insurance = daysUntil(v.insurance_end_date);
    const imm = daysUntil(v.imm_end_date);
    const kasko = daysUntil(v.kasko_end_date);
    const exhaust = daysUntil(v.exhaust_date);
    const profit = (stats.revenue || 0) - (stats.fuel || 0) - (stats.salary || 0);

    const quickStats = [
        { img: emoji('Travel and places/Police Car Light'), label: 'Kilometre', value: `${fmtKm(v.current_km)} km`, color: '#3B82F6' },
        { img: emoji('Travel and places/Fuel Pump'), label: 'Yakıt', value: v.fuel_type || '-', color: '#F59E0B' },
        { img: emoji('Objects/Tear-Off Calendar'), label: 'Model Yılı', value: v.model_year || '-', color: '#8B5CF6' },
        { img: emoji('Activities/Artist Palette'), label: 'Renk', value: v.color || '-', color: '#EC4899' },
        { img: emoji('Travel and places/Seat'), label: 'Koltuk', value: v.seat_count || '-', color: '#10B981' },
    ];

    const menuItems = [
        { icon: 'file-document-outline', label: 'Belgeler', color: '#3B82F6', gradient: ['#3B82F6', '#2563EB'], screen: 'VehicleDocuments', perm: 'documents.view' },
        { icon: 'gas-station-outline', label: 'Yakıt', color: '#F59E0B', gradient: ['#F59E0B', '#D97706'], screen: 'VehicleFuels', perm: 'fuels.view' },
        { icon: 'wrench-outline', label: 'Bakım', color: '#10B981', gradient: ['#10B981', '#059669'], screen: 'VehicleMaintenances', perm: 'maintenances.view' },
        { icon: 'alert-octagon-outline', label: 'Cezalar', color: '#EF4444', gradient: ['#EF4444', '#DC2626'], screen: 'VehiclePenalties', perm: 'penalties.view' },
        { icon: 'image-multiple-outline', label: 'Galeri', color: '#8B5CF6', gradient: ['#8B5CF6', '#7C3AED'], screen: 'VehicleGallery', perm: 'vehicles.view' },
        { icon: 'chart-bar', label: 'Raporlar', color: '#06B6D4', gradient: ['#06B6D4', '#0891B2'], screen: 'VehicleReports', perm: 'reports.view' },
    ].filter(m => hasPermission(m.perm));

    // Parallax background transform
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
                outputRange: [0, -10]
            })
        }]
    };

    return (
        <View style={st.container}>
            {/* Parallax Arka Plan */}
            <Animated.View style={[StyleSheet.absoluteFill, bgTransform]}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <View style={st.bgBlob1} />
                <View style={st.bgBlob2} />
            </Animated.View>
            <StatusBar barStyle="light-content" />

            <Animated.ScrollView 
                bounces={true} 
                showsVerticalScrollIndicator={false} 
                contentContainerStyle={{ paddingBottom: 80 }}
                onScroll={Animated.event(
                    [{ nativeEvent: { contentOffset: { y: scrollY } } }],
                    { useNativeDriver: true }
                )}
                scrollEventThrottle={16}
            >
                {/* ── HERO HEADER ── */}
                <View style={st.hero}>
                    <SafeAreaView edges={['top']} style={st.heroContent}>
                        <View style={st.heroTop}>
                            <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                                <BlurView intensity={30} tint="dark" style={StyleSheet.absoluteFillObject} />
                                <Icon name="chevron-left" size={28} color="#fff" />
                            </TouchableOpacity>
                            <View style={st.statusBadge}>
                                <BlurView intensity={30} tint="dark" style={StyleSheet.absoluteFillObject} />
                                <View style={[st.statusDot, { backgroundColor: v.is_active ? '#10B981' : '#EF4444', shadowColor: v.is_active ? '#10B981' : '#EF4444' }]} />
                                <Text style={st.statusText}>{v.is_active ? 'Aktif' : 'Pasif'}</Text>
                            </View>
                        </View>
                        <View style={st.heroCenter}>
                            <Animated.View style={[st.imageContainer, { opacity: fadeAnim, transform: [{ scale: imgScaleAnim }] }, floatTransform]}>
                                {/* Aracın arkasında parlama (Glow) efekti */}
                                <View style={st.imageGlow} />
                                <View style={{ zIndex: 10, elevation: 15 }}>
                                    <Image 
                                        source={getVehicleImage(v.vehicle_type)} 
                                        style={st.heroImage} 
                                        resizeMode="contain" 
                                    />
                                </View>
                            </Animated.View>
                        </View>
                        <Animated.View style={[st.heroBottom, { opacity: fadeAnim, transform: [{ translateY: slideAnim }] }]}>
                            <Text style={st.plateText}>{v.plate}</Text>
                            <Text style={st.brandText}>{[v.brand, v.model].filter(Boolean).join(' ') || v.vehicle_type || 'Araç'}</Text>
                            {v.vehicle_type && <View style={st.typeBadge}><Text style={st.typeText}>{v.vehicle_type}</Text></View>}
                        </Animated.View>
                    </SafeAreaView>
                </View>

                <Animated.View style={{ opacity: fadeAnim, transform: [{ translateY: slideAnim }] }}>
                    {/* ── QUICK STATS ── */}
                    <ScrollView horizontal showsHorizontalScrollIndicator={false} style={st.quickRow} contentContainerStyle={{ paddingHorizontal: 16, gap: 12 }}>
                        {quickStats.map((s, i) => (
                            <View key={i} style={st.quickCard}>
                                <BlurView intensity={20} tint="dark" style={StyleSheet.absoluteFillObject} />
                                <Animated.View style={[st.quickIcon, floatTransform]}>
                                    <Image source={s.img} style={{ width: 34, height: 34 }} resizeMode="contain" />
                                </Animated.View>
                                <Text style={st.quickLabel}>{s.label}</Text>
                                <Text style={st.quickValue}>{s.value}</Text>
                            </View>
                        ))}
                    </ScrollView>

                    {/* ── DATE STATUS CARDS ── */}
                    <View style={st.section}>
                        <Text style={st.sectionTitle}>Tarih Durumları</Text>
                        <View style={st.grid3Row}>
                            {[
                                { label: 'Egzoz Emisyon', date: v.exhaust_date, info: exhaust, icon: 'weather-windy' },
                                { label: 'İMM Poliçesi', date: v.imm_end_date, info: imm, icon: 'file-document-outline' },
                                { label: 'Kasko Poliçesi', date: v.kasko_end_date, info: kasko, icon: 'car-wrench' },
                            ].map((item, i) => (
                                <View key={i} style={st.dateCard3}>
                                    <BlurView intensity={30} tint="dark" style={StyleSheet.absoluteFillObject} />
                                    <View style={st.dateCardTop3}>
                                        <Icon name={item.icon} size={16} color="#94A3B8" />
                                        <Text style={st.dateCardLabel3} numberOfLines={1} adjustsFontSizeToFit>{item.label}</Text>
                                    </View>
                                    <Text style={st.dateCardDate3}>{formatDate(item.date)}</Text>
                                    <View style={[st.dateBadge, { backgroundColor: item.info.color + '20', borderColor: item.info.color + '40', borderWidth: 1 }]}>
                                        <View style={[st.dateBadgeDot, { backgroundColor: item.info.color }]} />
                                        <Text style={[st.dateBadgeText, { color: item.info.color }]} numberOfLines={1} adjustsFontSizeToFit>{item.info.text}</Text>
                                    </View>
                                </View>
                            ))}
                        </View>
                        <View style={st.grid2RowCentered}>
                            {[
                                { label: 'TÜVTÜRK Muayene', date: v.inspection_date, info: inspection, icon: 'clipboard-check-outline' },
                                { label: 'Trafik Sigortası', date: v.insurance_end_date, info: insurance, icon: 'shield-check-outline' },
                            ].map((item, i) => (
                                <View key={i} style={st.dateCard2}>
                                    <BlurView intensity={30} tint="dark" style={StyleSheet.absoluteFillObject} />
                                    <View style={st.dateCardTop2}>
                                        <Icon name={item.icon} size={20} color="#94A3B8" />
                                        <Text style={st.dateCardLabel2} numberOfLines={1} adjustsFontSizeToFit>{item.label}</Text>
                                    </View>
                                    <Text style={st.dateCardDate2}>{formatDate(item.date)}</Text>
                                    <View style={[st.dateBadge, { backgroundColor: item.info.color + '20', borderColor: item.info.color + '40', borderWidth: 1 }]}>
                                        <View style={[st.dateBadgeDot, { backgroundColor: item.info.color }]} />
                                        <Text style={[st.dateBadgeText, { color: item.info.color }]} numberOfLines={1} adjustsFontSizeToFit>{item.info.text}</Text>
                                    </View>
                                </View>
                            ))}
                        </View>
                    </View>

                    {/* ── FINANCIAL SUMMARY ── */}
                    {hasPermission('financials.view') && (
                        <View style={st.section}>
                            <Text style={st.sectionTitle}>Finansal Özet {loading && <ActivityIndicator size="small" color="#3B82F6" style={{marginLeft: 10}} />}</Text>
                        <View style={st.finRow}>
                            {[
                                { label: 'Gelir', value: stats.revenue, img: emoji('Objects/Money Bag'), color: '#10B981', bg: ['rgba(16, 185, 129, 0.2)', 'rgba(16, 185, 129, 0.05)'] },
                                { label: 'Yakıt', value: stats.fuel, img: emoji('Travel and places/Fuel Pump'), color: '#F59E0B', bg: ['rgba(245, 158, 11, 0.2)', 'rgba(245, 158, 11, 0.05)'] },
                                { label: 'Maaş', value: stats.salary, img: emoji('People/Man Pilot'), color: '#3B82F6', bg: ['rgba(59, 130, 246, 0.2)', 'rgba(59, 130, 246, 0.05)'] },
                            ].map((f, i) => (
                                <View key={i} style={st.finCardWrap}>
                                    <BlurView intensity={30} tint="dark" style={StyleSheet.absoluteFillObject} />
                                    <LinearGradient colors={f.bg} style={st.finCard}>
                                        <Animated.View style={floatTransform}>
                                            <Image source={f.img} style={{ width: 38, height: 38, marginBottom: 6 }} resizeMode="contain" />
                                        </Animated.View>
                                        <Text style={[st.finValue, { color: f.color }]}>{fmtMoney(f.value)}</Text>
                                        <Text style={st.finLabel}>{f.label}</Text>
                                    </LinearGradient>
                                </View>
                            ))}
                        </View>
                        <View style={st.profitCardWrap}>
                            <BlurView intensity={40} tint="dark" style={StyleSheet.absoluteFillObject} />
                            <LinearGradient colors={profit >= 0 ? ['rgba(16,185,129,0.3)', 'transparent'] : ['rgba(239,68,68,0.3)', 'transparent']} style={st.profitCard} start={{x:0, y:0}} end={{x:1, y:1}}>
                                <View style={st.profitLeft}>
                                    <Text style={st.profitLabel}>Net Kâr / Zarar</Text>
                                    <Text style={st.profitValue}>{fmtMoney(profit)}</Text>
                                </View>
                                <Animated.Image source={profit >= 0 ? emoji('Symbols/Check Mark Button') : emoji('Symbols/Cross Mark')} style={[{ width: 54, height: 54 }, floatTransform]} resizeMode="contain" />
                            </LinearGradient>
                        </View>
                    </View>
                    )}

                    {/* ── MAINTENANCE HEALTH ── */}
                    {mh.has_setting && (
                        <View style={st.section}>
                            <Text style={st.sectionTitle}>Bakım Sağlığı</Text>
                            {[
                                { label: 'Yağ Değişimi', remaining: mh.oil_change_remaining_km, percent: mh.oil_change_percent },
                                { label: 'Alt Yağlama', remaining: mh.bottom_lube_remaining_km, percent: mh.bottom_lube_percent },
                            ].map((b, i) => {
                                if (b.remaining === undefined) return null;
                                
                                const hasRecord = b.remaining !== null;
                                const pct = Math.min(100, Math.max(0, b.percent || 0));
                                const barColor = !hasRecord ? '#64748B' : (pct > 60 ? '#10B981' : pct > 30 ? '#F59E0B' : '#EF4444');
                                
                                return (
                                    <View key={i} style={st.healthRow}>
                                        <View style={st.healthTop}>
                                            <Text style={st.healthLabel}>{b.label}</Text>
                                            <Animated.Text style={[st.healthKm, { color: barColor, opacity: b.remaining < 0 ? blinkAnim : 1, textShadowColor: barColor+'60', textShadowRadius: 6 }]}>
                                                {hasRecord ? (b.remaining < 0 ? `${fmtKm(Math.abs(b.remaining))} km geçti` : `${fmtKm(b.remaining)} km kaldı`) : 'KAYIT BEKLENİYOR'}
                                            </Animated.Text>
                                        </View>
                                        <View style={st.healthBarBg}>
                                            <Animated.View style={[st.healthBarFill, { width: `${hasRecord ? pct : 0}%`, backgroundColor: barColor, shadowColor: barColor, shadowOpacity: 0.8, shadowRadius: 6, elevation: 4 }]} />
                                        </View>
                                    </View>
                                );
                            })}
                        </View>
                    )}

                    {/* ── ACTION MENU ── */}
                    <View style={st.section}>
                        <Text style={st.sectionTitle}>İşlemler</Text>
                        <View style={st.menuGrid}>
                            {menuItems.map((m, i) => {
                                const flipRotate = menuFlipAnims[i] ? menuFlipAnims[i].interpolate({
                                    inputRange: [0, 1],
                                    outputRange: ['-90deg', '0deg']
                                }) : '0deg';

                                return (
                                    <Animated.View key={i} style={[
                                        st.menuCardWrap,
                                        {
                                            opacity: menuFlipAnims[i] || 1,
                                            transform: [
                                                { perspective: 800 },
                                                { rotateX: flipRotate }
                                            ]
                                        }
                                    ]}>
                                        <TouchableOpacity style={st.menuCard} activeOpacity={0.7}
                                            onPress={() => navigation.navigate(m.screen, { vehicleId: v.id, vehicle: v })}>
                                            <BlurView intensity={30} tint="dark" style={StyleSheet.absoluteFillObject} />
                                            <LinearGradient colors={m.gradient} style={st.menuIcon}>
                                                <Icon name={m.icon} size={22} color="#fff" />
                                            </LinearGradient>
                                            <Text style={st.menuLabel}>{m.label}</Text>
                                            <Icon name="chevron-right" size={20} color="#94A3B8" />
                                        </TouchableOpacity>
                                    </Animated.View>
                                );
                            })}
                        </View>
                    </View>

                    <View style={{ height: 40 }} />
                </Animated.View>
            </Animated.ScrollView>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    loaderWrap: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#020617' },

    bgBlob1: { position: 'absolute', top: -50, right: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(59, 130, 246, 0.15)', transform: [{ scale: 1.5 }] },
    bgBlob2: { position: 'absolute', bottom: 100, left: -80, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(139, 92, 246, 0.1)', transform: [{ scale: 1.8 }] },

    // Hero
    hero: { paddingBottom: 30, overflow: 'hidden' },
    heroContent: { zIndex: 1 },
    heroTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 16, paddingTop: Platform.OS === 'android' ? 40 : 8 },
    backBtn: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    statusBadge: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 8, borderRadius: 20, gap: 8, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    statusDot: { width: 10, height: 10, borderRadius: 5, shadowOffset: { width: 0, height: 0 }, shadowOpacity: 1, shadowRadius: 6, elevation: 4 },
    statusText: { color: '#F8FAFC', fontSize: 13, fontWeight: '800', letterSpacing: 0.5 },
    heroCenter: { alignItems: 'center', marginVertical: 20 },
    imageContainer: { width: W * 0.9, height: 220, alignItems: 'center', justifyContent: 'center' },
    imageGlow: { position: 'absolute', width: 200, height: 200, borderRadius: 100, backgroundColor: 'rgba(59,130,246,0.15)', shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 0 }, shadowOpacity: 1, shadowRadius: 30, elevation: 10, transform: [{ scaleX: 1.5 }, { scaleY: 0.8 }] },
    heroImage: { width: 180, height: 180, zIndex: 2 },
    heroBottom: { alignItems: 'center', paddingHorizontal: 20, marginTop: 4 },
    plateText: { fontSize: 32, fontWeight: '900', color: '#F8FAFC', letterSpacing: 3, textTransform: 'uppercase', textShadowColor: 'rgba(0,0,0,0.6)', textShadowOffset: { width: 0, height: 4 }, textShadowRadius: 8 },
    brandText: { fontSize: 16, color: '#CBD5E1', fontWeight: '700', marginTop: 4, letterSpacing: 1 },
    typeBadge: { marginTop: 12, backgroundColor: 'rgba(255,255,255,0.1)', paddingHorizontal: 14, paddingVertical: 6, borderRadius: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    typeText: { color: '#F8FAFC', fontSize: 12, fontWeight: '800', letterSpacing: 1 },

    // Quick stats
    quickRow: { marginTop: -16 },
    quickCard: { borderRadius: 20, padding: 14, width: 110, alignItems: 'center', overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.4, shadowRadius: 8, elevation: 5 },
    quickIcon: { width: 44, height: 44, borderRadius: 16, alignItems: 'center', justifyContent: 'center', marginBottom: 8, backgroundColor: 'rgba(255,255,255,0.05)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    quickLabel: { fontSize: 11, color: '#94A3B8', fontWeight: '700', marginBottom: 2 },
    quickValue: { fontSize: 14, color: '#F8FAFC', fontWeight: '900' },

    // Sections
    section: { paddingHorizontal: 16, marginTop: 28 },
    sectionTitle: { fontSize: 18, fontWeight: '900', color: '#F8FAFC', marginBottom: 16, letterSpacing: -0.3, textShadowColor: 'rgba(0,0,0,0.5)', textShadowOffset: { width: 0, height: 2 }, textShadowRadius: 4 },

    // Date cards
    grid3Row: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 12 },
    grid2RowCentered: { flexDirection: 'row', gap: 10, justifyContent: 'center' },
    dateCard3: { width: '48%', flexGrow: 1, borderRadius: 16, padding: 12, alignItems: 'center', overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 8, elevation: 4 },
    dateCardTop3: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 8 },
    dateCardLabel3: { fontSize: 11, fontWeight: '800', color: '#CBD5E1' },
    dateCardDate3: { fontSize: 12, color: '#94A3B8', fontWeight: '700', marginBottom: 8 },
    
    dateCard2: { flex: 1, maxWidth: '48%', borderRadius: 18, padding: 14, alignItems: 'center', overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 8, elevation: 4 },
    dateCardTop2: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 8 },
    dateCardLabel2: { fontSize: 13, fontWeight: '800', color: '#CBD5E1' },
    dateCardDate2: { fontSize: 13, color: '#94A3B8', fontWeight: '700', marginBottom: 10 },
    
    dateBadge: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 8, paddingVertical: 6, borderRadius: 10, gap: 6, alignSelf: 'center', width: '100%', justifyContent: 'center' },
    dateBadgeDot: { width: 8, height: 8, borderRadius: 4, shadowOffset: { width: 0, height: 0 }, shadowOpacity: 0.8, shadowRadius: 4 },
    dateBadgeText: { fontSize: 10, fontWeight: '900', letterSpacing: 0.5 },

    // Financial
    finRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 12 },
    finCardWrap: { width: '31%', flexGrow: 1, borderRadius: 20, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 8, elevation: 4 },
    finCard: { padding: 16, alignItems: 'center' },
    finValue: { fontSize: 15, fontWeight: '900', marginTop: 8, letterSpacing: -0.5 },
    finLabel: { fontSize: 11, color: '#94A3B8', fontWeight: '700', marginTop: 4 },
    profitCardWrap: { borderRadius: 20, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.4, shadowRadius: 10, elevation: 6 },
    profitCard: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', padding: 22 },
    profitLeft: {},
    profitLabel: { color: '#94A3B8', fontSize: 13, fontWeight: '800', letterSpacing: 0.5 },
    profitValue: { color: '#F8FAFC', fontSize: 28, fontWeight: '900', marginTop: 4, letterSpacing: -1, textShadowColor: 'rgba(0,0,0,0.5)', textShadowOffset: { width: 0, height: 2 }, textShadowRadius: 6 },

    // Health bars
    healthRow: { marginBottom: 18 },
    healthTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 },
    healthLabel: { fontSize: 15, fontWeight: '800', color: '#F8FAFC' },
    healthKm: { fontSize: 13, fontWeight: '900' },
    healthBarBg: { height: 10, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 5, overflow: 'hidden' },
    healthBarFill: { height: 10, borderRadius: 5 },

    // Menu
    menuGrid: { gap: 10 },
    menuCardWrap: { borderRadius: 18, shadowColor: '#000', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.4, shadowRadius: 10, elevation: 6 },
    menuCard: { flexDirection: 'row', alignItems: 'center', borderRadius: 18, padding: 16, gap: 16, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.08)' },
    menuIcon: { width: 48, height: 48, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },
    menuLabel: { flex: 1, fontSize: 16, fontWeight: '800', color: '#F8FAFC', letterSpacing: 0.5 },
});
