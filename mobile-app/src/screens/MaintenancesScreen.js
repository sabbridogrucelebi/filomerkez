import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, StyleSheet, FlatList, ActivityIndicator, Alert, Text, Platform, TouchableOpacity, RefreshControl, Modal, ScrollView, Dimensions, KeyboardAvoidingView, Animated, Easing, TextInput } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import * as IntentLauncher from 'expo-intent-launcher';
import { BlurView } from 'expo-blur';
import { Image } from 'expo-image';
import api from '../api/axios';
import { emoji } from '../emoji';
import { AuthContext } from '../context/AuthContext';
import DatePickerInput from '../components/DatePickerInput';
import dayjs from 'dayjs';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');
const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(v || 0);
const fmtKm = (v) => new Intl.NumberFormat('tr-TR').format(v || 0);

const SelectInput = ({ icon, placeholder, value, options, onSelect }) => {
    const [open, setOpen] = useState(false);
    const selected = options.find(o => o.value === value);

    return (
        <>
            <TouchableOpacity style={s.fieldWrap} onPress={() => setOpen(true)} activeOpacity={0.7}>
                {icon && <Icon name={icon} size={20} color="#94A3B8" style={s.fieldIcon} />}
                <Text style={[s.fieldInput, { color: selected ? '#F8FAFC' : '#64748B', paddingTop: Platform.OS === 'ios' ? 16 : 14 }]}>
                    {selected ? selected.label : placeholder}
                </Text>
                <Icon name="chevron-down" size={20} color="#94A3B8" />
            </TouchableOpacity>

            <Modal visible={open} transparent animationType="fade">
                <BlurView intensity={40} tint="dark" style={s.modalOverlayCenter}>
                    <TouchableOpacity style={StyleSheet.absoluteFill} activeOpacity={1} onPress={() => setOpen(false)} />
                    <View style={s.centerModal}>
                        <LinearGradient colors={['rgba(255,255,255,0.05)', 'rgba(255,255,255,0.01)']} style={StyleSheet.absoluteFillObject} />
                        <Text style={s.modalTitle}>{placeholder}</Text>
                        <ScrollView style={{ maxHeight: 300 }} showsVerticalScrollIndicator={false}>
                            {options.map((opt, i) => (
                                <TouchableOpacity 
                                    key={i} 
                                    style={[s.menuItem, value === opt.value && { backgroundColor: 'rgba(59,130,246,0.1)' }]}
                                    onPress={() => { onSelect(opt.value); setOpen(false); }}
                                >
                                    <Text style={[s.menuText, value === opt.value ? { color: '#60A5FA', fontWeight: '800' } : { color: '#CBD5E1' }]}>{opt.label}</Text>
                                    {value === opt.value && <Icon name="check" size={20} color="#60A5FA" style={{ position: 'absolute', right: 16 }} />}
                                </TouchableOpacity>
                            ))}
                        </ScrollView>
                    </View>
                </BlurView>
            </Modal>
        </>
    );
};

