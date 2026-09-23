import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, TextInput, ActivityIndicator, Platform, Alert, KeyboardAvoidingView, TouchableWithoutFeedback, Keyboard } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { MapView, Marker, Circle } from '../components/MapProxy';
import SpaceWaves from '../components/SpaceWaves';
import api from '../api/axios';

export default function PilotCellRadiusMapScreen({ route, navigation }) {
    const { student, type } = route.params;
    
    const initialRadius = type === 'morning' ? (student.pickup_radius || 1000) : (student.dropoff_radius || 1000);
    const [radius, setRadius] = useState(initialRadius.toString());
    const [isSaving, setIsSaving] = useState(false);

    // Coordinate parsing
    const coord = type === 'morning' 
        ? { latitude: parseFloat(student.morning_lat), longitude: parseFloat(student.morning_lng) }
        : { latitude: parseFloat(student.evening_lat), longitude: parseFloat(student.evening_lng) };

    const titlePrefix = type === 'morning' ? 'Sabah Konumu' : 'Akşam Konumu';
    const color = type === 'morning' ? '#0284C7' : '#7C3AED';
    const radiusVal = parseInt(radius) || 0;

    // Calculate optimal zoom level based on radius (1 degree is ~111,320 meters)
    // Multiply by 2.5 to add padding around the circle so it perfectly fits the screen
    const calculatedDelta = radiusVal > 0 ? (radiusVal * 2.5) / 111320 : 0.015;

    const handleSave = async () => {
        if (radiusVal < 10 || radiusVal > 5000) {
            Alert.alert("Geçersiz Çap", "Çap değeri 10 metre ile 5000 metre arasında olmalıdır.");
            return;
        }

        setIsSaving(true);
        try {
            const res = await api.post('/v1/pilotcell/personnel/set-student-radius', {
                student_id: student.id,
                type: type,
                radius: radiusVal
            });

            if (res.data?.success) {
                Alert.alert('Başarılı', 'Bildirim mesafesi başarıyla kaydedildi.');
                navigation.goBack();
            }
        } catch (error) {
            Alert.alert('Hata', 'Mesafe kaydedilirken bir hata oluştu.');
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <SafeAreaView style={styles.container}>
            <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={{ flex: 1 }}>
                <SpaceWaves />
                <TouchableWithoutFeedback onPress={Keyboard.dismiss}>
                    <View style={styles.header}>
                <TouchableOpacity style={styles.backBtn} onPress={() => navigation.goBack()}>
                        <MaterialCommunityIcons name="arrow-left" size={26} color="#FFF" />
                    </TouchableOpacity>
                    <Text style={styles.headerTitle} numberOfLines={1}>{student.name} - Çap</Text>
                    <View style={{ width: 42 }} />
                </View>
                </TouchableWithoutFeedback>

                <View style={styles.mapContainer}>
                <View style={styles.mapTitleContainer}>
                    <MaterialCommunityIcons name="radar" size={18} color={color} />
                    <Text style={styles.mapTitle}>{titlePrefix} Çapı</Text>
                </View>
                
                <MapView
                    style={styles.map}
                    onPress={Keyboard.dismiss}
                    region={{
                        ...coord,
                        latitudeDelta: calculatedDelta,
                        longitudeDelta: calculatedDelta,
                    }}
                >
                    <Marker coordinate={coord} title={student.name} pinColor={color} />
                    {radiusVal > 0 && (
                        <Circle
                            center={coord}
                            radius={radiusVal}
                            fillColor={type === 'morning' ? 'rgba(2, 132, 199, 0.2)' : 'rgba(124, 58, 237, 0.2)'}
                            strokeColor={color}
                            strokeWidth={2}
                        />
                    )}
                </MapView>
            </View>

            <TouchableWithoutFeedback onPress={Keyboard.dismiss}>
                <View style={styles.footer}>
                <Text style={styles.inputLabel}>Yaklaşma Bildirim Çapı (Metre)</Text>
                <View style={styles.inputWrapper}>
                    <MaterialCommunityIcons name="map-marker-distance" size={24} color="#94A3B8" style={styles.inputIcon} />
                    <TextInput
                        style={styles.input}
                        value={radius}
                        onChangeText={setRadius}
                        keyboardType="numeric"
                        placeholder="Örn: 1000"
                        placeholderTextColor="#475569"
                        maxLength={5}
                    />
                    <Text style={styles.unitText}>m</Text>
                </View>
                <Text style={styles.helperText}>Araç öğrencinin konumuna bu mesafe kadar yaklaştığında "Araç yaklaşıyor" bildirimi gönderilir.</Text>
                
                <TouchableOpacity style={styles.saveBtn} onPress={handleSave} disabled={isSaving}>
                    {isSaving ? <ActivityIndicator color="#FFF" /> : (
                        <>
                            <MaterialCommunityIcons name="content-save" size={24} color="#FFF" />
                            <Text style={styles.saveBtnText}>Kaydet</Text>
                        </>
                    )}
                </TouchableOpacity>
            </View>
            </TouchableWithoutFeedback>
            </KeyboardAvoidingView>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: 10, paddingBottom: 15, zIndex: 10 },
    backBtn: { padding: 8, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 12 },
    headerTitle: { fontSize: 18, fontWeight: '900', color: '#FFF', letterSpacing: 0.5, flex: 1, textAlign: 'center', marginHorizontal: 10 },
    
    mapContainer: { flex: 1, marginHorizontal: 16, marginTop: 10, borderRadius: 24, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', backgroundColor: '#0F172A' },
    mapTitleContainer: { flexDirection: 'row', alignItems: 'center', position: 'absolute', top: 16, left: 16, right: 16, backgroundColor: 'rgba(15, 23, 42, 0.95)', padding: 12, borderRadius: 12, zIndex: 10, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 8, elevation: 5 },
    mapTitle: { fontSize: 15, fontWeight: '800', color: '#FFF', marginLeft: 8, letterSpacing: 0.5 },
    map: { flex: 1 },

    footer: { padding: 24, backgroundColor: '#0F172A', borderTopLeftRadius: 30, borderTopRightRadius: 30, marginTop: -20, zIndex: 5, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
    inputLabel: { color: '#E2E8F0', fontSize: 15, fontWeight: '700', marginBottom: 12 },
    inputWrapper: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(30, 41, 59, 0.8)', borderRadius: 16, borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.5)', paddingHorizontal: 16, height: 60 },
    inputIcon: { marginRight: 12 },
    input: { flex: 1, color: '#FFF', fontSize: 20, fontWeight: 'bold' },
    unitText: { color: '#8B5CF6', fontSize: 18, fontWeight: '900' },
    helperText: { color: '#94A3B8', fontSize: 12, marginTop: 12, marginBottom: 20, lineHeight: 18, fontWeight: '500' },
    
    saveBtn: { backgroundColor: '#8B5CF6', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 16, borderRadius: 16, shadowColor: '#8B5CF6', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.4, shadowRadius: 8, elevation: 5 },
    saveBtnText: { color: '#FFF', fontSize: 18, fontWeight: '900', marginLeft: 10, letterSpacing: 0.5 }
});
