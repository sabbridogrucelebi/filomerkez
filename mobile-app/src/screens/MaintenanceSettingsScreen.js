import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, TextInput, ActivityIndicator, Alert, KeyboardAvoidingView, Platform, Modal } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Ionicons from '@expo/vector-icons/Ionicons';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import useStatusBarStyle from '../hooks/useStatusBarStyle';

export default function MaintenanceSettingsScreen({ navigation }) {
    useStatusBarStyle('dark-content');
    const { hasPermission } = React.useContext(AuthContext);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    
    const [activeTab, setActiveTab] = useState('intervals'); // 'intervals' | 'mechanics'
    
    // Data states
    const [vehicles, setVehicles] = useState([]);
    const [mechanics, setMechanics] = useState([]);
    const [intervalSettings, setIntervalSettings] = useState({});
    
    // Mechanic modal states
    const [isMechanicModalVisible, setMechanicModalVisible] = useState(false);
    const [editingMechanic, setEditingMechanic] = useState(null);
    const [mechanicNameInput, setMechanicNameInput] = useState('');

    useEffect(() => {
        fetchSettings();
    }, []);

    const fetchSettings = async () => {
        try {
            const response = await api.get('/v1/maintenances-settings');
            if (response.data.success) {
                const { vehicles: vData, mechanics: mData } = response.data.data;
                setVehicles(vData || []);
                setMechanics(mData || []);
                
                const settingsMap = {};
                (vData || []).forEach(v => {
                    settingsMap[v.id] = {
                        vehicle_id: v.id,
                        oil_change_interval_km: v.maintenance_setting?.oil_change_interval_km?.toString() || '',
                        under_lubrication_interval_km: v.maintenance_setting?.under_lubrication_interval_km?.toString() || '',
                    };
                });
                setIntervalSettings(settingsMap);
            }
        } catch (error) {
            console.error('Ayarlar yüklenirken hata:', error);
            Alert.alert('Hata', 'Ayarlar yüklenemedi.');
        } finally {
            setLoading(false);
        }
    };

    const handleSaveIntervals = async () => {
        if (!hasPermission('maintenances.view')) {
            Alert.alert('Yetki Hatası', 'Bu işlem için yetkiniz yok.');
            return;
        }

        setSaving(true);
        try {
            const settingsArray = Object.values(intervalSettings);
            const response = await api.post('/v1/maintenances-settings', { settings: settingsArray });
            if (response.data.success) {
                Alert.alert('Başarılı', 'Araç bakım aralıkları kaydedildi.');
            }
        } catch (error) {
            console.error('Ayarlar kaydedilirken hata:', error);
            Alert.alert('Hata', 'Ayarlar kaydedilemedi.');
        } finally {
            setSaving(false);
        }
    };

    const handleIntervalChange = (vehicleId, field, value) => {
        setIntervalSettings(prev => ({
            ...prev,
            [vehicleId]: {
                ...prev[vehicleId],
                [field]: value
            }
        }));
    };

    const openAddMechanic = () => {
        setEditingMechanic(null);
        setMechanicNameInput('');
        setMechanicModalVisible(true);
    };

    const openEditMechanic = (mechanic) => {
        setEditingMechanic(mechanic);
        setMechanicNameInput(mechanic.name);
        setMechanicModalVisible(true);
    };

    const handleSaveMechanic = async () => {
        if (!mechanicNameInput.trim()) {
            Alert.alert('Hata', 'Usta adı boş olamaz.');
            return;
        }

        try {
            let response;
            if (editingMechanic) {
                response = await api.put(`/v1/maintenances-mechanics/${editingMechanic.id}`, { name: mechanicNameInput });
            } else {
                response = await api.post('/v1/maintenances-mechanics', { name: mechanicNameInput });
            }

            if (response.data.success) {
                setMechanicModalVisible(false);
                fetchSettings();
            }
        } catch (error) {
            console.error('Usta kaydedilirken hata:', error);
            Alert.alert('Hata', 'İşlem başarısız.');
        }
    };

    const handleToggleMechanic = async (mechanic) => {
        try {
            const response = await api.patch(`/v1/maintenances-mechanics/${mechanic.id}/toggle`);
            if (response.data.success) {
                fetchSettings();
            }
        } catch (error) {
            console.error('Usta durumu güncellenirken hata:', error);
            Alert.alert('Hata', 'İşlem başarısız.');
        }
    };

    const handleDeleteMechanic = (mechanic) => {
        Alert.alert(
            'Ustayı Sil',
            `${mechanic.name} ustasını silmek istediğinize emin misiniz? (Geçmiş kayıtlardaki isimler etkilenmez)`,
            [
                { text: 'İptal', style: 'cancel' },
                { 
                    text: 'Sil', 
                    style: 'destructive',
                    onPress: async () => {
                        try {
                            const response = await api.delete(`/v1/maintenances-mechanics/${mechanic.id}`);
                            if (response.data.success) {
                                fetchSettings();
                            }
                        } catch (error) {
                            console.error('Usta silinirken hata:', error);
                            Alert.alert('Hata', 'Silme işlemi başarısız.');
                        }
                    }
                }
            ]
        );
    };

    if (loading) {
        return (
            <SafeAreaView style={styles.loadingContainer}>
                <ActivityIndicator size="large" color="#4F46E5" />
            </SafeAreaView>
        );
    }

    return (
        <SafeAreaView style={styles.container} edges={['top', 'bottom', 'left', 'right']}>
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                    <Ionicons name="arrow-back" size={24} color="#1e293b" />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>Bakım Ayarları</Text>
            </View>

            <View style={styles.tabContainer}>
                <TouchableOpacity 
                    style={[styles.tabButton, activeTab === 'intervals' && styles.tabButtonActive]}
                    onPress={() => setActiveTab('intervals')}
                >
                    <Ionicons name="options-outline" size={20} color={activeTab === 'intervals' ? '#4F46E5' : '#64748b'} />
                    <Text style={[styles.tabText, activeTab === 'intervals' && styles.tabTextActive]}>Araç Aralıkları</Text>
                </TouchableOpacity>
                <TouchableOpacity 
                    style={[styles.tabButton, activeTab === 'mechanics' && styles.tabButtonActive]}
                    onPress={() => setActiveTab('mechanics')}
                >
                    <Ionicons name="people-outline" size={20} color={activeTab === 'mechanics' ? '#4F46E5' : '#64748b'} />
                    <Text style={[styles.tabText, activeTab === 'mechanics' && styles.tabTextActive]}>Ustalar</Text>
                </TouchableOpacity>
            </View>

            <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : null}>
                <ScrollView contentContainerStyle={styles.scrollContent}>
                    
                    {activeTab === 'intervals' ? (
                        <View style={styles.section}>
                            <View style={styles.sectionHeader}>
                                <Text style={styles.sectionTitle}>Araç Bazlı Aralıklar</Text>
                                <Text style={styles.sectionSubtitle}>Yağ bakımı ve alt yağlama kilometre aralıklarını belirleyin.</Text>
                            </View>

                            {vehicles.map(vehicle => (
                                <View key={vehicle.id} style={styles.card}>
                                    <View style={styles.cardHeader}>
                                        <View style={styles.plateBadge}>
                                            <Text style={styles.plateText}>{vehicle.plate}</Text>
                                        </View>
                                        <Text style={styles.vehicleModel}>{vehicle.brand || ''} {vehicle.model || ''}</Text>
                                    </View>
                                    
                                    <View style={styles.inputRow}>
                                        <View style={styles.inputGroup}>
                                            <Text style={styles.inputLabel}>Yağ Bakım Aralığı (KM)</Text>
                                            <TextInput
                                                style={styles.input}
                                                keyboardType="numeric"
                                                placeholder="Örn: 10000"
                                                value={intervalSettings[vehicle.id]?.oil_change_interval_km}
                                                onChangeText={(val) => handleIntervalChange(vehicle.id, 'oil_change_interval_km', val)}
                                            />
                                        </View>
                                        <View style={{ width: 12 }} />
                                        <View style={styles.inputGroup}>
                                            <Text style={styles.inputLabel}>Alt Yağlama Aralığı (KM)</Text>
                                            <TextInput
                                                style={styles.input}
                                                keyboardType="numeric"
                                                placeholder="Örn: 5000"
                                                value={intervalSettings[vehicle.id]?.under_lubrication_interval_km}
                                                onChangeText={(val) => handleIntervalChange(vehicle.id, 'under_lubrication_interval_km', val)}
                                            />
                                        </View>
                                    </View>
                                </View>
                            ))}

                            <TouchableOpacity 
                                style={styles.saveButton} 
                                onPress={handleSaveIntervals}
                                disabled={saving}
                            >
                                {saving ? (
                                    <ActivityIndicator size="small" color="#fff" />
                                ) : (
                                    <>
                                        <Ionicons name="save-outline" size={20} color="#fff" />
                                        <Text style={styles.saveButtonText}>Aralıkları Kaydet</Text>
                                    </>
                                )}
                            </TouchableOpacity>
                        </View>
                    ) : (
                        <View style={styles.section}>
                            <View style={styles.sectionHeaderRow}>
                                <View style={{ flex: 1 }}>
                                    <Text style={styles.sectionTitle}>Usta Listesi</Text>
                                    <Text style={styles.sectionSubtitle}>Sistemde kayıtlı ustaları yönetin.</Text>
                                </View>
                                <TouchableOpacity style={styles.addButton} onPress={openAddMechanic}>
                                    <Ionicons name="add" size={20} color="#fff" />
                                    <Text style={styles.addButtonText}>Yeni Usta</Text>
                                </TouchableOpacity>
                            </View>

                            {mechanics.length === 0 ? (
                                <View style={styles.emptyContainer}>
                                    <Ionicons name="people-outline" size={48} color="#cbd5e1" />
                                    <Text style={styles.emptyTitle}>Kayıtlı usta yok</Text>
                                    <Text style={styles.emptyDesc}>Yeni bir usta tanımlayarak başlayabilirsiniz.</Text>
                                </View>
                            ) : (
                                mechanics.map(mechanic => (
                                    <View key={mechanic.id} style={styles.mechanicCard}>
                                        <View style={styles.mechanicInfo}>
                                            <View style={styles.mechanicAvatar}>
                                                <Text style={styles.mechanicInitial}>{mechanic.name.charAt(0).toUpperCase()}</Text>
                                            </View>
                                            <View>
                                                <Text style={styles.mechanicName}>{mechanic.name}</Text>
                                                <View style={[styles.statusBadge, mechanic.is_active ? styles.statusActive : styles.statusInactive]}>
                                                    <Text style={[styles.statusText, mechanic.is_active ? styles.statusTextActive : styles.statusTextInactive]}>
                                                        {mechanic.is_active ? 'Aktif' : 'Pasif'}
                                                    </Text>
                                                </View>
                                            </View>
                                        </View>
                                        
                                        <View style={styles.mechanicActions}>
                                            <TouchableOpacity 
                                                style={styles.actionBtn} 
                                                onPress={() => handleToggleMechanic(mechanic)}
                                            >
                                                <Ionicons name={mechanic.is_active ? "eye-off-outline" : "eye-outline"} size={20} color="#64748b" />
                                            </TouchableOpacity>
                                            <TouchableOpacity 
                                                style={styles.actionBtn} 
                                                onPress={() => openEditMechanic(mechanic)}
                                            >
                                                <Ionicons name="pencil-outline" size={20} color="#4F46E5" />
                                            </TouchableOpacity>
                                            <TouchableOpacity 
                                                style={styles.actionBtn} 
                                                onPress={() => handleDeleteMechanic(mechanic)}
                                            >
                                                <Ionicons name="trash-outline" size={20} color="#ef4444" />
                                            </TouchableOpacity>
                                        </View>
                                    </View>
                                ))
                            )}
                        </View>
                    )}
                </ScrollView>
            </KeyboardAvoidingView>

            {/* Mechanic Modal */}
            <Modal
                visible={isMechanicModalVisible}
                transparent={true}
                animationType="fade"
                onRequestClose={() => setMechanicModalVisible(false)}
            >
                <View style={StyleSheet.absoluteFill}>
                    <TouchableOpacity 
                        style={styles.modalOverlay} 
                        activeOpacity={1} 
                        onPress={() => setMechanicModalVisible(false)}
                    />
                    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.modalContentWrapper}>
                        <View style={styles.modalBox}>
                            <View style={styles.modalHeader}>
                                <Text style={styles.modalTitle}>{editingMechanic ? 'Usta Düzenle' : 'Yeni Usta Ekle'}</Text>
                                <TouchableOpacity onPress={() => setMechanicModalVisible(false)}>
                                    <Ionicons name="close" size={24} color="#64748b" />
                                </TouchableOpacity>
                            </View>

                            <View style={styles.modalBody}>
                                {editingMechanic && (
                                    <View style={styles.warningBox}>
                                        <Ionicons name="warning-outline" size={16} color="#d97706" />
                                        <Text style={styles.warningText}>İsmi değiştirirseniz geçmiş kayıtlardaki isimler de güncellenir.</Text>
                                    </View>
                                )}
                                
                                <Text style={styles.inputLabel}>Usta Adı Soyadı</Text>
                                <TextInput
                                    style={styles.modalInput}
                                    placeholder="Örn: Ahmet Yılmaz"
                                    value={mechanicNameInput}
                                    onChangeText={setMechanicNameInput}
                                    autoFocus
                                />
                            </View>

                            <View style={styles.modalFooter}>
                                <TouchableOpacity 
                                    style={styles.modalCancelBtn} 
                                    onPress={() => setMechanicModalVisible(false)}
                                >
                                    <Text style={styles.modalCancelText}>İptal</Text>
                                </TouchableOpacity>
                                <TouchableOpacity 
                                    style={styles.modalSaveBtn} 
                                    onPress={handleSaveMechanic}
                                >
                                    <Text style={styles.modalSaveText}>Kaydet</Text>
                                </TouchableOpacity>
                            </View>
                        </View>
                    </KeyboardAvoidingView>
                </View>
            </Modal>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#f8fafc' },
    loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', padding: 16, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#f1f5f9' },
    backButton: { padding: 8, marginRight: 8, backgroundColor: '#f1f5f9', borderRadius: 12 },
    headerTitle: { fontSize: 20, fontWeight: '800', color: '#0f172a' },
    
    tabContainer: { flexDirection: 'row', backgroundColor: '#fff', paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: '#f1f5f9' },
    tabButton: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 14, gap: 8, borderBottomWidth: 2, borderBottomColor: 'transparent' },
    tabButtonActive: { borderBottomColor: '#4F46E5' },
    tabText: { fontSize: 14, fontWeight: '600', color: '#64748b' },
    tabTextActive: { color: '#4F46E5' },

    scrollContent: { padding: 16, paddingBottom: 120 },
    section: { gap: 16 },
    sectionHeader: { marginBottom: 8 },
    sectionHeaderRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 },
    sectionTitle: { fontSize: 18, fontWeight: '800', color: '#1e293b' },
    sectionSubtitle: { fontSize: 13, color: '#64748b', marginTop: 2 },
    
    card: { backgroundColor: '#fff', borderRadius: 20, padding: 16, borderWidth: 1, borderColor: '#e2e8f0', marginBottom: 12, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 8, elevation: 2 },
    cardHeader: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 16 },
    plateBadge: { backgroundColor: '#f1f5f9', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 8, borderWidth: 1, borderColor: '#e2e8f0' },
    plateText: { fontSize: 14, fontWeight: '800', color: '#0f172a' },
    vehicleModel: { fontSize: 14, color: '#64748b', fontWeight: '500' },
    
    inputRow: { flexDirection: 'row' },
    inputGroup: { flex: 1 },
    inputLabel: { fontSize: 12, fontWeight: '700', color: '#475569', marginBottom: 6 },
    input: { backgroundColor: '#f8fafc', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 12, paddingHorizontal: 12, paddingVertical: 10, fontSize: 14, color: '#0f172a', fontWeight: '600' },
    
    saveButton: { backgroundColor: '#4F46E5', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, padding: 16, borderRadius: 16, marginTop: 8 },
    saveButtonText: { color: '#fff', fontSize: 15, fontWeight: '700' },

    addButton: { backgroundColor: '#4F46E5', flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 10, gap: 4 },
    addButtonText: { color: '#fff', fontSize: 13, fontWeight: '700' },
    
    mechanicCard: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: '#fff', borderRadius: 16, padding: 16, borderWidth: 1, borderColor: '#e2e8f0', marginBottom: 10 },
    mechanicInfo: { flexDirection: 'row', alignItems: 'center', gap: 12, flex: 1 },
    mechanicAvatar: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#e0e7ff', alignItems: 'center', justifyContent: 'center' },
    mechanicInitial: { fontSize: 16, fontWeight: '800', color: '#4F46E5' },
    mechanicName: { fontSize: 15, fontWeight: '700', color: '#1e293b', marginBottom: 4 },
    statusBadge: { alignSelf: 'flex-start', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 6, borderWidth: 1 },
    statusActive: { backgroundColor: '#ecfdf5', borderColor: '#a7f3d0' },
    statusInactive: { backgroundColor: '#fef2f2', borderColor: '#fecaca' },
    statusText: { fontSize: 10, fontWeight: '800' },
    statusTextActive: { color: '#059669' },
    statusTextInactive: { color: '#dc2626' },
    
    mechanicActions: { flexDirection: 'row', alignItems: 'center', gap: 4 },
    actionBtn: { padding: 8, borderRadius: 10, backgroundColor: '#f8fafc' },
    
    emptyContainer: { alignItems: 'center', justifyContent: 'center', paddingVertical: 40, backgroundColor: '#fff', borderRadius: 20, borderWidth: 1, borderColor: '#e2e8f0', borderStyle: 'dashed' },
    emptyTitle: { fontSize: 16, fontWeight: '700', color: '#475569', marginTop: 12, marginBottom: 4 },
    emptyDesc: { fontSize: 13, color: '#94a3b8' },

    // Modal styles
    modalOverlay: { flex: 1, backgroundColor: 'rgba(15, 23, 42, 0.6)' },
    modalContentWrapper: { position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: '#fff', borderTopLeftRadius: 24, borderTopRightRadius: 24, shadowColor: '#000', shadowOffset: { width: 0, height: -4 }, shadowOpacity: 0.1, shadowRadius: 12, elevation: 10 },
    modalBox: { paddingBottom: Platform.OS === 'ios' ? 40 : 20 },
    modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', padding: 20, borderBottomWidth: 1, borderBottomColor: '#f1f5f9' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#0f172a' },
    modalBody: { padding: 20 },
    warningBox: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: '#fef3c7', padding: 12, borderRadius: 12, marginBottom: 16, borderWidth: 1, borderColor: '#fde68a' },
    warningText: { fontSize: 12, color: '#92400e', flex: 1, fontWeight: '500' },
    modalInput: { borderWidth: 1, borderColor: '#cbd5e1', borderRadius: 12, padding: 14, fontSize: 15, color: '#0f172a', fontWeight: '600', backgroundColor: '#f8fafc' },
    modalFooter: { flexDirection: 'row', gap: 12, paddingHorizontal: 20 },
    modalCancelBtn: { flex: 1, padding: 14, borderRadius: 12, backgroundColor: '#f1f5f9', alignItems: 'center' },
    modalCancelText: { color: '#475569', fontSize: 15, fontWeight: '700' },
    modalSaveBtn: { flex: 1, padding: 14, borderRadius: 12, backgroundColor: '#4F46E5', alignItems: 'center' },
    modalSaveText: { color: '#fff', fontSize: 15, fontWeight: '700' },
});
