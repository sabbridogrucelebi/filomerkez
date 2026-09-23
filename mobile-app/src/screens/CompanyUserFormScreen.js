import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, ActivityIndicator, Alert, TextInput, Switch, LayoutAnimation, UIManager, Platform, Animated, Easing, KeyboardAvoidingView } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import api from '../api/axios';
import { BlurView } from 'expo-blur';

if (Platform.OS === 'android' && UIManager.setLayoutAnimationEnabledExperimental) {
    UIManager.setLayoutAnimationEnabledExperimental(true);
}

const MODULE_CONFIG = [
    { title: 'Araçlar', icon: 'car-multiple', colors: ['#38BDF8', '#0284C7'], keys: ['vehicles.view', 'vehicles.create', 'vehicles.edit', 'vehicles.delete'] },
    { title: 'Personeller', icon: 'account-tie', colors: ['#818CF8', '#4F46E5'], keys: ['drivers.view', 'drivers.create', 'drivers.edit', 'drivers.delete'] },
    { title: 'Bakım / Tamir', icon: 'wrench', colors: ['#34D399', '#059669'], keys: ['maintenances.view', 'maintenances.create', 'maintenances.edit', 'maintenances.delete'] },
    { title: 'Yakıt', icon: 'gas-station', colors: ['#FBBF24', '#D97706'], keys: ['fuels.view', 'fuels.create', 'fuels.edit', 'fuels.delete', 'fuel_stations.view', 'fuel_stations.create', 'fuel_stations.edit', 'fuel_stations.delete'] },
    { title: 'Trafik Cezaları', icon: 'police-badge', colors: ['#F87171', '#DC2626'], keys: ['penalties.view', 'penalties.create', 'penalties.edit', 'penalties.delete'] },
    { title: 'Puantaj / Sefer', icon: 'calendar-clock', colors: ['#38BDF8', '#0284C7'], keys: ['trips.view', 'trips.create', 'trips.edit', 'trips.delete'] },
    { title: 'Maaşlar', icon: 'cash-multiple', colors: ['#A3E635', '#65A30D'], keys: ['payrolls.view', 'payrolls.create', 'payrolls.edit', 'payrolls.delete'] },
    { title: 'Müşteriler', icon: 'office-building', colors: ['#2DD4BF', '#0D9488'], keys: ['customers.view', 'customers.create', 'customers.edit', 'customers.delete'] },
    { title: 'Belgeler', icon: 'file-document-multiple', colors: ['#94A3B8', '#475569'], keys: ['documents.view', 'documents.create', 'documents.edit', 'documents.delete'] },
    { title: 'Raporlar & Finans', icon: 'chart-bar', colors: ['#818CF8', '#4338CA'], keys: ['reports.view', 'financials.view', 'dashboard.view'] }
];

