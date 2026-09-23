import React, { useState, useEffect, useContext } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity, ScrollView, ActivityIndicator, Alert, Platform, Modal, KeyboardAvoidingView } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import DateTimePicker from '@react-native-community/datetimepicker';
import dayjs from 'dayjs';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';

// Custom Select Modal to fix iOS Picker overlap
const SelectInput = ({ icon, placeholder, value, options, onSelect }) => {
    const [open, setOpen] = useState(false);
    const selected = options.find(o => o.value === value);

    return (
        <>
            <TouchableOpacity style={s.fieldWrap} onPress={() => setOpen(true)} activeOpacity={0.7}>
                <View style={s.fieldIconBox}>
                    <Icon name={icon} size={20} color="#3B82F6" />
                </View>
                <Text style={[s.fieldInputText, { color: selected ? '#0F172A' : '#94A3B8' }]}>
                    {selected ? selected.label : placeholder}
                </Text>
                <Icon name="chevron-down" size={20} color="#CBD5E1" />
            </TouchableOpacity>

            <Modal visible={open} transparent animationType="fade">
                <TouchableOpacity style={s.modalOverlayCenter} activeOpacity={1} onPress={() => setOpen(false)}>
                    <View style={s.centerModal}>
                        <Text style={s.modalTitle}>{placeholder}</Text>
                        <ScrollView style={{ maxHeight: 300 }} showsVerticalScrollIndicator={false}>
                            {options.map((opt, i) => (
                                <TouchableOpacity 
                                    key={i} 
                                    style={[s.menuItem, value === opt.value && { backgroundColor: '#EFF6FF', borderRadius: 12 }]}
                                    onPress={() => { onSelect(opt.value); setOpen(false); }}
                                >
                                    <Text style={[s.menuText, value === opt.value && { color: '#3B82F6', fontWeight: '800' }]}>{opt.label}</Text>
                                    {value === opt.value && <Icon name="check-circle" size={20} color="#3B82F6" style={{ position: 'absolute', right: 16 }} />}
                                </TouchableOpacity>
                            ))}
                            {options.length === 0 && (
                                <Text style={{ textAlign: 'center', color: '#94A3B8', marginVertical: 20 }}>Seçenek bulunamadı.</Text>
                            )}
                        </ScrollView>
                    </View>
                </TouchableOpacity>
            </Modal>
        </>
    );
};

