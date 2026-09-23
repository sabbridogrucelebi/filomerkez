import React, { useState, useEffect, useContext, useRef, useMemo } from 'react';
import { View, StyleSheet, FlatList, ActivityIndicator, Alert, TextInput, Text, TouchableOpacity, Animated, Platform, ScrollView, Switch, Image, Easing, KeyboardAvoidingView, Modal } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import api from '../api/axios';
import { emoji } from '../emoji';
import { AuthContext } from '../context/AuthContext';
import { EmptyState, Fab } from '../components';
import { todayUi, toApiDate } from '../utils/date';
import DatePickerInput from '../components/DatePickerInput';

export default function CustomersScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const [customers, setCustomers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    
    // Filters
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all'); 
    const [typeFilter, setTypeFilter] = useState('all');

    // Modal states
    const [modalVisible, setModalVisible] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [formData, setFormData] = useState({
        customer_type: '', company_name: '', company_title: '', authorized_person: '', authorized_phone: '',
        email: '', address: '', contract_start_date: '', contract_end_date: '', vat_rate: '20', withholding_rate: '', notes: '', is_active: true
    });
    const [validationErrors, setValidationErrors] = useState({});
    
    const [selectionModalVisible, setSelectionModalVisible] = useState(false);
    const [selectionType, setSelectionType] = useState(null);

    // Animations
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;
    
    useEffect(() => {
        const loop1 = Animated.loop(
            Animated.sequence([
                Animated.timing(blob1Anim, { toValue: 1, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob1Anim, { toValue: 0, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        const loop2 = Animated.loop(
            Animated.sequence([
                Animated.timing(blob2Anim, { toValue: 1, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob2Anim, { toValue: 0, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        loop1.start(); loop2.start();
        return () => { loop1.stop(); loop2.stop(); };
    }, []);

    const fetchCustomers = async () => {
        try {
            const response = await api.get('/v1/customers');
            if (response.data.success) setCustomers(response.data.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => { fetchCustomers(); }, []);
    const onRefresh = () => { setRefreshing(true); fetchCustomers(); };

    const openAdd = () => {
        setEditingId(null);
        setFormData({
            customer_type: '', company_name: '', company_title: '', authorized_person: '', authorized_phone: '', email: '', address: '',
            contract_start_date: todayUi(), contract_end_date: '', vat_rate: '20', withholding_rate: '', notes: '', is_active: true
        });
        setValidationErrors({});
        setModalVisible(true);
    };

    const openEdit = (item) => {
        setEditingId(item.id);
        setFormData({
            customer_type: item.customer_type || '', company_name: item.company_name || '', company_title: item.company_title || '',
            authorized_person: item.authorized_person || '', authorized_phone: item.authorized_phone || '', email: item.email || '', address: item.address || '',
            contract_start_date: item.contract_start_date ? new Date(item.contract_start_date).toLocaleDateString('tr-TR') : '',
            contract_end_date: item.contract_end_date ? new Date(item.contract_end_date).toLocaleDateString('tr-TR') : '',
            vat_rate: item.vat_rate ? item.vat_rate.toString() : '20', withholding_rate: item.withholding_rate || '', notes: item.notes || '', is_active: item.is_active !== 0
        });
        setValidationErrors({});
        setModalVisible(true);
    };

    const getSelectionData = () => {
        if (selectionType === 'customer_type') return [
            { label: 'Fabrika', value: 'Fabrika' }, { label: 'Okul', value: 'Okul' }, { label: 'Resmi Daire', value: 'Resmi Daire' }, { label: 'Diğer Servisler', value: 'Diğer Servisler' }
        ];
        if (selectionType === 'vat_rate') return [ { label: '%0', value: '0' }, { label: '%1', value: '1' }, { label: '%10', value: '10' }, { label: '%20', value: '20' } ];
        if (selectionType === 'withholding_rate') return [
            { label: 'Tevkifat Yok', value: '' }, { label: '2/10', value: '2/10' }, { label: '3/10', value: '3/10' }, { label: '4/10', value: '4/10' }, { label: '5/10', value: '5/10' }, { label: '7/10', value: '7/10' }, { label: '9/10', value: '9/10' }
        ];
        if (selectionType === 'status') return [ { label: 'Tümü', value: 'all' }, { label: 'Aktif', value: 'active' }, { label: 'Pasif', value: 'passive' } ];
        return [];
    };

    const handleSelectOption = (val) => {
        if (selectionType === 'status') { setStatusFilter(val); }
        else { setFormData({ ...formData, [selectionType]: val }); }
        setSelectionModalVisible(false);
    };

    const save = async () => {
        setValidationErrors({});
        try {
            setSaving(true);
            const payload = { ...formData, contract_start_date: toApiDate(formData.contract_start_date), contract_end_date: toApiDate(formData.contract_end_date) };
            const res = editingId ? await api.put(`/v1/customers/${editingId}`, payload) : await api.post('/v1/customers', payload);
            if (res.data.success) {
                setModalVisible(false);
                fetchCustomers();
            } else {
                Alert.alert('Hata', res.data.message || 'Kayıt başarısız.');
            }
        } catch (error) {
            if (error.response && error.response.status === 422) setValidationErrors(error.response.data.errors || {});
            else Alert.alert('Hata', 'Sunucu bağlantı hatası.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = (id) => {
        Alert.alert('Emin misiniz?', 'Müşteriyi silmek kalıcı bir işlemdir.', [
            { text: 'İptal', style: 'cancel' },
            { text: 'Sil', style: 'destructive', onPress: async () => {
                try {
                    const res = await api.delete(`/v1/customers/${id}`);
                    if (res.data.success) { setModalVisible(false); fetchCustomers(); }
                } catch (e) { Alert.alert('Hata', 'Silme işlemi başarısız.'); }
            }}
        ]);
    };

    const totalCustomers = customers.length;
    const activeCustomers = customers.filter(c => c.is_active !== 0).length;
    const passiveCustomers = customers.filter(c => c.is_active === 0).length;
    
    const filteredData = customers.filter(c => {
        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            if (!c.company_name?.toLowerCase().includes(q) && !c.authorized_person?.toLowerCase().includes(q) && !c.authorized_phone?.toLowerCase().includes(q)) return false;
        }
        if (statusFilter === 'active' && c.is_active === 0) return false;
        if (statusFilter === 'passive' && c.is_active !== 0) return false;
        if (typeFilter !== 'all' && c.customer_type !== typeFilter) return false;
        return true;
    });

    const filterChips = [
        { label: 'Tümü', value: 'all' },
        { label: 'Fabrika', value: 'Fabrika' },
        { label: 'Okul', value: 'Okul' },
        { label: 'Resmi Daire', value: 'Resmi Daire' },
        { label: 'Diğer', value: 'Diğer Servisler' },
    ];

    const renderCustomer = ({ item }) => {
        const type = item.customer_type;
        let iconName = 'domain';
        let glowColor = 'rgba(100,116,139,0.3)';
        let tintColor = '#94A3B8';
        
        if (type === 'Fabrika') { iconName = 'factory'; glowColor = 'rgba(56,189,248,0.2)'; tintColor = '#38BDF8'; }
        else if (type === 'Okul') { iconName = 'school'; glowColor = 'rgba(251,191,36,0.2)'; tintColor = '#FBBF24'; }
        else if (type === 'Resmi Daire') { iconName = 'bank'; glowColor = 'rgba(167,139,250,0.2)'; tintColor = '#A78BFA'; }
        else if (type === 'Diğer Servisler') { iconName = 'office-building'; glowColor = 'rgba(52,211,153,0.2)'; tintColor = '#34D399'; }

        const title = item.company_name || item.authorized_person || 'İsimsiz Müşteri';
        const sub = item.company_name ? `Yetkili: ${item.authorized_person || '-'}` : (item.authorized_person ? 'Bireysel' : '-');
        const isActive = !!item.is_active;

        return (
            <TouchableOpacity activeOpacity={0.8} onPress={() => navigation.navigate('CustomerDetail', { customerId: item.id, customerName: title })} style={s.cardWrapper}>
                <BlurView intensity={25} tint="dark" style={s.card}>
                    <View style={s.cardTop}>
                        <View style={[s.cardIconBox, { backgroundColor: glowColor, borderColor: tintColor }]}>
                            <Icon name={iconName} size={28} color={tintColor} style={{ textShadowColor: tintColor, textShadowRadius: 10 }} />
                        </View>
                        <View style={s.cardInfo}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 4, paddingRight: 40 }}>
                                <Text style={s.cardTitle} numberOfLines={1}>{title}</Text>
                            </View>
                            <View style={s.cardSubRow}>
                                <Icon name="account-tie" size={14} color="#94A3B8" />
                                <Text style={s.cardSubText} numberOfLines={1}>{sub}</Text>
                            </View>
                            <View style={s.cardSubRow}>
                                <Icon name="calendar-range" size={14} color="#94A3B8" />
                                <Text style={s.cardSubText}>Sözleşme: {item.contract_start_date ? new Date(item.contract_start_date).toLocaleDateString('tr-TR') : '-'}</Text>
                            </View>
                        </View>
                        <View style={s.cardRightActions}>
                            <View style={[s.statusDot, { backgroundColor: isActive ? '#34D399' : '#F87171', shadowColor: isActive ? '#34D399' : '#F87171' }]} />
                            {hasPermission('customers.edit') ? (
                                <TouchableOpacity style={s.editBtn} onPress={() => openEdit(item)}>
                                    <Icon name="pencil" size={18} color="#38BDF8" />
                                </TouchableOpacity>
                            ) : (
                                <View style={s.editBtn}>
                                    <Icon name="chevron-right" size={24} color="#94A3B8" />
                                </View>
                            )}
                        </View>
                    </View>
                </BlurView>
            </TouchableOpacity>
        );
    };

    return (
        <View style={s.container}>
            {/* 3D Animated Background */}
            <Animated.View style={StyleSheet.absoluteFill}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[s.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 60] }) }, { scale: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.25] }) }] }]} />
                <Animated.View style={[s.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -60] }) }, { scale: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[1, 1.3] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                {/* Custom Glass Header */}
                <View style={s.headerTop}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={26} color="#FFF" />
                    </TouchableOpacity>
                    <View style={{ flex: 1, alignItems: 'center' }}>
                        <Text style={s.headerTitle}>Müşteriler</Text>
                        <Text style={s.headerSubtitle}>Müşteri Yönetimi</Text>
                    </View>
                    {hasPermission('customers.create') ? (
                        <TouchableOpacity onPress={openAdd} style={s.backBtn}>
                            <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                            <Icon name="plus" size={26} color="#FFF" />
                        </TouchableOpacity>
                    ) : <View style={{ width: 44 }} />}
                </View>

                {/* Horizontal Scroll KPIs */}
                <View style={{ marginTop: 16 }}>
                    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={s.kpiScroll}>
                        <View style={s.kpiCardWrapper}>
                            <BlurView intensity={30} tint="dark" style={[s.kpiCard, { borderColor: 'rgba(56,189,248,0.3)' }]}>
                                <Image source={emoji('Travel and places/Office Building')} style={s.kpiIcon3D} resizeMode="contain" />
                                <View style={s.kpiInfo}>
                                    <Text style={[s.kpiValue, { color: '#38BDF8' }]}>{totalCustomers}</Text>
                                    <Text style={s.kpiTitle}>Toplam Müşteri</Text>
                                </View>
                            </BlurView>
                        </View>
                        <View style={s.kpiCardWrapper}>
                            <BlurView intensity={30} tint="dark" style={[s.kpiCard, { borderColor: 'rgba(52,211,153,0.3)' }]}>
                                <Image source={emoji('Symbols/Check Mark Button')} style={s.kpiIcon3D} resizeMode="contain" />
                                <View style={s.kpiInfo}>
                                    <Text style={[s.kpiValue, { color: '#34D399' }]}>{activeCustomers}</Text>
                                    <Text style={s.kpiTitle}>Aktif Müşteri</Text>
                                </View>
                            </BlurView>
                        </View>
                        <View style={s.kpiCardWrapper}>
                            <BlurView intensity={30} tint="dark" style={[s.kpiCard, { borderColor: 'rgba(248,113,113,0.3)' }]}>
                                <Image source={emoji('Symbols/Prohibited')} style={s.kpiIcon3D} resizeMode="contain" />
                                <View style={s.kpiInfo}>
                                    <Text style={[s.kpiValue, { color: '#F87171' }]}>{passiveCustomers}</Text>
                                    <Text style={s.kpiTitle}>Pasif Müşteri</Text>
                                </View>
                            </BlurView>
                        </View>
                    </ScrollView>
                </View>

                {/* Filters */}
                <View style={s.searchSection}>
                    <View style={s.searchInputBox}>
                        <Icon name="magnify" size={22} color="#94A3B8" />
                        <TextInput
                            style={s.searchInput}
                            placeholder="Firma adı veya yetkili ara..."
                            placeholderTextColor="#64748B"
                            value={searchQuery}
                            onChangeText={setSearchQuery}
                        />
                    </View>
                    <View style={{ flexDirection: 'row', gap: 10 }}>
                        <TouchableOpacity style={s.statusFilterBtn} onPress={() => { setSelectionType('status'); setSelectionModalVisible(true); }}>
                            <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />
                            <Text style={s.statusFilterText}>
                                {statusFilter === 'all' ? 'Tümü' : (statusFilter === 'active' ? 'Aktif' : 'Pasif')}
                            </Text>
                            <Icon name="chevron-down" size={16} color="#94A3B8" />
                        </TouchableOpacity>
                        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingRight: 20 }}>
                            {filterChips.map((chip, idx) => {
                                const isActive = typeFilter === chip.value;
                                return (
                                    <TouchableOpacity 
                                        key={idx} 
                                        style={[s.chip, isActive && s.chipActive]} 
                                        onPress={() => setTypeFilter(chip.value)}
                                    >
                                        {!isActive && <BlurView intensity={20} tint="light" style={StyleSheet.absoluteFillObject} />}
                                        {isActive && <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />}
                                        <Text style={[s.chipText, isActive && s.chipTextActive]}>{chip.label}</Text>
                                    </TouchableOpacity>
                                );
                            })}
                        </ScrollView>
                    </View>
                </View>

                {loading ? (
                    <View style={{ flex: 1, justifyContent: 'center' }}><ActivityIndicator size="large" color="#38BDF8" /></View>
                ) : filteredData.length === 0 ? (
                    <View style={{ flex: 1, justifyContent: 'center' }}>
                        <EmptyState title="Müşteri Bulunamadı" message="Bu filtrelere uygun bir müşteri kaydı yok." icon="domain" />
                    </View>
                ) : (
                    <FlatList
                        data={filteredData}
                        keyExtractor={(item) => item.id.toString()}
                        renderItem={renderCustomer}
                        contentContainerStyle={s.listContent}
                        showsVerticalScrollIndicator={false}
                        initialNumToRender={6}
                        maxToRenderPerBatch={8}
                        windowSize={5}
                        removeClippedSubviews={true}
                    />
                )}
            </SafeAreaView>

            {/* CUSTOM ADD/EDIT MODAL */}
            <Modal visible={modalVisible} animationType="slide" transparent>
                <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={s.modalOverlay}>
                    <BlurView intensity={40} tint="dark" style={StyleSheet.absoluteFillObject} />
                    <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setModalVisible(false)} />
                    
                    <BlurView intensity={70} tint="dark" style={s.modalContent}>
                        <View style={s.sheetHandle} />
                        <View style={s.modalHeader}>
                            <View style={{ flex: 1 }}>
                                <Text style={s.modalTitle}>{editingId ? 'Müşteriyi Düzenle' : 'Yeni Müşteri'}</Text>
                                <Text style={s.modalSubtitle}>Firma bilgilerini girin</Text>
                            </View>
                            <TouchableOpacity onPress={() => setModalVisible(false)} style={s.modalCloseBtn}>
                                <Icon name="close" size={24} color="#F8FAFC" />
                            </TouchableOpacity>
                        </View>

                        <ScrollView contentContainerStyle={{ padding: 20 }}>
                            
                            <Text style={s.inputLabel}>MÜŞTERİ TÜRÜ</Text>
                            <TouchableOpacity style={s.selectorBtn} onPress={() => { setSelectionType('customer_type'); setSelectionModalVisible(true); }}>
                                <Text style={[s.selectorBtnText, !formData.customer_type && { color: '#64748B' }]}>{formData.customer_type || 'Seçiniz'}</Text>
                                <Icon name="chevron-down" size={20} color="#64748B" />
                            </TouchableOpacity>

                            <Text style={s.inputLabel}>FİRMA ADI *</Text>
                            <TextInput style={s.input} value={formData.company_name} onChangeText={t => setFormData({...formData, company_name: t})} />
                            {validationErrors.company_name && <Text style={s.errorText}>{validationErrors.company_name[0]}</Text>}

                            <Text style={s.inputLabel}>FİRMA ÜNVANI</Text>
                            <TextInput style={s.input} value={formData.company_title} onChangeText={t => setFormData({...formData, company_title: t})} />

                            <Text style={s.inputLabel}>YETKİLİ KİŞİ *</Text>
                            <TextInput style={s.input} value={formData.authorized_person} onChangeText={t => setFormData({...formData, authorized_person: t})} />
                            {validationErrors.authorized_person && <Text style={s.errorText}>{validationErrors.authorized_person[0]}</Text>}

                            <Text style={s.inputLabel}>YETKİLİ TELEFON *</Text>
                            <TextInput style={s.input} value={formData.authorized_phone} keyboardType="phone-pad" onChangeText={t => setFormData({...formData, authorized_phone: t})} />

                            <Text style={s.inputLabel}>E-POSTA ADRESİ</Text>
                            <TextInput style={s.input} value={formData.email} keyboardType="email-address" autoCapitalize="none" onChangeText={t => setFormData({...formData, email: t})} />

                            <Text style={s.inputLabel}>ADRES BİLGİSİ</Text>
                            <TextInput style={[s.input, { height: 80, textAlignVertical: 'top' }]} value={formData.address} multiline onChangeText={t => setFormData({...formData, address: t})} />

                            <View style={{ flexDirection: 'row', gap: 12, marginTop: 10 }}>
                                <View style={{ flex: 1 }}>
                                    <Text style={s.inputLabel}>SÖZLEŞME BAŞLANGIÇ</Text>
                                    <DatePickerInput value={formData.contract_start_date} onChange={d => setFormData({...formData, contract_start_date: d})} dark />
                                </View>
                                <View style={{ flex: 1 }}>
                                    <Text style={s.inputLabel}>SÖZLEŞME BİTİŞ</Text>
                                    <DatePickerInput value={formData.contract_end_date} onChange={d => setFormData({...formData, contract_end_date: d})} dark />
                                </View>
                            </View>

                            <Text style={[s.inputLabel, { marginTop: 16 }]}>KDV ORANI</Text>
                            <TouchableOpacity style={s.selectorBtn} onPress={() => { setSelectionType('vat_rate'); setSelectionModalVisible(true); }}>
                                <Text style={s.selectorBtnText}>{formData.vat_rate ? `%${formData.vat_rate}` : 'Seçiniz'}</Text>
                                <Icon name="chevron-down" size={20} color="#64748B" />
                            </TouchableOpacity>

                            <Text style={s.inputLabel}>TEVKİFAT ORANI</Text>
                            <TouchableOpacity style={s.selectorBtn} onPress={() => { setSelectionType('withholding_rate'); setSelectionModalVisible(true); }}>
                                <Text style={s.selectorBtnText}>{formData.withholding_rate || 'Tevkifat Yok'}</Text>
                                <Icon name="chevron-down" size={20} color="#64748B" />
                            </TouchableOpacity>

                            <View style={s.switchRow}>
                                <Text style={s.switchLabel}>Müşteri aktif olarak kaydedilsin</Text>
                                <Switch value={formData.is_active} onValueChange={val => setFormData({...formData, is_active: val})} trackColor={{ false: '#475569', true: '#38BDF8' }} />
                            </View>

                            <View style={s.modalActions}>
                                {editingId && hasPermission('customers.delete') && (
                                    <TouchableOpacity style={s.deleteBtn} onPress={() => handleDelete(editingId)}>
                                        <Icon name="trash-can-outline" size={24} color="#F87171" />
                                    </TouchableOpacity>
                                )}
                                <TouchableOpacity style={[s.saveBtn, saving && { opacity: 0.7 }]} onPress={save} disabled={saving}>
                                    <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />
                                    {saving ? <ActivityIndicator color="#fff" /> : <Text style={s.saveBtnText}>Kaydet</Text>}
                                </TouchableOpacity>
                            </View>

                            <View style={{height: 40}} />
                        </ScrollView>
                    </BlurView>
                </KeyboardAvoidingView>
            </Modal>

            {/* INNER SELECTION MODAL */}
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
                                <TouchableOpacity key={index} style={s.selectionListItem} onPress={() => handleSelectOption(item.value)}>
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
    bgBlob1: { position: 'absolute', top: -50, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(56,189,248,0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(167,139,250,0.15)', filter: 'blur(40px)' },
    
    headerTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingTop: 10 },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC', letterSpacing: -0.5 },
    headerSubtitle: { fontSize: 13, fontWeight: '600', color: '#38BDF8', marginTop: 2, letterSpacing: 0.5 },

    // KPIs
    kpiScroll: { paddingHorizontal: 16, gap: 12, paddingBottom: 10 },
    kpiCardWrapper: { borderRadius: 20, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', width: 170 },
    kpiCard: { flexDirection: 'row', alignItems: 'center', padding: 16, gap: 12 },
    kpiIcon3D: { width: 44, height: 44 },
    kpiInfo: { flex: 1 },
    kpiValue: { fontSize: 24, fontWeight: '900', letterSpacing: -1 },
    kpiTitle: { fontSize: 11, fontWeight: '700', color: '#94A3B8', marginTop: -2 },

    // Search & Filters
    searchSection: { paddingHorizontal: 16, marginTop: 12, marginBottom: 10, zIndex: 10 },
    searchInputBox: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.3)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 16, paddingHorizontal: 16, height: 50, marginBottom: 12 },
    searchInput: { flex: 1, color: '#F8FAFC', fontSize: 15, fontWeight: '600', marginLeft: 10 },
    
    statusFilterBtn: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden', gap: 6 },
    statusFilterText: { fontSize: 12, fontWeight: '800', color: '#F8FAFC' },
    chip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden', marginRight: 8, justifyContent: 'center' },
    chipActive: { borderColor: 'transparent' },
    chipText: { fontSize: 12, fontWeight: '700', color: '#CBD5E1' },
    chipTextActive: { color: '#FFF', fontWeight: '900' },

    // List
    listContent: { paddingHorizontal: 16, paddingBottom: 100, gap: 12 },
    cardWrapper: { borderRadius: 20, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    card: { padding: 16 },
    cardTop: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
    cardIconBox: { width: 56, height: 56, borderRadius: 16, borderWidth: 1, alignItems: 'center', justifyContent: 'center' },
    cardInfo: { flex: 1 },
    cardTitle: { fontSize: 16, fontWeight: '800', color: '#F8FAFC', letterSpacing: -0.3 },
    cardSubRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 4 },
    cardSubText: { fontSize: 12, color: '#94A3B8', fontWeight: '600' },
    cardRightActions: { alignItems: 'flex-end', justifyContent: 'space-between', height: 56 },
    statusDot: { width: 10, height: 10, borderRadius: 5, shadowOffset: { width: 0, height: 0 }, shadowOpacity: 1, shadowRadius: 5, elevation: 5 },
    editBtn: { width: 36, height: 36, borderRadius: 18, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },

    // Modals
    modalOverlay: { flex: 1, justifyContent: 'flex-end' },
    modalContent: { borderTopLeftRadius: 32, borderTopRightRadius: 32, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginTop: 12 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 24, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    modalTitle: { fontSize: 22, fontWeight: '900', color: '#F8FAFC' },
    modalSubtitle: { fontSize: 13, fontWeight: '700', color: '#38BDF8', marginTop: 4 },
    modalCloseBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    
    inputLabel: { fontSize: 11, fontWeight: '800', color: '#94A3B8', marginBottom: 8, marginLeft: 4, letterSpacing: 0.5, marginTop: 16 },
    input: { backgroundColor: 'rgba(0,0,0,0.3)', borderRadius: 16, padding: 16, fontSize: 15, fontWeight: '600', color: '#F8FAFC', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    selectorBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.3)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 16, padding: 16 },
    selectorBtnText: { flex: 1, fontSize: 15, fontWeight: '600', color: '#F8FAFC' },
    switchRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 24, paddingHorizontal: 4 },
    switchLabel: { fontSize: 14, fontWeight: '700', color: '#E2E8F0' },
    errorText: { color: '#F87171', fontSize: 11, marginTop: 4, marginLeft: 4, fontWeight: '600' },
    
    modalActions: { flexDirection: 'row', marginTop: 32, gap: 12 },
    deleteBtn: { width: 64, height: 64, borderRadius: 20, backgroundColor: 'rgba(239,68,68,0.1)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(239,68,68,0.2)' },
    saveBtn: { flex: 1, height: 64, borderRadius: 20, overflow: 'hidden', alignItems: 'center', justifyContent: 'center' },
    saveBtnText: { color: '#FFFFFF', fontSize: 16, fontWeight: '900', letterSpacing: 0.5 },

    selectionListItem: { paddingVertical: 18, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    selectionListText: { fontSize: 16, fontWeight: '800', color: '#F8FAFC', textAlign: 'center' }
});
