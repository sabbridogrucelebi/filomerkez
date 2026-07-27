import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, StyleSheet, FlatList, ActivityIndicator, Alert, Text, Platform, TouchableOpacity, RefreshControl, Modal, ScrollView, Dimensions, Image, Animated } from 'react-native';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import * as IntentLauncher from 'expo-intent-launcher';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import { EmptyState, FormField } from '../components';
import DatePickerInput from '../components/DatePickerInput';

const { width } = Dimensions.get('window');
const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(v || 0);
const fmtKm = (v) => new Intl.NumberFormat('tr-TR').format(v || 0);

const BlinkingPlate = ({ plate }) => {
    const fadeAnim = useRef(new Animated.Value(1)).current;
    
    useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(fadeAnim, { toValue: 0.4, duration: 800, useNativeDriver: true }),
                Animated.timing(fadeAnim, { toValue: 1, duration: 800, useNativeDriver: true })
            ])
        ).start();
    }, [fadeAnim]);

    return (
        <Animated.View style={{ opacity: fadeAnim, backgroundColor: '#3B82F6', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 8, shadowColor: '#3B82F6', shadowOffset: { width:0, height:4 }, shadowOpacity: 0.4, shadowRadius: 6, elevation: 4 }}>
            <Text style={{ fontSize: 16, fontWeight: '900', color: '#FFFFFF', letterSpacing: 1 }}>{plate}</Text>
        </Animated.View>
    );
};

