import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, StyleSheet, ActivityIndicator, Alert, Text, TouchableOpacity, ScrollView, Modal, Platform, TextInput, KeyboardAvoidingView, Animated, Dimensions, Easing } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import { EmptyState } from '../components';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

const CURRENT_YEAR = new Date().getFullYear();
const YEARS = Array.from({length: 6}, (_, i) => (CURRENT_YEAR - 1 + i).toString());
const MONTHS = [
    { value: '1', label: 'Ocak' }, { value: '2', label: 'Şubat' }, { value: '3', label: 'Mart' },
    { value: '4', label: 'Nisan' }, { value: '5', label: 'Mayıs' }, { value: '6', label: 'Haziran' },
    { value: '7', label: 'Temmuz' }, { value: '8', label: 'Ağustos' }, { value: '9', label: 'Eylül' },
    { value: '10', label: 'Ekim' }, { value: '11', label: 'Kasım' }, { value: '12', label: 'Aralık' }
];

export default function TripsScreen({ route, navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    const params = route?.params || {};

    // Filters
    const [customers, setCustomers] = useState([]);
    const [selectedCustomer, setSelectedCustomer] = useState(params.customer_id ? params.customer_id.toString() : '');
    const [selectedMonth, setSelectedMonth] = useState(params.month ? params.month.toString() : (new Date().getMonth() + 1).toString());
    const [selectedYear, setSelectedYear] = useState(params.year ? params.year.toString() : CURRENT_YEAR.toString());

    // Matrix Data
    const [monthDays, setMonthDays] = useState([]);
    const [serviceRoutes, setServiceRoutes] = useState([]);
    const [matrix, setMatrix] = useState({});
    const [summary, setSummary] = useState(null);
    const [vehicles, setVehicles] = useState([]);

    // Cell Modal
    const [cellModalVisible, setCellModalVisible] = useState(false);
    const [activeCell, setActiveCell] = useState(null);
    const [formData, setFormData] = useState({ price: '', morning_id: '', evening_id: '', status: 'Yapıldı' });

    // Selection Modal
    const [selectionModalVisible, setSelectionModalVisible] = useState(false);
    const [selectionType, setSelectionType] = useState(null); // 'customer' | 'month' | 'year' | 'morning_vehicle' | 'evening_vehicle'

    const [exportingType, setExportingType] = useState(null);

    // Animations
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;

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

    const fetchMatrix = async () => {
        try {
            setLoading(true);
            const res = await api.get('/v1/trips/matrix', {
                params: { customer_id: selectedCustomer, month: selectedMonth, year: selectedYear }
            });
            if (res.data.success) {
                const d = res.data.data;
                setCustomers(d.customers || []);
                if (!selectedCustomer && d.selectedCustomer) {
                    setSelectedCustomer(d.selectedCustomer.id.toString());
                }
                setMonthDays(d.monthDays || []);
                setServiceRoutes(d.serviceRoutes || []);
                setMatrix(d.matrix || {});
                setSummary(d.summary || null);
                setVehicles(d.vehicles || []);
            }
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchMatrix(); }, [selectedCustomer, selectedMonth, selectedYear]);

    const exportReport = async (type) => {
        try {
            setExportingType(type);
            const token = Platform.OS === 'web' 
                ? await AsyncStorage.getItem('userToken') 
                : await require('expo-secure-store').getItemAsync('userToken');
                
            if (!token) return;

            const url = `${api.defaults.baseURL}/v1/trips/export-${type}?customer_id=${selectedCustomer}&month=${selectedMonth}&year=${selectedYear}`;
            const fileExt = type === 'excel' ? 'xlsx' : 'pdf';
            const customerName = selectedCustomerObj?.company_name.replace(/ /g, '_') || 'Firma';
            const monthName = selectedMonthObj?.label || 'Ay';
            const filename = `${customerName}_${monthName}_Puantaj.${fileExt}`;
            const fileUri = `${FileSystem.documentDirectory}${filename}`;

            const downloadRes = await FileSystem.downloadAsync(url, fileUri, { headers: { Authorization: `Bearer ${token}` } });

            if (downloadRes.status === 200) {
                await Sharing.shareAsync(downloadRes.uri, { dialogTitle: 'Puantaj Raporunu Paylaş' });
            }
        } catch (error) {
            console.error("Export Error:", error);
        } finally {
            setExportingType(null);
        }
    };

    const openCellModal = (route, day) => {
        if (!hasPermission('trips.create') && !hasPermission('trips.edit')) {
            Alert.alert('Yetkisiz', 'Veri girişi yapmaya yetkiniz yok.');
            return;
        }
        const cellData = matrix[day.date_key]?.[route.id];
        let initialPrice = cellData?.value !== null && cellData?.value !== undefined ? cellData.value.toString() : '';
        let initialMorning = cellData?.morning_vehicle_id || cellData?.default_morning_vehicle_id || '';
        let initialEvening = cellData?.evening_vehicle_id || cellData?.default_evening_vehicle_id || '';

        setActiveCell({ route, day, cellData });
        setFormData({
            price: initialPrice,
            morning_id: initialMorning ? initialMorning.toString() : '',
            evening_id: initialEvening ? initialEvening.toString() : '',
            status: cellData?.trip_status || 'Yapıldı'
        });
        setCellModalVisible(true);
    };

    const handleSaveCell = async () => {
        if (!activeCell) return;
        setSaving(true);
        try {
            const payload = {
                service_route_id: activeCell.route.id,
                trip_date: activeCell.day.date_key,
                trip_price: formData.price,
                morning_vehicle_id: formData.morning_id,
                evening_vehicle_id: formData.evening_id,
                trip_status: formData.status
            };
            const res = await api.post('/v1/trips/upsert-cell', payload);
            if (res.data.success) {
                setCellModalVisible(false);
                fetchMatrix();
            }
        } catch (e) {
            Alert.alert('Hata', 'Kayıt başarısız.');
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteCell = async () => {
        if (!activeCell) return;
        setSaving(true);
        try {
            const payload = {
                service_route_id: activeCell.route.id,
                trip_date: activeCell.day.date_key,
                trip_price: '',
                trip_status: 'İptal'
            };
            const res = await api.post('/v1/trips/upsert-cell', payload);
            if (res.data.success) {
                setCellModalVisible(false);
                fetchMatrix();
            }
        } catch (e) {
            Alert.alert('Hata', 'Silme işlemi başarısız.');
        } finally {
            setSaving(false);
        }
    };

    const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(v || 0);

    const getSelectionData = () => {
        if (selectionType === 'customer') return customers.map(c => ({ label: c.company_name, value: c.id.toString() }));
        if (selectionType === 'month') return MONTHS;
        if (selectionType === 'year') return YEARS.map(y => ({ label: y, value: y }));
        if (selectionType === 'morning_vehicle' || selectionType === 'evening_vehicle') {
            const currentRoute = activeCell?.route;
            const defaultPlate = selectionType === 'morning_vehicle' ? currentRoute?.morning_plate : currentRoute?.evening_plate;
            return [
                { label: `Varsayılan: ${defaultPlate || 'Yok'}`, value: '' },
                ...vehicles.map(v => ({ label: v.plate, value: v.id.toString() }))
            ];
        }
        return [];
    };

    const handleSelectOption = (val) => {
        if (selectionType === 'customer') setSelectedCustomer(val);
        else if (selectionType === 'month') setSelectedMonth(val);
        else if (selectionType === 'year') setSelectedYear(val);
        else if (selectionType === 'morning_vehicle') setFormData({ ...formData, morning_id: val });
        else if (selectionType === 'evening_vehicle') setFormData({ ...formData, evening_id: val });
        setSelectionModalVisible(false);
    };

    const selectedCustomerObj = customers.find(c => c.id.toString() === selectedCustomer);
    const selectedMonthObj = MONTHS.find(m => m.value === selectedMonth);
    const morningVehicleObj = formData.morning_id ? vehicles.find(v => v.id.toString() === formData.morning_id) : null;
    const eveningVehicleObj = formData.evening_id ? vehicles.find(v => v.id.toString() === formData.evening_id) : null;

    const FilterChip = ({ icon, label, value, onPress, flex }) => (
        <TouchableOpacity onPress={onPress} activeOpacity={0.8} style={[s.filterChip, flex ? { flex } : {}]}>
            <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />
            <View style={s.filterIconWrap}>
                <Icon name={icon} size={16} color="#38BDF8" />
            </View>
            <View style={{ flex: 1, paddingLeft: 10 }}>
                <Text style={s.filterLabel}>{label}</Text>
                <Text style={s.filterValue} numberOfLines={1}>{value || 'Seçiniz'}</Text>
            </View>
            <Icon name="chevron-down" size={18} color="#94A3B8" />
        </TouchableOpacity>
    );

    return (
        <View style={s.container}>
            {/* 3D Animated Background */}
            <Animated.View style={StyleSheet.absoluteFill}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[s.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 60] }) }, { scale: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.25] }) }] }]} />
                <Animated.View style={[s.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -60] }) }, { scale: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.3] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                <View style={s.headerTop}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={26} color="#FFF" />
                    </TouchableOpacity>
                    <View style={{ flex: 1, alignItems: 'center' }}>
                        <Text style={s.headerTitle}>Puantaj & Seferler</Text>
                        <Text style={s.headerSubtitle}>Canlı Matris Tablosu</Text>
                    </View>
                    <View style={{ width: 44 }} />
                </View>

                {/* Filters */}
                <View style={{ paddingHorizontal: 16, marginTop: 16, gap: 12 }}>
                    <FilterChip 
                        icon="domain" 
                        label="MÜŞTERİ SEÇİMİ" 
                        value={selectedCustomerObj?.company_name} 
                        onPress={() => { setSelectionType('customer'); setSelectionModalVisible(true); }}
                    />
                    <View style={{ flexDirection: 'row', gap: 12 }}>
                        <FilterChip 
                            icon="calendar-month" 
                            label="AY" 
                            value={selectedMonthObj?.label} 
                            onPress={() => { setSelectionType('month'); setSelectionModalVisible(true); }}
                            flex={1}
                        />
                        <FilterChip 
                            icon="calendar-blank" 
                            label="YIL" 
                            value={selectedYear} 
                            onPress={() => { setSelectionType('year'); setSelectionModalVisible(true); }}
                            flex={1}
                        />
                    </View>
                </View>

                {/* Export Buttons */}
                {selectedCustomer && (
                    <View style={s.actionsRow}>
                        <View style={s.routeCountBadge}>
                            <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />
                            <Text style={s.routeCountText}>Toplam Güzergah: {serviceRoutes.length}</Text>
                        </View>
                        <View style={{ flexDirection: 'row', gap: 10 }}>
                            <TouchableOpacity 
                                style={[s.exportBtn, { borderColor: 'rgba(239,68,68,0.3)' }]}
                                onPress={() => exportReport('pdf')}
                                disabled={exportingType !== null}
                            >
                                <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />
                                {exportingType === 'pdf' ? <ActivityIndicator size="small" color="#F87171" /> : <Icon name="file-pdf-box" size={16} color="#F87171" />}
                                <Text style={[s.exportBtnText, { color: '#F87171' }]}>PDF</Text>
                            </TouchableOpacity>
                            <TouchableOpacity 
                                style={[s.exportBtn, { borderColor: 'rgba(34,197,94,0.3)' }]}
                                onPress={() => exportReport('excel')}
                                disabled={exportingType !== null}
                            >
                                <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />
                                {exportingType === 'excel' ? <ActivityIndicator size="small" color="#4ADE80" /> : <Icon name="file-excel-box" size={16} color="#4ADE80" />}
                                <Text style={[s.exportBtnText, { color: '#4ADE80' }]}>Excel</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                )}

                {loading && !serviceRoutes.length ? (
                    <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}><ActivityIndicator size="large" color="#38BDF8" /></View>
                ) : !selectedCustomer ? (
                    <View style={{ flex: 1, justifyContent: 'center' }}>
                        <EmptyState title="Müşteri Bekleniyor" message="Puantaj tablosunu görüntülemek için yukarıdan bir müşteri seçiniz." icon="domain" />
                    </View>
                ) : (
                    <ScrollView style={{ flex: 1, marginTop: 10 }} contentContainerStyle={{ paddingBottom: 120 }} showsVerticalScrollIndicator={false}>
                        {/* THE MATRIX */}
                        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: 16 }}>
                            <BlurView intensity={20} tint="dark" style={s.matrixWrapper}>
                                <View style={s.row}>
                                    <View style={[s.cellHeader, s.routeCol]}>
                                        <Text style={s.colHeaderText}>GÜZERGAH / ARAÇ</Text>
                                    </View>
                                    {monthDays.map(day => (
                                        <View key={day.date_key} style={[s.cellHeader, s.dayCol, day.is_weekend && s.weekendHeader, day.is_holiday && s.holidayHeader]}>
                                            <Text style={[s.dayText, (day.is_weekend||day.is_holiday) && { color: '#F87171' }]}>{day.day}</Text>
                                            <Text style={[s.dayNameText, (day.is_weekend||day.is_holiday) && { color: '#FCA5A5' }]}>{day.day_name.substring(0,3)}</Text>
                                            {day.is_holiday && <Text style={s.holidayLabel} numberOfLines={1}>{day.holiday_name}</Text>}
                                        </View>
                                    ))}
                                </View>

                                {serviceRoutes.map(route => (
                                    <View key={route.id} style={s.row}>
                                        <View style={[s.cell, s.routeCol, { backgroundColor: 'rgba(255,255,255,0.05)', borderRightWidth: 1, borderRightColor: 'rgba(255,255,255,0.1)' }]}>
                                            <Text style={s.routeTitle} numberOfLines={2}>{route.route_name}</Text>
                                            <View style={{ marginTop: 6, flexDirection: 'row', gap: 6 }}>
                                                <View style={s.routeVehicleBadge}>
                                                    <Text style={s.routeVehicleInfo} numberOfLines={1}>S: {route.morning_plate || '-'}</Text>
                                                </View>
                                                <View style={s.routeVehicleBadge}>
                                                    <Text style={s.routeVehicleInfo} numberOfLines={1}>A: {route.evening_plate || '-'}</Text>
                                                </View>
                                            </View>
                                        </View>
                                        
                                        {monthDays.map(day => {
                                            const cell = matrix[day.date_key]?.[route.id] || {};
                                            const hasRecord = cell.has_record;
                                            const price = cell.value !== null && cell.value !== undefined ? cell.value : '';
                                            
                                            let cellStyle = [s.cell, s.dayCol];
                                            if (day.is_weekend) cellStyle.push({ backgroundColor: 'rgba(239,68,68,0.05)' });
                                            if (day.is_holiday) cellStyle.push({ backgroundColor: 'rgba(217,70,239,0.05)' });
                                            if (hasRecord) cellStyle.push({ backgroundColor: 'rgba(56,189,248,0.15)', borderWidth: 1, borderColor: 'rgba(56,189,248,0.3)' });

                                            return (
                                                <TouchableOpacity 
                                                    key={day.date_key} 
                                                    style={cellStyle}
                                                    activeOpacity={0.7}
                                                    onPress={() => openCellModal(route, day)}
                                                >
                                                    <Text style={[s.cellPrice, hasRecord && { color: '#38BDF8', fontWeight: '900' }]}>
                                                        {price !== '' ? price : '-'}
                                                    </Text>
                                                    {hasRecord && (cell.morning_vehicle_id !== cell.default_morning_vehicle_id || cell.evening_vehicle_id !== cell.default_evening_vehicle_id) && (
                                                        <View style={s.changedVehicleDot} />
                                                    )}
                                                </TouchableOpacity>
                                            );
                                        })}
                                    </View>
                                ))}
                            </BlurView>
                        </ScrollView>

                        {/* Summary Cards */}
                        {summary && (
                            <View style={s.summaryContainer}>
                                <BlurView intensity={25} tint="dark" style={[s.summaryCard, { borderColor: 'rgba(255,255,255,0.1)' }]}>
                                    <Text style={s.summaryLabel}>Ara Toplam</Text>
                                    <Text style={s.summaryValue}>{fmtMoney(summary.subtotal)}</Text>
                                </BlurView>
                                <BlurView intensity={25} tint="dark" style={[s.summaryCard, { borderColor: 'rgba(255,255,255,0.1)' }]}>
                                    <Text style={s.summaryLabel}>KDV (%{summary.vat_rate})</Text>
                                    <Text style={s.summaryValue}>{fmtMoney(summary.vat_amount)}</Text>
                                </BlurView>
                                {summary.withholding_amount > 0 && (
                                    <BlurView intensity={25} tint="dark" style={[s.summaryCard, { borderColor: 'rgba(245,158,11,0.3)' }]}>
                                        <Text style={[s.summaryLabel, { color: '#FCD34D' }]}>Tevkifat Tutarı</Text>
                                        <Text style={[s.summaryValue, { color: '#FBBF24' }]}>{fmtMoney(summary.withholding_amount)}</Text>
                                    </BlurView>
                                )}
                                <BlurView intensity={40} tint="light" style={[s.summaryCard, { borderColor: 'rgba(56,189,248,0.5)', backgroundColor: 'rgba(15,23,42,0.6)' }]}>
                                    <Text style={[s.summaryLabel, { color: '#94A3B8' }]}>NET FATURA TUTARI</Text>
                                    <Text style={[s.summaryValue, { color: '#38BDF8', fontSize: 28, textShadowColor: 'rgba(56,189,248,0.5)', textShadowOffset: { width: 0, height: 0 }, textShadowRadius: 10 }]}>{fmtMoney(summary.net_total)}</Text>
                                </BlurView>
                            </View>
                        )}
                    </ScrollView>
                )}
            </SafeAreaView>

            {/* CELL MODAL */}
            <Modal visible={cellModalVisible} animationType="slide" transparent>
                <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={s.modalOverlay}>
                    <BlurView intensity={40} tint="dark" style={StyleSheet.absoluteFillObject} />
                    <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setCellModalVisible(false)} />
                    
                    <BlurView intensity={60} tint="dark" style={s.modalContent}>
                        <View style={s.sheetHandle} />
                        <View style={s.modalHeader}>
                            <View style={{ flex: 1 }}>
                                <Text style={s.modalTitle}>{activeCell?.day?.display_date}</Text>
                                <Text style={s.modalSubtitle} numberOfLines={2}>{activeCell?.route?.route_name}</Text>
                            </View>
                            <TouchableOpacity onPress={() => setCellModalVisible(false)} style={s.modalCloseBtn}>
                                <Icon name="close" size={24} color="#F8FAFC" />
                            </TouchableOpacity>
                        </View>

                        <ScrollView contentContainerStyle={{ padding: 20 }}>
                            <Text style={s.inputLabel}>TUTAR (₺) *</Text>
                            <TextInput 
                                style={s.priceInput}
                                value={formData.price}
                                onChangeText={t => setFormData({...formData, price: t})}
                                keyboardType="numeric"
                                placeholder="0.00"
                                placeholderTextColor="#64748B"
                                autoFocus
                            />
                            <Text style={s.hintText}>Fiyatı silmek bu kaydı tamamen iptal eder.</Text>

                            <Text style={[s.inputLabel, { marginTop: 24, color: '#38BDF8' }]}>☀️ SABAH ARACI (İsteğe Bağlı)</Text>
                            <TouchableOpacity 
                                style={[s.vehicleSelectorBtn, { borderColor: 'rgba(56,189,248,0.3)' }]} 
                                onPress={() => { setSelectionType('morning_vehicle'); setSelectionModalVisible(true); }}
                            >
                                <Icon name="white-balance-sunny" size={20} color="#38BDF8" />
                                <Text style={[s.vehicleSelectorText, { color: '#E0F2FE' }]}>
                                    {morningVehicleObj ? morningVehicleObj.plate : `Varsayılan: ${activeCell?.route?.morning_plate || 'Yok'}`}
                                </Text>
                                <Icon name="chevron-down" size={20} color="#94A3B8" />
                            </TouchableOpacity>

                            <Text style={[s.inputLabel, { marginTop: 16, color: '#A78BFA' }]}>🌙 AKŞAM ARACI (İsteğe Bağlı)</Text>
                            <TouchableOpacity 
                                style={[s.vehicleSelectorBtn, { borderColor: 'rgba(167,139,250,0.3)' }]} 
                                onPress={() => { setSelectionType('evening_vehicle'); setSelectionModalVisible(true); }}
                            >
                                <Icon name="moon-waning-crescent" size={20} color="#A78BFA" />
                                <Text style={[s.vehicleSelectorText, { color: '#EDE9FE' }]}>
                                    {eveningVehicleObj ? eveningVehicleObj.plate : `Varsayılan: ${activeCell?.route?.evening_plate || 'Yok'}`}
                                </Text>
                                <Icon name="chevron-down" size={20} color="#94A3B8" />
                            </TouchableOpacity>

                            <View style={s.modalActions}>
                                <TouchableOpacity style={s.deleteBtn} onPress={handleDeleteCell} disabled={saving || !activeCell?.cellData?.has_record}>
                                    <Icon name="trash-can-outline" size={24} color={activeCell?.cellData?.has_record ? "#F87171" : "#475569"} />
                                </TouchableOpacity>
                                <TouchableOpacity style={[s.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSaveCell} disabled={saving}>
                                    <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />
                                    {saving ? <ActivityIndicator color="#fff" /> : <Text style={s.saveBtnText}>Kaydet</Text>}
                                </TouchableOpacity>
                            </View>
                        </ScrollView>
                    </BlurView>
                </KeyboardAvoidingView>
            </Modal>

            {/* SELECTION MODAL */}
            <Modal visible={selectionModalVisible} animationType="slide" transparent>
                <View style={s.modalOverlay}>
                    <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setSelectionModalVisible(false)} />
                    <BlurView intensity={70} tint="dark" style={[s.modalContent, { maxHeight: '70%' }]}>
                        <View style={s.sheetHandle} />
                        <View style={s.modalHeader}>
                            <Text style={s.modalTitle}>Lütfen Seçiniz</Text>
                            <TouchableOpacity onPress={() => setSelectionModalVisible(false)} style={s.modalCloseBtn}>
                                <Icon name="close" size={24} color="#F8FAFC" />
                            </TouchableOpacity>
                        </View>
                        <ScrollView contentContainerStyle={{ padding: 16 }}>
                            {getSelectionData().map((item, index) => (
                                <TouchableOpacity 
                                    key={index} 
                                    style={s.selectionListItem}
                                    onPress={() => handleSelectOption(item.value)}
                                >
                                    <Text style={s.selectionListText}>{item.label}</Text>
                                </TouchableOpacity>
                            ))}
                        </ScrollView>
                    </BlurView>
                </View>
            </Modal>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -100, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(59, 130, 246, 0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(16, 185, 129, 0.12)', filter: 'blur(40px)' },
    
    headerTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingTop: 10 },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5 },
    headerSubtitle: { fontSize: 13, fontWeight: '600', color: '#34D399', marginTop: 2, letterSpacing: 0.5 },
    
    // Chips
    filterChip: { flexDirection: 'row', alignItems: 'center', padding: 12, borderRadius: 16, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    filterIconWrap: { width: 32, height: 32, borderRadius: 10, backgroundColor: 'rgba(56,189,248,0.15)', alignItems: 'center', justifyContent: 'center' },
    filterLabel: { fontSize: 10, fontWeight: '800', color: '#94A3B8', letterSpacing: 0.5, marginBottom: 2 },
    filterValue: { fontSize: 14, fontWeight: '800', color: '#F8FAFC' },

    actionsRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 16, marginTop: 16 },
    routeCountBadge: { paddingHorizontal: 12, paddingVertical: 8, borderRadius: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    routeCountText: { fontSize: 12, fontWeight: '800', color: '#F8FAFC' },
    exportBtn: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 12, borderWidth: 1, overflow: 'hidden', gap: 6 },
    exportBtnText: { fontSize: 13, fontWeight: '800' },

    // Matrix Table
    matrixWrapper: { borderRadius: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden', marginTop: 10 },
    row: { flexDirection: 'row', borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    routeCol: { width: 160, paddingHorizontal: 12, paddingVertical: 12, justifyContent: 'center' },
    dayCol: { width: 60, paddingHorizontal: 2, paddingVertical: 12, justifyContent: 'center', alignItems: 'center', borderLeftWidth: 1, borderLeftColor: 'rgba(255,255,255,0.05)' },
    
    cellHeader: { backgroundColor: 'rgba(0,0,0,0.3)' },
    colHeaderText: { fontSize: 11, fontWeight: '800', color: '#94A3B8', letterSpacing: 0.5 },
    weekendHeader: { backgroundColor: 'rgba(239,68,68,0.1)' },
    holidayHeader: { backgroundColor: 'rgba(217,70,239,0.1)' },
    
    dayText: { fontSize: 16, fontWeight: '900', color: '#E2E8F0' },
    dayNameText: { fontSize: 9, fontWeight: '700', color: '#94A3B8', textTransform: 'uppercase' },
    holidayLabel: { fontSize: 7, fontWeight: '800', color: '#F472B6', marginTop: 2 },

    cell: { backgroundColor: 'rgba(0,0,0,0.1)' },
    routeTitle: { fontSize: 13, fontWeight: '800', color: '#F8FAFC', lineHeight: 18 },
    routeVehicleBadge: { backgroundColor: 'rgba(255,255,255,0.1)', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6 },
    routeVehicleInfo: { fontSize: 9, color: '#CBD5E1', fontWeight: '700' },
    
    cellPrice: { fontSize: 13, fontWeight: '700', color: '#94A3B8' },
    changedVehicleDot: { position: 'absolute', top: 4, right: 4, width: 6, height: 6, borderRadius: 3, backgroundColor: '#F59E0B' },

    // Summary Cards
    summaryContainer: { paddingHorizontal: 16, paddingVertical: 20, gap: 12 },
    summaryCard: { padding: 20, borderRadius: 20, borderWidth: 1, overflow: 'hidden' },
    summaryLabel: { fontSize: 12, fontWeight: '800', color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 6 },
    summaryValue: { fontSize: 22, fontWeight: '900', color: '#F8FAFC' },

    // Modals
    modalOverlay: { flex: 1, justifyContent: 'flex-end' },
    modalContent: { borderTopLeftRadius: 32, borderTopRightRadius: 32, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginTop: 12 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 24, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.1)' },
    modalTitle: { fontSize: 22, fontWeight: '900', color: '#F8FAFC' },
    modalSubtitle: { fontSize: 13, fontWeight: '700', color: '#94A3B8', marginTop: 4 },
    modalCloseBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    
    inputLabel: { fontSize: 11, fontWeight: '800', color: '#94A3B8', marginBottom: 8, marginLeft: 4, letterSpacing: 0.5 },
    priceInput: { fontSize: 32, fontWeight: '900', color: '#F8FAFC', backgroundColor: 'rgba(0,0,0,0.3)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 20, padding: 24, textAlign: 'center' },
    hintText: { fontSize: 11, color: '#64748B', textAlign: 'center', marginTop: 8, fontWeight: '600' },

    vehicleSelectorBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.2)', borderWidth: 1, borderRadius: 16, padding: 16 },
    vehicleSelectorText: { flex: 1, fontSize: 15, fontWeight: '700', marginLeft: 12 },

    modalActions: { flexDirection: 'row', marginTop: 32, gap: 12 },
    deleteBtn: { width: 64, height: 64, borderRadius: 20, backgroundColor: 'rgba(239,68,68,0.1)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(239,68,68,0.2)' },
    saveBtn: { flex: 1, height: 64, borderRadius: 20, overflow: 'hidden', alignItems: 'center', justifyContent: 'center' },
    saveBtnText: { color: '#FFFFFF', fontSize: 16, fontWeight: '900', letterSpacing: 0.5 },

    selectionListItem: { paddingVertical: 18, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    selectionListText: { fontSize: 16, fontWeight: '800', color: '#F8FAFC', textAlign: 'center' },
});
