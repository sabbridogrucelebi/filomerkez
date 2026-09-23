import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Alert, Dimensions, TextInput, Platform, Animated, Easing, Modal, KeyboardAvoidingView, ScrollView } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import dayjs from 'dayjs';
import 'dayjs/locale/tr';

dayjs.locale('tr');
const { width: W } = Dimensions.get('window');
const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 0 }).format(v || 0);

export default function PayrollScreen({ navigation }) {
    const { hasPermission, user } = useContext(AuthContext);
    
    const [period, setPeriod] = useState(dayjs().format('YYYY-MM'));
    const [data, setData] = useState([]);
    const [isLocked, setIsLocked] = useState(false);
    const [loading, setLoading] = useState(true);
    
    // Modal & Form State
    const [modalVisible, setModalVisible] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editingDriver, setEditingDriver] = useState(null);
    const [formData, setFormData] = useState({
        base_salary: '0', bank_payment: '0', advance_payment: '0', deduction: '0', extra_bonus: '0', traffic_penalty: '0', deduction_notes: '', extra_notes: ''
    });

    // Animations
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        const loop = Animated.loop(
            Animated.sequence([
                Animated.timing(blob1Anim, { toValue: 1, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob1Anim, { toValue: 0, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        const loop2 = Animated.loop(
            Animated.sequence([
                Animated.timing(blob2Anim, { toValue: 1, duration: 12000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob2Anim, { toValue: 0, duration: 12000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        loop.start(); loop2.start();
        return () => { loop.stop(); loop2.stop(); };
    }, []);

    const fetchPeriodData = async (currentPeriod) => {
        setLoading(true);
        try {
            const r = await api.get(`/v1/payrolls/period/${currentPeriod}`);
            if (r.data.success) {
                setData(r.data.data.payrolls || []);
                setIsLocked(r.data.data.is_locked || false);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchPeriodData(period); }, [period]);

    const changeMonth = (diff) => {
        const newPeriod = dayjs(period, 'YYYY-MM').add(diff, 'month').format('YYYY-MM');
        setPeriod(newPeriod);
    };

    const toggleLock = async () => {
        if (!user?.role?.includes('admin')) {
            Alert.alert('Yetkisiz', 'Sadece yöneticiler kilidi değiştirebilir.');
            return;
        }
        try {
            const r = await api.post(`/v1/payrolls/period/${period}/lock`);
            if (r.data.success) setIsLocked(r.data.data.is_locked);
        } catch (e) {
            Alert.alert('Hata', 'Kilit durumu değiştirilemedi.');
        }
    };

    const openEdit = (item) => {
        if (isLocked) {
            Alert.alert('Kilitli Dönem', 'Bu dönem kilitli olduğu için düzenleme yapılamaz.');
            return;
        }
        setEditingDriver(item);
        const existing = item.existing || {};
        const calc = item.calculation || {};
        
        setFormData({
            base_salary: (existing.base_salary ?? calc.base_salary ?? 0).toString(),
            bank_payment: (existing.bank_payment ?? 0).toString(),
            traffic_penalty: (existing.traffic_penalty ?? 0).toString(),
            advance_payment: (existing.advance_payment ?? 0).toString(),
            deduction: (existing.deduction ?? 0).toString(),
            deduction_notes: existing.deduction_notes ?? '',
            extra_bonus: (existing.extra_bonus ?? 0).toString(),
            extra_notes: existing.extra_notes ?? ''
        });
        setModalVisible(true);
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            const payload = {
                driver_id: editingDriver.driver.id,
                period: period,
                data: {
                    base_salary: parseFloat(formData.base_salary) || 0,
                    bank_payment: parseFloat(formData.bank_payment) || 0,
                    traffic_penalty: parseFloat(formData.traffic_penalty) || 0,
                    advance_payment: parseFloat(formData.advance_payment) || 0,
                    deduction: parseFloat(formData.deduction) || 0,
                    deduction_notes: formData.deduction_notes || '',
                    extra_bonus: parseFloat(formData.extra_bonus) || 0,
                    extra_notes: formData.extra_notes || '',
                    extra_earnings: editingDriver.calculation?.extra_earnings || 0,
                }
            };
            
            const r = await api.post('/v1/payrolls/single-update', payload);
            if (r.data.success) {
                setModalVisible(false);
                fetchPeriodData(period);
            }
        } catch (e) {
            Alert.alert('Hata', e.response?.data?.message || 'Bordro güncellenemedi.');
        } finally {
            setSaving(false);
        }
    };

    // Calculate Grand Totals
    const tNet = data.reduce((sum, d) => {
        let net = 0;
        if (d.existing && d.existing.net_salary != null) {
            net = parseFloat(d.existing.net_salary);
        } else {
            const base = parseFloat(d.calculation?.base_salary || 0);
            const extra = parseFloat(d.calculation?.extra_earnings || 0);
            const penalty = parseFloat(d.existing?.traffic_penalty || 0);
            net = base + extra - penalty;
        }
        return sum + (isNaN(net) ? 0 : net);
    }, 0);

    const tBank = data.reduce((sum, d) => sum + parseFloat(d.existing?.bank_payment || 0), 0);
    const tExtra = data.reduce((sum, d) => sum + parseFloat(d.calculation?.extra_earnings || 0) + parseFloat(d.existing?.extra_bonus || 0), 0);

    const renderCard = ({ item }) => {
        const c = item.calculation || {};
        const e = item.existing || {};
        
        const base = parseFloat(e.base_salary ?? c.base_salary ?? 0);
        const bank = parseFloat(e.bank_payment ?? 0);
        const extraEarn = parseFloat(c.extra_earnings ?? 0);
        const penalty = parseFloat(e.traffic_penalty ?? 0);
        const advance = parseFloat(e.advance_payment ?? 0);
        const deduc = parseFloat(e.deduction ?? 0);
        const extraB = parseFloat(e.extra_bonus ?? 0);
        const net = e.net_salary != null ? parseFloat(e.net_salary) : (base + extraEarn + extraB - bank - penalty - advance - deduc);

        return (
            <View style={s.cardWrapper}>
                <BlurView intensity={25} tint="dark" style={s.card}>
                    <View style={s.cardHeader}>
                        <View style={s.cardTitleArea}>
                            <View style={s.driverIcon}><Icon name="account-tie" size={24} color="#38BDF8" /></View>
                            <View>
                                <Text style={s.driverName}>{item.driver.full_name}</Text>
                                {item.driver.vehicle?.plate && (
                                    <View style={s.plateBadge}>
                                        <Text style={s.plateText}>{item.driver.vehicle.plate}</Text>
                                    </View>
                                )}
                            </View>
                        </View>
                        <View style={s.cardActions}>
                            <TouchableOpacity style={s.actionBtn} onPress={() => navigation.navigate('PayrollDetail', { driverData: item, periodMonth: period })}>
                                <BlurView intensity={20} tint="light" style={s.actionBtnInner}>
                                    <Icon name="file-document-outline" size={16} color="#E2E8F0" />
                                </BlurView>
                            </TouchableOpacity>
                            <TouchableOpacity style={s.actionBtn} onPress={() => openEdit(item)}>
                                <BlurView intensity={20} tint="light" style={[s.actionBtnInner, { backgroundColor: 'rgba(52,211,153,0.2)', borderColor: 'rgba(52,211,153,0.3)' }]}>
                                    <Icon name="pencil" size={16} color="#34D399" />
                                </BlurView>
                            </TouchableOpacity>
                        </View>
                    </View>

                    <View style={s.statsGrid}>
                        <View style={s.statBox}><Text style={s.statLabel} numberOfLines={1}>Ana Maaş</Text><Text style={s.statVal} numberOfLines={1}>{fmtMoney(base)}</Text></View>
                        <View style={s.statBox}><Text style={s.statLabel} numberOfLines={1}>Banka</Text><Text style={[s.statVal, {color:'#60A5FA'}]} numberOfLines={1}>{fmtMoney(bank)}</Text></View>
                        <View style={s.statBox}><Text style={s.statLabel} numberOfLines={1}>Ek Hakediş</Text><Text style={[s.statVal, {color:'#34D399'}]} numberOfLines={1}>+{fmtMoney(extraEarn)}</Text></View>
                        <View style={s.statBox}><Text style={s.statLabel} numberOfLines={1}>T. Cezası</Text><Text style={[s.statVal, {color:'#F87171'}]} numberOfLines={1}>-{fmtMoney(penalty)}</Text></View>
                        <View style={s.statBox}><Text style={s.statLabel} numberOfLines={1}>Avans</Text><Text style={[s.statVal, {color:'#FBBF24'}]} numberOfLines={1}>-{fmtMoney(advance)}</Text></View>
                        <View style={s.statBox}><Text style={s.statLabel} numberOfLines={1}>Kesinti</Text><Text style={[s.statVal, {color:'#F87171'}]} numberOfLines={1}>-{fmtMoney(deduc)}</Text></View>
                        <View style={s.statBox}><Text style={s.statLabel} numberOfLines={1}>Ekstra(+)</Text><Text style={[s.statVal, {color:'#C084FC'}]} numberOfLines={1}>+{fmtMoney(extraB)}</Text></View>
                        <LinearGradient colors={['rgba(16,185,129,0.1)', 'rgba(16,185,129,0.2)']} style={[s.statBox, { borderColor: 'rgba(16,185,129,0.3)' }]}>
                            <Text style={[s.statLabel, {color:'#6EE7B7'}]} numberOfLines={1}>Net Ödenecek</Text>
                            <Text style={[s.statVal, {color:'#10B981', fontWeight:'900'}]} numberOfLines={1}>{fmtMoney(net)}</Text>
                        </LinearGradient>
                    </View>
                </BlurView>
            </View>
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
                <View style={s.headerTop}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={26} color="#FFF" />
                    </TouchableOpacity>
                    <View style={{ flex: 1, alignItems: 'center' }}>
                        <Text style={s.headerTitle}>Maaşlar</Text>
                        <Text style={s.headerSubtitle}>Maaş & Finansal Yönetim</Text>
                    </View>
                    <View style={{ width: 44 }} />
                </View>

                {/* Period Section */}
                <View style={s.periodSection}>
                    <View style={s.monthPicker}>
                        <TouchableOpacity onPress={() => changeMonth(-1)} style={s.monthArrow}>
                            <Icon name="chevron-left" size={24} color="#94A3B8" />
                        </TouchableOpacity>
                        <Text style={s.monthText}>{dayjs(period, 'YYYY-MM').format('MMMM YYYY')}</Text>
                        <TouchableOpacity onPress={() => changeMonth(1)} style={s.monthArrow}>
                            <Icon name="chevron-right" size={24} color="#94A3B8" />
                        </TouchableOpacity>
                    </View>
                    <TouchableOpacity onPress={toggleLock} style={[s.lockBadge, { backgroundColor: isLocked ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)' }]}>
                        <Icon name={isLocked ? 'lock' : 'lock-open-variant'} size={16} color={isLocked ? '#F87171' : '#34D399'} />
                        <Text style={[s.lockText, { color: isLocked ? '#F87171' : '#34D399' }]}>{isLocked ? 'KİLİTLİ' : 'AÇIK'}</Text>
                    </TouchableOpacity>
                </View>

                {/* Totals Section */}
                <View style={s.totalsWrap}>
                    <BlurView intensity={30} tint="dark" style={s.totalsCard}>
                        <Text style={s.tTitle}>GENEL TOPLAMLAR</Text>
                        <View style={s.tGrid}>
                            <View style={s.tBox}>
                                <View style={[s.tIconWrap, { backgroundColor: 'rgba(52,211,153,0.1)' }]}><Icon name="cash-multiple" size={20} color="#34D399" /></View>
                                <Text style={s.tLabel}>Toplam Net</Text>
                                <Text style={[s.tVal, {color:'#34D399'}]}>{fmtMoney(tNet)}</Text>
                            </View>
                            <View style={s.tBox}>
                                <View style={[s.tIconWrap, { backgroundColor: 'rgba(96,165,250,0.1)' }]}><Icon name="bank" size={20} color="#60A5FA" /></View>
                                <Text style={s.tLabel}>Toplam Banka</Text>
                                <Text style={[s.tVal, {color:'#60A5FA'}]}>{fmtMoney(tBank)}</Text>
                            </View>
                            <View style={s.tBox}>
                                <View style={[s.tIconWrap, { backgroundColor: 'rgba(192,132,252,0.1)' }]}><Icon name="plus-circle-multiple" size={20} color="#C084FC" /></View>
                                <Text style={s.tLabel}>Ek Hakediş</Text>
                                <Text style={[s.tVal, {color:'#C084FC'}]}>{fmtMoney(tExtra)}</Text>
                            </View>
                        </View>
                    </BlurView>
                </View>

                {loading ? (
                    <View style={{ flex: 1, justifyContent: 'center' }}><ActivityIndicator size="large" color="#38BDF8" /></View>
                ) : (
                    <FlatList
                        data={data}
                        keyExtractor={(i) => i.driver.id.toString()}
                        renderItem={renderCard}
                        contentContainerStyle={s.listContent}
                        showsVerticalScrollIndicator={false}
                        initialNumToRender={6}
                        maxToRenderPerBatch={6}
                        windowSize={5}
                        removeClippedSubviews={true}
                    />
                )}
            </SafeAreaView>

            {/* Custom 3D Bottom Sheet Modal */}
            <Modal visible={modalVisible} animationType="slide" transparent>
                <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={s.modalOverlay}>
                    <BlurView intensity={40} tint="dark" style={StyleSheet.absoluteFillObject} />
                    <TouchableOpacity style={StyleSheet.absoluteFill} onPress={() => setModalVisible(false)} />
                    
                    <BlurView intensity={70} tint="dark" style={s.modalContent}>
                        <View style={s.sheetHandle} />
                        <View style={s.modalHeader}>
                            <View style={{ flex: 1 }}>
                                <Text style={s.modalTitle}>Bordro Düzenle</Text>
                                <Text style={s.modalSubtitle}>{editingDriver?.driver?.full_name} • {dayjs(period, 'YYYY-MM').format('MMMM YYYY')}</Text>
                            </View>
                            <TouchableOpacity onPress={() => setModalVisible(false)} style={s.modalCloseBtn}>
                                <Icon name="close" size={24} color="#F8FAFC" />
                            </TouchableOpacity>
                        </View>

                        <ScrollView contentContainerStyle={{ padding: 20 }}>
                            <View style={s.inputRow}>
                                <View style={s.inputWrap}>
                                    <Text style={s.inputLabel}>Ana Maaş</Text>
                                    <TextInput style={s.input} keyboardType="numeric" value={formData.base_salary} onChangeText={t => setFormData({...formData, base_salary: t})} />
                                </View>
                                <View style={s.inputWrap}>
                                    <Text style={s.inputLabel}>Bankaya Yatan</Text>
                                    <TextInput style={s.input} keyboardType="numeric" value={formData.bank_payment} onChangeText={t => setFormData({...formData, bank_payment: t})} />
                                </View>
                            </View>
                            
                            <View style={s.inputRow}>
                                <View style={s.inputWrap}>
                                    <Text style={[s.inputLabel, { color: '#F87171' }]}>Trafik Cezası (-)</Text>
                                    <TextInput style={[s.input, { borderColor: 'rgba(239,68,68,0.3)', backgroundColor: 'rgba(239,68,68,0.05)' }]} keyboardType="numeric" value={formData.traffic_penalty} onChangeText={t => setFormData({...formData, traffic_penalty: t})} />
                                </View>
                                <View style={s.inputWrap}>
                                    <Text style={[s.inputLabel, { color: '#FBBF24' }]}>Avans (-)</Text>
                                    <TextInput style={[s.input, { borderColor: 'rgba(245,158,11,0.3)', backgroundColor: 'rgba(245,158,11,0.05)' }]} keyboardType="numeric" value={formData.advance_payment} onChangeText={t => setFormData({...formData, advance_payment: t})} />
                                </View>
                            </View>

                            <View style={s.inputRow}>
                                <View style={s.inputWrap}>
                                    <Text style={[s.inputLabel, { color: '#F87171' }]}>Kesinti / İcra (-)</Text>
                                    <TextInput style={[s.input, { borderColor: 'rgba(239,68,68,0.3)', backgroundColor: 'rgba(239,68,68,0.05)' }]} keyboardType="numeric" value={formData.deduction} onChangeText={t => setFormData({...formData, deduction: t})} />
                                    <TextInput style={[s.input, s.notesInput, { color: '#FCA5A5', borderColor: 'rgba(239,68,68,0.2)' }]} placeholder="Kesinti Sebebi" placeholderTextColor="rgba(252,165,165,0.5)" value={formData.deduction_notes} onChangeText={t => setFormData({...formData, deduction_notes: t})} />
                                </View>
                                <View style={s.inputWrap}>
                                    <Text style={[s.inputLabel, { color: '#34D399' }]}>Ekstra (+)</Text>
                                    <TextInput style={[s.input, { borderColor: 'rgba(52,211,153,0.3)', backgroundColor: 'rgba(52,211,153,0.05)' }]} keyboardType="numeric" value={formData.extra_bonus} onChangeText={t => setFormData({...formData, extra_bonus: t})} />
                                    <TextInput style={[s.input, s.notesInput, { color: '#6EE7B7', borderColor: 'rgba(52,211,153,0.2)' }]} placeholder="Ekstra Sebebi" placeholderTextColor="rgba(110,231,183,0.5)" value={formData.extra_notes} onChangeText={t => setFormData({...formData, extra_notes: t})} />
                                </View>
                            </View>

                            <TouchableOpacity style={[s.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSave} disabled={saving}>
                                <LinearGradient colors={['#3B82F6', '#2563EB']} style={StyleSheet.absoluteFillObject} />
                                {saving ? <ActivityIndicator color="#fff" /> : <Text style={s.saveBtnText}>Bordroyu Kaydet</Text>}
                            </TouchableOpacity>
                            <View style={{height: 40}} />
                        </ScrollView>
                    </BlurView>
                </KeyboardAvoidingView>
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
    headerSubtitle: { fontSize: 13, fontWeight: '600', color: '#38BDF8', marginTop: 2, letterSpacing: 0.5 },
    
    periodSection: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 16, marginTop: 16 },
    monthPicker: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.05)', borderRadius: 16, paddingHorizontal: 8, paddingVertical: 6, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    monthArrow: { padding: 4 },
    monthText: { fontSize: 16, fontWeight: '800', color: '#F8FAFC', marginHorizontal: 12, minWidth: 110, textAlign: 'center' },
    lockBadge: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 12, gap: 6, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    lockText: { fontSize: 11, fontWeight: '800', letterSpacing: 0.5 },

    totalsWrap: { padding: 16, marginTop: 4 },
    totalsCard: { borderRadius: 24, padding: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    tTitle: { fontSize: 11, fontWeight: '800', color: '#94A3B8', marginBottom: 16, letterSpacing: 1 },
    tGrid: { flexDirection: 'row', justifyContent: 'space-between' },
    tBox: { flex: 1, alignItems: 'center' },
    tIconWrap: { width: 40, height: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginBottom: 8 },
    tLabel: { fontSize: 11, color: '#94A3B8', fontWeight: '700', marginBottom: 4 },
    tVal: { fontSize: 16, fontWeight: '900' },

    listContent: { paddingHorizontal: 16, paddingBottom: 100, gap: 16 },
    cardWrapper: { borderRadius: 24, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    card: { padding: 16 },
    cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 16 },
    cardTitleArea: { flexDirection: 'row', alignItems: 'center', gap: 12, flex: 1 },
    driverIcon: { width: 44, height: 44, borderRadius: 14, backgroundColor: 'rgba(56,189,248,0.15)', alignItems: 'center', justifyContent: 'center' },
    driverName: { fontSize: 15, fontWeight: '800', color: '#F8FAFC' },
    plateBadge: { backgroundColor: 'rgba(255,255,255,0.1)', alignSelf: 'flex-start', paddingHorizontal: 8, paddingVertical: 2, borderRadius: 6, marginTop: 4 },
    plateText: { fontSize: 10, fontWeight: '700', color: '#CBD5E1' },
    cardActions: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    actionBtn: { borderRadius: 12, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    actionBtnInner: { paddingHorizontal: 12, paddingVertical: 10, alignItems: 'center', justifyContent: 'center' },
    
    statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, justifyContent: 'space-between' },
    statBox: { width: '23.5%', backgroundColor: 'rgba(0,0,0,0.2)', borderRadius: 12, paddingVertical: 8, paddingHorizontal: 2, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    statLabel: { fontSize: 9, color: '#94A3B8', fontWeight: '700', marginBottom: 4, textAlign: 'center' },
    statVal: { fontSize: 11, color: '#E2E8F0', fontWeight: '800', textAlign: 'center' },

    // Modals
    modalOverlay: { flex: 1, justifyContent: 'flex-end' },
    modalContent: { borderTopLeftRadius: 32, borderTopRightRadius: 32, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.2)', alignSelf: 'center', marginTop: 12 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 24, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    modalTitle: { fontSize: 22, fontWeight: '900', color: '#F8FAFC' },
    modalSubtitle: { fontSize: 13, fontWeight: '700', color: '#38BDF8', marginTop: 4 },
    modalCloseBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center' },
    
    inputRow: { flexDirection: 'row', gap: 12, marginBottom: 16 },
    inputWrap: { flex: 1 },
    inputLabel: { fontSize: 11, fontWeight: '800', color: '#94A3B8', marginBottom: 8, marginLeft: 4, letterSpacing: 0.5 },
    input: { backgroundColor: 'rgba(0,0,0,0.3)', borderRadius: 16, padding: 16, fontSize: 16, fontWeight: '800', color: '#F8FAFC', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    notesInput: { marginTop: 8, fontSize: 12, fontStyle: 'italic', paddingVertical: 12, fontWeight: '600' },
    
    saveBtn: { marginTop: 12, height: 60, borderRadius: 20, overflow: 'hidden', alignItems: 'center', justifyContent: 'center' },
    saveBtnText: { color: '#FFFFFF', fontSize: 16, fontWeight: '900', letterSpacing: 0.5 }
});