export default function CompanyUserFormScreen({ route, navigation }) {
    const { userId } = route.params || {};
    const isEditing = !!userId;

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [permissionsList, setPermissionsList] = useState([]);

    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        role: 'operation',
        is_active: true,
        permissions: []
    });

    const [errors, setErrors] = useState({});
    const [expandedModules, setExpandedModules] = useState({});

    // Animations
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        const loop = Animated.loop(Animated.sequence([
            Animated.timing(blob1Anim, { toValue: 1, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
            Animated.timing(blob1Anim, { toValue: 0, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
        ]));
        const loop2 = Animated.loop(Animated.sequence([
            Animated.timing(blob2Anim, { toValue: 1, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
            Animated.timing(blob2Anim, { toValue: 0, duration: 11000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
        ]));
        loop.start(); loop2.start();
        return () => { loop.stop(); loop2.stop(); };
    }, []);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const optRes = await api.get('/v1/company-users/options');
                if (optRes.data.success) {
                    setPermissionsList(optRes.data.data.permissions || []);
                }

                if (isEditing) {
                    const userRes = await api.get(`/v1/company-users/${userId}`);
                    if (userRes.data.success) {
                        const u = userRes.data.data;
                        setForm({
                            name: u.name,
                            email: u.email,
                            password: '', 
                            role: u.role,
                            is_active: !!u.is_active,
                            permissions: u.permission_ids || []
                        });
                    }
                }
            } catch (error) {
                Alert.alert('Hata', 'Veriler alınamadı. Yetkiniz olmayabilir.');
                navigation.goBack();
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, [userId]);

    const togglePermission = (permId) => {
        setForm(prev => {
            const current = prev.permissions;
            if (current.includes(permId)) {
                return { ...prev, permissions: current.filter(id => id !== permId) };
            } else {
                return { ...prev, permissions: [...current, permId] };
            }
        });
    };

    const toggleModuleExpander = (index) => {
        LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
        setExpandedModules(prev => ({
            ...prev,
            [index]: !prev[index]
        }));
    };

    const selectAllInModule = (moduleKeys) => {
        const pMap = {};
        permissionsList.forEach(p => pMap[p.key] = p.id);
        
        const idsToToggle = moduleKeys.map(k => pMap[k]).filter(id => id !== undefined);
        const allSelected = idsToToggle.every(id => form.permissions.includes(id));
        
        setForm(prev => {
            let newPerms = [...prev.permissions];
            if (allSelected) {
                newPerms = newPerms.filter(id => !idsToToggle.includes(id));
            } else {
                idsToToggle.forEach(id => {
                    if (!newPerms.includes(id)) newPerms.push(id);
                });
            }
            return { ...prev, permissions: newPerms };
        });
    };

    const getModuleStats = (moduleKeys) => {
        const pMap = {};
        permissionsList.forEach(p => pMap[p.key] = p.id);
        const ids = moduleKeys.map(k => pMap[k]).filter(id => id !== undefined);
        const active = ids.filter(id => form.permissions.includes(id)).length;
        return { total: ids.length, active, ids };
    };

    const handleSave = async () => {
        setErrors({});
        setSaving(true);
        try {
            const payload = { ...form };
            if (isEditing && !payload.password) {
                delete payload.password; 
            }

            let res;
            if (isEditing) {
                res = await api.put(`/v1/company-users/${userId}`, payload);
            } else {
                res = await api.post('/v1/company-users', payload);
            }

            if (res.data.success) {
                Alert.alert('Başarılı', res.data.message || 'Kullanıcı kaydedildi.');
                navigation.goBack();
            } else {
                Alert.alert('Hata', res.data.message || 'Bir hata oluştu.');
            }
        } catch (e) {
            if (e.response?.status === 422) {
                setErrors(e.response.data.errors || {});
                Alert.alert('Eksik Bilgi', 'Lütfen formu kontrol edin.');
            } else if (e.response?.status === 403) {
                Alert.alert('Hata', e.response.data.message || 'Yetkiniz yok veya limit aşıldı.');
            } else {
                Alert.alert('Hata', 'Sunucu ile bağlantı kurulamadı.');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <View style={st.container}>
                <View style={st.loader}><ActivityIndicator size="large" color="#818CF8" /></View>
            </View>
        );
    }

    return (
        <View style={st.container}>
            {/* Animated Background */}
            <Animated.View style={StyleSheet.absoluteFill}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[st.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 60] }) }] }]} />
                <Animated.View style={[st.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -60] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top']}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={28} color="#FFF" />
                    </TouchableOpacity>
                    <Text style={st.headerTitle}>{isEditing ? "Kullanıcı Düzenle" : "Yeni Kullanıcı"}</Text>
                    <View style={{ width: 44 }} />
                </View>

                <ScrollView contentContainerStyle={st.scrollContent} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
                    
                    {/* Temel Bilgiler */}
                    <BlurView intensity={30} tint="dark" style={st.section}>
                        <View style={st.sectionHeader}>
                            <Icon name="card-account-details-outline" size={24} color="#818CF8" />
                            <Text style={st.sectionTitle}>Hesap Bilgileri</Text>
                        </View>

                        <View style={st.inputGroup}>
                            <Text style={st.label}>Ad Soyad</Text>
                            <TextInput 
                                style={[st.input, errors.name && st.inputError]} 
                                value={form.name} 
                                onChangeText={t => setForm({...form, name: t})} 
                                placeholder="Örn: Sabri Doğru"
                                placeholderTextColor="#94A3B8"
                            />
                            {errors.name && <Text style={st.errorTxt}>{errors.name[0]}</Text>}
                        </View>

                        <View style={st.inputGroup}>
                            <Text style={st.label}>E-posta</Text>
                            <TextInput 
                                style={[st.input, errors.email && st.inputError]} 
                                value={form.email} 
                                onChangeText={t => setForm({...form, email: t})} 
                                placeholder="ornek@firma.com"
                                autoCapitalize="none"
                                keyboardType="email-address"
                                placeholderTextColor="#94A3B8"
                            />
                            {errors.email && <Text style={st.errorTxt}>{errors.email[0]}</Text>}
                        </View>

                        <View style={st.inputGroup}>
                            <Text style={st.label}>{isEditing ? 'Yeni Şifre (Boş = Değişmez)' : 'Şifre'}</Text>
                            <TextInput 
                                style={[st.input, errors.password && st.inputError]} 
                                value={form.password} 
                                onChangeText={t => setForm({...form, password: t})} 
                                placeholder="En az 8 karakter"
                                secureTextEntry
                                placeholderTextColor="#94A3B8"
                            />
                            {errors.password && <Text style={st.errorTxt}>{errors.password[0]}</Text>}
                        </View>

                        <View style={st.inputGroup}>
                            <Text style={st.label}>Yetki Rolü</Text>
                            <View style={st.rolesRow}>
                                {[
                                    {val: 'company_admin', label: 'Firma Yöneticisi'},
                                    {val: 'operation', label: 'Operasyon'},
                                    {val: 'accounting', label: 'Muhasebe'},
                                    {val: 'viewer', label: 'Gözlemci'}
                                ].map(role => (
                                    <TouchableOpacity 
                                        key={role.val} 
                                        style={[st.roleChip, form.role === role.val && st.roleChipActive]}
                                        onPress={() => setForm({...form, role: role.val})}
                                    >
                                        <Text style={[st.roleChipTxt, form.role === role.val && st.roleChipTxtActive]}>
                                            {role.label}
                                        </Text>
                                    </TouchableOpacity>
                                ))}
                            </View>
                            {errors.role && <Text style={st.errorTxt}>{errors.role[0]}</Text>}
                        </View>

                        <View style={st.switchRow}>
                            <View>
                                <Text style={st.switchLabel}>Hesap Aktifliği</Text>
                                <Text style={st.switchSub}>Kullanıcı sisteme giriş yapabilsin mi?</Text>
                            </View>
                            <Switch 
                                value={form.is_active} 
                                onValueChange={v => setForm({...form, is_active: v})} 
                                trackColor={{ false: 'rgba(255,255,255,0.2)', true: '#34D399' }}
                                thumbColor="#FFF"
                            />
                        </View>
                    </BlurView>

                    {/* Modül Yetkileri */}
                    {form.role !== 'company_admin' && (
                        <BlurView intensity={30} tint="dark" style={st.section}>
                            <View style={st.sectionHeader}>
                                <View style={st.iconBoxPurp}>
                                    <Icon name="shield-key-outline" size={24} color="#FFF" />
                                </View>
                                <View style={{ flex: 1, marginLeft: 12 }}>
                                    <Text style={st.sectionTitleNoMargin}>Menü Erişimi</Text>
                                    <Text style={st.sectionSubNoMargin}>Kullanıcının modül yetkilerini yönetin</Text>
                                </View>
                            </View>

                            {MODULE_CONFIG.map((mod, index) => {
                                const { total, active, ids } = getModuleStats(mod.keys);
                                if (total === 0) return null; 
                                const isExpanded = !!expandedModules[index];
                                const isActive = active > 0;

                                return (
                                    <View key={index} style={[st.moduleCard, isActive ? { borderColor: mod.colors[0] } : null]}>
                                        <TouchableOpacity 
                                            style={st.moduleHeader} 
                                            activeOpacity={0.7} 
                                            onPress={() => toggleModuleExpander(index)}
                                        >
                                            <LinearGradient colors={isActive ? mod.colors : ['rgba(255,255,255,0.1)', 'rgba(255,255,255,0.05)']} style={st.moduleIconWrap}>
                                                <Icon name={mod.icon} size={24} color={isActive ? '#FFF' : '#94A3B8'} />
                                            </LinearGradient>
                                            
                                            <View style={st.moduleInfo}>
                                                <Text style={st.moduleTitle}>{mod.title}</Text>
                                                <View style={st.moduleCounterWrap}>
                                                    <View style={[st.moduleCounterBadge, isActive ? { backgroundColor: mod.colors[0] } : null]}>
                                                        <Text style={[st.moduleCounterTxt, isActive ? { color: '#FFF' } : null]}>{active}</Text>
                                                    </View>
                                                    <Text style={st.moduleTotalTxt}>/ {total}</Text>
                                                </View>
                                            </View>
                                            <Icon name={isExpanded ? "chevron-up" : "chevron-down"} size={24} color="#94A3B8" />
                                        </TouchableOpacity>

                                        {isExpanded && (
                                            <View style={st.moduleContent}>
                                                <TouchableOpacity 
                                                    style={st.selectAllBtn} 
                                                    onPress={() => selectAllInModule(mod.keys)}
                                                >
                                                    <Text style={st.selectAllBtnTxt}>Hepsini {active === total ? 'Kaldır' : 'Seç'}</Text>
                                                </TouchableOpacity>

                                                <View style={st.permList}>
                                                    {mod.keys.map(k => {
                                                        const permObj = permissionsList.find(p => p.key === k);
                                                        if (!permObj) return null;
                                                        const isSelected = form.permissions.includes(permObj.id);
                                                        return (
                                                            <TouchableOpacity 
                                                                key={permObj.id} 
                                                                style={[st.permRow, isSelected && { backgroundColor: mod.colors[0] + '20', borderColor: mod.colors[0] + '60' }]}
                                                                activeOpacity={0.7}
                                                                onPress={() => togglePermission(permObj.id)}
                                                            >
                                                                <Icon name={isSelected ? "checkbox-marked-circle" : "checkbox-blank-circle-outline"} size={22} color={isSelected ? mod.colors[0] : "#64748B"} style={{ marginRight: 12 }} />
                                                                <Text style={[st.permLabel, isSelected && { color: mod.colors[0] }]}>{permObj.label.toUpperCase()}</Text>
                                                            </TouchableOpacity>
                                                        );
                                                    })}
                                                </View>
                                            </View>
                                        )}
                                    </View>
                                );
                            })}
                        </BlurView>
                    )}

                    <TouchableOpacity style={[st.saveBtn, saving && { opacity: 0.7 }]} onPress={handleSave} disabled={saving}>
                        <LinearGradient colors={['#818CF8', '#4F46E5']} style={StyleSheet.absoluteFillObject} />
                        {saving ? <ActivityIndicator color="#FFF" /> : <Text style={st.saveBtnTxt}>Kaydet</Text>}
                    </TouchableOpacity>
                    <View style={{ height: 120 }} />
                </ScrollView>
            </SafeAreaView>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -50, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(99,102,241,0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(56,189,248,0.15)', filter: 'blur(40px)' },

    loader: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 16, paddingTop: 8, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 18, fontWeight: '900', color: '#F8FAFC' },

    scrollContent: { padding: 16 },
    
    section: { borderRadius: 24, padding: 20, marginBottom: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    sectionHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 20 },
    sectionTitle: { fontSize: 18, fontWeight: '800', color: '#F8FAFC', marginLeft: 10 },

    inputGroup: { marginBottom: 20 },
    label: { fontSize: 12, fontWeight: '800', color: '#94A3B8', marginBottom: 8, textTransform: 'uppercase', letterSpacing: 0.5 },
    input: { backgroundColor: 'rgba(0,0,0,0.3)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', borderRadius: 16, padding: 16, fontSize: 15, color: '#F8FAFC', fontWeight: '600' },
    inputError: { borderColor: '#F87171', backgroundColor: 'rgba(239,68,68,0.1)' },
    errorTxt: { color: '#F87171', fontSize: 11, marginTop: 4, fontWeight: '600' },

    rolesRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    roleChip: { paddingHorizontal: 16, paddingVertical: 10, borderRadius: 12, backgroundColor: 'rgba(255,255,255,0.05)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    roleChipActive: { backgroundColor: 'rgba(99,102,241,0.2)', borderColor: '#818CF8' },
    roleChipTxt: { fontSize: 13, fontWeight: '700', color: '#94A3B8' },
    roleChipTxtActive: { color: '#818CF8' },

    switchRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 10, paddingTop: 20, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
    switchLabel: { fontSize: 14, fontWeight: '800', color: '#F8FAFC' },
    switchSub: { fontSize: 12, color: '#94A3B8', marginTop: 2 },

    iconBoxPurp: { width: 44, height: 44, borderRadius: 14, backgroundColor: '#818CF8', alignItems: 'center', justifyContent: 'center' },
    sectionTitleNoMargin: { fontSize: 18, fontWeight: '900', color: '#F8FAFC' },
    sectionSubNoMargin: { fontSize: 12, color: '#94A3B8', fontWeight: '500', marginTop: 2 },

    moduleCard: { backgroundColor: 'rgba(0,0,0,0.2)', borderRadius: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', marginBottom: 12, overflow: 'hidden' },
    moduleHeader: { flexDirection: 'row', alignItems: 'center', padding: 16 },
    moduleIconWrap: { width: 48, height: 48, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
    moduleInfo: { flex: 1, marginLeft: 16 },
    moduleTitle: { fontSize: 15, fontWeight: '800', color: '#F8FAFC', marginBottom: 6 },
    moduleCounterWrap: { flexDirection: 'row', alignItems: 'center' },
    moduleCounterBadge: { backgroundColor: 'rgba(255,255,255,0.1)', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 8, minWidth: 24, alignItems: 'center' },
    moduleCounterTxt: { fontSize: 11, fontWeight: '900', color: '#94A3B8' },
    moduleTotalTxt: { fontSize: 11, fontWeight: '700', color: '#64748B', marginLeft: 6 },
    
    moduleContent: { padding: 16, paddingTop: 0, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)', backgroundColor: 'transparent' },
    selectAllBtn: { alignSelf: 'flex-start', backgroundColor: 'rgba(255,255,255,0.1)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 8, marginVertical: 12 },
    selectAllBtnTxt: { fontSize: 10, fontWeight: '800', color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 0.5 },
    
    permList: { gap: 8 },
    permRow: { flexDirection: 'row', alignItems: 'center', padding: 12, borderRadius: 12, backgroundColor: 'rgba(255,255,255,0.05)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    permLabel: { fontSize: 11, fontWeight: '800', color: '#94A3B8' },

    saveBtn: { borderRadius: 20, paddingVertical: 18, alignItems: 'center', marginTop: 10, overflow: 'hidden' },
    saveBtnTxt: { color: '#FFF', fontSize: 16, fontWeight: '800' }
});
