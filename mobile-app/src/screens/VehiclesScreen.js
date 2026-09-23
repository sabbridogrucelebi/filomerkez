import React, { useState, useCallback, useEffect, useRef, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, RefreshControl, Modal, TextInput, Alert, KeyboardAvoidingView, Platform, Image, Animated, Easing, Dimensions, Linking, StatusBar } from 'react-native';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import { useFocusEffect } from '@react-navigation/native';
import api from '../api/axios';
import { emoji } from '../emoji';
import SpaceWaves from '../components/SpaceWaves';

// Her bir liste elemanının giriş animasyonu için ayrı Component
const AnimatedVehicleRow = ({ item, index, navigation, kpiFilter, getVehicleImage, setActionItem, floatAnim }) => {
    const slideAnim = useRef(new Animated.Value(50)).current;
    const opacityAnim = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        Animated.parallel([
            Animated.timing(opacityAnim, {
                toValue: 1,
                duration: 400,
                delay: Math.min(index * 100, 500),
                useNativeDriver: true,
            }),
            Animated.spring(slideAnim, {
                toValue: 0,
                friction: 6,
                tension: 40,
                delay: Math.min(index * 100, 500),
                useNativeDriver: true,
            })
        ]).start();
    }, []);

    const floatTransform = {
        transform: [{
            translateY: floatAnim.interpolate({
                inputRange: [0, 1],
                outputRange: [0, -4]
            })
        }]
    };

    const imgSource = item.image_url ? { uri: item.image_url } : getVehicleImage(item.vehicle_type || item.type);

    const inspectionDays = item.inspection_date ? Math.ceil((new Date(item.inspection_date) - new Date()) / (1000 * 60 * 60 * 24)) : null;
    const showInspectionWarning = kpiFilter === 'upcoming_inspection' && inspectionDays !== null && inspectionDays <= 15;

    const insuranceDays = item.insurance_end_date ? Math.ceil((new Date(item.insurance_end_date) - new Date()) / (1000 * 60 * 60 * 24)) : null;
    const showInsuranceWarning = kpiFilter === 'upcoming_insurance' && insuranceDays !== null && insuranceDays <= 10;

    return (
        <Animated.View style={{ opacity: opacityAnim, transform: [{ translateY: slideAnim }] }}>
            <TouchableOpacity 
                style={[s.tableRow, (showInspectionWarning || showInsuranceWarning) && {flexDirection: 'column', alignItems: 'stretch'}]} 
                activeOpacity={0.7} 
                onPress={()=>navigation.navigate('VehicleDetail', { vehicle: item })} 
                onLongPress={()=>setActionItem(item)}
            >
                <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />
                
                {/* Main Row Content */}
                <View style={{ flexDirection: 'row', alignItems: 'center', width: '100%', padding: 12 }}>
                    <Animated.View style={[s.rowImageWrap, floatTransform]}>
                        <Image source={imgSource} style={s.rowImage} />
                    </Animated.View>
                    
                    <View style={s.rowColMain}>
                        <Text style={s.rowPlate}>{item.plate}</Text>
                        <Text style={s.rowBrand} numberOfLines={1}>{item.brand_model || 'Belirtilmemiş'}</Text>
                    </View>

                    <View style={s.rowColMid}>
                        <View style={s.rowBadge}>
                            <Text style={s.rowBadgeText}>{item.model_year || 'Yıl Yok'}</Text>
                        </View>
                        <View style={[s.statusDotSmall, { backgroundColor: item.status === 'active' ? '#10B981' : '#EF4444', shadowColor: item.status === 'active' ? '#10B981' : '#EF4444' }]} />
                    </View>

                    <View style={s.rowColRight}>
                        <Text style={s.rowDriver} numberOfLines={1}>{item.driver || 'Atanmamış'}</Text>
                        <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                            <Icon name="speedometer" size={12} color="#10B981" style={{ marginRight: 2 }} />
                            <Text style={s.rowKm}>{(!item.current_km || item.current_km === 0) ? '0 km' : `${Number(item.current_km).toLocaleString('tr-TR')} km`}</Text>
                        </View>
                    </View>

                    <Icon name="chevron-right" size={20} color="#64748B" style={{ marginLeft: 6 }} />
                </View>

                {/* Inspection Warning Box (3D Neon Style) */}
                {showInspectionWarning && (
                    <View style={s.warningBox}>
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                            <View>
                                <Text style={s.warningDateText}>SON TARİH: {new Date(item.inspection_date).toLocaleDateString('tr-TR')}</Text>
                                <Text style={[s.warningDaysText, { color: inspectionDays < 0 ? '#EF4444' : '#FBBF24', textShadowColor: inspectionDays < 0 ? 'rgba(239, 68, 68, 0.4)' : 'rgba(251, 191, 36, 0.4)' }]}>
                                    {inspectionDays < 0 ? `${Math.abs(inspectionDays)} GÜN GEÇTİ` : `${inspectionDays} GÜN KALDI`}
                                </Text>
                            </View>
                            <TouchableOpacity style={[s.actionBtn, { shadowColor: '#3B82F6' }]} onPress={() => Linking.openURL('https://www.tuvturk.com.tr/hizmetlerimiz/hizli-islemler/arac-muayene-randevusu-alma')}>
                                <Text style={s.actionBtnText}>RANDEVU AL</Text>
                                <Icon name="open-in-new" size={14} color="#FFF" style={{ marginLeft: 4 }} />
                            </TouchableOpacity>
                        </View>
                    </View>
                )}

                {/* Insurance Warning Box (3D Neon Style) */}
                {showInsuranceWarning && (
                    <View style={s.warningBox}>
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                            <View>
                                <Text style={s.warningDateText}>POLİÇE BİTİŞ: {new Date(item.insurance_end_date).toLocaleDateString('tr-TR')}</Text>
                                <Text style={[s.warningDaysText, { color: insuranceDays < 0 ? '#EF4444' : '#F472B6', textShadowColor: insuranceDays < 0 ? 'rgba(239, 68, 68, 0.4)' : 'rgba(244, 114, 182, 0.4)' }]}>
                                    {insuranceDays < 0 ? `${Math.abs(insuranceDays)} GÜN GEÇTİ` : `${insuranceDays} GÜN KALDI`}
                                </Text>
                            </View>
                        </View>
                    </View>
                )}

            </TouchableOpacity>
        </Animated.View>
    );
};