export default function FuelFormScreen({ navigation, route }) {
    const editId = route.params?.id || null;
    const { hasPermission } = useContext(AuthContext);

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [options, setOptions] = useState({ vehicles: [], stations: [] });

    const fuelTypes = [
        { label: 'Motorin (Dizel)', value: 'Motorin' },
        { label: 'Kurşunsuz Benzin', value: 'Benzin' },
        { label: 'Otogaz (LPG)', value: 'LPG' },
        { label: 'AdBlue', value: 'AdBlue' }
    ];

    const emptyForm = {
        vehicle_id: '',
        fuel_station_id: '',
        station_name: '',
        fuel_type: 'Motorin',
        date: new Date(),
        liters: '',
        price_per_liter: '',
        km: '',
        notes: ''
    };
    const [form, setForm] = useState(emptyForm);
    const [showDatePicker, setShowDatePicker] = useState(false);

    useEffect(() => {
        const loadInitialData = async () => {
            try {
                // Fetch options
                const optRes = await api.get('/v1/fuels/options');
                setOptions({
                    vehicles: optRes.data.data.vehicles.map(v => ({ label: v.plate, value: v.id })),
                    stations: optRes.data.data.stations.map(s => ({ label: s.name, value: s.id }))
                });

                // Fetch existing fuel if editing
                if (editId) {
                    const res = await api.get(`/v1/fuels/${editId}`);
                    const d = res.data.data;
                    setForm({
                        vehicle_id: d.vehicle_id || '',
                        fuel_station_id: d.fuel_station_id || '',
                        station_name: d.station_name || '',
                        fuel_type: d.fuel_type || 'Motorin',
                        date: d.date ? new Date(d.date) : new Date(),
                        liters: d.liters ? d.liters.toString() : '',
                        price_per_liter: d.price_per_liter ? d.price_per_liter.toString() : '',
                        km: d.km ? d.km.toString() : '',
                        notes: d.notes || ''
                    });
                }
            } catch (e) {
                Alert.alert('Hata', 'Veriler yüklenirken sorun oluştu.');
                navigation.goBack();
            } finally {
                setLoading(false);
            }
        };
        loadInitialData();
    }, [editId]);

    const handleSave = async () => {
        if (!form.vehicle_id) return Alert.alert('Hata', 'Lütfen araç seçiniz.');
        if (!form.liters || !form.price_per_liter) return Alert.alert('Hata', 'Litre ve birim fiyatı boş bırakılamaz.');

        setSaving(true);
        try {
            const payload = {
                ...form,
                date: dayjs(form.date).format('YYYY-MM-DD'),
                liters: parseFloat(form.liters.replace(',', '.')),
                price_per_liter: parseFloat(form.price_per_liter.replace(',', '.')),
                km: form.km ? parseInt(form.km) : null
            };

            // If a station is selected from the list, clear custom station_name to avoid confusion, and vice-versa
            if (payload.fuel_station_id) {
                payload.station_name = null;
            } else if (!payload.station_name) {
                payload.fuel_station_id = null;
            }

            if (editId) {
                await api.put(`/v1/fuels/${editId}`, payload);
            } else {
                await api.post('/v1/fuels', payload);
            }
            navigation.goBack();
        } catch (e) {
            console.log(e.response?.data);
            Alert.alert('Hata', e.response?.data?.message || 'Kayıt işlemi başarısız.');
        } finally {
            setSaving(false);
        }
    };

    // Auto calculate Total Cost
    const calculateTotal = () => {
        const l = parseFloat(form.liters.replace(',', '.')) || 0;
        const p = parseFloat(form.price_per_liter.replace(',', '.')) || 0;
        return (l * p).toFixed(2);
    };

    if (loading) {
        return (
            <SafeAreaView style={s.container} edges={['top']}>
                <View style={s.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                        <Icon name="chevron-left" size={26} color="#0F172A" />
                    </TouchableOpacity>
                    <Text style={s.headerTitle}>{editId ? 'Yakıtı Düzenle' : 'Yeni Yakıt Ekle'}</Text>
                    <View style={{width: 44}} />
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
                <Text style={s.headerTitle}>{editId ? 'Yakıtı Düzenle' : 'Yeni Yakıt Ekle'}</Text>
                <View style={{width: 44}} />
            </View>

            <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : null}>
                <ScrollView contentContainerStyle={s.scrollContent} showsVerticalScrollIndicator={false}>
                    
                    {/* Live Calculation Card */}
                    <LinearGradient colors={['#0F172A', '#1E293B']} style={s.liveCalcCard}>
                        <Icon name="calculator-variant" size={24} color="#64748B" style={{ position: 'absolute', top: 16, right: 16 }} />
                        <Text style={s.liveCalcLabel}>TOPLAM TUTAR</Text>
                        <Text style={s.liveCalcValue}>₺{calculateTotal()}</Text>
                    </LinearGradient>

                    <Text style={s.sectionTitle}>Temel Bilgiler</Text>
                    
                    <SelectInput 
                        icon="car-sports" 
                        placeholder="Araç Seçiniz *" 
                        value={form.vehicle_id} 
                        options={options.vehicles} 
                        onSelect={(v) => setForm({...form, vehicle_id: v})} 
                    />

                    <SelectInput 
                        icon="gas-station" 
                        placeholder="Yakıt Türü *" 
                        value={form.fuel_type} 
                        options={fuelTypes} 
                        onSelect={(v) => setForm({...form, fuel_type: v})} 
                    />

                    {/* Date Picker */}
                    <View style={s.fieldWrap}>
                        <View style={s.fieldIconBox}><Icon name="calendar" size={20} color="#3B82F6" /></View>
                        {Platform.OS === 'android' ? (
                            <TouchableOpacity style={{ flex: 1 }} onPress={() => setShowDatePicker(true)}>
                                <Text style={[s.fieldInputText, { paddingTop: 14 }]}>{dayjs(form.date).format('DD.MM.YYYY')}</Text>
                            </TouchableOpacity>
                        ) : (
                            <DateTimePicker value={form.date} mode="date" display="compact" onChange={(e, selected) => selected && setForm({...form, date: selected})} style={{ flex: 1 }} />
                        )}
                        {Platform.OS === 'android' && showDatePicker && (
                            <DateTimePicker value={form.date} mode="date" display="default" onChange={(e, selected) => { setShowDatePicker(false); if (selected) setForm({...form, date: selected}); }} />
                        )}
                    </View>

                    <Text style={s.sectionTitle}>Tutar ve Hacim</Text>
                    
                    <View style={s.rowFields}>
                        <View style={[s.fieldWrap, { flex: 1, marginRight: 8 }]}>
                            <View style={[s.fieldIconBox, { backgroundColor: '#EFF6FF' }]}><Icon name="water" size={20} color="#3B82F6" /></View>
                            <TextInput 
                                style={s.fieldInput} 
                                placeholder="Litre *" 
                                placeholderTextColor="#94A3B8" 
                                keyboardType="numeric" 
                                value={form.liters} 
                                onChangeText={(t) => setForm({...form, liters: t})} 
                            />
                        </View>
                        <View style={[s.fieldWrap, { flex: 1, marginLeft: 8 }]}>
                            <View style={[s.fieldIconBox, { backgroundColor: '#ECFDF5' }]}><Icon name="currency-try" size={20} color="#10B981" /></View>
                            <TextInput 
                                style={s.fieldInput} 
                                placeholder="Birim Fiyat *" 
                                placeholderTextColor="#94A3B8" 
                                keyboardType="numeric" 
                                value={form.price_per_liter} 
                                onChangeText={(t) => setForm({...form, price_per_liter: t})} 
                            />
                        </View>
                    </View>

                    <Text style={s.sectionTitle}>İstasyon ve Ekstralar</Text>

                    <SelectInput 
                        icon="store-marker-outline" 
                        placeholder="Kayıtlı İstasyon" 
                        value={form.fuel_station_id} 
                        options={options.stations} 
                        onSelect={(v) => setForm({...form, fuel_station_id: v})} 
                    />

                    {!form.fuel_station_id && (
                        <View style={s.fieldWrap}>
                            <View style={[s.fieldIconBox, { backgroundColor: '#F8FAFC' }]}><Icon name="pencil-outline" size={20} color="#64748B" /></View>
                            <TextInput 
                                style={s.fieldInput} 
                                placeholder="Veya Manuel İstasyon Adı" 
                                placeholderTextColor="#94A3B8" 
                                value={form.station_name} 
                                onChangeText={(t) => setForm({...form, station_name: t})} 
                            />
                        </View>
                    )}

                    <View style={s.fieldWrap}>
                        <View style={[s.fieldIconBox, { backgroundColor: '#FFFBEB' }]}><Icon name="speedometer" size={20} color="#F59E0B" /></View>
                        <TextInput 
                            style={s.fieldInput} 
                            placeholder="Alındığı Anki Kilometre (KM)" 
                            placeholderTextColor="#94A3B8" 
                            keyboardType="numeric" 
                            value={form.km} 
                            onChangeText={(t) => setForm({...form, km: t})} 
                        />
                    </View>

                    <View style={[s.fieldWrap, { height: 80, alignItems: 'flex-start', paddingTop: 8 }]}>
                        <View style={[s.fieldIconBox, { backgroundColor: '#F8FAFC', marginTop: 4 }]}><Icon name="text" size={20} color="#64748B" /></View>
                        <TextInput 
                            style={[s.fieldInput, { height: 60, textAlignVertical: 'top' }]} 
                            placeholder="Özel Notlar (Opsiyonel)" 
                            placeholderTextColor="#94A3B8" 
                            multiline 
                            value={form.notes} 
                            onChangeText={(t) => setForm({...form, notes: t})} 
                        />
                    </View>

                    <TouchableOpacity style={s.saveBtn} onPress={handleSave} disabled={saving} activeOpacity={0.8}>
                        {saving ? (
                            <ActivityIndicator color="#FFF" />
                        ) : (
                            <>
                                <Icon name="check-circle" size={22} color="#FFF" style={{ marginRight: 8 }} />
                                <Text style={s.saveBtnText}>Yakıt Kaydını {editId ? 'Güncelle' : 'Kaydet'}</Text>
                            </>
                        )}
                    </TouchableOpacity>

                </ScrollView>
            </KeyboardAvoidingView>
        </SafeAreaView>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: 10, paddingBottom: 15 },
    backBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: '#FFF', alignItems: 'center', justifyContent: 'center', shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 4, elevation: 2 },
    headerTitle: { fontSize: 18, fontWeight: '900', color: '#0F172A' },
    
    scrollContent: { padding: 20, paddingBottom: 120 },
    
    liveCalcCard: { padding: 24, borderRadius: 24, marginBottom: 24, shadowColor: '#3B82F6', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.2, shadowRadius: 12, elevation: 8 },
    liveCalcLabel: { fontSize: 12, color: '#94A3B8', fontWeight: '800', letterSpacing: 1.5, marginBottom: 8 },
    liveCalcValue: { fontSize: 36, color: '#10B981', fontWeight: '900', letterSpacing: -1 },

    sectionTitle: { fontSize: 13, fontWeight: '800', color: '#64748B', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 12, marginTop: 10 },
    
    fieldWrap: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#FFF', borderRadius: 16, paddingHorizontal: 12, height: 56, marginBottom: 12, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.03, shadowRadius: 4, elevation: 1 },
    fieldIconBox: { width: 36, height: 36, borderRadius: 10, backgroundColor: '#EFF6FF', alignItems: 'center', justifyContent: 'center', marginRight: 12 },
    fieldInputText: { flex: 1, fontSize: 15, color: '#0F172A', fontWeight: '600' },
    fieldInput: { flex: 1, fontSize: 15, color: '#0F172A', fontWeight: '600', height: '100%' },
    rowFields: { flexDirection: 'row', justifyContent: 'space-between' },

    saveBtn: { flexDirection: 'row', backgroundColor: '#0F172A', height: 56, borderRadius: 16, alignItems: 'center', justifyContent: 'center', marginTop: 24, shadowColor: '#0F172A', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 8, elevation: 5 },
    saveBtnText: { color: '#FFF', fontSize: 16, fontWeight: '800' },

    modalOverlayCenter: { flex: 1, backgroundColor: 'rgba(15,23,42,0.6)', justifyContent: 'center', padding: 20 },
    centerModal: { backgroundColor: '#FFF', borderRadius: 24, padding: 24, maxHeight: '80%' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A', marginBottom: 16 },
    menuItem: { flexDirection: 'row', alignItems: 'center', padding: 16, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    menuText: { fontSize: 15, fontWeight: '600', color: '#475569' },
});
