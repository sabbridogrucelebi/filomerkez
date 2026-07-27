import React, { useState, useCallback, useEffect, useRef, useContext } from 'react';
import { AuthContext } from '../context/AuthContext';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, RefreshControl, Modal, TextInput, Alert, KeyboardAvoidingView, Platform, ScrollView, Image, Animated, Easing, Dimensions, Linking } from 'react-native';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');


import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect } from '@react-navigation/native';
import api from '../api/axios';
import SpaceWaves from '../components/SpaceWaves';

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
    
    const [modalVisible, setModalVisible] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [actionItem, setActionItem] = useState(null); 
    const emptyForm = { plate:'', brand:'', model:'', vehicle_type:'', model_year:'', current_km:'', engine_no:'', chassis_no:'', fuel_type:'', color:'', seat_count:'', is_active:true };
    const [formData, setFormData] = useState(emptyForm);

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedSearch(searchQuery);
        }, 500);
        return () => clearTimeout(handler);
    }, [searchQuery]);

    const fetchVehicles = async (pageNumber = 1) => {
        if (pageNumber === 1 && !refreshing) setLoading(true);
        else if (pageNumber > 1) setLoadingMore(true);

        try {
            const params = { page: pageNumber, per_page: 100 };
            if (statusFilter !== 'all') params.status = statusFilter;
            if (kpiFilter) params.filter = kpiFilter;
            if (debouncedSearch) params.search = debouncedSearch;

            const r = await api.get('/v1/vehicles', { params });
            const newVehicles = r.data.data.vehicles || [];
            
            if (pageNumber === 1) setVehicles(newVehicles);
            else setVehicles(prev => [...prev, ...newVehicles]);

            setKpi(r.data.data.kpi || { total:0, upcoming_inspection:0, upcoming_insurance:0, active_count:0 });
            
            const meta = r.data.meta;
            if (meta) {
                setHasMore(meta.current_page < meta.last_page);
                setPage(meta.current_page);
            } else {
                setHasMore(false);
            }
        } catch(e) { 
            if (e.response && e.response.status === 403) {
                Alert.alert("Erişim Engellendi", "Bu alanı görüntüleme yetkiniz yok.");
            } else if (!e.response) {
                // Network Error
                Alert.alert("Bağlantı Hatası", "Sunucuya ulaşılamıyor. Lütfen API URL (IP adresi) ayarlarınızı kontrol edin.");
            } else {
                console.error(e); 
            }
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

    const openAdd = () => { setEditingId(null); setFormData(emptyForm); setModalVisible(true); };
    const openEdit = (v) => { setEditingId(v.id); setFormData({ plate:v.plate||'', brand:v.brand||'', model:v.model||'', vehicle_type:v.vehicle_type||'', model_year:v.model_year?String(v.model_year):'', current_km:v.current_km?String(v.current_km):'', engine_no:v.engine_no||'', chassis_no:v.chassis_no||'', fuel_type:v.fuel_type||'', color:v.color||'', seat_count:v.seat_count?String(v.seat_count):'', is_active:v.is_active }); setModalVisible(true); setActionItem(null); };

    const save = async () => {
        if(!formData.plate){Alert.alert('Eksik Bilgi','Araç plakası zorunludur.');return;}
        setSaving(true);
        try { editingId ? await api.put(`/vehicles/${editingId}`,formData) : await api.post('/vehicles',formData); setModalVisible(false); fetchVehicles(1); }
        catch(e){Alert.alert('İşlem Başarısız',e.response?.data?.message||'Sistemsel bir hata oluştu.');}
        finally{setSaving(false);}
    };

    const del = (id,plate) => { setActionItem(null); const go=async()=>{try{await api.delete(`/vehicles/${id}`);fetchVehicles(1);}catch{Alert.alert('İşlem Başarısız','Kayıt silinemedi.');}}; Platform.OS==='web'?window.confirm(`${plate} plakalı aracı silmek istediğinize emin misiniz?`)&&go():Alert.alert('Kayıt Silinecek',`${plate} plakalı aracı silmek istediğinize emin misiniz?`,[{text:'Vazgeç',style:'cancel'},{text:'Sil',style:'destructive',onPress:go}]); };

    const fmtKm = k => (!k||k===0) ? '0 km' : Number(k).toLocaleString('tr-TR')+' km';
    const filterLabel = statusFilter==='active'?'Sadece Aktif':statusFilter==='passive'?'Sadece Pasif':'Tüm Araçlar';

    const getWarningIcon = (item) => {
        const check = (date) => date && (new Date(date) - new Date() < 1000 * 60 * 60 * 24 * 15);
        if (check(item.inspection_date) || check(item.kasko_end_date)) {
            return <Icon name="alert" size={16} color="#F59E0B" style={{marginLeft: 6}} />;
        }
        return null;
    };

    const getVehicleImage = (type) => {
        const t = (type || '').toLowerCase();
        if (t.includes('minibüs')) return require('../../assets/arac_tipleri/servis_pilot_minibus.png');
        if (t.includes('midibüs')) return require('../../assets/arac_tipleri/servis_pilot_midibus.png');
        if (t.includes('otobüs')) return require('../../assets/arac_tipleri/servis_pilot_otobus.png');
        if (t.includes('panelvan')) return require('../../assets/arac_tipleri/servis_pilot_panelvan.png');
        if (t.includes('kamyonet')) return require('../../assets/arac_tipleri/servis_pilot_kamyonet.png');
        if (t.includes('binek') || t.includes('sedan') || t.includes('taksi')) return require('../../assets/arac_tipleri/servis_pilot_taksi.png');
        
        return require('../../assets/arac_tipleri/servis_pilot_panelvan.png');
    };

    const renderRow = ({ item }) => {
        const imgSource = item.image_url ? { uri: item.image_url } : getVehicleImage(item.vehicle_type || item.type);

        const inspectionDays = item.inspection_date ? Math.ceil((new Date(item.inspection_date) - new Date()) / (1000 * 60 * 60 * 24)) : null;
        const showInspectionWarning = kpiFilter === 'upcoming_inspection' && inspectionDays !== null && inspectionDays <= 10;

        const insuranceDays = item.insurance_end_date ? Math.ceil((new Date(item.insurance_end_date) - new Date()) / (1000 * 60 * 60 * 24)) : null;
        const showInsuranceWarning = kpiFilter === 'upcoming_insurance' && insuranceDays !== null && insuranceDays <= 10;

        return (
            <TouchableOpacity style={[s.tableRow, (showInspectionWarning || showInsuranceWarning) && {flexDirection: 'column', alignItems: 'stretch'}]} activeOpacity={0.7} onPress={()=>navigation.navigate('VehicleDetail', { vehicle: item })} onLongPress={()=>setActionItem(item)}>
                
                {/* Main Row Content */}
                <View style={{ flexDirection: 'row', alignItems: 'center', width: '100%' }}>
                    <Image source={imgSource} style={s.rowImage} />
                    
                    <View style={s.rowColMain}>
                        <Text style={s.rowPlate}>{item.plate}</Text>
                        <Text style={s.rowBrand} numberOfLines={1}>{item.brand_model || 'Belirtilmemiş'}</Text>
                    </View>

                    <View style={s.rowColMid}>
                        <View style={s.rowBadge}>
                            <Text style={s.rowBadgeText}>{item.model_year || 'Yıl Yok'}</Text>
                        </View>
                        <View style={[s.statusDotSmall, { backgroundColor: item.status === 'active' ? '#10B981' : '#EF4444' }]} />
                    </View>

                    <View style={s.rowColRight}>
                        <Text style={s.rowDriver} numberOfLines={1}>{item.driver || 'Atanmamış'}</Text>
                        <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                            <Icon name="speedometer" size={12} color="#10B981" style={{ marginRight: 2 }} />
                            <Text style={s.rowKm}>{(!item.current_km || item.current_km === 0) ? '0 km' : `${Number(item.current_km).toLocaleString('tr-TR')} km`}</Text>
                        </View>
                    </View>

                    <Icon name="chevron-right" size={18} color="#CBD5E1" style={{ marginLeft: 4 }} />
                </View>

                {/* Inspection Warning Box */}
                {showInspectionWarning && (
                    <View style={s.warningBox}>
                        <View style={StyleSheet.absoluteFillObject}>
                            <SpaceWaves />
                        </View>
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', zIndex: 1 }}>
                            <View>
                                <Text style={s.warningDateText}>SON TARİH: {new Date(item.inspection_date).toLocaleDateString('tr-TR')}</Text>
                                <Text style={[s.warningDaysText, { color: inspectionDays < 0 ? '#EF4444' : '#FBBF24' }]}>
                                    {inspectionDays < 0 ? `${Math.abs(inspectionDays)} GÜN GEÇTİ` : `${inspectionDays} GÜN KALDI`}
                                </Text>
                            </View>
                            <TouchableOpacity style={s.actionBtn} onPress={() => Linking.openURL('https://www.tuvturk.com.tr/hizmetlerimiz/hizli-islemler/arac-muayene-randevusu-alma')}>
                                <Text style={s.actionBtnText}>RANDEVU AL</Text>
                                <Icon name="open-in-new" size={14} color="#FFF" style={{ marginLeft: 4 }} />
                            </TouchableOpacity>
                        </View>
                    </View>
                )}

                {/* Insurance Warning Box */}
                {showInsuranceWarning && (
                    <View style={s.warningBox}>
                        <View style={StyleSheet.absoluteFillObject}>
                            <SpaceWaves />
                        </View>
                        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', zIndex: 1 }}>
                            <View>
                                <Text style={s.warningDateText}>POLİÇE BİTİŞ: {new Date(item.insurance_end_date).toLocaleDateString('tr-TR')}</Text>
                                <Text style={[s.warningDaysText, { color: insuranceDays < 0 ? '#EF4444' : '#F472B6' }]}>
                                    {insuranceDays < 0 ? `${Math.abs(insuranceDays)} GÜN GEÇTİ` : `${insuranceDays} GÜN KALDI`}
                                </Text>
                            </View>
                        </View>
                    </View>
                )}

            </TouchableOpacity>
        );
    };

    const renderFooter = () => {
        if (!loadingMore) return null;
        return <View style={s.footerLoader}><ActivityIndicator size="small" color="#0F172A" /></View>;
    };

    return (
        <View style={s.container}>
            <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                {/* Header */}
                <View style={s.header}>
                    <View style={{ flex: 1 }}>
                        <Text style={s.headerTitle}>Filo Yönetimi</Text>
                        <Text style={s.headerSub}>Araçlarınızın anlık durumunu takip edin</Text>
                    </View>
                    {hasPermission('vehicles.create') && (
                        <TouchableOpacity style={s.addBtn} onPress={openAdd} activeOpacity={0.8}>
                            <LinearGradient colors={['#3B82F6', '#2563EB']} style={s.addBtnGradient}>
                                <Icon name="plus" size={28} color="#FFF" />
                            </LinearGradient>
                        </TouchableOpacity>
                    )}
                </View>

                {/* KPI Cards */}
                <View style={{ paddingHorizontal: 20, paddingBottom: 16 }}>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
                        <TouchableOpacity 
                            style={[{ flex: 1 }, kpiFilter === null && { transform: [{ scale: 1.02 }] }]} 
                            activeOpacity={0.8} 
                            onPress={() => { setKpiFilter(null); setStatusFilter('all'); }}>
                            <LinearGradient colors={['#0F172A', '#1E293B']} style={[s.kpiCard, kpiFilter === null && s.kpiCardActive]}>
                                <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Oncoming%20Bus.png' }} style={[s.kpiIcon, {width: 24, height: 24}]} resizeMode="contain" />
                                <Text style={s.kpiValue} adjustsFontSizeToFit numberOfLines={1}>{kpi?.total || 0}</Text>
                                <Text style={s.kpiTitle} numberOfLines={1}>Toplam</Text>
                            </LinearGradient>
                        </TouchableOpacity>

                        <TouchableOpacity 
                            style={[{ flex: 1 }, kpiFilter === 'upcoming_inspection' && { transform: [{ scale: 1.02 }] }]} 
                            activeOpacity={0.8} 
                            onPress={() => { setKpiFilter('upcoming_inspection'); setStatusFilter('all'); }}>
                            <LinearGradient colors={['#F59E0B', '#D97706']} style={[s.kpiCard, kpiFilter === 'upcoming_inspection' && s.kpiCardActive]}>
                                <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Symbols/Warning.png' }} style={[s.kpiIcon, {width: 24, height: 24}]} resizeMode="contain" />
                                <Text style={s.kpiValue} adjustsFontSizeToFit numberOfLines={1}>{kpi?.upcoming_inspection || 0}</Text>
                                <Text style={s.kpiTitle} numberOfLines={1}>Muayene</Text>
                            </LinearGradient>
                        </TouchableOpacity>

                        <TouchableOpacity 
                            style={[{ flex: 1 }, kpiFilter === 'upcoming_insurance' && { transform: [{ scale: 1.02 }] }]} 
                            activeOpacity={0.8} 
                            onPress={() => { setKpiFilter('upcoming_insurance'); setStatusFilter('all'); }}>
                            <LinearGradient colors={['#EF4444', '#DC2626']} style={[s.kpiCard, kpiFilter === 'upcoming_insurance' && s.kpiCardActive]}>
                                <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Shield.png' }} style={[s.kpiIcon, {width: 24, height: 24}]} resizeMode="contain" />
                                <Text style={s.kpiValue} adjustsFontSizeToFit numberOfLines={1}>{kpi?.upcoming_insurance || 0}</Text>
                                <Text style={s.kpiTitle} numberOfLines={1}>Sigorta</Text>
                            </LinearGradient>
                        </TouchableOpacity>
                    </View>
                </View>

                {/* Search & Filter */}
                <View style={s.searchContainer}>
                    <View style={s.searchWrap}>
                        <Icon name="magnify" size={22} color="#94A3B8" />
                        <TextInput
                            style={s.searchInput}
                            placeholder="Plaka, marka veya personel ara..."
                            placeholderTextColor="#94A3B8"
                            value={searchQuery}
                            onChangeText={setSearchQuery}
                        />
                        {searchQuery.length > 0 && (
                            <TouchableOpacity onPress={() => setSearchQuery('')} style={{ padding: 4 }}>
                                <Icon name="close-circle" size={20} color="#CBD5E1" />
                            </TouchableOpacity>
                        )}
                    </View>
                    <TouchableOpacity style={[s.filterBtn, statusFilter !== 'all' && s.filterBtnActive]} onPress={() => setShowFilter(true)} activeOpacity={0.7}>
                        <Icon name="filter-variant" size={24} color={statusFilter !== 'all' ? '#FFF' : '#64748B'} />
                    </TouchableOpacity>
                </View>

                {/* List Table Header */}
                <View style={s.tableHeader}>
                    <Text style={[s.thText, { width: 44 }]}></Text>
                    <Text style={[s.thText, { flex: 2 }]}>PLAKA / MARKA</Text>
                    <Text style={[s.thText, { flex: 1, textAlign: 'center' }]}>YIL/DRM</Text>
                    <Text style={[s.thText, { flex: 1.5, textAlign: 'right', paddingRight: 10 }]}>PERSONEL/KM</Text>
                </View>

                {/* List */}
                {loading && page === 1 ? (
                    <View style={s.centerLoader}>
                        <ActivityIndicator size="large" color="#3B82F6" />
                    </View>
                ) : (
                    <FlatList
                        data={vehicles}
                        keyExtractor={item => String(item.id)}
                        renderItem={renderRow}
                        contentContainerStyle={s.listContent}
                        showsVerticalScrollIndicator={false}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchVehicles(1); }} tintColor="#3B82F6" />}
                        ListEmptyComponent={
                            <View style={s.emptyState}>
                                <View style={[s.emptyIconWrap, {backgroundColor: 'transparent'}]}>
                                    <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Automobile.png' }} style={{width: 64, height: 64}} resizeMode="contain" />
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
            <Modal visible={showFilter} transparent animationType="slide">
                <View style={s.modalOverlay}>
                    <View style={s.filterModal}>
                        <View style={s.modalHeader}>
                            <Text style={s.modalTitle}>Filtrele</Text>
                            <TouchableOpacity onPress={() => setShowFilter(false)} style={s.modalClose}>
                                <Icon name="close" size={24} color="#64748B" />
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
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    
    header: { flexDirection: 'row', paddingHorizontal: 20, paddingTop: Platform.OS === 'ios' ? 24 : 40, paddingBottom: 20, alignItems: 'center' },
    headerTitle: { fontSize: 28, fontWeight: '900', color: '#0F172A', letterSpacing: -0.5 },
    headerSub: { fontSize: 13, color: '#64748B', fontWeight: '500', marginTop: 4 },
    addBtn: { borderRadius: 16, overflow: 'hidden', shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 8 }, shadowopacity: 1, shadowRadius: 12, elevation: 8 },
    addBtnGradient: { width: 48, height: 48, alignItems: 'center', justifyContent: 'center' },

    kpiCard: { padding: 12, borderRadius: 20, justifyContent: 'space-between', shadowColor: '#000', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.15, shadowRadius: 8, elevation: 4 },
    kpiCardActive: { borderWidth: 2, borderColor: '#FFFFFF' },
    kpiIcon: { marginBottom: 8, opacity: 1 },
    kpiValue: { fontSize: 20, fontWeight: '900', color: '#FFF', letterSpacing: -0.5 },
    kpiTitle: { fontSize: 10, color: 'rgba(255,255,255,0.8)', fontWeight: '600', marginTop: 2 },

    searchContainer: { flexDirection: 'row', paddingHorizontal: 20, marginBottom: 12, gap: 10 },
    searchWrap: { flex: 1, flexDirection: 'row', alignItems: 'center', backgroundColor: '#FFFFFF', borderRadius: 14, paddingHorizontal: 12, height: 46, shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.1, shadowRadius: 8, elevation: 3, borderWidth: 1, borderColor: '#F1F5F9' },
    searchInput: { flex: 1, marginLeft: 8, fontSize: 14, color: '#0F172A', fontWeight: '500' },
    filterBtn: { width: 46, height: 46, backgroundColor: '#FFFFFF', borderRadius: 14, alignItems: 'center', justifyContent: 'center', shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.1, shadowRadius: 8, elevation: 3, borderWidth: 1, borderColor: '#F1F5F9' },
    filterBtnActive: { backgroundColor: '#3B82F6', borderColor: '#3B82F6' },

    tableHeader: { flexDirection: 'row', paddingHorizontal: 20, paddingVertical: 10, backgroundColor: '#F1F5F9', borderTopWidth: 1, borderBottomWidth: 1, borderColor: '#E2E8F0', alignItems: 'center' },
    thText: { fontSize: 10, fontWeight: '800', color: '#64748B', letterSpacing: 0.5 },

    listContent: { paddingBottom: 120 },
    
    tableRow: { flexDirection: 'row', paddingHorizontal: 20, paddingVertical: 12, backgroundColor: '#FFFFFF', borderBottomWidth: 1, borderColor: '#F1F5F9', alignItems: 'center' },
    rowImage: { width: 40, height: 40, resizeMode: 'contain', marginRight: 10 },
    rowColMain: { flex: 2, justifyContent: 'center' },
    rowPlate: { fontSize: 14, fontWeight: '800', color: '#0F172A', marginBottom: 2 },
    rowBrand: { fontSize: 11, color: '#64748B', fontWeight: '500' },
    rowColMid: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    rowBadge: { backgroundColor: '#FEF08A', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4, marginBottom: 4 },
    rowBadgeText: { fontSize: 9, fontWeight: '700', color: '#854D0E' },
    statusDotSmall: { width: 8, height: 8, borderRadius: 4 },
    rowColRight: { flex: 1.5, alignItems: 'flex-end', justifyContent: 'center' },
    rowDriver: { fontSize: 11, color: '#0F172A', fontWeight: '600', marginBottom: 2 },
    rowKm: { fontSize: 11, color: '#10B981', fontWeight: '700' },
    
    warningBox: { backgroundColor: '#0F172A', borderRadius: 16, padding: 16, marginTop: 12, overflow: 'hidden' },
    warningDateText: { fontSize: 11, color: '#94A3B8', fontWeight: '600', marginBottom: 4 },
    warningDaysText: { fontSize: 16, fontWeight: '900', letterSpacing: -0.5 },
    actionBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#3B82F6', paddingHorizontal: 14, paddingVertical: 10, borderRadius: 12 },
    actionBtnText: { fontSize: 11, color: '#FFFFFF', fontWeight: '800', letterSpacing: 0.5 },
    inspectionDetailChip: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.1)', paddingHorizontal: 10, paddingVertical: 6, borderRadius: 8, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    inspectionDetailText: { fontSize: 10, color: '#F1F5F9', fontWeight: '700', marginLeft: 6 },

    centerLoader: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingTop: 100 },
    footerLoader: { paddingVertical: 20, alignItems: 'center' },
    
    emptyState: { alignItems: 'center', justifyContent: 'center', paddingVertical: 60, paddingHorizontal: 40 },
    emptyIconWrap: { width: 96, height: 96, borderRadius: 48, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center', marginBottom: 20 },
    emptyTitle: { fontSize: 18, fontWeight: '800', color: '#1E293B', marginBottom: 8 },
    emptyText: { fontSize: 14, color: '#64748B', textAlign: 'center', lineHeight: 20 },

    modalOverlay: { flex: 1, backgroundColor: 'rgba(15, 23, 42, 0.6)', justifyContent: 'flex-end' },
    filterModal: { backgroundColor: '#FFFFFF', borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, paddingBottom: 40 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 },
    modalTitle: { fontSize: 20, fontWeight: '900', color: '#0F172A' },
    modalClose: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    filterLabel: { fontSize: 13, fontWeight: '700', color: '#64748B', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 12 },
    filterRow: { flexDirection: 'row', gap: 12, flexWrap: 'wrap' },
    filterChip: { paddingHorizontal: 20, paddingVertical: 12, borderRadius: 16, backgroundColor: '#F1F5F9', borderWidth: 1, borderColor: 'transparent' },
    filterChipActive: { backgroundColor: '#EFF6FF', borderColor: '#3B82F6' },
    filterChipText: { fontSize: 14, fontWeight: '600', color: '#475569' },
    filterChipTextActive: { color: '#3B82F6', fontWeight: '700' }
});