export default function MaintenancesScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const insets = useSafeAreaInsets();
    
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
    const [showFilters, setShowFilters] = useState(false);

    const [modalVisible, setModalVisible] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [formData, setFormData] = useState({
        vehicle_id: '', service_date: new Date().toISOString().split('T')[0], maintenance_type: '',
        title: '', km: '', amount: '', service_name: '', description: '', next_service_km: ''
    });

    const categories = ['YAĞ BAKIMI', 'ALT YAĞLAMA', 'LASTİK BAKIMI', 'AKÜ BAKIMI', 'AĞIR BAKIM', 'ANTFRİZ BAKIMI', 'ARIZA/ONARIM', 'MUAYENE', 'DİĞER BAKIMLAR'];

    // Animations
    const scrollY = useRef(new Animated.Value(0)).current;
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;
    const flipAnims = useRef([...Array(60)].map(() => new Animated.Value(0))).current;

    useEffect(() => {
        const loop = Animated.loop(
            Animated.sequence([
                Animated.timing(blob1Anim, { toValue: 1, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob1Anim, { toValue: 0, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        const loop2 = Animated.loop(
            Animated.sequence([
                Animated.timing(blob2Anim, { toValue: 1, duration: 10000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob2Anim, { toValue: 0, duration: 10000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        loop.start(); loop2.start();
        return () => { loop.stop(); loop2.stop(); };
    }, []);

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
            
            if (mRes.data?.data) {
                setMaintenances(mRes.data.data.maintenances || []);
                setSummary(mRes.data.data.summary || { total_count: 0, this_month_count: 0, total_cost: 0 });
            }
            if (oRes.data?.data) {
                if (oRes.data.data.vehicles) setVehicles(oRes.data.data.vehicles);
                if (oRes.data.data.mechanics) setMechanics(oRes.data.data.mechanics);
                if (oRes.data.data.noteSuggestions) setNoteSuggestions(oRes.data.data.noteSuggestions);
                if (oRes.data.data.titleSuggestions) setTitleSuggestions(oRes.data.data.titleSuggestions);
            }

            // Stagger animation
            flipAnims.forEach(a => a.setValue(0));
            Animated.stagger(80, flipAnims.map((anim) => 
                Animated.spring(anim, { toValue: 1, friction: 7, tension: 40, useNativeDriver: true })
            )).start();

        } catch (e) {
            Alert.alert('Hata', 'Veriler yüklenirken bir sorun oluştu.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => { fetchData(); }, [activeFilters]);

    const openAdd = () => {
        if (!hasPermission('maintenances.create')) { Alert.alert('Yetki Yok', 'Bakım kaydı ekleme yetkiniz bulunmuyor.'); return; }
        setEditingId(null);
        setFormData({
            vehicle_id: '', service_date: new Date().toISOString().split('T')[0], maintenance_type: '',
            title: '', km: '', next_service_km: '', amount: '', service_name: '', description: ''
        });
        setModalVisible(true);
    };

    const openEdit = (item) => {
        if (!hasPermission('maintenances.edit')) { Alert.alert('Yetki Yok', 'Bakım kaydı düzenleme yetkiniz bulunmuyor.'); return; }
        setEditingId(item.id);
        setFormData({
            vehicle_id: item.vehicle_id ? item.vehicle_id.toString() : '',
            service_date: item.date ? item.date.split('T')[0] : new Date().toISOString().split('T')[0],
            maintenance_type: item.type || item.maintenance_type || '',
            title: item.title || '',
            km: item.km ? item.km.toString() : '',
            next_service_km: item.next_km ? item.next_km.toString() : '',
            amount: item.amount ? item.amount.toString() : '',
            service_name: item.service_name || '',
            description: item.description || ''
        });
        setModalVisible(true);
    };

    const handleSave = async () => {
        if (!formData.vehicle_id || !formData.title || !formData.service_date || !formData.maintenance_type) {
            Alert.alert('Eksik Bilgi', 'Araç, Tarih, Kategori ve İşlem Adı zorunludur.'); return;
        }
        setSaving(true);
        try {
            const url = editingId ? `/v1/maintenances/${editingId}` : '/v1/maintenances';
            const method = editingId ? 'PUT' : 'POST';
            await api({ method, url, data: formData });
            
            setModalVisible(false);
            fetchData();
        } catch (e) {
            Alert.alert('Hata', 'Kayıt işlemi başarısız oldu.');
        } finally {
            setSaving(false);
        }
    };

    const confirmDelete = (id) => {
        if (!hasPermission('maintenances.delete')) { Alert.alert('Yetki Yok', 'Yetkiniz bulunmuyor.'); return; }
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
            let token = Platform.OS === 'web' ? await AsyncStorage.getItem('userToken') : await SecureStore.getItemAsync('userToken');
            if (!token) throw new Error('Token not found');

            const params = new URLSearchParams();
            if (activeFilters.search) params.append('search', activeFilters.search);
            if (activeFilters.start_date) params.append('start_date', activeFilters.start_date);
            if (activeFilters.end_date) params.append('end_date', activeFilters.end_date);

            const endpoint = `/v1/maintenances/export-${format}?${params.toString()}`;
            const url = api.defaults.baseURL + endpoint;

            const filename = `Bakim_Raporu_${new Date().getTime()}.${format === 'excel' ? 'xlsx' : 'pdf'}`;
            const fileUri = FileSystem.documentDirectory + filename;

            const downloadRes = await FileSystem.downloadAsync(url, fileUri, { headers: { Authorization: `Bearer ${token}` } });
            
            if (downloadRes.status !== 200) { Alert.alert('Hata', 'Rapor oluşturulamadı.'); return; }

            if (isView && Platform.OS === 'android') {
                const cUri = await FileSystem.getContentUriAsync(downloadRes.uri);
                await IntentLauncher.startActivityAsync('android.intent.action.VIEW', { data: cUri, flags: 1, type: format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            } else {
                if (await Sharing.isAvailableAsync()) {
                    await Sharing.shareAsync(downloadRes.uri, { UTI: format === 'pdf' ? 'com.adobe.pdf' : 'com.microsoft.excel.xls', dialogTitle: isView ? 'Görüntüle' : 'Dosyayı Paylaş' });
                } else {
                    Alert.alert('Başarılı', 'Rapor indirildi: ' + downloadRes.uri);
                }
            }
        } catch (e) {
            Alert.alert('Hata', 'İndirme işlemi başarısız oldu.');
        } finally {
            setDownloadingFormat(null);
        }
    };

    const getTypeStyle = (type) => {
        const t = (type || '').toUpperCase();
        if (t.includes('LASTİK')) return { color: '#34D399', icon: 'tire', bg: 'rgba(16,185,129,0.15)', border: 'rgba(16,185,129,0.3)' };
        if (t.includes('AKÜ')) return { color: '#FBBF24', icon: 'car-battery', bg: 'rgba(245,158,11,0.15)', border: 'rgba(245,158,11,0.3)' };
        if (t.includes('AĞIR')) return { color: '#F87171', icon: 'car-wrench', bg: 'rgba(239,68,68,0.15)', border: 'rgba(239,68,68,0.3)' };
        if (t.includes('ANTFRİZ') || t.includes('ANTİFRİZ')) return { color: '#60A5FA', icon: 'snowflake', bg: 'rgba(59,130,246,0.15)', border: 'rgba(59,130,246,0.3)' };
        if (t.includes('ALT YAĞLAMA')) return { color: '#22D3EE', icon: 'wrench', bg: 'rgba(6,182,212,0.15)', border: 'rgba(6,182,212,0.3)' };
        if (t.includes('YAĞ')) return { color: '#FACC15', icon: 'oil', bg: 'rgba(234,179,8,0.15)', border: 'rgba(234,179,8,0.3)' };
        if (t.includes('ARIZA')) return { color: '#F87171', icon: 'alert-octagon-outline', bg: 'rgba(239,68,68,0.15)', border: 'rgba(239,68,68,0.3)' };
        if (t.includes('MUAYENE')) return { color: '#A78BFA', icon: 'shield-check-outline', bg: 'rgba(139,92,246,0.15)', border: 'rgba(139,92,246,0.3)' };
        return { color: '#818CF8', icon: 'tools', bg: 'rgba(99,102,241,0.15)', border: 'rgba(99,102,241,0.3)' };
    };

    const renderCard = ({ item, index }) => {
        const animIndex = index % 60;
        const flipAnim = flipAnims[animIndex] || new Animated.Value(1);
        const ts = getTypeStyle(item.type || item.maintenance_type);

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
                <BlurView intensity={25} tint="dark" style={[s.card, { borderColor: ts.border }]}>
                    <View style={s.cardHeader}>
                        <View style={[s.iconBox, { backgroundColor: ts.bg }]}>
                            <Icon name={ts.icon} size={26} color={ts.color} />
                        </View>
                        <View style={s.cardInfo}>
                            <Text style={s.cardTitle}>{item.title}</Text>
                            <View style={s.amountRow}>
                                <Text style={s.amountText}>{fmtMoney(item.amount)}</Text>
                                <View style={s.statusBadge}>
                                    <View style={s.statusDot} />
                                    <Text style={s.statusText}>Tamamlandı</Text>
                                </View>
                            </View>
                            {item.description ? (
                                <Text style={s.cardDesc} numberOfLines={2}>{item.description}</Text>
                            ) : null}
                        </View>
                        <View style={s.cardActions}>
                            <View style={s.plateBadge}>
                                <Text style={s.plateText}>{item.vehicle?.plate || '?'}</Text>
                            </View>
                            <View style={{ flexDirection: 'row', gap: 12, marginTop: 12 }}>
                                <TouchableOpacity onPress={() => openEdit(item)}>
                                    <Icon name="pencil" size={22} color="#60A5FA" />
                                </TouchableOpacity>
                                <TouchableOpacity onPress={() => confirmDelete(item.id)}>
                                    <Icon name="trash-can" size={22} color="#F87171" />
                                </TouchableOpacity>
                            </View>
                        </View>
                    </View>

                    <View style={s.cardGrid}>
                        <View style={s.gridRow}>
                            <View style={s.gridCol}>
                                <Text style={s.gridLabel}>TÜR</Text>
                                <Text style={s.gridValue}>{item.maintenance_type || item.type || '-'}</Text>
                            </View>
                            <View style={s.gridDivider} />
                            <View style={s.gridCol}>
                                <Text style={[s.gridLabel, { color: '#FBBF24' }]}>TARİH</Text>
                                <Text style={[s.gridValue, { color: '#F8FAFC' }]}>{item.service_date || item.date ? dayjs(item.service_date || item.date).format('DD.MM.YYYY') : '-'}</Text>
                                <Text style={[s.gridSub, { color: '#FCD34D' }]}>{item.next_service_date || item.next_date ? `Sonraki: ${dayjs(item.next_service_date || item.next_date).format('DD.MM.YYYY')}` : 'Sonraki tarih yok'}</Text>
                            </View>
                        </View>
                        <View style={s.gridHDivider} />
                        <View style={s.gridRow}>
                            <View style={s.gridCol}>
                                <Text style={s.gridLabel}>SERVİS</Text>
                                <Text style={s.gridValue} numberOfLines={1}>{item.service_name || '-'}</Text>
                            </View>
                            <View style={s.gridDivider} />
                            <View style={s.gridCol}>
                                <Text style={[s.gridLabel, { color: '#34D399' }]}>KİLOMETRE</Text>
                                <Text style={[s.gridValue, { color: '#F8FAFC' }]}>{item.km ? `${fmtKm(item.km)} KM` : '-'}</Text>
                                <Text style={[s.gridSub, { color: '#6EE7B7' }]}>{item.next_service_km || item.next_km ? `Sonraki: ${fmtKm(item.next_service_km || item.next_km)} KM` : 'Sonraki KM yok'}</Text>
                            </View>
                        </View>
                    </View>
                </BlurView>
            </Animated.View>
        );
    };

    const renderHeader = () => (
        <View style={{ marginBottom: 20 }}>
            {/* KPIs */}
            <View style={s.kpiRow}>
                <View style={s.kpiWrapper}>
                    <BlurView intensity={20} tint="dark" style={[s.kpiCardFix, { borderColor: 'rgba(59,130,246,0.3)' }]}>
                        <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(59,130,246,0.15)' }]}><Icon name="tools" size={24} color="#60A5FA" /></View>
                        <Text style={s.kpiValue}>{summary.total_count}</Text>
                        <Text style={s.kpiLabel}>Toplam Bakım</Text>
                    </BlurView>
                </View>
                <View style={[s.kpiWrapper, { marginHorizontal: 12 }]}>
                    <BlurView intensity={20} tint="dark" style={[s.kpiCardFix, { borderColor: 'rgba(16,185,129,0.3)' }]}>
                        <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(16,185,129,0.15)' }]}><Icon name="calendar-check" size={24} color="#34D399" /></View>
                        <Text style={s.kpiValue}>{summary.this_month_count}</Text>
                        <Text style={s.kpiLabel}>Bu Ay Yapılan</Text>
                    </BlurView>
                </View>
                <View style={s.kpiWrapper}>
                    <BlurView intensity={20} tint="dark" style={[s.kpiCardFix, { borderColor: 'rgba(239,68,68,0.3)' }]}>
                        <View style={[s.kpiIconWrap, { backgroundColor: 'rgba(239,68,68,0.15)' }]}><Icon name="currency-try" size={24} color="#F87171" /></View>
                        <Text style={s.kpiValue} numberOfLines={1} adjustsFontSizeToFit>{fmtMoney(summary.total_cost)}</Text>
                        <Text style={s.kpiLabel}>Top. Maliyet</Text>
                    </BlurView>
                </View>
            </View>

            {/* Actions & Filters */}
            <View style={s.actionsRow}>
                <TouchableOpacity style={s.actionBtn} onPress={() => handleDownload('excel')} disabled={downloadingFormat !== null}>
                    <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                    {downloadingFormat === 'excel' ? <ActivityIndicator size="small" color="#34D399" /> : <Icon name="file-excel" size={20} color="#34D399" />}
                </TouchableOpacity>
                <TouchableOpacity style={s.actionBtn} onPress={() => handleDownload('pdf')} disabled={downloadingFormat !== null}>
                    <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                    {downloadingFormat === 'pdf' ? <ActivityIndicator size="small" color="#F87171" /> : <Icon name="file-pdf-box" size={20} color="#F87171" />}
                </TouchableOpacity>
                <TouchableOpacity style={s.actionBtn} onPress={() => handleDownload('pdf', true)} disabled={downloadingFormat !== null}>
                    <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                    {downloadingFormat === 'view' ? <ActivityIndicator size="small" color="#60A5FA" /> : <Icon name="eye" size={20} color="#60A5FA" />}
                </TouchableOpacity>
                <TouchableOpacity style={[s.actionBtn, { flex: 2, backgroundColor: showFilters ? 'rgba(59,130,246,0.3)' : 'transparent' }]} onPress={() => setShowFilters(!showFilters)}>
                    <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                    <Icon name="filter-variant" size={20} color="#F8FAFC" />
                    <Text style={{ color: '#F8FAFC', fontWeight: '800', marginLeft: 8 }}>Filtrele</Text>
                </TouchableOpacity>
            </View>
        </View>
    );

    return (
        <View style={s.container}>
            {/* 3D Animated Background */}
            <Animated.View style={[StyleSheet.absoluteFill, { transform: [{ translateY: scrollY.interpolate({ inputRange: [-100, 0, 500], outputRange: [-20, 0, 100], extrapolate: 'clamp' }) }] }]}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[s.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 50] }) }, { scale: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.2] }) }] }]} />
                <Animated.View style={[s.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -50] }) }, { scale: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.3] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                {/* Header */}
                <View style={s.header}>
                    <View style={{ flex: 1 }}>
                        <Text style={s.headerTitle}>Bakım & Tamir</Text>
                        <Text style={s.headerSub}>Tüm Araç Bakım Kayıtları</Text>
                    </View>
                    <View style={{ flexDirection: 'row', gap: 10 }}>
                        {hasPermission('maintenances.view') && (
                            <TouchableOpacity style={s.headerIconBtn} onPress={() => navigation.navigate('MaintenanceSettings')}>
                                <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                                <Icon name="cog" size={22} color="#FFF" />
                            </TouchableOpacity>
                        )}
                        {hasPermission('maintenances.create') && (
                            <TouchableOpacity style={s.headerIconBtn} onPress={openAdd}>
                                <LinearGradient colors={['#8B5CF6', '#4F46E5']} style={StyleSheet.absoluteFillObject} />
                                <Icon name="plus" size={24} color="#FFF" />
                            </TouchableOpacity>
                        )}
                    </View>
                </View>

                {/* Filter Modal Overlay */}
                <Modal visible={showFilters} transparent animationType="slide">
                    <BlurView intensity={40} tint="dark" style={s.modalOverlay}>
                        <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setShowFilters(false)} />
                        <BlurView intensity={50} tint="dark" style={[s.bottomSheet, { paddingBottom: Math.max(insets.bottom, 20) }]}>
                            <View style={s.sheetHandle} />
                            <Text style={s.sheetTitle}>Kayıtları Filtrele</Text>
                            <View style={{ flexDirection: 'row', gap: 12, marginBottom: 16 }}>
                                <View style={{ flex: 1 }}>
                                    <DatePickerInput label="BAŞLANGIÇ" value={filters.start_date} onChange={(d) => setFilters({...filters, start_date: d})} darkTheme />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <DatePickerInput label="BİTİŞ" value={filters.end_date} onChange={(d) => setFilters({...filters, end_date: d})} darkTheme />
                                </View>
                            </View>
                            <View style={s.fieldWrap}>
                                <Icon name="magnify" size={20} color="#94A3B8" style={{ marginRight: 10 }} />
                                <TextInput style={s.fieldInput} placeholderTextColor="#64748B" placeholder="Plaka, servis, bakım adı..." value={filters.search} onChangeText={t => setFilters({...filters, search: t})} />
                            </View>
                            <View style={s.formActions}>
                                <TouchableOpacity style={s.cancelBtn} onPress={() => { setFilters({ search: '', start_date: '', end_date: '' }); setActiveFilters({ search: '', start_date: '', end_date: '' }); setShowFilters(false); }}>
                                    <Text style={s.cancelBtnText}>Temizle</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={s.saveBtn} onPress={() => { setActiveFilters(filters); setShowFilters(false); }}>
                                    <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />
                                    <Text style={s.saveBtnText}>Uygula</Text>
                                </TouchableOpacity>
                            </View>
                        </BlurView>
                    </BlurView>
                </Modal>

                {/* List */}
                {loading ? (
                    <View style={s.loader}><ActivityIndicator size="large" color="#60A5FA" /></View>
                ) : (
                    <Animated.FlatList
                        onScroll={Animated.event([{ nativeEvent: { contentOffset: { y: scrollY } } }], { useNativeDriver: true })}
                        scrollEventThrottle={16}
                        data={maintenances}
                        keyExtractor={item => item.id.toString()}
                        renderItem={renderCard}
                        ListHeaderComponent={renderHeader}
                        contentContainerStyle={{ paddingHorizontal: 20, paddingBottom: 100, paddingTop: 10 }}
                        showsVerticalScrollIndicator={false}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => fetchData(true)} tintColor="#60A5FA" />}
                        ListEmptyComponent={
                            <View style={s.empty}>
                                <Image source={emoji('Objects/Hammer and Wrench')} style={{width: 64, height: 64, opacity: 0.8}} resizeMode="contain" />
                                <Text style={s.emptyText}>Bakım kaydı bulunamadı.</Text>
                            </View>
                        }
                    />
                )}

            </SafeAreaView>

            {/* Add/Edit Form Modal */}
            <Modal visible={modalVisible} transparent animationType="slide">
                <BlurView intensity={40} tint="dark" style={s.modalOverlay}>
                    <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setModalVisible(false)} />
                    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ width: '100%', flex: 1, justifyContent: 'flex-end' }}>
                        <BlurView intensity={50} tint="dark" style={[s.formSheet, { paddingBottom: Math.max(insets.bottom, 20), maxHeight: SCREEN_HEIGHT * 0.9 }]}>
                            <View style={s.sheetHeader}>
                                <Text style={s.sheetTitle}>{editingId ? 'Bakım Düzenle' : 'Yeni Bakım Ekle'}</Text>
                                <TouchableOpacity onPress={() => setModalVisible(false)} style={s.closeIcon}>
                                    <Icon name="close" size={22} color="#CBD5E1" />
                                </TouchableOpacity>
                            </View>
                            
                            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: 20 }}>
                                <Text style={s.sectionTitle}>Temel Bilgiler</Text>
                                <SelectInput icon="car" placeholder="Araç Seçin *" value={formData.vehicle_id} options={vehicles.map(v => ({ label: v.plate, value: v.id.toString() }))} onSelect={v => setFormData({...formData, vehicle_id: v})} />
                                <View style={{ marginBottom: 12 }}>
                                    <DatePickerInput label="Tarih *" value={formData.service_date} onChange={d => setFormData({...formData, service_date: d})} darkTheme />
                                </View>
                                <SelectInput icon="tag" placeholder="Kategori Seçin *" value={formData.maintenance_type} options={categories.map(c => ({ label: c, value: c }))} onSelect={v => setFormData({...formData, maintenance_type: v})} />

                                <Text style={s.sectionTitle}>Detaylar</Text>
                                <View style={s.fieldWrap}>
                                    <Icon name="text" size={20} color="#94A3B8" style={s.fieldIcon} />
                                    <TextInput style={s.fieldInput} placeholderTextColor="#64748B" placeholder="İşlem Adı *" value={formData.title} onChangeText={t => setFormData({...formData, title: t})} />
                                </View>
                                {titleSuggestions?.length > 0 && formData.title?.length > 0 && (
                                    <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 12 }}>
                                        {titleSuggestions.filter(s => s && s.toUpperCase().includes(formData.title.toUpperCase()) && s.toUpperCase() !== formData.title.toUpperCase()).map((s, idx) => (
                                            <TouchableOpacity key={idx} style={s.suggestionPill} onPress={() => setFormData({...formData, title: s})}>
                                                <Text style={s.suggestionText}>{s}</Text>
                                            </TouchableOpacity>
                                        ))}
                                    </ScrollView>
                                )}

                                <View style={{ flexDirection: 'row', gap: 10 }}>
                                    <View style={[s.fieldWrap, { flex: 1 }]}>
                                        <Icon name="speedometer" size={20} color="#94A3B8" style={s.fieldIcon} />
                                        <TextInput style={s.fieldInput} placeholderTextColor="#64748B" placeholder="Mevcut KM" value={formData.km} onChangeText={t => setFormData({...formData, km: t})} keyboardType="numeric" />
                                    </View>
                                    <View style={[s.fieldWrap, { flex: 1 }]}>
                                        <Icon name="speedometer-medium" size={20} color="#94A3B8" style={s.fieldIcon} />
                                        <TextInput style={s.fieldInput} placeholderTextColor="#64748B" placeholder="Sonraki KM" value={formData.next_service_km} onChangeText={t => setFormData({...formData, next_service_km: t})} keyboardType="numeric" />
                                    </View>
                                </View>

                                <View style={{ flexDirection: 'row', gap: 10 }}>
                                    <View style={[s.fieldWrap, { flex: 1 }]}>
                                        <Icon name="currency-try" size={20} color="#94A3B8" style={s.fieldIcon} />
                                        <TextInput style={s.fieldInput} placeholderTextColor="#64748B" placeholder="Tutar" value={formData.amount} onChangeText={t => setFormData({...formData, amount: t})} keyboardType="numeric" />
                                    </View>
                                    <View style={[s.fieldWrap, { flex: 1 }]}>
                                        <Icon name="store" size={20} color="#94A3B8" style={s.fieldIcon} />
                                        <TextInput style={s.fieldInput} placeholderTextColor="#64748B" placeholder="Servis/Usta" value={formData.service_name} onChangeText={t => setFormData({...formData, service_name: t})} />
                                    </View>
                                </View>

                                <View style={[s.fieldWrap, { height: 80, alignItems: 'flex-start', paddingTop: 12 }]}>
                                    <Icon name="text-box-outline" size={20} color="#94A3B8" style={s.fieldIcon} />
                                    <TextInput style={[s.fieldInput, { textAlignVertical: 'top' }]} placeholderTextColor="#64748B" placeholder="Notlar..." value={formData.description} onChangeText={t => setFormData({...formData, description: t})} multiline numberOfLines={3} />
                                </View>
                                <View style={{ height: 40 }} />
                            </ScrollView>

                            <View style={s.formActionsWrap}>
                                <TouchableOpacity style={s.cancelBtn} onPress={() => setModalVisible(false)}>
                                    <Text style={s.cancelBtnText}>Vazgeç</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={s.saveBtn} onPress={handleSave} disabled={saving}>
                                    <LinearGradient colors={['#8B5CF6', '#4F46E5']} style={StyleSheet.absoluteFillObject} />
                                    {saving ? <ActivityIndicator color="#FFF" /> : <Text style={s.saveBtnText}>{editingId ? 'Güncelle' : 'Kaydet'}</Text>}
                                </TouchableOpacity>
                            </View>
                        </BlurView>
                    </KeyboardAvoidingView>
                </BlurView>
            </Modal>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -100, right: -100, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(99, 102, 241, 0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, left: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(236, 72, 153, 0.12)', filter: 'blur(40px)' },
    
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: Platform.OS === 'ios' ? 10 : 30, paddingBottom: 16 },
    headerTitle: { fontSize: 30, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5, textShadowColor: 'rgba(255,255,255,0.3)', textShadowOffset: { width: 0, height: 0 }, textShadowRadius: 10 },
    headerSub: { fontSize: 13, color: '#94A3B8', fontWeight: '500', marginTop: 4 },
    headerIconBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    
    kpiRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 16 },
    kpiWrapper: { flex: 1 },
    kpiCardFix: { padding: 14, borderRadius: 20, overflow: 'hidden', justifyContent: 'space-between', borderWidth: 1 },
    kpiIconWrap: { width: 36, height: 36, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginBottom: 12 },
    kpiValue: { fontSize: 22, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5 },
    kpiLabel: { fontSize: 11, color: '#94A3B8', fontWeight: '700', marginTop: 4 },

    actionsRow: { flexDirection: 'row', gap: 10 },
    actionBtn: { flex: 1, height: 44, borderRadius: 14, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', flexDirection: 'row' },

    loader: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingTop: 100 },
    empty: { alignItems: 'center', marginTop: 80 },
    emptyText: { fontSize: 15, color: '#94A3B8', fontWeight: '600', marginTop: 12 },

    cardWrapper: { marginBottom: 16 },
    card: { borderRadius: 24, borderWidth: 1, overflow: 'hidden' },
    cardHeader: { flexDirection: 'row', alignItems: 'flex-start', padding: 20 },
    iconBox: { width: 48, height: 48, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },
    cardInfo: { flex: 1, marginLeft: 14 },
    cardTitle: { fontSize: 16, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.2, marginBottom: 6 },
    amountRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 8 },
    amountText: { fontSize: 16, fontWeight: '900', color: '#34D399' },
    statusBadge: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(16,185,129,0.15)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, borderWidth: 1, borderColor: 'rgba(16,185,129,0.3)' },
    statusDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#34D399', marginRight: 6 },
    statusText: { fontSize: 10, fontWeight: '900', color: '#34D399' },
    cardDesc: { fontSize: 13, color: '#94A3B8', fontWeight: '500' },
    cardActions: { alignItems: 'flex-end' },
    plateBadge: { backgroundColor: 'rgba(59,130,246,0.15)', paddingHorizontal: 10, paddingVertical: 6, borderRadius: 10, borderWidth: 1, borderColor: 'rgba(59,130,246,0.3)' },
    plateText: { fontSize: 13, fontWeight: '900', color: '#60A5FA' },

    cardGrid: { backgroundColor: 'rgba(0,0,0,0.2)', padding: 16, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
    gridRow: { flexDirection: 'row' },
    gridCol: { flex: 1 },
    gridDivider: { width: 1, backgroundColor: 'rgba(255,255,255,0.1)', marginHorizontal: 16 },
    gridHDivider: { height: 1, backgroundColor: 'rgba(255,255,255,0.1)', marginVertical: 12 },
    gridLabel: { fontSize: 10, fontWeight: '800', color: '#64748B', letterSpacing: 0.5, marginBottom: 4 },
    gridValue: { fontSize: 14, fontWeight: '800', color: '#CBD5E1' },
    gridSub: { fontSize: 11, fontWeight: '600', marginTop: 2 },

    modalOverlay: { flex: 1, justifyContent: 'flex-end' },
    modalOverlayCenter: { flex: 1, justifyContent: 'center', padding: 20 },
    bottomSheet: { borderTopLeftRadius: 32, borderTopRightRadius: 32, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginBottom: 20 },
    sheetTitle: { fontSize: 22, fontWeight: '900', color: '#F8FAFC', marginBottom: 24 },
    
    formSheet: { borderTopLeftRadius: 32, borderTopRightRadius: 32, paddingTop: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, marginBottom: 20 },
    closeIcon: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', justifyContent: 'center' },
    sectionTitle: { fontSize: 13, fontWeight: '900', color: '#8B5CF6', letterSpacing: 1, textTransform: 'uppercase', marginBottom: 12, marginTop: 10 },
    
    fieldWrap: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.2)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 14, paddingHorizontal: 16, height: 54, marginBottom: 12 },
    fieldIcon: { marginRight: 12 },
    fieldInput: { flex: 1, fontSize: 15, color: '#F8FAFC', fontWeight: '600', height: '100%' },
    
    formActionsWrap: { flexDirection: 'row', padding: 20, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)', backgroundColor: 'rgba(0,0,0,0.3)' },
    formActions: { flexDirection: 'row', marginTop: 10 },
    cancelBtn: { flex: 1, paddingVertical: 16, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', marginRight: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    cancelBtnText: { color: '#94A3B8', fontSize: 15, fontWeight: '800' },
    saveBtn: { flex: 2, borderRadius: 16, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
    saveBtnText: { color: '#FFF', fontSize: 15, fontWeight: '900' },

    suggestionPill: { backgroundColor: 'rgba(59,130,246,0.15)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 12, marginRight: 8, borderWidth: 1, borderColor: 'rgba(59,130,246,0.3)' },
    suggestionText: { fontSize: 12, fontWeight: '700', color: '#60A5FA' },

    centerModal: { backgroundColor: 'rgba(15,23,42,0.8)', borderRadius: 24, padding: 24, maxHeight: '80%', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#F8FAFC', marginBottom: 16 },
    menuItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    menuText: { fontSize: 15, fontWeight: '600' }
});
