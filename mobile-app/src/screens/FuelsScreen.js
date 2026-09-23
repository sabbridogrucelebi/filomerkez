import React, { useState, useEffect, useContext, useRef, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Animated, Dimensions, TextInput, Modal, Easing, RefreshControl } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect } from '@react-navigation/native';
import DateTimePicker from '@react-native-community/datetimepicker';
import { BlurView } from 'expo-blur';
import { Image } from 'expo-image';
import dayjs from 'dayjs';
import api from '../api/axios';
import { emoji } from '../emoji';
import { AuthContext } from '../context/AuthContext';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(v || 0);
const fmtKm = (v) => new Intl.NumberFormat('tr-TR').format(v || 0);

export default function FuelsScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const insets = useSafeAreaInsets();
    
    const [rawFuels, setRawFuels] = useState([]);
    const [processedFuels, setProcessedFuels] = useState([]);
    const [displayedFuels, setDisplayedFuels] = useState([]);
    
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    
    const [searchQuery, setSearchQuery] = useState('');
    const [startDate, setStartDate] = useState(null);
    const [endDate, setEndDate] = useState(null);
    
    const [showFilters, setShowFilters] = useState(false);
    const [showStartPicker, setShowStartPicker] = useState(false);
    const [showEndPicker, setShowEndPicker] = useState(false);

    const [kpi, setKpi] = useState({ totalCost: 0, totalLiters: 0, count: 0, debt: 0 });

    // Animations
    const scrollY = useRef(new Animated.Value(0)).current;
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;
    const flipAnims = useRef([...Array(100)].map(() => new Animated.Value(0))).current;

    useEffect(() => {
        const loop = Animated.loop(
            Animated.sequence([
                Animated.timing(blob1Anim, { toValue: 1, duration: 9000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob1Anim, { toValue: 0, duration: 9000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        const loop2 = Animated.loop(
            Animated.sequence([
                Animated.timing(blob2Anim, { toValue: 1, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob2Anim, { toValue: 0, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        loop.start(); loop2.start();
        return () => { loop.stop(); loop2.stop(); };
    }, []);

    const processFuels = (data) => {
        const sortedAsc = [...data].sort((a, b) => new Date(a.date) - new Date(b.date));
        const grouped = {};
        sortedAsc.forEach(f => {
            const plate = f.vehicle?.plate || 'Bilinmeyen';
            if (!grouped[plate]) grouped[plate] = [];
            grouped[plate].push(f);
        });

        const finalData = [];
        Object.values(grouped).forEach(vehicleFuels => {
            let lastKm = null;
            vehicleFuels.forEach(f => {
                let diff = '-';
                let kml = '-';
                if (f.km) {
                    if (lastKm !== null && f.km > lastKm) {
                        diff = (f.km - lastKm).toString();
                        if (f.liters && parseFloat(f.liters) > 0) {
                            kml = (parseFloat(diff) / parseFloat(f.liters)).toFixed(2);
                        }
                    }
                    lastKm = f.km;
                }
                finalData.push({ ...f, km_diff: diff, km_per_liter: kml });
            });
        });

        return finalData.sort((a, b) => new Date(b.date) - new Date(a.date));
    };

    const fetchData = async (hideLoader = false) => {
        if (!hideLoader) setLoading(true);
        try {
            const res = await api.get('/v1/fuels');
            const data = res.data?.data?.fuels || [];
            const apiDebt = res.data?.data?.total_debt || 0;
            setRawFuels(data);
            
            const pFuels = processFuels(data);
            setProcessedFuels(pFuels);
            applyFilters(pFuels, searchQuery, startDate, endDate, apiDebt);

            // Stagger animation
            flipAnims.forEach(a => a.setValue(0));
            Animated.stagger(60, flipAnims.map((anim) => 
                Animated.spring(anim, { toValue: 1, friction: 8, tension: 50, useNativeDriver: true })
            )).start();

        } catch (e) {
            console.log('Fuels Error:', e.response?.data || e.message);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const applyFilters = (data, query, start, end, apiDebt = null) => {
        let filtered = data;
        
        if (query) {
            const q = query.toLowerCase();
            filtered = filtered.filter(f => 
                f.vehicle?.plate?.toLowerCase().includes(q) || 
                f.station?.name?.toLowerCase().includes(q) ||
                f.station_name?.toLowerCase().includes(q)
            );
        }

        if (start) {
            filtered = filtered.filter(f => dayjs(f.date).isAfter(dayjs(start).subtract(1, 'day')));
        }
        if (end) {
            filtered = filtered.filter(f => dayjs(f.date).isBefore(dayjs(end).add(1, 'day')));
        }

        setDisplayedFuels(filtered);
        
        const cost = filtered.reduce((sum, item) => sum + parseFloat(item.gross_total_cost || item.total_cost || 0), 0);
        const liters = filtered.reduce((sum, item) => sum + parseFloat(item.liters || 0), 0);
        
        setKpi(prev => ({ 
            totalCost: cost, 
            totalLiters: liters, 
            count: filtered.length, 
            debt: apiDebt !== null ? apiDebt : prev.debt 
        }));
    };

    const handleFilterSubmit = () => {
        applyFilters(processedFuels, searchQuery, startDate, endDate);
        setShowFilters(false);
    };

    const handleClearFilters = () => {
        setSearchQuery('');
        setStartDate(null);
        setEndDate(null);
        applyFilters(processedFuels, '', null, null);
        setShowFilters(false);
    };

    useFocusEffect(useCallback(() => { fetchData(true); }, []));

    const confirmDelete = (item) => {
        import('react-native').then(({ Alert }) => {
            Alert.alert('Silme Onayı', `${dayjs(item.date).format('DD.MM.YYYY')} tarihli yakıt kaydını silmek istediğinize emin misiniz?`, [
                { text: 'Vazgeç', style: 'cancel' },
                { 
                    text: 'Sil', style: 'destructive', 
                    onPress: async () => {
                        try {
                            await api.delete(`/v1/fuels/${item.id}`);
                            fetchData(true);
                        } catch (e) {}
                    }
                }
            ]);
        });
    };

    const renderHeader = () => (
        <View style={{ marginBottom: 20 }}>
            {/* Action Bar */}
            <View style={s.actionsRow}>
                <TouchableOpacity style={[s.actionBtn, { flex: 2, backgroundColor: showFilters ? 'rgba(59,130,246,0.3)' : 'transparent' }]} onPress={() => setShowFilters(true)}>
                    <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                    <Icon name="filter-variant" size={20} color="#F8FAFC" />
                    <Text style={{ color: '#F8FAFC', fontWeight: '800', marginLeft: 8 }}>Kayıtları Filtrele</Text>
                </TouchableOpacity>
                <TouchableOpacity style={s.actionBtn} onPress={() => navigation.navigate('FuelStations')}>
                    <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                    <Icon name="gas-station" size={20} color="#34D399" />
                    <Text style={{ color: '#34D399', fontWeight: '800', marginLeft: 8 }}>İstasyonlar</Text>
                </TouchableOpacity>
            </View>

            {/* KPI ScrollView */}
            <Animated.ScrollView 
                horizontal 
                showsHorizontalScrollIndicator={false} 
                contentContainerStyle={s.kpiScrollContent}
                decelerationRate="fast"
                snapToInterval={SCREEN_WIDTH * 0.45 + 12}
            >
                <View style={s.statCardContainer}>
                    <BlurView intensity={25} tint="dark" style={[s.statCard, { borderColor: 'rgba(59,130,246,0.3)' }]}>
                        <View style={s.kpiTopRow}>
                            <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(59,130,246,0.15)' }]}><Icon name="currency-try" size={20} color="#60A5FA" /></View>
                        </View>
                        <Text style={s.kpiValue} numberOfLines={1} adjustsFontSizeToFit>₺{fmtMoney(kpi.totalCost)}</Text>
                        <Text style={s.kpiLabel}>Toplam Tutar</Text>
                    </BlurView>
                </View>

                <View style={s.statCardContainer}>
                    <BlurView intensity={25} tint="dark" style={[s.statCard, { borderColor: 'rgba(16,185,129,0.3)' }]}>
                        <View style={s.kpiTopRow}>
                            <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(16,185,129,0.15)' }]}><Icon name="water-outline" size={20} color="#34D399" /></View>
                        </View>
                        <Text style={s.kpiValue} numberOfLines={1} adjustsFontSizeToFit>{fmtKm(kpi.totalLiters)} L</Text>
                        <Text style={s.kpiLabel}>Toplam Litre</Text>
                    </BlurView>
                </View>

                <View style={s.statCardContainer}>
                    <BlurView intensity={25} tint="dark" style={[s.statCard, { borderColor: 'rgba(168,85,247,0.3)' }]}>
                        <View style={s.kpiTopRow}>
                            <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(168,85,247,0.15)' }]}><Icon name="file-document-outline" size={20} color="#C084FC" /></View>
                        </View>
                        <Text style={s.kpiValue} numberOfLines={1} adjustsFontSizeToFit>{kpi.count}</Text>
                        <Text style={s.kpiLabel}>Kayıt Adedi</Text>
                    </BlurView>
                </View>

                <View style={s.statCardContainer}>
                    <BlurView intensity={25} tint="dark" style={[s.statCard, { borderColor: kpi.debt < 0 ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' }]}>
                        <View style={s.kpiTopRow}>
                            <View style={[s.kpiIconWrap, { backgroundColor: kpi.debt < 0 ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)' }]}><Icon name="bank-outline" size={20} color={kpi.debt < 0 ? '#34D399' : '#F87171'} /></View>
                        </View>
                        <Text style={s.kpiValue} numberOfLines={1} adjustsFontSizeToFit>{kpi.debt < 0 ? '-' : ''}₺{fmtKm(Math.abs(kpi.debt))}</Text>
                        <Text style={s.kpiLabel}>İstasyon Borcu</Text>
                    </BlurView>
                </View>
            </Animated.ScrollView>
        </View>
    );

    const renderCard = ({ item, index }) => {
        const animIndex = index % 100;
        const flipAnim = flipAnims[animIndex] || new Animated.Value(1);
        const stationName = item.station?.name || item.station_name || 'Bilinmiyor';

        const animatedStyle = {
            opacity: flipAnim,
            transform: [
                { perspective: 1000 },
                { rotateX: flipAnim.interpolate({ inputRange: [0, 1], outputRange: ['-90deg', '0deg'] }) },
                { translateY: flipAnim.interpolate({ inputRange: [0, 1], outputRange: [50, 0] }) },
                { scale: flipAnim.interpolate({ inputRange: [0, 0.6, 1], outputRange: [0.8, 1.05, 1] }) }
            ]
        };

        return (
            <Animated.View style={[s.cardWrapper, animatedStyle]}>
                <BlurView intensity={25} tint="dark" style={[s.card, { borderLeftWidth: 4, borderLeftColor: item.is_paid ? '#10B981' : '#F87171' }]}>
                    <View style={s.cardHeader}>
                        {/* Top Row: Plate and Amount */}
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                            <View style={s.plateBadge}>
                                <Text style={s.plateText}>{item.vehicle?.plate || '?'}</Text>
                            </View>
                            <Text style={[s.amountText, { color: item.is_paid ? '#34D399' : '#F87171' }]}>₺{fmtKm(item.gross_total_cost || item.total_cost)}</Text>
                        </View>
                        
                        {/* Bottom Row: Station Name, Date and Actions */}
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end' }}>
                            <View style={s.cardInfo}>
                                <Text style={s.cardTitle} numberOfLines={2}>{stationName}</Text>
                                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 6 }}>
                                    <Icon name="calendar-blank" size={14} color="#94A3B8" />
                                    <Text style={s.cardDesc}>{dayjs(item.date).format('DD.MM.YYYY')}</Text>
                                </View>
                            </View>
                            <View style={{ flexDirection: 'row', gap: 12, paddingBottom: 4 }}>
                                {hasPermission('fuels.edit') && (
                                    <TouchableOpacity onPress={() => navigation.navigate('FuelForm', { id: item.id })}>
                                        <Icon name="pencil" size={22} color="#60A5FA" />
                                    </TouchableOpacity>
                                )}
                                {hasPermission('fuels.delete') && (
                                    <TouchableOpacity onPress={() => confirmDelete(item)}>
                                        <Icon name="trash-can" size={22} color="#F87171" />
                                    </TouchableOpacity>
                                )}
                            </View>
                        </View>
                    </View>

                    <View style={s.cardGrid}>
                        <View style={s.gridRow}>
                            <View style={s.gridCol}>
                                <Text style={s.gridLabel}>KİLOMETRE</Text>
                                <Text style={s.gridValue}>{item.km ? fmtKm(item.km) : '-'}</Text>
                            </View>
                            <View style={s.gridDivider} />
                            <View style={s.gridCol}>
                                <Text style={[s.gridLabel, { color: '#FBBF24' }]}>KM FARK</Text>
                                <Text style={[s.gridValue, { color: '#FCD34D' }]}>{item.km_diff}</Text>
                            </View>
                            <View style={s.gridDivider} />
                            <View style={s.gridCol}>
                                <Text style={[s.gridLabel, { color: '#60A5FA' }]}>LİTRE</Text>
                                <Text style={[s.gridValue, { color: '#93C5FD' }]}>{parseFloat(item.liters).toFixed(2)} L</Text>
                            </View>
                        </View>
                        <View style={s.gridHDivider} />
                        <View style={s.gridRow}>
                            <View style={s.gridCol}>
                                <Text style={s.gridLabel}>BİRİM FİYAT</Text>
                                <Text style={s.gridValue}>₺{parseFloat(item.price_per_liter).toFixed(2)}</Text>
                            </View>
                            <View style={s.gridDivider} />
                            <View style={s.gridCol}>
                                <Text style={s.gridLabel}>YAKIT TÜRÜ</Text>
                                <Text style={s.gridValue}>{item.fuel_type}</Text>
                            </View>
                            <View style={s.gridDivider} />
                            <View style={s.gridCol}>
                                <Text style={[s.gridLabel, { color: '#34D399' }]}>KM/L</Text>
                                <Text style={[s.gridValue, { color: '#6EE7B7' }]}>{item.km_per_liter}</Text>
                            </View>
                        </View>
                    </View>
                </BlurView>
            </Animated.View>
        );
    };

    return (
        <View style={s.container}>
            {/* 3D Animated Background */}
            <Animated.View style={[StyleSheet.absoluteFill, { transform: [{ translateY: scrollY.interpolate({ inputRange: [-100, 0, 500], outputRange: [-20, 0, 100], extrapolate: 'clamp' }) }] }]}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[s.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 60] }) }, { scale: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.25] }) }] }]} />
                <Animated.View style={[s.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -60] }) }, { scale: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.3] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                {/* Header */}
                <View style={s.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.headerIconBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={26} color="#FFF" />
                    </TouchableOpacity>
                    <View style={{ flex: 1, paddingLeft: 16 }}>
                        <Text style={s.headerTitle}>Yakıt Kayıtları</Text>
                    </View>
                </View>

                {/* Filter Modal Overlay */}
                <Modal visible={showFilters} transparent animationType="slide">
                    <BlurView intensity={40} tint="dark" style={s.modalOverlay}>
                        <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setShowFilters(false)} />
                        <BlurView intensity={50} tint="dark" style={[s.bottomSheet, { paddingBottom: Math.max(insets.bottom, 20) }]}>
                            <View style={s.sheetHandle} />
                            <Text style={s.sheetTitle}>Kayıtları Filtrele</Text>
                            
                            <View style={s.fieldWrap}>
                                <Icon name="magnify" size={20} color="#94A3B8" style={{ marginRight: 10 }} />
                                <TextInput style={s.fieldInput} placeholderTextColor="#64748B" placeholder="Plaka veya İstasyon Ara..." value={searchQuery} onChangeText={setSearchQuery} />
                            </View>

                            <View style={{ flexDirection: 'row', gap: 12, marginTop: 12 }}>
                                <TouchableOpacity style={[s.fieldWrap, { flex: 1 }]} onPress={() => setShowStartPicker(true)}>
                                    <Icon name="calendar-start" size={20} color="#94A3B8" style={{ marginRight: 10 }} />
                                    <Text style={[s.fieldInput, !startDate && { color: '#64748B' }, { paddingTop: 16 }]}>{startDate ? dayjs(startDate).format('DD.MM.YY') : 'Başlangıç'}</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={[s.fieldWrap, { flex: 1 }]} onPress={() => setShowEndPicker(true)}>
                                    <Icon name="calendar-end" size={20} color="#94A3B8" style={{ marginRight: 10 }} />
                                    <Text style={[s.fieldInput, !endDate && { color: '#64748B' }, { paddingTop: 16 }]}>{endDate ? dayjs(endDate).format('DD.MM.YY') : 'Bitiş'}</Text>
                                </TouchableOpacity>
                            </View>

                            <View style={s.formActions}>
                                <TouchableOpacity style={s.cancelBtn} onPress={handleClearFilters}>
                                    <Text style={s.cancelBtnText}>Temizle</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={s.saveBtn} onPress={handleFilterSubmit}>
                                    <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />
                                    <Text style={s.saveBtnText}>Uygula</Text>
                                </TouchableOpacity>
                            </View>
                        </BlurView>
                    </BlurView>
                </Modal>

                {/* Date Pickers */}
                {(showStartPicker || showEndPicker) && (
                    <DateTimePicker 
                        value={showStartPicker ? (startDate || new Date()) : (endDate || new Date())} 
                        mode="date" display="default" themeVariant="dark"
                        onChange={(e, selected) => {
                            setShowStartPicker(false); setShowEndPicker(false);
                            if (selected) {
                                if (showStartPicker) setStartDate(selected);
                                else setEndDate(selected);
                            }
                        }} 
                    />
                )}

                {/* List */}
                {loading ? (
                    <View style={s.loader}><ActivityIndicator size="large" color="#60A5FA" /></View>
                ) : (
                    <Animated.FlatList
                        onScroll={Animated.event([{ nativeEvent: { contentOffset: { y: scrollY } } }], { useNativeDriver: true })}
                        scrollEventThrottle={16}
                        data={displayedFuels}
                        keyExtractor={item => item.id.toString()}
                        renderItem={renderCard}
                        ListHeaderComponent={renderHeader}
                        contentContainerStyle={{ paddingHorizontal: 20, paddingBottom: 100, paddingTop: 10 }}
                        showsVerticalScrollIndicator={false}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchData(true)} tintColor="#60A5FA" />}
                        ListEmptyComponent={
                            <View style={s.empty}>
                                <Image source={emoji('Travel and places/Fuel Pump')} style={{width: 64, height: 64, opacity: 0.8}} resizeMode="contain" />
                                <Text style={s.emptyText}>Yakıt kaydı bulunamadı.</Text>
                            </View>
                        }
                    />
                )}

            </SafeAreaView>

            {/* FAB */}
            {hasPermission('fuels.create') && (
                <TouchableOpacity style={[s.fab, { bottom: Math.max(insets.bottom, 24) }]} onPress={() => navigation.navigate('FuelForm')}>
                    <LinearGradient colors={['#8B5CF6', '#4F46E5']} style={s.fabGradient} start={{x:0, y:0}} end={{x:1, y:1}}>
                        <Icon name="gas-station-outline" size={24} color="#FFF" />
                        <Icon name="plus" size={14} color="#FFF" style={{ position: 'absolute', top: 12, right: 12 }} />
                    </LinearGradient>
                </TouchableOpacity>
            )}
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -100, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(59, 130, 246, 0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(16, 185, 129, 0.12)', filter: 'blur(40px)' },
    
    header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingTop: 10, paddingBottom: 16 },
    headerIconBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 26, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5, textShadowColor: 'rgba(255,255,255,0.3)', textShadowOffset: { width: 0, height: 0 }, textShadowRadius: 10 },
    
    actionsRow: { flexDirection: 'row', gap: 10, paddingHorizontal: 20, marginBottom: 16 },
    actionBtn: { flex: 1, height: 44, borderRadius: 14, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', flexDirection: 'row' },

    kpiScrollContent: { paddingHorizontal: 20, paddingBottom: 16, gap: 12 },
    statCardContainer: { width: SCREEN_WIDTH * 0.45 },
    statCard: { padding: 16, borderRadius: 20, overflow: 'hidden', borderWidth: 1 },
    kpiTopRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 12 },
    kpiIconWrap: { width: 34, height: 34, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
    kpiValue: { fontSize: 18, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5, marginBottom: 4 },
    kpiLabel: { fontSize: 12, color: '#94A3B8', fontWeight: '600' },

    loader: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingTop: 100 },
    empty: { alignItems: 'center', marginTop: 80 },
    emptyText: { fontSize: 15, color: '#94A3B8', fontWeight: '600', marginTop: 12 },

    cardWrapper: { marginBottom: 16 },
    card: { borderRadius: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    cardHeader: { padding: 20, paddingBottom: 16 },
    plateBadge: { backgroundColor: 'rgba(59,130,246,0.15)', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 12, borderWidth: 1, borderColor: 'rgba(59,130,246,0.3)' },
    plateText: { fontSize: 15, fontWeight: '900', color: '#60A5FA', letterSpacing: 1 },
    cardInfo: { flex: 1, paddingRight: 16 },
    cardTitle: { fontSize: 16, fontWeight: '800', color: '#F8FAFC', letterSpacing: -0.2, lineHeight: 22 },
    cardDesc: { fontSize: 13, color: '#94A3B8', fontWeight: '600' },
    amountText: { fontSize: 20, fontWeight: '900' },

    cardGrid: { backgroundColor: 'rgba(0,0,0,0.2)', padding: 16, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
    gridRow: { flexDirection: 'row' },
    gridCol: { flex: 1 },
    gridDivider: { width: 1, backgroundColor: 'rgba(255,255,255,0.1)', marginHorizontal: 12 },
    gridHDivider: { height: 1, backgroundColor: 'rgba(255,255,255,0.1)', marginVertical: 12 },
    gridLabel: { fontSize: 10, fontWeight: '800', color: '#64748B', letterSpacing: 0.5, marginBottom: 4 },
    gridValue: { fontSize: 13, fontWeight: '800', color: '#CBD5E1' },

    modalOverlay: { flex: 1, justifyContent: 'flex-end' },
    bottomSheet: { borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginBottom: 20 },
    sheetTitle: { fontSize: 22, fontWeight: '900', color: '#F8FAFC', marginBottom: 24 },
    
    fieldWrap: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.2)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 14, paddingHorizontal: 16, height: 54 },
    fieldInput: { flex: 1, fontSize: 15, color: '#F8FAFC', fontWeight: '600', height: '100%' },
    
    formActions: { flexDirection: 'row', marginTop: 24 },
    cancelBtn: { flex: 1, paddingVertical: 16, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', marginRight: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    cancelBtnText: { color: '#94A3B8', fontSize: 15, fontWeight: '800' },
    saveBtn: { flex: 2, borderRadius: 16, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
    saveBtnText: { color: '#FFF', fontSize: 15, fontWeight: '900' },

    fab: { position: 'absolute', right: 20, borderRadius: 28, shadowColor: '#8B5CF6', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.5, shadowRadius: 10, elevation: 8 },
    fabGradient: { width: 56, height: 56, borderRadius: 28, alignItems: 'center', justifyContent: 'center' },
});