export default function VehiclesScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const [vehicles, setVehicles] = useState([]);
    const [kpi, setKpi] = useState({ total: 0, upcoming_inspection: 0, upcoming_insurance: 0, active_count: 0 });
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);

    const [searchQuery, setSearchQuery] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('active');
    const [kpiFilter, setKpiFilter] = useState(null);
    const [showFilter, setShowFilter] = useState(false);
    
    const [editingId, setEditingId] = useState(null);
    const [actionItem, setActionItem] = useState(null); 

    // Animasyon Değerleri
    const scrollY = useRef(new Animated.Value(0)).current;
    const floatAnim = useRef(new Animated.Value(0)).current;
    const kpiFlipAnims = useRef([new Animated.Value(0), new Animated.Value(0), new Animated.Value(0)]).current;

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedSearch(searchQuery);
        }, 500);
        return () => clearTimeout(handler);
    }, [searchQuery]);

    const animateKPIs = () => {
        kpiFlipAnims.forEach(a => a.setValue(0));
        Animated.stagger(150, kpiFlipAnims.map(anim => 
            Animated.spring(anim, {
                toValue: 1,
                friction: 6,
                tension: 40,
                useNativeDriver: true
            })
        )).start();
    };

    useEffect(() => {
        // Sürekli Yüzen İkonlar Animasyonu
        Animated.loop(
            Animated.sequence([
                Animated.timing(floatAnim, { toValue: 1, duration: 2500, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(floatAnim, { toValue: 0, duration: 2500, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        ).start();
    }, []);

    const fetchVehicles = async (pageNumber = 1) => {
        if (pageNumber === 1 && !refreshing && vehicles.length === 0) setLoading(true);
        else if (pageNumber > 1) setLoadingMore(true);

        try {
            const params = { page: pageNumber, per_page: 100 };
            if (statusFilter !== 'all') params.status = statusFilter;
            if (kpiFilter) params.filter = kpiFilter;
            if (debouncedSearch) params.search = debouncedSearch;

            const r = await api.get('/v1/vehicles', { params });
            const newVehicles = r.data.data.vehicles || [];
            
            if (pageNumber === 1) {
                setVehicles(newVehicles);
                animateKPIs();
            } else {
                setVehicles(prev => [...prev, ...newVehicles]);
            }

            setKpi(r.data.data.kpi || { total:0, upcoming_inspection:0, upcoming_insurance:0, active_count:0 });
            
            const meta = r.data.meta;
            if (meta) {
                setHasMore(meta.current_page < meta.last_page);
                setPage(meta.current_page);
            } else {
                setHasMore(false);
            }
        } catch(e) { 
            // Kullanıcıyı rahatsız etmeden sessizce logla (sunucu hatası vs.)
            console.log('Araçlar yüklenemedi:', e);
        } finally { 
            setLoading(false); 
            setRefreshing(false); 
            setLoadingMore(false);
        }
    };

    useFocusEffect(useCallback(() => { 
        fetchVehicles(1); 
    }, [statusFilter, debouncedSearch, kpiFilter]));

    const loadMore = () => {
        if (hasMore && !loadingMore && !loading) fetchVehicles(page + 1);
    };

    // Yer tutucu araç görselleri uygulama içinden geliyor: internet yokken veya
    // üçüncü taraf barındırma erişilemezken de liste bozulmuyor.
    const getVehicleImage = (type) => {
        const t = (type || '').toLowerCase();
        if (t.includes('minib')) return require('../../assets/arac_tipleri/servis_pilot_minibus.png');
        if (t.includes('midib')) return require('../../assets/arac_tipleri/servis_pilot_midibus.png');
        if (t.includes('otob')) return require('../../assets/arac_tipleri/servis_pilot_otobus.png');
        if (t.includes('panelvan')) return require('../../assets/arac_tipleri/servis_pilot_panelvan.png');
        if (t.includes('kamyonet')) return require('../../assets/arac_tipleri/servis_pilot_kamyonet.png');
        if (t.includes('binek') || t.includes('sedan') || t.includes('taksi')) return require('../../assets/arac_tipleri/servis_pilot_taksi.png');

        return require('../../assets/arac_tipleri/servis_pilot_panelvan.png');
    };

    const renderFooter = () => {
        if (!loadingMore) return null;
        return <View style={s.footerLoader}><ActivityIndicator size="small" color="#8B5CF6" /></View>;
    };

    // Animasyon Dönüşümleri
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
                outputRange: [0, -6]
            })
        }]
    };

    const renderKpiCard = (index, label, value, isActive, onPress, gradientColors, iconUrl) => {
        const flipRotate = kpiFlipAnims[index].interpolate({
            inputRange: [0, 1],
            outputRange: ['-90deg', '0deg']
        });
        
        const scaleEffect = kpiFlipAnims[index].interpolate({
            inputRange: [0, 0.5, 1],
            outputRange: [0.5, 1.1, 1]
        });

        return (
            <Animated.View style={[
                s.kpiCardWrap, 
                isActive && { transform: [{ scale: 1.05 }] },
                !isActive && {
                    opacity: kpiFlipAnims[index],
                    transform: [
                        { perspective: 800 },
                        { rotateX: flipRotate },
                        { scale: scaleEffect }
                    ]
                }
            ]}>
                <TouchableOpacity activeOpacity={0.8} onPress={onPress} style={{ flex: 1 }}>
                    <BlurView intensity={isActive ? 60 : 30} tint="dark" style={{ borderRadius: 20, overflow: 'hidden' }}>
                        <LinearGradient 
                            colors={isActive ? [gradientColors[0], gradientColors[1]] : [gradientColors[0]+'50', gradientColors[1]+'30']} 
                            style={[s.kpiCard, isActive && s.kpiCardActive]}
                            start={{x:0, y:0}} end={{x:1, y:1}}
                        >
                            <Animated.View style={[s.kpiIconWrap, floatTransform]}>
                                <Image source={iconUrl} style={s.kpiIcon} resizeMode="contain" />
                            </Animated.View>
                            <Text style={s.kpiValue} adjustsFontSizeToFit numberOfLines={1}>{value}</Text>
                            <Text style={s.kpiTitle} numberOfLines={1}>{label}</Text>
                        </LinearGradient>
                    </BlurView>
                </TouchableOpacity>
            </Animated.View>
        );
    };

    return (
        <View style={s.container}>
            {/* Parallax Arka Plan */}
            <Animated.View style={[StyleSheet.absoluteFill, bgTransform]}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <View style={s.bgBlob1} />
                <View style={s.bgBlob2} />
            </Animated.View>
            <StatusBar barStyle="light-content" />

            <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                {/* Header */}
                <View style={s.header}>
                    <View style={{ flex: 1 }}>
                        <Text style={s.headerTitle}>Filo Yönetimi</Text>
                        <Text style={s.headerSub}>Araçlarınızın anlık durumunu takip edin</Text>
                    </View>
                    {hasPermission('vehicles.create') && (
                        <TouchableOpacity style={s.addBtn} activeOpacity={0.8} onPress={() => {/* add logic */}}>
                            <BlurView intensity={40} tint="light" style={{ borderRadius: 16 }}>
                                <LinearGradient colors={['rgba(59,130,246,0.8)', 'rgba(37,99,235,0.8)']} style={s.addBtnGradient}>
                                    <Icon name="plus" size={28} color="#FFF" />
                                </LinearGradient>
                            </BlurView>
                        </TouchableOpacity>
                    )}
                </View>

                {/* KPI Cards */}
                <View style={s.kpiContainer}>
                    {renderKpiCard(
                        0, 'Toplam', kpi?.total || 0, 
                        kpiFilter === null, 
                        () => { setKpiFilter(null); setStatusFilter('all'); animateKPIs(); }, 
                        ['#3B82F6', '#1D4ED8'], 
                        emoji('Travel and places/Oncoming Bus')
                    )}
                    {renderKpiCard(
                        1, 'Muayene', kpi?.upcoming_inspection || 0, 
                        kpiFilter === 'upcoming_inspection', 
                        () => { setKpiFilter('upcoming_inspection'); setStatusFilter('all'); animateKPIs(); }, 
                        ['#F59E0B', '#B45309'], 
                        emoji('Symbols/Warning')
                    )}
                    {renderKpiCard(
                        2, 'Sigorta', kpi?.upcoming_insurance || 0, 
                        kpiFilter === 'upcoming_insurance', 
                        () => { setKpiFilter('upcoming_insurance'); setStatusFilter('all'); animateKPIs(); }, 
                        ['#EF4444', '#B91C1C'], 
                        emoji('Objects/Shield')
                    )}
                </View>

                {/* Search & Filter */}
                <View style={s.searchContainer}>
                    <View style={s.searchWrap}>
                        <BlurView intensity={20} tint="dark" style={StyleSheet.absoluteFillObject} />
                        <Icon name="magnify" size={22} color="#94A3B8" />
                        <TextInput
                            style={s.searchInput}
                            placeholder="Plaka, marka veya personel ara..."
                            placeholderTextColor="#64748B"
                            value={searchQuery}
                            onChangeText={setSearchQuery}
                        />
                        {searchQuery.length > 0 && (
                            <TouchableOpacity onPress={() => setSearchQuery('')} style={{ padding: 4 }}>
                                <Icon name="close-circle" size={20} color="#94A3B8" />
                            </TouchableOpacity>
                        )}
                    </View>
                    <TouchableOpacity style={[s.filterBtn, statusFilter !== 'all' && s.filterBtnActive]} onPress={() => setShowFilter(true)} activeOpacity={0.7}>
                        <BlurView intensity={20} tint="dark" style={[StyleSheet.absoluteFillObject, {borderRadius: 14}]} />
                        <Icon name="filter-variant" size={24} color={statusFilter !== 'all' ? '#FFF' : '#94A3B8'} />
                    </TouchableOpacity>
                </View>

                {/* List Table Header */}
                <View style={s.tableHeader}>
                    <Text style={[s.thText, { width: 50 }]}></Text>
                    <Text style={[s.thText, { flex: 2 }]}>PLAKA / MARKA</Text>
                    <Text style={[s.thText, { flex: 1, textAlign: 'center' }]}>YIL/DRM</Text>
                    <Text style={[s.thText, { flex: 1.5, textAlign: 'right', paddingRight: 24 }]}>PERSONEL/KM</Text>
                </View>

                {/* List */}
                {loading && page === 1 ? (
                    <View style={s.centerLoader}>
                        <ActivityIndicator size="large" color="#8B5CF6" />
                    </View>
                ) : (
                    <Animated.FlatList
                        data={vehicles}
                        keyExtractor={item => String(item.id)}
                        renderItem={({item, index}) => <AnimatedVehicleRow item={item} index={index} navigation={navigation} kpiFilter={kpiFilter} getVehicleImage={getVehicleImage} setActionItem={setActionItem} floatAnim={floatAnim} />}
                        contentContainerStyle={s.listContent}
                        showsVerticalScrollIndicator={false}
                        onScroll={Animated.event(
                            [{ nativeEvent: { contentOffset: { y: scrollY } } }],
                            { useNativeDriver: true }
                        )}
                        scrollEventThrottle={16}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchVehicles(1); }} tintColor="#8B5CF6" />}
                        ListEmptyComponent={
                            <View style={s.emptyState}>
                                <View style={s.emptyIconWrap}>
                                    <Image source={emoji('Travel and places/Automobile')} style={{width: 64, height: 64}} resizeMode="contain" />
                                </View>
                                <Text style={s.emptyTitle}>Araç Bulunamadı</Text>
                                <Text style={s.emptyText}>Arama kriterlerinize uygun araç listelenemedi.</Text>
                            </View>
                        }
                        ListFooterComponent={renderFooter}
                        onEndReached={loadMore}
                        onEndReachedThreshold={0.5}
                    />
                )}
            </SafeAreaView>

            {/* Filter Modal */}
            <Modal visible={showFilter} transparent animationType="fade">
                <View style={s.modalOverlay}>
                    <BlurView intensity={40} tint="dark" style={StyleSheet.absoluteFillObject} />
                    <View style={s.filterModal}>
                        <View style={s.modalHeader}>
                            <Text style={s.modalTitle}>Filtrele</Text>
                            <TouchableOpacity onPress={() => setShowFilter(false)} style={s.modalClose}>
                                <Icon name="close" size={24} color="#F8FAFC" />
                            </TouchableOpacity>
                        </View>
                        <Text style={s.filterLabel}>Araç Durumu</Text>
                        <View style={s.filterRow}>
                            <TouchableOpacity style={[s.filterChip, statusFilter === 'all' && s.filterChipActive]} onPress={() => { setStatusFilter('all'); setShowFilter(false); }}>
                                <Text style={[s.filterChipText, statusFilter === 'all' && s.filterChipTextActive]}>Tümü</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={[s.filterChip, statusFilter === 'active' && s.filterChipActive]} onPress={() => { setStatusFilter('active'); setShowFilter(false); }}>
                                <Text style={[s.filterChipText, statusFilter === 'active' && s.filterChipTextActive]}>Aktif</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={[s.filterChip, statusFilter === 'passive' && s.filterChipActive]} onPress={() => { setStatusFilter('passive'); setShowFilter(false); }}>
                                <Text style={[s.filterChipText, statusFilter === 'passive' && s.filterChipTextActive]}>Pasif</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    
    // Parallax background elements
    bgBlob1: { position: 'absolute', top: -100, right: -50, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(59, 130, 246, 0.12)', transform: [{ scale: 1.5 }] },
    bgBlob2: { position: 'absolute', bottom: 100, left: -100, width: 250, height: 250, borderRadius: 125, backgroundColor: 'rgba(139, 92, 246, 0.1)', transform: [{ scale: 1.8 }] },
    
    header: { flexDirection: 'row', paddingHorizontal: 20, paddingTop: Platform.OS === 'ios' ? 10 : 20, paddingBottom: 20, alignItems: 'center' },
    headerTitle: { fontSize: 28, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5, textShadowColor: 'rgba(0,0,0,0.5)', textShadowOffset: {width: 0, height: 2}, textShadowRadius: 4 },
    headerSub: { fontSize: 13, color: '#94A3B8', fontWeight: '500', marginTop: 4 },
    addBtn: { borderRadius: 16, shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.6, shadowRadius: 10, elevation: 8 },
    addBtnGradient: { width: 48, height: 48, alignItems: 'center', justifyContent: 'center', borderRadius: 16 },

    kpiContainer: { flexDirection: 'row', paddingHorizontal: 20, paddingBottom: 16, gap: 10 },
    kpiCardWrap: { flex: 1, borderRadius: 20, shadowColor: '#000', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.4, shadowRadius: 12, elevation: 6 },
    kpiCard: { padding: 14, borderRadius: 20, justifyContent: 'space-between', minHeight: 90, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    kpiCardActive: { borderColor: 'rgba(255,255,255,0.3)', shadowColor: '#FFF', shadowOpacity: 0.2, shadowRadius: 10 },
    kpiIconWrap: { width: 28, height: 28, marginBottom: 8 },
    kpiIcon: { width: 28, height: 28 },
    kpiValue: { fontSize: 22, fontWeight: '900', color: '#FFF', letterSpacing: -0.5, textShadowColor: 'rgba(0,0,0,0.5)', textShadowOffset: {width: 0, height: 1}, textShadowRadius: 2 },
    kpiTitle: { fontSize: 11, color: 'rgba(255,255,255,0.85)', fontWeight: '700', marginTop: 2 },

    searchContainer: { flexDirection: 'row', paddingHorizontal: 20, marginBottom: 12, gap: 10 },
    searchWrap: { flex: 1, flexDirection: 'row', alignItems: 'center', borderRadius: 14, paddingHorizontal: 12, height: 46, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    searchInput: { flex: 1, marginLeft: 8, fontSize: 14, color: '#F8FAFC', fontWeight: '600' },
    filterBtn: { width: 46, height: 46, borderRadius: 14, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    filterBtnActive: { borderColor: '#3B82F6', backgroundColor: 'rgba(59,130,246,0.3)' },

    tableHeader: { flexDirection: 'row', paddingHorizontal: 20, paddingVertical: 10, borderTopWidth: 1, borderBottomWidth: 1, borderColor: 'rgba(255,255,255,0.05)', backgroundColor: 'rgba(0,0,0,0.2)', alignItems: 'center' },
    thText: { fontSize: 10, fontWeight: '800', color: '#94A3B8', letterSpacing: 0.5 },

    listContent: { paddingBottom: 120, gap: 12, paddingHorizontal: 20, paddingTop: 10 },
    
    tableRow: { borderRadius: 16, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.08)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.4, shadowRadius: 8, elevation: 5 },
    rowImageWrap: { width: 44, height: 44, borderRadius: 12, backgroundColor: 'rgba(59, 130, 246, 0.15)', alignItems: 'center', justifyContent: 'center', marginRight: 12, borderWidth: 1, borderColor: 'rgba(59, 130, 246, 0.3)' },
    rowImage: { width: 34, height: 34, resizeMode: 'contain' },
    rowColMain: { flex: 2, justifyContent: 'center' },
    rowPlate: { fontSize: 14, fontWeight: '900', color: '#F8FAFC', marginBottom: 2, letterSpacing: 0.5 },
    rowBrand: { fontSize: 11, color: '#94A3B8', fontWeight: '600' },
    rowColMid: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    rowBadge: { backgroundColor: 'rgba(254, 240, 138, 0.15)', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6, marginBottom: 6, borderWidth: 1, borderColor: 'rgba(254, 240, 138, 0.3)' },
    rowBadgeText: { fontSize: 9, fontWeight: '800', color: '#FEF08A' },
    statusDotSmall: { width: 10, height: 10, borderRadius: 5, shadowOffset: { width: 0, height: 0 }, shadowOpacity: 0.8, shadowRadius: 4, elevation: 4 },
    rowColRight: { flex: 1.5, alignItems: 'flex-end', justifyContent: 'center' },
    rowDriver: { fontSize: 11, color: '#E2E8F0', fontWeight: '700', marginBottom: 4 },
    rowKm: { fontSize: 11, color: '#10B981', fontWeight: '800' },
    
    warningBox: { backgroundColor: 'rgba(0,0,0,0.3)', padding: 14, borderTopWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    warningDateText: { fontSize: 10, color: '#94A3B8', fontWeight: '700', marginBottom: 4, letterSpacing: 0.5 },
    warningDaysText: { fontSize: 14, fontWeight: '900', letterSpacing: -0.5, textShadowOffset: { width: 0, height: 0 }, textShadowRadius: 8 },
    actionBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(59, 130, 246, 0.2)', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 10, borderWidth: 1, borderColor: 'rgba(59, 130, 246, 0.5)' },
    actionBtnText: { fontSize: 10, color: '#60A5FA', fontWeight: '900', letterSpacing: 0.5 },

    centerLoader: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingTop: 100 },
    footerLoader: { paddingVertical: 20, alignItems: 'center' },
    
    emptyState: { alignItems: 'center', justifyContent: 'center', paddingVertical: 60, paddingHorizontal: 40 },
    emptyIconWrap: { width: 96, height: 96, borderRadius: 48, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', justifyContent: 'center', marginBottom: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    emptyTitle: { fontSize: 18, fontWeight: '900', color: '#F8FAFC', marginBottom: 8 },
    emptyText: { fontSize: 14, color: '#94A3B8', textAlign: 'center', lineHeight: 22, fontWeight: '500' },

    modalOverlay: { flex: 1, justifyContent: 'flex-end' },
    filterModal: { backgroundColor: '#1E293B', borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, paddingBottom: 40, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: -10 }, shadowOpacity: 0.5, shadowRadius: 20, elevation: 20 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 },
    modalTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC' },
    modalClose: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    filterLabel: { fontSize: 13, fontWeight: '800', color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 12 },
    filterRow: { flexDirection: 'row', gap: 12, flexWrap: 'wrap' },
    filterChip: { paddingHorizontal: 20, paddingVertical: 12, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.05)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    filterChipActive: { backgroundColor: 'rgba(59,130,246,0.2)', borderColor: '#3B82F6' },
    filterChipText: { fontSize: 14, fontWeight: '700', color: '#CBD5E1' },
    filterChipTextActive: { color: '#60A5FA', fontWeight: '800' }
});
