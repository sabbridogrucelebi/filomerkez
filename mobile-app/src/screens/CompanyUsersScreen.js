import React, { useState, useCallback, useRef, useEffect } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, Alert, RefreshControl, Animated, Easing } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { useFocusEffect } from '@react-navigation/native';
import api from '../api/axios';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';

export default function CompanyUsersScreen({ navigation }) {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState(null);

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

    const fetchUsers = async () => {
        try {
            setError(null);
            const response = await api.get('/v1/company-users');
            setUsers(response.data.data || []);
        } catch (err) {
            setError(err.response?.status === 403 ? 'Bu işlem için yetkiniz yok.' : 'Veriler alınamadı.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useFocusEffect(
        useCallback(() => {
            fetchUsers();
        }, [])
    );

    const onRefresh = () => {
        setRefreshing(true);
        fetchUsers();
    };

    const handleDelete = (user) => {
        Alert.alert(
            "Kullanıcıyı Sil",
            `${user.name} isimli kullanıcıyı silmek istediğinize emin misiniz? Bu işlem geri alınamaz.`,
            [
                { text: "Vazgeç", style: "cancel" },
                { 
                    text: "Sil", 
                    style: "destructive",
                    onPress: async () => {
                        try {
                            const res = await api.delete(`/v1/company-users/${user.id}`);
                            if (res.data.success) {
                                setUsers(prev => prev.filter(u => u.id !== user.id));
                            } else {
                                Alert.alert("Hata", res.data.message || "Silinemedi.");
                            }
                        } catch (e) {
                            Alert.alert("Hata", e.response?.data?.message || "Silme işlemi başarısız.");
                        }
                    }
                }
            ]
        );
    };

    const getRoleName = (role) => {
        switch (role) {
            case 'company_admin': return 'Firma Yöneticisi';
            case 'operation': return 'Operasyon';
            case 'accounting': return 'Muhasebe';
            case 'viewer': return 'Gözlemci';
            default: return role;
        }
    };

    const renderItem = ({ item }) => {
        const initials = item.name.substring(0, 2).toUpperCase();
        
        return (
            <TouchableOpacity 
                activeOpacity={0.8} 
                onPress={() => navigation.navigate('CompanyUserForm', { userId: item.id })}
                style={{ marginBottom: 12 }}
            >
                <BlurView intensity={30} tint="dark" style={st.card}>
                    <View style={st.cardHeader}>
                        <View style={st.avatar}>
                            <Text style={st.avatarTxt}>{initials}</Text>
                        </View>
                        <View style={st.cardInfo}>
                            <Text style={st.cardTitle}>{item.name}</Text>
                            <Text style={st.cardEmail}>{item.email}</Text>
                        </View>
                        <View style={st.statusWrap}>
                            <View style={[st.statusDot, { backgroundColor: item.is_active ? '#34D399' : '#94A3B8' }]} />
                            <Text style={[st.statusTxt, { color: item.is_active ? '#34D399' : '#94A3B8' }]}>
                                {item.is_active ? 'Aktif' : 'Pasif'}
                            </Text>
                        </View>
                    </View>
                    
                    <View style={st.cardFooter}>
                        <View style={st.roleBadge}>
                            <Icon name="shield-account" size={14} color="#818CF8" />
                            <Text style={st.roleTxt}>{getRoleName(item.role)}</Text>
                        </View>
                        <TouchableOpacity 
                            style={st.deleteBtn} 
                            onPress={() => handleDelete(item)}
                            hitSlop={{top:10, bottom:10, left:10, right:10}}
                        >
                            <Icon name="trash-can-outline" size={20} color="#F87171" />
                        </TouchableOpacity>
                    </View>
                </BlurView>
            </TouchableOpacity>
        );
    };

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
                    <View style={{ flex: 1, alignItems: 'center' }}>
                        <Text style={st.headerTitle}>Kullanıcılar</Text>
                        <Text style={st.headerSubtitle}>Erişim Kontrolü</Text>
                    </View>
                    <View style={{ width: 44 }} />
                </View>

                {loading ? (
                    <View style={st.center}><ActivityIndicator size="large" color="#818CF8" /></View>
                ) : error ? (
                    <View style={st.center}>
                        <Icon name="alert-circle-outline" size={48} color="#F87171" />
                        <Text style={st.errorTxt}>{error}</Text>
                    </View>
                ) : (
                    <FlatList
                        data={users}
                        keyExtractor={i => i.id.toString()}
                        renderItem={renderItem}
                        contentContainerStyle={st.listContent}
                        showsVerticalScrollIndicator={false}
                        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#818CF8" />}
                        ListEmptyComponent={
                            <View style={st.emptyState}>
                                <Icon name="account-group" size={64} color="#475569" />
                                <Text style={st.emptyTitle}>Kullanıcı Bulunamadı</Text>
                                <Text style={st.emptyDesc}>Henüz sisteme eklenmiş bir kullanıcı yok.</Text>
                            </View>
                        }
                    />
                )}

                <TouchableOpacity 
                    style={st.fab} 
                    activeOpacity={0.8} 
                    onPress={() => navigation.navigate('CompanyUserForm')}
                >
                    <LinearGradient colors={['#6366F1', '#4338CA']} style={StyleSheet.absoluteFillObject} />
                    <Icon name="plus" size={28} color="#FFF" />
                </TouchableOpacity>
            </SafeAreaView>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -50, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(99,102,241,0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(56,189,248,0.15)', filter: 'blur(40px)' },

    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 16, paddingTop: 8, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC' },
    headerSubtitle: { fontSize: 13, fontWeight: '600', color: '#818CF8', marginTop: 2 },
    
    center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 20 },
    errorTxt: { color: '#94A3B8', marginTop: 10, textAlign: 'center', fontSize: 16 },
    listContent: { padding: 16, paddingBottom: 100 },
    
    card: { borderRadius: 20, padding: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', overflow: 'hidden' },
    cardHeader: { flexDirection: 'row', alignItems: 'center' },
    avatar: { width: 48, height: 48, borderRadius: 16, backgroundColor: 'rgba(99,102,241,0.2)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(99,102,241,0.3)' },
    avatarTxt: { fontSize: 18, fontWeight: '900', color: '#818CF8' },
    cardInfo: { flex: 1, marginLeft: 12 },
    cardTitle: { fontSize: 16, fontWeight: '800', color: '#F8FAFC' },
    cardEmail: { fontSize: 13, color: '#94A3B8', fontWeight: '500', marginTop: 2 },
    statusWrap: { alignItems: 'flex-end', justifyContent: 'center' },
    statusDot: { width: 8, height: 8, borderRadius: 4, marginBottom: 4 },
    statusTxt: { fontSize: 10, fontWeight: '800', textTransform: 'uppercase' },

    cardFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 16, paddingTop: 16, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
    roleBadge: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(99,102,241,0.1)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 10, borderWidth: 1, borderColor: 'rgba(99,102,241,0.2)' },
    roleTxt: { fontSize: 12, fontWeight: '700', color: '#818CF8', marginLeft: 6 },
    deleteBtn: { padding: 6, backgroundColor: 'rgba(239,68,68,0.1)', borderRadius: 10, borderWidth: 1, borderColor: 'rgba(239,68,68,0.2)' },

    emptyState: { alignItems: 'center', justifyContent: 'center', paddingVertical: 60 },
    emptyTitle: { fontSize: 18, fontWeight: '800', color: '#F8FAFC', marginTop: 16 },
    emptyDesc: { fontSize: 14, color: '#94A3B8', marginTop: 8, textAlign: 'center' },

    fab: { position: 'absolute', right: 20, bottom: 30, width: 64, height: 64, borderRadius: 32, alignItems: 'center', justifyContent: 'center', overflow: 'hidden', shadowColor: '#6366F1', shadowOffset: {width:0,height:8}, shadowOpacity: 0.4, shadowRadius: 16, elevation: 10, borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' }
});
