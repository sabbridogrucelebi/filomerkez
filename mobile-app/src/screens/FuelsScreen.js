import React, { useState, useEffect, useContext, useRef, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Animated, PanResponder, Dimensions, TextInput, Image } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect } from '@react-navigation/native';
import DateTimePicker from '@react-native-community/datetimepicker';
import dayjs from 'dayjs';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

// Custom Swipeable Row
const SwipeableRow = ({ children, onEdit, onDelete, hasEditPerm, hasDeletePerm }) => {
    const pan = useRef(new Animated.ValueXY()).current;
    
    const panResponder = useRef(
        PanResponder.create({
            onMoveShouldSetPanResponder: (e, gestureState) => Math.abs(gestureState.dx) > 15 && Math.abs(gestureState.dx) > Math.abs(gestureState.dy),
            onPanResponderMove: (e, gestureState) => {
                if (gestureState.dx < 0 && gestureState.dx > -160) {
                    pan.setValue({ x: gestureState.dx, y: 0 });
                }
            },
            onPanResponderRelease: (e, gestureState) => {
                if (gestureState.dx < -60) {
                    Animated.spring(pan, { toValue: { x: -140, y: 0 }, useNativeDriver: true, tension: 40, friction: 5 }).start();
                } else {
                    Animated.spring(pan, { toValue: { x: 0, y: 0 }, useNativeDriver: true, tension: 40, friction: 5 }).start();
                }
            }
        })
    ).current;

    const close = () => {
        Animated.spring(pan, { toValue: { x: 0, y: 0 }, useNativeDriver: true }).start();
    };

    return (
        <View style={s.swipeContainer}>
            <View style={s.actionButtons}>
                {hasEditPerm && (
                    <TouchableOpacity style={[s.actionBtn, { backgroundColor: '#3B82F6' }]} onPress={() => { close(); onEdit(); }}>
                        <Icon name="pencil" size={24} color="#FFF" />
                    </TouchableOpacity>
                )}
                {hasDeletePerm && (
                    <TouchableOpacity style={[s.actionBtn, { backgroundColor: '#EF4444' }]} onPress={() => { close(); onDelete(); }}>
                        <Icon name="delete" size={24} color="#FFF" />
                    </TouchableOpacity>
                )}
            </View>
            <Animated.View style={[s.swipeContent, { transform: [{ translateX: pan.x }] }]} {...panResponder.panHandlers}>
                {children}
            </Animated.View>
        </View>
    );
};