export default function MaintenancesScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const [maintenances, setMaintenances] = useState([]);
    const [summary, setSummary] = useState({ total_count: 0, this_month_count: 0, total_cost: 0 });
    const [downloadingFormat, setDownloadingFormat] = useState(null);
    const [vehicles, setVehicles] = useState([]);
    const [mechanics, setMechanics] = useState([]);
    const [noteSuggestions, setNoteSuggestions] = useState([]);
    const [titleSuggestions, setTitleSuggestions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [filters, setFilters] = useState({ search: '', start_date: '', end_date: '' });
    const [activeFilters, setActiveFilters] = useState({ search: '', start_date: '', end_date: '' });

    const [modalVisible, setModalVisible] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [formData, setFormData] = useState({
        vehicle_id: '',
        service_date: new Date().toISOString().split('T')[0],
        maintenance_type: 'Periyodik',
        title: '',
        km: '',
        amount: '',
        service_name: '',
        description: ''
    });

    const [showCategorySelect, setShowCategorySelect] = useState(false);
    const [showVehicleSelect, setShowVehicleSelect] = useState(false);
    const [vehicleSearchQuery, setVehicleSearchQuery] = useState('');
    const [showMechanicSelect, setShowMechanicSelect] = useState(false);
    const [customMechanic, setCustomMechanic] = useState(false);
    
    const categories = ['YAĞ BAKIMI', 'ALT YAĞLAMA', 'LASTİK BAKIMI', 'AKÜ BAKIMI', 'AĞIR BAKIM', 'ANTFRİZ BAKIMI', 'ARIZA/ONARIM', 'MUAYENE', 'DİĞER BAKIMLAR'];

    const fetchData = async (isRefreshing = false) => {
        if (!isRefreshing) setLoading(true);
        try {
            const params = {};
            if (activeFilters.search) params.search = activeFilters.search;
            if (activeFilters.start_date) params.start_date = activeFilters.start_date;
            if (activeFilters.end_date) params.end_date = activeFilters.end_date;

            const [mRes, oRes] = await Promise.all([
                api.get('/v1/maintenances', { params }),
                api.get('/v1/maintenances/options')
            ]);
            
            if (mRes.data && mRes.data.data) {
                if (mRes.data.data.maintenances) {
                    setMaintenances(mRes.data.data.maintenances);
                }
                if (mRes.data.data.summary) {
                    setSummary(mRes.data.data.summary);
                }
            }
            if (oRes.data && oRes.data.data) {
                if (oRes.data.data.vehicles) setVehicles(oRes.data.data.vehicles);
                if (oRes.data.data.mechanics) setMechanics(oRes.data.data.mechanics);
                if (oRes.data.data.noteSuggestions) setNoteSuggestions(oRes.data.data.noteSuggestions);
                if (oRes.data.data.titleSuggestions) setTitleSuggestions(oRes.data.data.titleSuggestions);
            }
        } catch (e) {
            console.error(e);
            Alert.alert('Hata', 'Veriler yüklenirken bir sorun oluştu.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => { fetchData(); }, [activeFilters]);

    const openAdd = () => {
        if (!hasPermission('maintenances.create')) {
            Alert.alert('Yetki Yok', 'Bakım kaydı ekleme yetkiniz bulunmuyor.');
            return;
        }

        const today = new Date();
        const y = today.getFullYear();
        const m = String(today.getMonth() + 1).padStart(2, '0');
        const d = String(today.getDate()).padStart(2, '0');

        setEditingId(null);
        setFormData({
            vehicle_id: '',
            service_date: `${y}-${m}-${d}`,
            maintenance_type: '',
            title: '',
            km: '',
            next_service_km: '',
            amount: '',
            service_name: '',
            description: ''
        });
        setCustomMechanic(false);
        setModalVisible(true);
    };

    const openEdit = (item) => {
        if (!hasPermission('maintenances.edit')) {
            Alert.alert('Yetki Yok', 'Bakım kaydı düzenleme yetkiniz bulunmuyor.');
            return;
        }
        setEditingId(item.id);
        setFormData({
            vehicle_id: item.vehicle_id || '',
            service_date: item.date ? item.date.split('T')[0] : new Date().toISOString().split('T')[0],
            maintenance_type: item.type || '',
            title: item.title || '',
            km: item.km ? item.km.toString() : '',
            next_service_km: item.next_km ? item.next_km.toString() : '',
            amount: item.amount ? item.amount.toString() : '',
            service_name: item.service_name || '',
            description: item.description || ''
        });
        
        // Custom mechanic check
        if (item.service_name && !mechanics.some(m => m.id === item.service_name)) {
            setCustomMechanic(true);
        } else {
            setCustomMechanic(false);
        }
        setModalVisible(true);
    };

    const handleSave = async () => {
        if (!formData.vehicle_id) {
            Alert.alert('Eksik Bilgi', 'Lütfen bir araç seçiniz.'); return;
        }
        if (!formData.title || !formData.service_date || !formData.maintenance_type) {
            Alert.alert('Eksik Bilgi', 'Tarih, Kategori ve İşlem Adı zorunludur.'); return;
        }

        setSaving(true);
        try {
            const url = editingId ? `/v1/maintenances/${editingId}` : '/v1/maintenances';
            const method = editingId ? 'PUT' : 'POST';
            await api({ method, url, data: formData });
            
            Alert.alert('Başarılı', editingId ? 'Bakım güncellendi.' : 'Bakım kaydedildi.');
            setModalVisible(false);
            fetchData();
        } catch (e) {
            console.error(e);
            Alert.alert('Hata', 'Kayıt işlemi başarısız oldu.');
        } finally {
            setSaving(false);
        }
    };

    const confirmDelete = (id) => {
        if (!hasPermission('maintenances.delete')) {
            Alert.alert('Yetki Yok', 'Bakım kaydı silme yetkiniz bulunmuyor.');
            return;
        }
        Alert.alert('Silinecek', 'Bu bakım kaydını silmek istediğinize emin misiniz?', [
            { text: 'İptal', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try { await api.delete(`/v1/maintenances/${id}`); fetchData(); }
                catch (e) { Alert.alert('Hata', 'Silinemedi.'); }
            }}
        ]);
    };

    const handleDownload = async (format, isView = false) => {
        try {
            setDownloadingFormat(isView ? 'view' : format);
            let token;
            if (Platform.OS === 'web') {
                token = await AsyncStorage.getItem('userToken');
            } else {
                token = await SecureStore.getItemAsync('userToken');
            }
            if (!token) throw new Error('Token not found');

            const params = new URLSearchParams();
            if (activeFilters.search) params.append('search', activeFilters.search);
            if (activeFilters.vehicle_id) params.append('vehicle_id', activeFilters.vehicle_id);
            if (activeFilters.maintenance_type) params.append('maintenance_type', activeFilters.maintenance_type);
            if (activeFilters.start_date) params.append('start_date', activeFilters.start_date);
            if (activeFilters.end_date) params.append('end_date', activeFilters.end_date);

            const endpoint = `/v1/maintenances/export-${format}?${params.toString()}`;
            const url = api.defaults.baseURL + endpoint;

            const filename = `Bakim_Raporu_${new Date().getTime()}.${format === 'excel' ? 'xlsx' : 'pdf'}`;
            const fileUri = FileSystem.documentDirectory + filename;

            const downloadRes = await FileSystem.downloadAsync(url, fileUri, {
                headers: { Authorization: `Bearer ${token}` }
            });

            if (downloadRes.status !== 200) {
                Alert.alert('Hata', 'Rapor oluşturulurken bir sorun oluştu.');
                return;
            }

            if (isView && Platform.OS === 'android') {
                const cUri = await FileSystem.getContentUriAsync(downloadRes.uri);
                await IntentLauncher.startActivityAsync('android.intent.action.VIEW', {
                    data: cUri,
                    flags: 1,
                    type: format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                });
            } else {
                if (await Sharing.isAvailableAsync()) {
                    await Sharing.shareAsync(downloadRes.uri, {
                        UTI: format === 'pdf' ? 'com.adobe.pdf' : 'com.microsoft.excel.xls',
                        dialogTitle: isView ? 'Görüntüle' : 'Dosyayı Kaydet / Paylaş'
                    });
                } else {
                    Alert.alert('Başarılı', 'Rapor indirildi: ' + downloadRes.uri);
                }
            }
        } catch (e) {
            console.error(e);
            Alert.alert('Hata', 'İndirme işlemi başarısız oldu.');
        } finally {
            setDownloadingFormat(null);
        }
    };

    const getTypeStyle = (type) => {
        const t = (type || '').toUpperCase();
        if (t.includes('LASTİK')) return { color: '#10B981', icon: 'tire', bg: '#D1FAE5' };
        if (t.includes('AKÜ')) return { color: '#F59E0B', icon: 'car-battery', bg: '#FEF3C7' };
        if (t.includes('AĞIR')) return { color: '#EF4444', icon: 'car-wrench', bg: '#FEE2E2' };
        if (t.includes('ANTFRİZ') || t.includes('ANTİFRİZ')) return { color: '#3B82F6', icon: 'snowflake', bg: '#DBEAFE' };
        if (t.includes('ALT YAĞLAMA')) return { color: '#06B6D4', icon: 'wrench', bg: '#CFFAFE' };
        if (t.includes('YAĞ')) return { color: '#EAB308', icon: 'oil', bg: '#FEF9C3' };
        if (t.includes('ARIZA')) return { color: '#EF4444', icon: 'alert-octagon-outline', bg: '#FEF2F2' };
        if (t.includes('MUAYENE')) return { color: '#F59E0B', icon: 'shield-check-outline', bg: '#FFFBEB' };
        return { color: '#6366F1', icon: 'tools', bg: '#E0E7FF' };
    };

    const toTitleCase = (str) => {
        if (!str) return '';
        return str.toString().split(' ').map(word => {
            if (!word) return '';
            const first = word.charAt(0).toLocaleUpperCase('tr-TR');
            const rest = word.slice(1).toLocaleLowerCase('tr-TR');
            return first + rest;
        }).join(' ');
    };

    const renderHeader = () => (
        <View style={st.kpiContainer}>
            <View style={st.kpiRow}>
                <LinearGradient colors={['#60A5FA', '#2563EB', '#1D4ED8']} locations={[0, 0.5, 1]} style={[st.kpiCard, { flex: 1, marginRight: 8 }]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }}>
                    <View style={st.kpiGlowTop} />
                    <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Hammer%20and%20Wrench.png' }} style={[st.kpiIconBg, { width: 80, height: 80, opacity: 1, right: -10, bottom: -10 }]} resizeMode="contain" />
                    <Text style={st.kpiTitle}>Toplam Bakım</Text>
                    <Text style={st.kpiValue}>{summary.total_count}</Text>
                    <Text style={st.kpiSub}>Sistemde kayıtlı işlemler</Text>
                </LinearGradient>
                
                <TouchableOpacity 
                    style={{ flex: 1, marginLeft: 8 }}
                    onPress={() => {
                        const now = new Date();
                        const y = now.getFullYear();
                        const m = String(now.getMonth() + 1).padStart(2, '0');
                        const firstDay = `${y}-${m}-01`;
                        const lastDay = new Date(y, now.getMonth() + 1, 0).toISOString().split('T')[0];
                        setFilters({...filters, start_date: firstDay, end_date: lastDay});
                        setActiveFilters({...activeFilters, start_date: firstDay, end_date: lastDay});
                    }}
                >
                    <LinearGradient colors={['#34D399', '#059669', '#047857']} locations={[0, 0.5, 1]} style={[st.kpiCard]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }}>
                        <View style={st.kpiGlowTop} />
                        <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Spiral%20Calendar.png' }} style={[st.kpiIconBg, { width: 80, height: 80, opacity: 1, right: -10, bottom: -10 }]} resizeMode="contain" />
                        <Text style={st.kpiTitle}>Bu Ay Yapılan</Text>
                        <Text style={st.kpiValue}>{summary.this_month_count}</Text>
                        <Text style={st.kpiSub}>Tamamlanan kayıtlar</Text>
                    </LinearGradient>
                </TouchableOpacity>
            </View>
            <View style={[st.kpiRow, { marginTop: 16 }]}>
                <LinearGradient colors={['#FB7185', '#E11D48', '#BE123C']} locations={[0, 0.5, 1]} style={[st.kpiCard, { flex: 1 }]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }}>
                    <View style={st.kpiGlowTop} />
                    <Image source={{ uri: 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Money%20Bag.png' }} style={[st.kpiIconBg, { width: 100, height: 100, opacity: 1, right: -15, bottom: -15 }]} resizeMode="contain" />
                    <Text style={st.kpiTitle}>Toplam Maliyet</Text>
                    <Text style={st.kpiValue}>{fmtMoney(summary.total_cost)}</Text>
                    <Text style={st.kpiSub}>Tüm bakım işlemlerinin maliyeti</Text>
                </LinearGradient>
            </View>

            {/* Export Actions Row */}
            <View style={st.exportActionsRow}>
                <TouchableOpacity 
                    style={[st.exportBtn, { flex: 1, borderColor: '#10B981', backgroundColor: '#ECFDF5' }]} 
                    onPress={() => handleDownload('excel', false)}
                    disabled={downloadingFormat !== null}
                >
                    {downloadingFormat === 'excel' ? <ActivityIndicator size="small" color="#10B981" /> : <Icon name="file-excel" size={16} color="#10B981" />}
                    <Text style={[st.exportBtnText, { color: '#10B981', fontSize: 11 }]} numberOfLines={1}>Excel</Text>
                </TouchableOpacity>

                <TouchableOpacity 
                    style={[st.exportBtn, { flex: 1, borderColor: '#EF4444', backgroundColor: '#FEF2F2' }]} 
                    onPress={() => handleDownload('pdf', false)}
                    disabled={downloadingFormat !== null}
                >
                    {downloadingFormat === 'pdf' ? <ActivityIndicator size="small" color="#EF4444" /> : <Icon name="file-pdf-box" size={16} color="#EF4444" />}
                    <Text style={[st.exportBtnText, { color: '#EF4444', fontSize: 11 }]} numberOfLines={1}>PDF İndir</Text>
                </TouchableOpacity>

                <TouchableOpacity 
                    style={[st.exportBtn, { flex: 1, borderColor: '#3B82F6', backgroundColor: '#EFF6FF' }]} 
                    onPress={() => handleDownload('pdf', true)}
                    disabled={downloadingFormat !== null}
                >
                    {downloadingFormat === 'view' ? <ActivityIndicator size="small" color="#3B82F6" /> : <Icon name="eye" size={16} color="#3B82F6" />}
                    <Text style={[st.exportBtnText, { color: '#3B82F6', fontSize: 11 }]} numberOfLines={1}>PDF Gör</Text>
                </TouchableOpacity>
            </View>

            {/* Filter Section */}
            <View style={st.filterCard}>
                <Text style={st.filterCardTitle}>Kayıtları Filtrele</Text>
                <View style={{ flexDirection: 'row', gap: 10, marginBottom: 12 }}>
                    <View style={{ flex: 1 }}>
                        <DatePickerInput 
                            label="BAŞLANGIÇ TARİHİ" 
                            value={filters.start_date} 
                            onChange={(d) => setFilters({...filters, start_date: d})}
                            placeholder="Seçiniz"
                        />
                    </View>
                    <View style={{ flex: 1 }}>
                        <DatePickerInput 
                            label="BİTİŞ TARİHİ" 
                            value={filters.end_date} 
                            onChange={(d) => setFilters({...filters, end_date: d})}
                            placeholder="Seçiniz"
                        />
                    </View>
                </View>
                <FormField 
                    placeholder="Araç plakası, servis veya bakım adı..."
                    value={filters.search}
                    onChangeText={(t) => setFilters({...filters, search: t})}
                    icon="magnify"
                />
                <View style={{ flexDirection: 'row', gap: 10, marginTop: 12 }}>
                    <TouchableOpacity style={st.filterClearBtn} onPress={() => {
                        setFilters({ search: '', start_date: '', end_date: '' });
                        setActiveFilters({ search: '', start_date: '', end_date: '' });
                    }}>
                        <Text style={st.filterClearText}>Temizle</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={st.filterBtn} onPress={() => setActiveFilters(filters)}>
                        <Icon name="filter-variant" size={18} color="#fff" />
                        <Text style={st.filterBtnText}>Filtrele</Text>
                    </TouchableOpacity>
                </View>
            </View>
        </View>
    );

    const renderItem = ({ item }) => {
        const ts = getTypeStyle(item.type || item.maintenance_type);
        const hasDesc = item.description && item.description.trim() !== '';

        return (
            <View style={[st.card, { borderLeftColor: ts.color }]}>
                <View style={st.cardHeader}>
                    <View style={[st.iconBox, { backgroundColor: ts.bg }]}>
                        <Icon name={ts.icon} size={24} color={ts.color} />
                    </View>
                    <View style={{ flex: 1, paddingLeft: 12, paddingRight: 8 }}>
                        <Text style={st.cardTitle}>{toTitleCase(item.title)}</Text>
                        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 2 }}>
                            <Text style={st.amountTextSmall}>{fmtMoney(item.amount)}</Text>
                            <View style={st.statusBadge}>
                                <View style={st.statusDot} />
                                <Text style={st.statusText}>Tamamlandı</Text>
                            </View>
                        </View>
                        <Text style={[st.cardDesc, hasDesc && { color: '#EF4444' }]}>
                            {hasDesc ? toTitleCase(item.description) : 'Açıklama yok'}
                        </Text>
                    </View>
                    <View style={{ alignItems: 'flex-end', justifyContent: 'flex-start' }}>
                        <BlinkingPlate plate={item.vehicle?.plate || 'Bilinmiyor'} />
                        <View style={{ flexDirection: 'row', gap: 12, marginTop: 8 }}>
                            <TouchableOpacity onPress={() => openEdit(item)} style={{ padding: 4 }}>
                                <Icon name="pencil-outline" size={20} color="#3B82F6" />
                            </TouchableOpacity>
                            <TouchableOpacity onPress={() => confirmDelete(item.id)} style={{ padding: 4 }}>
                                <Icon name="trash-can-outline" size={20} color="#EF4444" />
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>

                {/* 2x2 Grid */}
                <View style={st.cardGrid}>
                    <View style={st.gridRow}>
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="tag-outline" size={14} color="#64748B" />
                                <Text style={st.gridLabel}>TÜR</Text>
                            </View>
                            <Text style={st.gridValue}>{toTitleCase(item.maintenance_type || item.type) || '-'}</Text>
                        </View>
                        <View style={st.gridDivider} />
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="calendar-blank-outline" size={14} color="#F59E0B" />
                                <Text style={[st.gridLabel, { color: '#F59E0B' }]}>TARİH</Text>
                            </View>
                            <Text style={[st.gridValue, { color: '#D97706' }]}>{item.service_date || item.date ? new Date(item.service_date || item.date).toLocaleDateString('tr-TR') : '-'}</Text>
                            <Text style={[st.gridSubValue, { color: '#FBBF24' }]}>{item.next_service_date || item.next_date ? `Sonraki: ${new Date(item.next_service_date || item.next_date).toLocaleDateString('tr-TR')}` : 'Sonraki tarih yok'}</Text>
                        </View>
                    </View>
                    
                    <View style={st.gridHorizontalDivider} />
                    
                    <View style={st.gridRow}>
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="store-outline" size={14} color="#64748B" />
                                <Text style={st.gridLabel}>SERVİS</Text>
                            </View>
                            <Text style={st.gridValue}>{toTitleCase(item.service_name) || '-'}</Text>
                        </View>
                        <View style={st.gridDivider} />
                        <View style={st.gridCol}>
                            <View style={st.gridLabelRow}>
                                <Icon name="speedometer" size={14} color="#10B981" />
                                <Text style={[st.gridLabel, { color: '#10B981' }]}>KİLOMETRE</Text>
                            </View>
                            <Text style={[st.gridValue, { color: '#059669' }]}>{item.km ? `${fmtKm(item.km)} KM` : '-'}</Text>
                            <Text style={[st.gridSubValue, { color: '#34D399' }]}>{item.next_service_km || item.next_km ? `Sonraki: ${fmtKm(item.next_service_km || item.next_km)} KM` : 'Sonraki KM yok'}</Text>
                        </View>
                    </View>
                </View>
            </View>
        );
    };

    return (
        <View style={st.container}>
            <View style={{ backgroundColor: '#fff', zIndex: 10, paddingTop: Platform.OS === 'android' ? 44 : 54, paddingBottom: 12 }}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <Icon name="chevron-left" size={26} color="#0F172A" />
                    </TouchableOpacity>
                    <View style={st.headerCenter}>
                        <Text style={st.headerTitle}>Bakım / Tamir</Text>
                        <Text style={st.headerSubtitle}>Tüm Araç Bakım Kayıtları</Text>
                    </View>
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                        {hasPermission('maintenances.view') && (
                            <TouchableOpacity onPress={() => navigation.navigate('MaintenanceSettings')} style={st.headerSettingsBtn}>
                                <Icon name="cog-outline" size={22} color="#0F172A" />
                            </TouchableOpacity>
                        )}
                        {hasPermission('maintenances.create') ? (
                            <TouchableOpacity onPress={openAdd} style={st.headerAddBtn}>
                                <Icon name="plus" size={22} color="#fff" />
                            </TouchableOpacity>
                        ) : (
                            <View style={{ width: 40 }} />
                        )}
                    </View>
                </View>
            </View>

            {loading ? (
                <View style={st.loader}><ActivityIndicator size="large" color="#3B82F6" /></View>
            ) : (
                <FlatList
                    data={maintenances}
                    ListHeaderComponent={renderHeader()}
                    renderItem={renderItem}
                    keyExtractor={item => item.id.toString()}
                    contentContainerStyle={st.listContent}
                    showsVerticalScrollIndicator={false}
                    refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchData(true)} tintColor="#3B82F6" />}
                    ListEmptyComponent={<EmptyState title="Bakım Kaydı Yok" message="Sistemde henüz bakım kaydı bulunmuyor." icon="wrench-outline" />}
                />
            )}

            {/* Main Form Modal */}
            <Modal visible={modalVisible} animationType="slide" transparent>
                <View style={st.modalOverlay}>
                    <View style={st.modalContent}>
                        <View style={st.modalHeader}>
                            <Text style={st.modalTitle}>{editingId ? 'Bakım Düzenle' : 'Yeni Bakım Ekle'}</Text>
                            <TouchableOpacity style={st.modalCloseBtn} onPress={() => setModalVisible(false)}>
                                <Icon name="close" size={24} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        
                        <ScrollView contentContainerStyle={{ padding: 20 }} showsVerticalScrollIndicator={false}>
                            <Text style={st.inputLabel}>Araç *</Text>
                            <TouchableOpacity style={st.selectBtn} onPress={() => { setShowVehicleSelect(true); setVehicleSearchQuery(''); }}>
                                <Text style={[st.selectBtnText, !formData.vehicle_id && { color: '#94A3B8' }]}>
                                    {formData.vehicle_id ? (vehicles?.find(v => v.id === formData.vehicle_id)?.plate || 'Seçildi') : 'Araç Seçiniz'}
                                </Text>
                                <Icon name="chevron-down" size={20} color="#64748B" />
                            </TouchableOpacity>

                            <View style={{ marginTop: 16 }}>
                                <DatePickerInput
                                    label="Tarih *"
                                    value={formData.service_date}
                                    onChange={(val) => setFormData({ ...formData, service_date: val })}
                                />
                            </View>

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>Kategori *</Text>
                            <TouchableOpacity style={st.selectBtn} onPress={() => setShowCategorySelect(true)}>
                                <Text style={[st.selectBtnText, !formData.maintenance_type && { color: '#94A3B8' }]}>
                                    {formData.maintenance_type || 'KATEGORİ SEÇİNİZ'}
                                </Text>
                                <Icon name="chevron-down" size={20} color="#64748B" />
                            </TouchableOpacity>

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>Araca Yapılan İşlem Adı *</Text>
                            <FormField
                                value={formData.title}
                                onChangeText={(val) => setFormData({ ...formData, title: val })}
                                placeholder="Örn: Yağ Bakımı Yapıldı"
                            />
                            {titleSuggestions?.length > 0 && formData.title?.length > 0 && (
                                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 8 }} keyboardShouldPersistTaps="handled">
                                    {titleSuggestions.filter(s => s && s.toUpperCase().includes(formData.title.toUpperCase()) && s.toUpperCase() !== formData.title.toUpperCase()).map((s, idx) => (
                                        <TouchableOpacity key={idx} style={st.suggestionPill} onPress={() => setFormData({...formData, title: s})}>
                                            <Text style={st.suggestionText}>{s}</Text>
                                        </TouchableOpacity>
                                    ))}
                                </ScrollView>
                            )}

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>Araç Bakım KM'si</Text>
                            <FormField
                                value={formData.km}
                                onChangeText={(val) => setFormData({ ...formData, km: val })}
                                placeholder="Örn: 150000"
                                keyboardType="numeric"
                            />

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>Bir Sonraki Bakım KM</Text>
                            <FormField
                                value={formData.next_service_km || ''}
                                onChangeText={(val) => setFormData({ ...formData, next_service_km: val })}
                                placeholder="Opsiyonel"
                                keyboardType="numeric"
                            />
                            <Text style={st.helperText}>YAĞ BAKIMI veya ALT YAĞLAMA seçildiğinde otomatik hesaplanır.</Text>

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>Tutar</Text>
                            <FormField
                                value={formData.amount}
                                onChangeText={(val) => setFormData({ ...formData, amount: val })}
                                placeholder="₺"
                                keyboardType="numeric"
                            />

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>Usta</Text>
                            <TouchableOpacity style={st.selectBtn} onPress={() => setShowMechanicSelect(true)}>
                                <Text style={[st.selectBtnText, !formData.service_name && !customMechanic && { color: '#94A3B8' }]}>
                                    {customMechanic ? 'DİĞER' : (formData.service_name || 'SEÇİNİZ')}
                                </Text>
                                <Icon name="chevron-down" size={20} color="#64748B" />
                            </TouchableOpacity>

                            {customMechanic && (
                                <View style={{ marginTop: 12 }}>
                                    <FormField
                                        value={formData.service_name}
                                        onChangeText={(val) => setFormData({ ...formData, service_name: val })}
                                        placeholder="Usta veya Servis Adı Yazınız"
                                    />
                                </View>
                            )}

                            <Text style={[st.inputLabel, { marginTop: 16 }]}>Not</Text>
                            <FormField
                                value={formData.description}
                                onChangeText={(val) => setFormData({ ...formData, description: val })}
                                placeholder="Bakım ile ilgili notlar..."
                                multiline
                                numberOfLines={4}
                                style={{ height: 100, textAlignVertical: 'top' }}
                            />
                            {noteSuggestions?.length > 0 && formData.description?.length > 0 && (
                                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginTop: 8 }} keyboardShouldPersistTaps="handled">
                                    {noteSuggestions.filter(s => s && s.toUpperCase().includes(formData.description.toUpperCase()) && s.toUpperCase() !== formData.description.toUpperCase()).map((s, idx) => (
                                        <TouchableOpacity key={idx} style={st.suggestionPill} onPress={() => setFormData({...formData, description: s})}>
                                            <Text style={st.suggestionText}>{s}</Text>
                                        </TouchableOpacity>
                                    ))}
                                </ScrollView>
                            )}

                            <TouchableOpacity 
                                style={[st.saveBtn, saving && { opacity: 0.7 }]} 
                                onPress={handleSave} 
                                disabled={saving}
                            >
                                {saving ? <ActivityIndicator color="#fff" /> : <Text style={st.saveBtnText}>{editingId ? 'Güncelle' : 'Kaydet'}</Text>}
                            </TouchableOpacity>
                            <View style={{ height: 60 }} />
                        </ScrollView>

                        {/* İç İçe Modal Çözümü (Android Fix) -> Absolute Positioned Overlay */}
                        {showVehicleSelect && (
                            <View style={[StyleSheet.absoluteFill, { backgroundColor: '#fff', borderRadius: 24, zIndex: 100 }]}>
                                <View style={st.modalHeader}>
                                    <Text style={st.modalTitle}>Araç Seçiniz</Text>
                                    <TouchableOpacity style={st.modalClose} onPress={() => setShowVehicleSelect(false)}>
                                        <Icon name="close" size={20} color="#64748B" />
                                    </TouchableOpacity>
                                </View>
                                
                                <View style={{ paddingHorizontal: 16, paddingTop: 16, paddingBottom: 8 }}>
                                    <FormField 
                                        placeholder="Plaka Ara..." 
                                        value={vehicleSearchQuery} 
                                        onChangeText={setVehicleSearchQuery}
                                        icon="magnify"
                                        autoCapitalize="characters"
                                    />
                                </View>

                                <ScrollView>
                                    {vehicles?.filter(v => v.plate.toUpperCase().includes(vehicleSearchQuery.toUpperCase())).map(v => (
                                        <TouchableOpacity 
                                            key={v.id} 
                                            style={[st.categoryOption, formData.vehicle_id === v.id && st.categoryOptionActive]}
                                            onPress={() => {
                                                setFormData({...formData, vehicle_id: v.id});
                                                setShowVehicleSelect(false);
                                            }}
                                        >
                                            <Icon name="car" size={22} color={formData.vehicle_id === v.id ? '#3B82F6' : '#94A3B8'} />
                                            <Text style={[st.categoryOptionText, formData.vehicle_id === v.id && st.categoryOptionTextActive, { flex: 1, marginLeft: 12 }]}>
                                                {v.plate}
                                            </Text>
                                            {formData.vehicle_id === v.id && <Icon name="check-circle" size={22} color="#3B82F6" />}
                                        </TouchableOpacity>
                                    ))}
                                    <View style={{height:30}}/>
                                </ScrollView>
                            </View>
                        )}

                        {showCategorySelect && (
                            <View style={[StyleSheet.absoluteFill, { backgroundColor: '#fff', borderRadius: 24, zIndex: 100 }]}>
                                <View style={st.modalHeader}>
                                    <Text style={st.modalTitle}>Kategori Seçiniz</Text>
                                    <TouchableOpacity style={st.modalClose} onPress={() => setShowCategorySelect(false)}>
                                        <Icon name="close" size={20} color="#64748B" />
                                    </TouchableOpacity>
                                </View>
                                <ScrollView>
                                    {categories.map((cat, idx) => {
                                        const cIcon = getTypeStyle(cat);
                                        return (
                                        <TouchableOpacity 
                                            key={idx} 
                                            style={[st.categoryOption, formData.maintenance_type === cat && st.categoryOptionActive]}
                                            onPress={() => {
                                                let newTitle = formData.title;
                                                if (!formData.title || formData.title.endsWith('Yapıldı') || formData.title.endsWith('Bakımı')) {
                                                    if (cat === 'YAĞ BAKIMI') newTitle = 'Yağ Bakımı Yapıldı';
                                                    else if (cat === 'ALT YAĞLAMA') newTitle = 'Alt Yağlama Yapıldı';
                                                    else if (cat === 'LASTİK BAKIMI') newTitle = 'Lastik Bakımı Yapıldı';
                                                    else if (cat === 'AKÜ BAKIMI') newTitle = 'Akü Bakımı Yapıldı';
                                                    else if (cat === 'AĞIR BAKIM') newTitle = 'Ağır Bakım Yapıldı';
                                                    else if (cat === 'ANTFRİZ BAKIMI') newTitle = 'Antfriz Bakımı Yapıldı';
                                                    else if (cat === 'ARIZA/ONARIM') newTitle = 'Arıza Onarım Yapıldı';
                                                    else if (cat === 'MUAYENE') newTitle = 'Muayene Yapıldı';
                                                }

                                                setFormData({...formData, maintenance_type: cat, title: newTitle});
                                                setShowCategorySelect(false);
                                            }}
                                        >
                                            <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                                                <View style={{ width: 36, height: 36, borderRadius: 18, backgroundColor: cIcon.bg, alignItems: 'center', justifyContent: 'center', marginRight: 12 }}>
                                                    <Icon name={cIcon.icon} size={18} color={cIcon.color} />
                                                </View>
                                                <Text style={[st.categoryOptionText, formData.maintenance_type === cat && st.categoryOptionTextActive]}>
                                                    {cat}
                                                </Text>
                                            </View>
                                            {formData.maintenance_type === cat && <Icon name="check-circle" size={20} color="#3B82F6" />}
                                        </TouchableOpacity>
                                        );
                                    })}
                                    <View style={{height:30}}/>
                                </ScrollView>
                            </View>
                        )}

                        {showMechanicSelect && (
                            <View style={[StyleSheet.absoluteFill, { backgroundColor: '#fff', borderRadius: 24, zIndex: 100 }]}>
                                <View style={st.modalHeader}>
                                    <Text style={st.modalTitle}>Usta Seçiniz</Text>
                                    <TouchableOpacity style={st.modalClose} onPress={() => setShowMechanicSelect(false)}>
                                        <Icon name="close" size={20} color="#64748B" />
                                    </TouchableOpacity>
                                </View>
                                <ScrollView>
                                    {mechanics?.map((m, idx) => (
                                        <TouchableOpacity 
                                            key={idx} 
                                            style={[st.categoryOption, formData.service_name === m && !customMechanic && st.categoryOptionActive]}
                                            onPress={() => {
                                                setCustomMechanic(false);
                                                setFormData({...formData, service_name: m});
                                                setShowMechanicSelect(false);
                                            }}
                                        >
                                            <Icon name="account-wrench" size={22} color={formData.service_name === m && !customMechanic ? '#3B82F6' : '#94A3B8'} />
                                            <Text style={[st.categoryOptionText, formData.service_name === m && !customMechanic && st.categoryOptionTextActive, { marginLeft: 12, flex: 1 }]}>
                                                {m}
                                            </Text>
                                            {formData.service_name === m && !customMechanic && <Icon name="check-circle" size={22} color="#3B82F6" />}
                                        </TouchableOpacity>
                                    ))}
                                    <TouchableOpacity 
                                        style={[st.categoryOption, customMechanic && st.categoryOptionActive]}
                                        onPress={() => {
                                            setCustomMechanic(true);
                                            setFormData({...formData, service_name: ''});
                                            setShowMechanicSelect(false);
                                        }}
                                    >
                                        <Icon name="pencil" size={22} color={customMechanic ? '#3B82F6' : '#94A3B8'} />
                                        <Text style={[st.categoryOptionText, customMechanic && st.categoryOptionTextActive, { marginLeft: 12, flex: 1 }]}>
                                            DİĞER
                                        </Text>
                                        {customMechanic && <Icon name="check-circle" size={22} color="#3B82F6" />}
                                    </TouchableOpacity>
                                    <View style={{height:30}}/>
                                </ScrollView>
                            </View>
                        )}
                    </View>
                </View>
            </Modal>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16 },
    backBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F8FAFC', alignItems: 'center', justifyContent: 'center' },
    headerCenter: { flex: 1, alignItems: 'center' },
    headerTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A', marginTop: 8 },
    headerSubtitle: { fontSize: 12, fontWeight: '600', color: '#64748B', marginTop: 2 },
    
    listContent: { padding: 16, paddingBottom: 100 },
    
    kpiContainer: { marginBottom: 20 },
    kpiRow: { flexDirection: 'row', justifyContent: 'space-between' },
    kpiCard: { padding: 20, borderRadius: 24, shadowColor: '#000', shadowOffset: { width: 0, height: 12 }, shadowopacity: 15, shadowRadius: 16, elevation: 10, position: 'relative', overflow: 'hidden' },
    kpiGlowTop: { position: 'absolute', top: 0, left: 0, right: 0, height: 2, backgroundColor: 'rgba(255,255,255,0.6)', opacity: 0.8 },
    kpiIconBg: { position: 'absolute', right: -15, bottom: -15, transform: [{ rotate: '-15deg' }] },
    kpiTitle: { fontSize: 13, fontWeight: '800', color: 'rgba(255,255,255,0.9)', marginBottom: 6, textShadowColor: 'rgba(0,0,0,0.2)', textShadowOffset: { width: 0, height: 1 }, textShadowRadius: 2 },
    kpiValue: { fontSize: 32, fontWeight: '900', color: '#FFF', letterSpacing: -1, marginBottom: 4, textShadowColor: 'rgba(0,0,0,0.3)', textShadowOffset: { width: 0, height: 2 }, textShadowRadius: 4 },
    kpiSub: { fontSize: 11, color: 'rgba(255,255,255,0.8)', fontWeight: '600' },

    filterCard: { backgroundColor: '#fff', borderRadius: 20, padding: 16, marginTop: 16, shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.1, shadowRadius: 8, elevation: 3 },
    filterCardTitle: { fontSize: 13, fontWeight: '800', color: '#64748B', marginBottom: 12 },
    filterBtn: { flex: 2, backgroundColor: '#3B82F6', borderRadius: 10, height: 44, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6 },
    filterBtnText: { color: '#fff', fontWeight: '800', fontSize: 14 },
    filterClearBtn: { flex: 1, backgroundColor: '#F1F5F9', borderRadius: 10, height: 44, alignItems: 'center', justifyContent: 'center' },
    filterClearText: { color: '#64748B', fontWeight: '700', fontSize: 14 },

    card: { backgroundColor: '#fff', borderRadius: 24, padding: 16, marginBottom: 16, shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.15, shadowRadius: 12, elevation: 4, borderLeftWidth: 5 },
    cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 },
    iconBox: { width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
    cardTitle: { fontSize: 15, fontWeight: '800', color: '#0F172A', marginBottom: 2 },
    cardPlate: { fontSize: 11, fontWeight: '700', color: '#2563EB', backgroundColor: '#EFF6FF', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4, overflow: 'hidden' },
    statusBadge: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#ECFDF5', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4 },
    statusDot: { width: 4, height: 4, borderRadius: 2, backgroundColor: '#10B981', marginRight: 4 },
    statusText: { fontSize: 10, fontWeight: '700', color: '#10B981' },
    cardDesc: { fontSize: 12, color: '#64748B', fontWeight: '500', marginTop: 4 },
    amountTextSmall: { fontSize: 14, fontWeight: '900', color: '#0F172A' },
    
    cardGrid: { backgroundColor: '#F8FAFC', borderRadius: 16, padding: 12, borderWidth: 1, borderColor: '#F1F5F9' },
    gridRow: { flexDirection: 'row', alignItems: 'flex-start' },
    gridCol: { flex: 1, paddingVertical: 4 },
    gridDivider: { width: 1, backgroundColor: '#E2E8F0', marginHorizontal: 12 },
    gridHorizontalDivider: { height: 1, backgroundColor: '#E2E8F0', marginVertical: 8 },
    gridLabelRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 6, gap: 4 },
    gridLabel: { fontSize: 10, fontWeight: '800', color: '#64748B', letterSpacing: 0.5 },
    gridValue: { fontSize: 13, fontWeight: '800', color: '#1E293B', marginBottom: 2 },
    gridSubValue: { fontSize: 10, color: '#94A3B8', fontWeight: '600' },

    headerAddBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#3B82F6', alignItems: 'center', justifyContent: 'center', shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 6, elevation: 4 },
    headerSettingsBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },

    exportActionsRow: { flexDirection: 'row', justifyContent: 'flex-end', gap: 12, paddingHorizontal: 16, marginBottom: 16, marginTop: -4 },
    exportBtn: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 8, borderWidth: 1, gap: 6 },
    exportBtnText: { fontSize: 13, fontWeight: '700' },

    // Modal
    modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.6)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#fff', borderTopLeftRadius: 24, borderTopRightRadius: 24, maxHeight: '90%' },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 20, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A' },
    modalClose: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    inputLabel: { fontSize: 13, fontWeight: '700', color: '#475569', marginBottom: 8, marginTop: 16 },
    saveBtn: { backgroundColor: '#3B82F6', borderRadius: 12, paddingVertical: 16, alignItems: 'center', marginTop: 24 },
    saveBtnText: { color: '#fff', fontSize: 15, fontWeight: '800' },

    // Select button styles
    selectBtn: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', backgroundColor: '#F8FAFC', borderWidth: 1, borderColor: '#E2E8F0', borderRadius: 12, paddingHorizontal: 16, height: 50 },
    selectBtnText: { fontSize: 14, color: '#0F172A', fontWeight: '600' },
    helperText: { fontSize: 11, color: '#64748B', marginTop: 4, marginLeft: 4 },
    
    // Category modal options
    categoryOption: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14, paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    categoryOptionActive: { backgroundColor: '#EFF6FF', borderRadius: 8, borderBottomWidth: 0 },
    categoryOptionText: { fontSize: 14, color: '#334155', fontWeight: '500' },
    categoryOptionTextActive: { color: '#3B82F6', fontWeight: '700' },

    suggestionPill: { backgroundColor: '#EFF6FF', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 16, marginRight: 8, borderWidth: 1, borderColor: '#BFDBFE' },
    suggestionText: { color: '#1D4ED8', fontSize: 12, fontWeight: '600' },
});