export default function FuelsScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    
    const [rawFuels, setRawFuels] = useState([]);
    const [processedFuels, setProcessedFuels] = useState([]);
    const [displayedFuels, setDisplayedFuels] = useState([]);
    
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    
    // Filters
    const [searchQuery, setSearchQuery] = useState('');
    const [startDate, setStartDate] = useState(null);
    const [endDate, setEndDate] = useState(null);
    const [showStartPicker, setShowStartPicker] = useState(false);
    const [showEndPicker, setShowEndPicker] = useState(false);

    // KPIs
    const [kpi, setKpi] = useState({ totalCost: 0, totalLiters: 0, count: 0, debt: 0 });

    const processFuels = (data) => {
        // Sort ascending by date to calculate KM differences accurately
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

        // Re-sort descending for the list display
        return finalData.sort((a, b) => new Date(b.date) - new Date(a.date));
    };

    const fetchData = async (hideLoader = false) => {
        if (!hideLoader) setLoading(true);
        try {
            const res = await api.get('/v1/fuels');
            const data = res.data.data.fuels || [];
            const apiDebt = res.data.data.total_debt || 0;
            setRawFuels(data);
            
            const pFuels = processFuels(data);
            setProcessedFuels(pFuels);
            applyFilters(pFuels, searchQuery, startDate, endDate, apiDebt);

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
        
        // Update KPIs based on filtered data
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
    };

    const handleClearFilters = () => {
        setSearchQuery('');
        setStartDate(null);
        setEndDate(null);
        applyFilters(processedFuels, '', null, null);
    };

    useFocusEffect(useCallback(() => { fetchData(true); }, []));

    const confirmDelete = (item) => {
        import('react-native').then(({ Alert }) => {
            Alert.alert('Silme Onayı', `${dayjs(item.date).format('DD.MM.YYYY')} tarihli yakıt kaydını silmek istediğinize emin misiniz?`, [
                { text: 'Vazgeç', style: 'cancel' },
                { 
                    text: 'Sil', 
                    style: 'destructive', 
                    onPress: async () => {
                        try {
                            await api.delete(`/v1/fuels/${item.id}`);
                            fetchData(true);
                        } catch (e) {
                            Alert.alert('Hata', 'Kayıt silinemedi.');
                        }
                    }
                }
            ]);
        });
    };

    const formatCurrency = (val) => {
        return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
    };

    const renderDataRow = ({ item }) => {
        const stationName = item.station?.name || item.station_name || 'Bilinmiyor';
        
        return (
            <SwipeableRow 
                onEdit={() => navigation.navigate('FuelForm', { id: item.id })} 
                onDelete={() => confirmDelete(item)}
                hasEditPerm={hasPermission('fuels.edit')}
                hasDeletePerm={hasPermission('fuels.delete')}
            >
                <LinearGradient 
                    colors={['#FFFFFF', '#F8FAFC']} 
                    start={{x:0, y:0}} 
                    end={{x:0, y:1}} 
                    style={[s.dataRow, { borderLeftWidth: 4, borderLeftColor: item.is_paid ? '#10B981' : '#EF4444' }]}
                >
                    <View style={s.rowTop}>
                        <Text style={s.colDate}>{dayjs(item.date).format('DD.MM.YY')}</Text>
                        <Text style={s.colPlate} numberOfLines={1}>{item.vehicle?.plate || '-'}</Text>
                        <Text style={s.colStation} numberOfLines={1}>{stationName}</Text>
                        <Text style={[s.colTotal, { color: item.is_paid ? '#10B981' : '#EF4444' }]}>₺{formatCurrency(item.gross_total_cost || item.total_cost)}</Text>
                    </View>
                    <View style={s.rowDivider} />
                    <View style={s.rowMid}>
                        <View style={s.dataCell}><Text style={s.cellLabel}>KM</Text><Text style={s.cellVal}>{item.km || '-'}</Text></View>
                        <View style={s.dataCell}><Text style={s.cellLabel}>Fark</Text><Text style={s.cellVal}>{item.km_diff}</Text></View>
                        <View style={s.dataCell}><Text style={s.cellLabel}>Tür</Text><Text style={s.cellVal}>{item.fuel_type}</Text></View>
                        <View style={s.dataCell}><Text style={s.cellLabel}>Litre</Text><Text style={s.cellVal}>{parseFloat(item.liters).toFixed(2)}</Text></View>
                        <View style={s.dataCell}><Text style={s.cellLabel}>Fiyat</Text><Text style={s.cellVal}>₺{parseFloat(item.price_per_liter).toFixed(2)}</Text></View>
                        <View style={s.dataCell}><Text style={s.cellLabel}>KM/L</Text><Text style={s.cellVal}>{item.km_per_liter}</Text></View>
                    </View>
                </LinearGradient>
            </SwipeableRow>
        );
    };

    if (loading && !refreshing) {
        return (
            <SafeAreaView style={s.container} edges={['top']}>
                <View style={s.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                        <Icon name="chevron-left" size={26} color="#0F172A" />
                    </TouchableOpacity>
                    <Text style={s.headerTitle}>Yakıt Kayıt Listesi</Text>
                    <View style={{width: 40}} />
                </View>
                <ActivityIndicator size="large" color="#3B82F6" style={{ marginTop: 100 }} />
            </SafeAreaView>
        );
    }

    return (
        <SafeAreaView style={s.container} edges={['top']}>
            <View style={s.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                    <Icon name="chevron-left" size={26} color="#0F172A" />
                </TouchableOpacity>
                <Text style={s.headerTitle}>Yakıt Kayıt Listesi</Text>
                <TouchableOpacity 
                    onPress={() => navigation.navigate('FuelStations')}
                    style={s.stationsBtn}
                >
                    <Icon name="gas-station" size={20} color="#3B82F6" />
                </TouchableOpacity>
            </View>

            {/* 2x2 Dense KPI Grid (3D Premium) */}
            <View style={s.kpiGrid}>
                <View style={[s.statCardContainer, { width: '48%' }]}>
                    <LinearGradient colors={['#3B82F6', '#2563EB']} start={{x:0, y:0}} end={{x:1, y:1}} style={s.statCard}>
                        <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Money%20Bag.png' }} style={[s.statCardBgIcon, { width: 70, height: 70, opacity: 1 }]} resizeMode="contain" />
                        <View style={s.statCardInner}>
                            <Text style={s.statCardLabel}>Toplam Tutar</Text>
                            <Text style={s.statCardVal} numberOfLines={1}>₺{formatCurrency(kpi.totalCost)}</Text>
                        </View>
                    </LinearGradient>
                </View>

                <View style={[s.statCardContainer, { width: '48%' }]}>
                    <LinearGradient colors={['#10B981', '#059669']} start={{x:0, y:0}} end={{x:1, y:1}} style={s.statCard}>
                        <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Fuel%20Pump.png' }} style={[s.statCardBgIcon, { width: 70, height: 70, opacity: 1 }]} resizeMode="contain" />
                        <View style={s.statCardInner}>
                            <Text style={s.statCardLabel}>Toplam Litre</Text>
                            <Text style={s.statCardVal} numberOfLines={1}>{formatCurrency(kpi.totalLiters)}</Text>
                        </View>
                    </LinearGradient>
                </View>

                <View style={[s.statCardContainer, { width: '48%' }]}>
                    <LinearGradient colors={['#8B5CF6', '#6D28D9']} start={{x:0, y:0}} end={{x:1, y:1}} style={s.statCard}>
                        <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Page%20with%20Curl.png' }} style={[s.statCardBgIcon, { width: 70, height: 70, opacity: 1 }]} resizeMode="contain" />
                        <View style={s.statCardInner}>
                            <Text style={s.statCardLabel}>Kayıt Adedi</Text>
                            <Text style={s.statCardVal} numberOfLines={1}>{kpi.count}</Text>
                        </View>
                    </LinearGradient>
                </View>

                <View style={[s.statCardContainer, { width: '48%' }]}>
                    <LinearGradient colors={kpi.debt < 0 ? ['#10B981', '#059669'] : ['#EC4899', '#BE185D']} start={{x:0, y:0}} end={{x:1, y:1}} style={s.statCard}>
                        <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Bank.png' }} style={[s.statCardBgIcon, { width: 70, height: 70, opacity: 1 }]} resizeMode="contain" />
                        <View style={s.statCardInner}>
                            <Text style={s.statCardLabel}>İstasyon Borcu</Text>
                            <Text style={s.statCardVal} numberOfLines={1}>{kpi.debt < 0 ? '-' : ''}₺{formatCurrency(Math.abs(kpi.debt))}</Text>
                        </View>
                    </LinearGradient>
                </View>
            </View>

            {/* Filter Section */}
            <View style={s.filterContainer}>
                <View style={s.filterRow}>
                    <TouchableOpacity style={s.dateBtn} onPress={() => setShowStartPicker(true)}>
                        <Icon name="calendar-start" size={16} color="#64748B" />
                        <Text style={s.dateBtnText}>{startDate ? dayjs(startDate).format('DD.MM.YY') : 'Başlangıç'}</Text>
                    </TouchableOpacity>
                    <View style={{ width: 8 }} />
                    <TouchableOpacity style={s.dateBtn} onPress={() => setShowEndPicker(true)}>
                        <Icon name="calendar-end" size={16} color="#64748B" />
                        <Text style={s.dateBtnText}>{endDate ? dayjs(endDate).format('DD.MM.YY') : 'Bitiş'}</Text>
                    </TouchableOpacity>
                </View>
                
                <View style={s.searchRow}>
                    <View style={s.searchWrap}>
                        <Icon name="magnify" size={20} color="#94A3B8" style={{ marginLeft: 12 }} />
                        <TextInput 
                            style={s.searchInput}
                            placeholder="Plaka / İstasyon Ara..."
                            placeholderTextColor="#94A3B8"
                            value={searchQuery}
                            onChangeText={setSearchQuery}
                            returnKeyType="search"
                            onSubmitEditing={handleFilterSubmit}
                        />
                    </View>
                    <TouchableOpacity style={s.filterBtn} onPress={handleFilterSubmit}>
                        <Text style={s.filterBtnText}>Filtrele</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={s.clearBtn} onPress={handleClearFilters}>
                        <Icon name="close" size={20} color="#64748B" />
                    </TouchableOpacity>
                </View>
            </View>

            {(showStartPicker || showEndPicker) && (
                <DateTimePicker 
                    value={showStartPicker ? (startDate || new Date()) : (endDate || new Date())} 
                    mode="date" 
                    display="default" 
                    onChange={(e, selected) => {
                        setShowStartPicker(false);
                        setShowEndPicker(false);
                        if (selected) {
                            if (showStartPicker) setStartDate(selected);
                            else setEndDate(selected);
                        }
                    }} 
                />
            )}

            <FlatList
                data={displayedFuels}
                keyExtractor={item => item.id.toString()}
                renderItem={renderDataRow}
                contentContainerStyle={s.listContent}
                showsVerticalScrollIndicator={false}
                refreshing={refreshing}
                onRefresh={() => { setRefreshing(true); fetchData(false); }}
                ListEmptyComponent={
                    <View style={s.empty}>
                        <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Fuel%20Pump.png' }} style={{ width: 64, height: 64, opacity: 0.7 }} resizeMode="contain" />
                        <Text style={s.emptyText}>Kriterlere uygun kayıt bulunamadı.</Text>
                    </View>
                }
            />

            {hasPermission('fuels.create') && (
                <TouchableOpacity 
                    style={s.fab} 
                    activeOpacity={0.8}
                    onPress={() => navigation.navigate('FuelForm')}
                >
                    <LinearGradient colors={['#3B82F6', '#2563EB']} style={s.fabGradient}>
                        <Icon name="plus" size={28} color="#FFF" />
                    </LinearGradient>
                </TouchableOpacity>
            )}
        </SafeAreaView>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F1F5F9' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingTop: 10, paddingBottom: 15 },
    backBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#FFF', alignItems: 'center', justifyContent: 'center', shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 4, elevation: 2 },
    headerTitle: { fontSize: 18, fontWeight: '900', color: '#0F172A' },
    stationsBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#EFF6FF', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#BFDBFE' },
    
    // 3D Premium KPI Grid
    kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: 16, justifyContent: 'space-between', rowGap: 12, paddingBottom: 16 },
    statCardContainer: { 
        height: 85, 
        borderRadius: 20,
        shadowColor: '#000', 
        shadowOffset: { width: 0, height: 6 }, 
        shadowOpacity: 0.25, 
        shadowRadius: 10, 
        elevation: 8,
        backgroundColor: '#fff' 
    },
    statCard: { 
        flex: 1, 
        borderRadius: 20, 
        overflow: 'hidden',
        borderTopWidth: 1.5,
        borderTopColor: 'rgba(255,255,255,0.4)',
        borderBottomWidth: 4,
        borderBottomColor: 'rgba(0,0,0,0.2)',
        borderLeftWidth: 0.5,
        borderRightWidth: 0.5,
        borderColor: 'rgba(0,0,0,0.1)'
    },
    statCardBgIcon: { position: 'absolute', right: -10, bottom: -10, transform: [{ rotate: '-15deg' }] },
    statCardInner: { flex: 1, padding: 12, justifyContent: 'center', backgroundColor: 'rgba(255,255,255,0.05)' },
    statCardLabel: { fontSize: 11, color: 'rgba(255,255,255,0.9)', fontWeight: '800', letterSpacing: 0.5, textShadowColor: 'rgba(0,0,0,0.2)', textShadowOffset: { width: 0, height: 1 }, textShadowRadius: 2, marginBottom: 4 },
    statCardVal: { fontSize: 18, color: '#FFF', fontWeight: '900', textShadowColor: 'rgba(0,0,0,0.2)', textShadowOffset: { width: 0, height: 2 }, textShadowRadius: 4 },

    // Filters
    filterContainer: { paddingHorizontal: 16, paddingBottom: 12 },
    filterRow: { flexDirection: 'row', marginBottom: 8 },
    dateBtn: { flex: 1, flexDirection: 'row', backgroundColor: '#FFF', height: 38, borderRadius: 8, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#E2E8F0' },
    dateBtnText: { fontSize: 13, color: '#334155', fontWeight: '600', marginLeft: 6 },
    
    searchRow: { flexDirection: 'row', alignItems: 'center' },
    searchWrap: { flex: 1, flexDirection: 'row', backgroundColor: '#FFF', height: 40, borderRadius: 8, alignItems: 'center', borderWidth: 1, borderColor: '#E2E8F0' },
    searchInput: { flex: 1, height: '100%', paddingHorizontal: 10, fontSize: 13, color: '#0F172A' },
    filterBtn: { backgroundColor: '#3B82F6', height: 40, paddingHorizontal: 16, borderRadius: 8, alignItems: 'center', justifyContent: 'center', marginLeft: 8 },
    filterBtnText: { color: '#FFF', fontSize: 13, fontWeight: '700' },
    clearBtn: { backgroundColor: '#FFF', height: 40, width: 40, borderRadius: 8, alignItems: 'center', justifyContent: 'center', marginLeft: 8, borderWidth: 1, borderColor: '#E2E8F0' },

    // List
    listContent: { paddingHorizontal: 16, paddingBottom: 100 },
    empty: { alignItems: 'center', marginTop: 40 },
    emptyText: { fontSize: 14, color: '#94A3B8', fontWeight: '600', marginTop: 12 },

    // Swipe & Rows
    swipeContainer: { 
        marginBottom: 16, 
        borderRadius: 16, 
        backgroundColor: '#FFF',
        shadowColor: '#3B82F6',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.1,
        shadowRadius: 10,
        elevation: 4,
    },
    actionButtons: { position: 'absolute', right: 0, top: 0, bottom: 0, flexDirection: 'row', width: 140 },
    actionBtn: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    swipeContent: { backgroundColor: 'transparent', borderRadius: 16 },

    // Data Row (Dense Table - 3D Premium)
    dataRow: { 
        padding: 16, 
        borderRadius: 16,
        borderWidth: 1,
        borderColor: '#E2E8F0',
        borderBottomWidth: 4,
    },
    rowTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
    colDate: { width: 60, fontSize: 12, color: '#64748B', fontWeight: '600' },
    colPlate: { width: 75, fontSize: 13, color: '#0F172A', fontWeight: '800' },
    colStation: { flex: 1, fontSize: 12, color: '#475569', fontWeight: '500', marginHorizontal: 4 },
    colTotal: { fontSize: 14, color: '#10B981', fontWeight: '800', textAlign: 'right' },
    
    rowDivider: { height: 1, backgroundColor: '#F1F5F9', marginVertical: 8 },
    
    rowMid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between' },
    dataCell: { width: '31%', marginBottom: 6 },
    cellLabel: { fontSize: 10, color: '#94A3B8', fontWeight: '600', textTransform: 'uppercase', marginBottom: 2 },
    cellVal: { fontSize: 12, color: '#334155', fontWeight: '700' },

    fab: { position: 'absolute', right: 20, bottom: 24, borderRadius: 28, shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 6 }, shadowopacity: 1, shadowRadius: 10, elevation: 8 },
    fabGradient: { width: 56, height: 56, borderRadius: 28, alignItems: 'center', justifyContent: 'center' },
});
